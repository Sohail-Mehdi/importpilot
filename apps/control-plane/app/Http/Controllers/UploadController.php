<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ImportSession;
use App\Models\UploadAttempt;
use App\Models\SourceArtifact;
use App\Models\AuditEvent;
use App\Support\ImportLifecycle;
use App\Support\XlsxStructureValidator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class UploadController extends Controller {
    public function store(Request $request, Project $project, ImportSession $session) {
        Gate::authorize('update', $project);
        if ($session->project_id !== $project->id) { abort(404); }
        if (!in_array($session->state, ['CREATED', 'UPLOADING'], true)) {
            abort(400, 'Session is not in CREATED state');
        }

        $validated = $request->validate([
            'filename' => 'required|string|max:255',
            'content_type' => 'required|string|in:text/csv,text/tab-separated-values,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ]);

        ImportLifecycle::transition($session, 'UPLOADING');

        $key = 'temp/' . $project->id . '/' . $session->id . '/' . Str::random(40);
        $expires = now()->addMinutes(15);

        $attempt = UploadAttempt::create([
            'import_session_id' => $session->id,
            'storage_key' => $key,
            'status' => 'PENDING',
            'original_filename' => $validated['filename'],
            'expires_at' => $expires
        ]);

        // Local SeaweedFS in compose.yml is started as `server -s3` without an
        // identity config, so signature enforcement is NOT proven here.
        $requestUrl = Storage::disk('s3')->temporaryUploadUrl($key, $expires, [
            'ContentType' => $validated['content_type']
        ]);

        AuditEvent::create(['project_id' => $project->id, 'action' => 'upload.authorized', 'context' => ['user_id' => $request->user()->id, 'attempt_id' => $attempt->id]]);

        return response()->json([
            'upload_id' => $attempt->id,
            'url' => is_array($requestUrl) ? $requestUrl['url'] : (string) $requestUrl,
            'headers' => is_array($requestUrl) ? $requestUrl['headers'] : [],
            'expires_at' => $expires->toIso8601String()
        ], 201);
    }

    public function complete(Request $request, Project $project, ImportSession $session, UploadAttempt $attempt) {
        Gate::authorize('update', $project);
        if ($session->project_id !== $project->id || $attempt->import_session_id !== $session->id) { abort(404); }

        if ($attempt->status === 'VERIFIED') {
            return response()->json(SourceArtifact::where('import_session_id', $session->id)->first());
        }

        if ($attempt->status === 'VERIFYING' && $attempt->updated_at->diffInMinutes(now()) > 5) {
            $attempt->update(['status' => 'PENDING']);
        }

        $updated = UploadAttempt::where('id', $attempt->id)
            ->where('status', 'PENDING')
            ->update(['status' => 'VERIFYING']);

        if (!$updated) {
            $attempt->refresh();
            if ($attempt->status === 'VERIFIED') {
                return response()->json(SourceArtifact::where('import_session_id', $session->id)->first());
            }
            return response()->json(['message' => 'Upload attempt cannot be processed in its current state.'], 400);
        }

        if (now()->greaterThan($attempt->expires_at)) {
            $attempt->update(['status' => 'EXPIRED']);
            return response()->json(['message' => 'Upload attempt expired.'], 400);
        }

        $disk = Storage::disk('s3');
        if (!$disk->exists($attempt->storage_key)) {
            $attempt->update(['status' => 'PENDING']);
            return response()->json(['message' => 'File not found in storage.'], 400);
        }

        $size = $disk->size($attempt->storage_key);
        $maxSize = 100 * 1024 * 1024;
        if ($size == 0) {
            $attempt->update(['status' => 'REJECTED']);
            return response()->json(['message' => 'Empty file.'], 400);
        }
        if ($size > $maxSize) {
            $attempt->update(['status' => 'REJECTED']);
            return response()->json(['message' => 'File exceeds 100MB size limit.'], 400);
        }

        $stream = $disk->readStream($attempt->storage_key);
        $header = fread($stream, 4);
        fclose($stream);

        $filename = strtolower($attempt->original_filename);
        $type = 'text/csv';
        if (str_ends_with($filename, '.xlsx')) {
            if ($header !== "PK\x03\x04") {
                $attempt->update(['status' => 'REJECTED']);
                return response()->json(['message' => 'Invalid XLSX file format.'], 400);
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'xlsx_');
            $in = $disk->readStream($attempt->storage_key);
            $out = fopen($tempPath, 'w');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            $xlsxError = XlsxStructureValidator::validateFile($tempPath);
            unlink($tempPath);
            if ($xlsxError !== null) {
                $attempt->update(['status' => 'REJECTED']);
                return response()->json(['message' => $xlsxError], 400);
            }

            $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        } elseif (str_ends_with($filename, '.tsv')) {
            $type = 'text/tab-separated-values';
        }

        $finalKey = 'sources/' . $project->id . '/' . $session->id . '/' . Str::random(40);
        if (!$disk->copy($attempt->storage_key, $finalKey)) {
            $attempt->update(['status' => 'PENDING']);
            return response()->json(['message' => 'Storage operation failed.'], 500);
        }

        return DB::transaction(function() use ($request, $project, $session, $attempt, $size, $type, $finalKey, $disk) {
            $attempt->update(['status' => 'VERIFIED']);

            $artifact = SourceArtifact::firstOrCreate(
                ['import_session_id' => $session->id],
                [
                    'storage_key' => $finalKey,
                    'original_filename' => $attempt->original_filename,
                    'file_size' => $size,
                    'content_type' => $type
                ]
            );

            $session->refresh();
            ImportLifecycle::transition($session, 'UPLOADED');

            AuditEvent::create(['project_id' => $project->id, 'action' => 'upload.completed', 'context' => ['user_id' => $request->user()->id, 'artifact_id' => $artifact->id]]);

            try {
                $inspectUrl = rtrim(config('app.data_engine_url'), '/').'/api/v1/jobs/inspect';
                $response = Http::withToken(config('app.internal_token'))
                    ->timeout(15)
                    ->post($inspectUrl, [
                        'contract_version' => '1.0',
                        'job_id' => (string) Str::uuid(),
                        'import_id' => $session->id,
                        'session_id' => $session->id,
                        'organization_id' => $project->organization_id,
                        'project_id' => $project->id,
                        'operation' => 'inspect',
                        'attempt' => 1,
                        'idempotency_key' => 'inspect:'.$session->id,
                        'requested_at' => now()->toIso8601String(),
                        'storage_key' => $artifact->storage_key,
                        'content_type' => $artifact->content_type,
                        'callback_url' => url('/api/v1/internal/jobs/callback'),
                    ]);
                if (!$response->successful()) {
                    throw new \RuntimeException('Inspect dispatch HTTP '.$response->status());
                }
                ImportLifecycle::transition($session, 'INSPECTING');
            } catch (\Exception $e) {
                Log::error('Failed to dispatch inspection job', [
                    'session_id' => $session->id,
                    'error_type' => $e::class,
                ]);
            }

            $disk->delete($attempt->storage_key);

            return response()->json($artifact->fresh());
        });
    }
}
