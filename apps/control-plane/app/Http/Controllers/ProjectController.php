<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Organization;
use App\Models\AuditEvent;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller {
    public function index(Request $request) {
        $orgIds = $request->user()->organizations()->pluck('organizations.id');
        return Project::whereIn('organization_id', $orgIds)->get();
    }
    public function store(Request $request) {
        $validated = $request->validate(['organization_id' => 'required|uuid', 'name' => 'required|string|max:255']);
        Gate::authorize('create', [Project::class, $validated['organization_id']]);
        $project = Project::create($validated);
        AuditEvent::create(['project_id' => $project->id, 'action' => 'project.created', 'context' => ['user_id' => $request->user()->id]]);
        return response()->json($project, 201);
    }
    public function show(Request $request, Project $project) {
        Gate::authorize('view', $project);
        return response()->json($project);
    }
}
