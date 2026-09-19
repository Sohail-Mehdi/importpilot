<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ImportSchema;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;

class ImportSchemaController extends Controller {
    public function index(Request $request, Project $project) {
        Gate::authorize('view', $project);
        return response()->json($project->importSchemas);
    }
    public function store(Request $request, Project $project) {
        Gate::authorize('update', $project);
        $validated = $request->validate([
            'schema_name' => 'required|string|max:255',
            'definition' => 'required|array'
        ]);
        
        $maxRetries = 3;
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                $latest = ImportSchema::where('project_id', $project->id)
                    ->where('schema_name', $validated['schema_name'])
                    ->orderBy('version', 'desc')
                    ->first();
                    
                $version = $latest ? $latest->version + 1 : 1;
                
                $schema = ImportSchema::create([
                    'project_id' => $project->id,
                    'schema_name' => $validated['schema_name'],
                    'definition' => $validated['definition'],
                    'version' => $version
                ]);
                break;
            } catch (\Illuminate\Database\QueryException $e) {
                if ($e->getCode() == 23505 && $i < $maxRetries - 1) { continue; }
                throw $e;
            }
        }
        
        AuditEvent::create(['project_id' => $project->id, 'action' => 'schema.created', 'context' => ['user_id' => $request->user()->id, 'version' => $version]]);
        return response()->json($schema, 201);
    }
    public function show(Request $request, Project $project, ImportSchema $schema) {
        Gate::authorize('view', $project);
        if ($schema->project_id !== $project->id) { abort(404); }
        return response()->json($schema);
    }
}
