<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ImportSession;
use App\Models\UploadAttempt;
use App\Models\SourceArtifact;

class Phase2Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        \Illuminate\Support\Facades\Http::fake([
            'http://127.0.0.1:8001/*' => \Illuminate\Support\Facades\Http::response(['task_id' => 'test-task', 'status' => 'QUEUED'], 200),
            '*' => \Illuminate\Support\Facades\Http::response(['status' => 'ok'], 200)
        ]);
    }

    public function test_upload_authorization_and_completion()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => 'CREATED']);

        // Request upload authorization
        $response = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload", [
            'filename' => 'data.csv',
            'content_type' => 'text/csv'
        ]);
        $response->assertStatus(201);
        $response->assertJsonStructure(['upload_id', 'url', 'expires_at']);
        
        $uploadId = $response->json('upload_id');
        $attempt = UploadAttempt::find($uploadId);
        
        $this->assertEquals('PENDING', $attempt->status);
        
        // Mock S3 file with some content so size > 0
        Storage::disk('s3')->put($attempt->storage_key, 'col1,col2\nval1,val2');
        
        // Complete upload
        $compResponse = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$uploadId}/complete");
        $compResponse->assertStatus(200);
        $compResponse->assertJsonStructure(['id', 'storage_key', 'file_size', 'content_type']);
        
        $this->assertDatabaseHas('source_artifacts', ['import_session_id' => $session->id]);
        $this->assertEquals('INSPECTING', $session->fresh()->state);
        $this->assertEquals('VERIFIED', $attempt->fresh()->status);
        
        $artifactKey = $compResponse->json('storage_key');
        $this->assertNotEquals($attempt->storage_key, $artifactKey); // A2: Keys must differ
        $this->assertTrue(Storage::disk('s3')->exists($artifactKey)); // File copied
        
        // Idempotency: second call returns the same artifact
        $compResponse2 = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$uploadId}/complete");
        $compResponse2->assertStatus(200);
        $this->assertEquals($compResponse->json('id'), $compResponse2->json('id'));
    }

    public function test_cross_tenant_upload_denied()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // User 1 creates Org and Project and Session
        $org1 = Organization::create(['name' => 'Org A']);
        $org1->memberships()->create(['user_id' => $user1->id, 'role' => 'owner']);
        $proj1 = Project::create(['organization_id' => $org1->id, 'name' => 'Proj A']);
        $sess1 = ImportSession::create(['project_id' => $proj1->id, 'state' => 'CREATED']);

        $this->actingAs($user2);
        
        // User 2 attempts to upload to Project A
        $this->postJson("/api/v1/projects/{$proj1->id}/sessions/{$sess1->id}/upload", [
            'filename' => 'data.csv',
            'content_type' => 'text/csv'
        ])->assertStatus(403);
    }
    
    public function test_file_not_found_in_storage()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => 'CREATED']);

        $attempt = UploadAttempt::create([
            'import_session_id' => $session->id,
            'storage_key' => 'fake_key',
            'status' => 'PENDING',
            'original_filename' => 'data.csv',
            'expires_at' => now()->addMinutes(15)
        ]);
        
        // Attempt complete without mocking the S3 file
        $compResponse = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$attempt->id}/complete");
        $compResponse->assertStatus(400); // Bad Request because file doesn't exist
    }
    
    public function test_reject_invalid_xlsx()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => 'CREATED']);

        $attempt = UploadAttempt::create([
            'import_session_id' => $session->id,
            'storage_key' => 'fake_key',
            'status' => 'PENDING',
            'original_filename' => 'data.xlsx',
            'expires_at' => now()->addMinutes(15)
        ]);
        
        // Mock S3 file with invalid ZIP signature
        Storage::disk('s3')->put('fake_key', 'This is a fake xlsx without ZIP headers');
        
        $compResponse = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$attempt->id}/complete");
        $compResponse->assertStatus(400);
        $this->assertEquals('REJECTED', $attempt->fresh()->status);
    }

    public function test_reject_xlsx_zip_without_workbook_parts()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);

        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => 'CREATED']);

        $attempt = UploadAttempt::create([
            'import_session_id' => $session->id,
            'storage_key' => 'fake_xlsx_zip',
            'status' => 'PENDING',
            'original_filename' => 'data.xlsx',
            'expires_at' => now()->addMinutes(15)
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'ziponly');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'not a spreadsheet');
        $zip->close();
        Storage::disk('s3')->put('fake_xlsx_zip', file_get_contents($zipPath));
        unlink($zipPath);

        $compResponse = $this->postJson("/api/v1/projects/{$project->id}/sessions/{$session->id}/upload/{$attempt->id}/complete");
        $compResponse->assertStatus(400);
        $this->assertEquals('REJECTED', $attempt->fresh()->status);
        $this->assertStringContainsString('structural', $compResponse->json('message'));
    }
}
