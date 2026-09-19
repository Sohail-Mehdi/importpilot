<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Models\ApiKey;
use App\Models\Project;
use Symfony\Component\HttpFoundation\Response;

class ProjectApiKeyAuth {
    public function handle(Request $request, Closure $next): Response {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Unauthorized. Missing API key.'], 401);
        }
        
        $hashed = hash('sha256', $token);
        $apiKey = ApiKey::where('key_hash', $hashed)->first();
        
        if (!$apiKey) {
            return response()->json(['message' => 'Unauthorized. Invalid API key.'], 401);
        }
        
        // Inject project into request for controllers to use
        $request->attributes->add(['project' => $apiKey->project]);
        
        return $next($request);
    }
}
