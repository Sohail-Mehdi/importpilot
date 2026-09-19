<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ApiKey;

class Phase1Test extends TestCase
{
    use RefreshDatabase;

    public function test_auth_registration_and_login()
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret123'
        ]);
        $response->assertStatus(201);
        
        $this->postJson('/api/v1/logout')->assertStatus(200);
        
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'secret123'
        ]);
        $loginResponse->assertStatus(200);
        
        // Test unauthenticated access
        $this->postJson('/api/v1/logout')->assertStatus(200);
        $this->getJson('/api/v1/organizations')->assertStatus(401);
    }

    public function test_organizations_and_tenant_isolation()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates Org A
        $this->actingAs($user1);
        $orgResponse = $this->postJson('/api/v1/organizations', ['name' => 'Org A']);
        $orgResponse->assertStatus(201);
        $orgA = $orgResponse->json('id');
        
        // User 1 creates Project X in Org A
        $projResponse = $this->postJson('/api/v1/projects', ['name' => 'Project X', 'organization_id' => $orgA]);
        $projResponse->assertStatus(201);
        $projX = $projResponse->json('id');
        
        // User 2 attempts to view Org A
        $this->actingAs($user2);
        $this->getJson("/api/v1/organizations/{$orgA}")->assertStatus(403);
        
        // User 2 attempts to view Project X
        $this->getJson("/api/v1/projects/{$projX}")->assertStatus(403);
        
        // User 2 creates Org B
        $this->postJson('/api/v1/organizations', ['name' => 'Org B'])->assertStatus(201);
        
        // Test Audit Event was created
        $this->assertDatabaseHas('audit_events', ['action' => 'project.created', 'project_id' => $projX]);
    }

    public function test_project_api_keys()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        
        $keyResponse = $this->postJson("/api/v1/projects/{$project->id}/api-keys");
        $keyResponse->assertStatus(201);
        $secret = $keyResponse->json('secret');
        $keyId = $keyResponse->json('id');
        
        $this->assertDatabaseHas('audit_events', ['action' => 'apikey.created']);
        $this->assertDatabaseMissing('api_keys', ['key_hash' => $secret]); // Should be hashed
        
        // Test integration API using the key
        $integResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $secret
        ])->postJson('/api/v1/integration/sessions');
        $integResponse->assertStatus(201);
        
        // Test invalid key
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $secret . 'invalid'
        ])->postJson('/api/v1/integration/sessions')->assertStatus(401);
        
        // Revoke key
        $this->deleteJson("/api/v1/projects/{$project->id}/api-keys/{$keyId}")->assertStatus(204);
        
        // Test revoked key
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $secret
        ])->postJson('/api/v1/integration/sessions')->assertStatus(401);
    }
    
    public function test_schema_versioning()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        
        // Create V1
        $schemaResponse = $this->postJson("/api/v1/projects/{$project->id}/schemas", [
            'schema_name' => 'customers',
            'definition' => ['type' => 'object']
        ]);
        $schemaResponse->assertStatus(201);
        $this->assertEquals(1, $schemaResponse->json('version'));
        
        // Create V2
        $schemaResponse2 = $this->postJson("/api/v1/projects/{$project->id}/schemas", [
            'schema_name' => 'customers',
            'definition' => ['type' => 'object', 'properties' => []]
        ]);
        $schemaResponse2->assertStatus(201);
        $this->assertEquals(2, $schemaResponse2->json('version'));
        
        $this->assertDatabaseHas('audit_events', ['action' => 'schema.created']);
    }
}
