<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\ApiKey;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ApiKeyController extends Controller {
    public function index(Request $request, Project $project) {
        Gate::authorize('view', $project);
        return response()->json($project->apiKeys);
    }
    public function store(Request $request, Project $project) {
        Gate::authorize('update', $project);
        $plainTextToken = Str::random(40);
        $hashed = hash('sha256', $plainTextToken);
        $key = $project->apiKeys()->create(['key_hash' => $hashed]);
        AuditEvent::create(['project_id' => $project->id, 'action' => 'apikey.created', 'context' => ['user_id' => $request->user()->id]]);
        return response()->json(['id' => $key->id, 'secret' => $plainTextToken], 201);
    }
    public function destroy(Request $request, Project $project, ApiKey $apiKey) {
        Gate::authorize('update', $project);
        if ($apiKey->project_id !== $project->id) { abort(404); }
        $apiKey->delete();
        AuditEvent::create(['project_id' => $project->id, 'action' => 'apikey.revoked', 'context' => ['user_id' => $request->user()->id]]);
        return response()->json(null, 204);
    }
}
