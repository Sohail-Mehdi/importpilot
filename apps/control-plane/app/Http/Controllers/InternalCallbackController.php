<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\ImportSession;
use App\Models\SourceArtifact;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Log;

class InternalCallbackController extends Controller {
    public function handle(Request $request) {
        $token = $request->bearerToken();
        if ($token !== config('app.internal_token', 'secret-token')) {
            abort(401, 'Unauthorized');
        }

        $validated = $request->validate([
            'session_id' => 'required|uuid',
            'status' => 'required|string',
            'profile' => 'nullable|array',
            'parquet_key' => 'nullable|string',
            'error' => 'nullable|string'
        ]);

        $session = ImportSession::find($validated['session_id']);
        if (!$session) { abort(404); }

        if ($validated['status'] === 'COMPLETED') {
            $session->update(['state' => 'AWAITING_MAPPING']);
            // Store profile in artifact
            $artifact = SourceArtifact::where('import_session_id', $session->id)->first();
            if ($artifact) {
                // we can add a metadata column, but for now just audit log it
                $artifact->update(['checksum' => $validated['parquet_key']]); // repurpose checksum for now to hold parquet_key temporarily, or better just log it
            }
            AuditEvent::create([
                'project_id' => $session->project_id, 
                'action' => 'inspection.completed', 
                'context' => ['profile' => $validated['profile'], 'parquet_key' => $validated['parquet_key']]
            ]);
        } else {
            $session->update(['state' => 'FAILED']);
            AuditEvent::create([
                'project_id' => $session->project_id, 
                'action' => 'inspection.failed', 
                'context' => ['error' => $validated['error']]
            ]);
        }
        
        return response()->json(['message' => 'Callback processed']);
    }
}
