<?php
namespace App\Policies;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy {
    public function view(User $user, Project $project) {
        return $project->organization->memberships()->where('user_id', $user->id)->exists();
    }
    public function create(User $user, $organization_id) {
        return \App\Models\Organization::find($organization_id)->memberships()->where('user_id', $user->id)->whereIn('role', ['owner', 'admin'])->exists();
    }
    public function update(User $user, Project $project) {
        return $project->organization->memberships()->where('user_id', $user->id)->whereIn('role', ['owner', 'admin'])->exists();
    }
}
