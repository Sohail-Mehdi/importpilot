<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\ImportSession;
use App\Models\SourceArtifact;
use App\Models\AuditEvent;
use App\Support\ImportLifecycle;

class InternalCallbackController extends Controller {
    public function handle(Request $request) {
        $token = $request->bearerToken();
        if ($token !== config('app.internal_token')) {
            abort(401, 'Unauthorized');
        }

        $validated = $request->validate([
            'session_id' => 'required|uuid',
            'status' => 'required|string',
            'profile' => 'nullable|array',
            'inspect' => 'nullable|array',
            'parquet_key' => 'nullable|string',
            'error' => 'nullable|string',
            'job_id' => 'nullable|string',
            'idempotency_key' => 'nullable|string',
        ]);

        $session = ImportSession::find($validated['session_id']);
        if (!$session) { abort(404); }

        if ($validated['status'] === 'COMPLETED') {
            if ($session->state === 'AWAITING_MAPPING') {
                return response()->json(['message' => 'Duplicate callback ignored']);
            }
            if (!ImportLifecycle::transition($session, 'AWAITING_MAPPING')) {
                return response()->json(['message' => 'Stale callback ignored'], 409);
            }
            $artifact = SourceArtifact::where('import_session_id', $session->id)->first();
            if ($artifact) {
                $artifact->update([
                    'parquet_key' => $validated['parquet_key'] ?? null,
                    'inspection_profile' => $validated['profile'] ?? null,
                ]);
            }
            AuditEvent::create([
                'project_id' => $session->project_id,
                'action' => 'inspection.completed',
                'context' => [
                    'parquet_key' => $validated['parquet_key'] ?? null,
                    'job_id' => $validated['job_id'] ?? null,
                    'row_count' => $validated['inspect']['row_count'] ?? ($validated['profile']['row_count'] ?? null),
                    'file_type' => $validated['inspect']['file_type'] ?? null,
                    'warnings' => $validated['inspect']['warnings'] ?? [],
                ]
            ]);
        } else {
            if ($session->state === 'AWAITING_MAPPING') {
                return response()->json(['message' => 'Stale failure callback ignored']);
            }
            if ($session->state === 'FAILED') {
                return response()->json(['message' => 'Duplicate failure callback ignored']);
            }
            if (!ImportLifecycle::transition($session, 'FAILED')) {
                return response()->json(['message' => 'Stale callback ignored'], 409);
            }
            AuditEvent::create([
                'project_id' => $session->project_id,
                'action' => 'inspection.failed',
                'context' => ['error' => $validated['error'] ?? null]
            ]);
        }

        return response()->json(['message' => 'Callback processed']);
    }
}
