<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ImportSession;

class Phase2IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Http::fake([
            'http://127.0.0.1:8001/api/v1/jobs/inspect' => \Illuminate\Support\Facades\Http::response(['status' => 'QUEUED'], 200),
        ]);
    }

    public function test_real_s3_upload_flow()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => 'CREATED']);

        // 1. Get Presigned URL
        $response = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload", [
            'filename' => 'real_data.csv',
            'content_type' => 'text/csv'
        ]);
        $response->assertStatus(201);
        
        $uploadId = $response->json('upload_id');
        $url = $response->json('url');
        
        // 2. Perform Real Upload to SeaweedFS S3
        $csvContent = "id,name\\n1,Test";
        $uploadResponse = Http::withBody($csvContent, 'text/csv')->put($url);
        
        $this->assertTrue($uploadResponse->successful(), "Failed to upload to S3: " . $uploadResponse->body());

        // 3. Complete Upload
        $compResponse = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$uploadId}/complete");
        $compResponse->assertStatus(200);
        
        $this->assertEquals($session->id, $compResponse->json('import_session_id'));
        $this->assertEquals(strlen($csvContent), $compResponse->json('file_size'));
        $this->assertEquals('text/csv', $compResponse->json('content_type'));
    }
}
