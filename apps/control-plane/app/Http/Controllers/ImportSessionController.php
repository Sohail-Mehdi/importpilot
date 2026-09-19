<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ImportSchema;
use App\Models\ImportSession;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;

class ImportSessionController extends Controller {
    public function store(Request $request, Project $project) {
        Gate::authorize('update', $project);
        $validated = $request->validate([
            'schema_id' => 'nullable|uuid|exists:import_schemas,id'
        ]);
        
        if (isset($validated['schema_id'])) {
            $schema = ImportSchema::find($validated['schema_id']);
            if ($schema->project_id !== $project->id) { abort(400, 'Invalid schema'); }
        }
        
        $session = ImportSession::create([
            'project_id' => $project->id,
            'schema_id' => $validated['schema_id'] ?? null,
            'state' => 'CREATED'
        ]);
        
        AuditEvent::create(['project_id' => $project->id, 'action' => 'session.created', 'context' => ['user_id' => $request->user()->id]]);
        return response()->json($session, 201);
    }
    public function show(Request $request, Project $project, ImportSession $session) {
        Gate::authorize('view', $project);
        if ($session->project_id !== $project->id) { abort(404); }
        return response()->json($session);
    }
}
