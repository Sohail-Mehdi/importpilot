<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Membership;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller {
    public function index(Request $request) {
        return $request->user()->organizations;
    }
    public function store(Request $request) {
        $validated = $request->validate(['name' => 'required|string|max:255']);
        $org = Organization::create($validated);
        Membership::create(['user_id' => $request->user()->id, 'organization_id' => $org->id, 'role' => 'owner']);
        return response()->json($org, 201);
    }
    public function show(Request $request, Organization $organization) {
        Gate::authorize('view', $organization);
        return response()->json($organization);
    }
}
