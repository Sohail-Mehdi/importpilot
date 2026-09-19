<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\ImportSchemaController;
use App\Http\Controllers\ImportSessionController;

Route::prefix('v1')->middleware([\Illuminate\Session\Middleware\StartSession::class])->group(function() {
    Route::post('/internal/jobs/callback', [\App\Http\Controllers\InternalCallbackController::class, 'handle']);

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // User / Dashboard Routes
    Route::middleware(['auth:web'])->group(function() {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', function (Request $request) { return $request->user(); });
        
        Route::apiResource('organizations', OrganizationController::class);
        Route::apiResource('projects', ProjectController::class);
        
        // Project sub-resources
        Route::apiResource('projects.api-keys', ApiKeyController::class)->only(['index', 'store', 'destroy']);
        Route::apiResource('projects.schemas', ImportSchemaController::class)->only(['index', 'store', 'show']);
        
        Route::apiResource('projects.sessions', ImportSessionController::class)->only(['store', 'show']);
        Route::post('projects/{project}/sessions/{session}/upload', [\App\Http\Controllers\UploadController::class, 'store']);
        Route::post('projects/{project}/sessions/{session}/upload/{attempt}/complete', [\App\Http\Controllers\UploadController::class, 'complete']);

    });
    
    // Integration API Routes (uses API Key)
    Route::middleware(\App\Http\Middleware\ProjectApiKeyAuth::class)->prefix('integration')->group(function() {
        // Here, controllers will resolve the project from $request->attributes->get('project')
        Route::post('/sessions', function(Request $request) {
            $project = $request->attributes->get('project');
            $validated = $request->validate(['schema_id' => 'nullable|uuid|exists:import_schemas,id']);
            if (isset($validated['schema_id'])) {
                $schema = \App\Models\ImportSchema::find($validated['schema_id']);
                if ($schema->project_id !== $project->id) { abort(400, 'Invalid schema'); }
            }
            $session = \App\Models\ImportSession::create(['project_id' => $project->id, 'schema_id' => $validated['schema_id'] ?? null, 'state' => 'CREATED']);
            \App\Models\AuditEvent::create(['project_id' => $project->id, 'action' => 'session.created', 'context' => ['integration' => true]]);
            return response()->json($session, 201);
        });
    });
});
