<?php
namespace App\Policies;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy {
    public function view(User $user, Organization $organization) {
        return $organization->memberships()->where('user_id', $user->id)->exists();
    }
    public function update(User $user, Organization $organization) {
        return $organization->memberships()->where('user_id', $user->id)->where('role', 'owner')->exists();
    }
}
