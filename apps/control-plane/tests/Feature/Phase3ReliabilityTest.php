<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ImportSession;
use App\Models\SourceArtifact;
use App\Support\ImportLifecycle;

class Phase3ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function seedSession(string $state = 'INSPECTING'): array
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Org']);
        $org->memberships()->create(['user_id' => $user->id, 'role' => 'owner']);
        $project = Project::create(['organization_id' => $org->id, 'name' => 'Proj']);
        $session = ImportSession::create(['project_id' => $project->id, 'state' => $state]);
        $artifact = SourceArtifact::create([
            'import_session_id' => $session->id,
            'storage_key' => 'sources/'.$project->id.'/'.$session->id.'/file',
            'original_filename' => 'customers_good.csv',
            'file_size' => 32,
            'content_type' => 'text/csv',
        ]);
        return [$user, $project, $session, $artifact];
    }

    public function test_callback_requires_internal_bearer_token()
    {
        [, , $session] = $this->seedSession();
        $this->postJson('/api/v1/internal/jobs/callback', [
            'session_id' => $session->id,
            'status' => 'COMPLETED',
            'profile' => ['row_count' => 1],
            'parquet_key' => 'intermediate/x/data.parquet',
        ])->assertStatus(401);
    }

    public function test_successful_callback_persists_result_and_awaiting_mapping()
    {
        [, $project, $session] = $this->seedSession('INSPECTING');

        $this->withToken(config('app.internal_token'))
            ->postJson('/api/v1/internal/jobs/callback', [
                'session_id' => $session->id,
                'status' => 'COMPLETED',
                'profile' => ['row_count' => 2, 'columns' => []],
                'inspect' => ['file_type' => 'csv', 'row_count' => 2, 'warnings' => []],
                'parquet_key' => 'intermediate/'.$project->id.'/'.$session->id.'/data.parquet',
            ])->assertStatus(200);

        $this->assertEquals('AWAITING_MAPPING', $session->fresh()->state);
        $this->assertDatabaseHas('source_artifacts', [
            'import_session_id' => $session->id,
            'parquet_key' => 'intermediate/'.$project->id.'/'.$session->id.'/data.parquet',
        ]);
        $this->assertDatabaseHas('audit_events', [
            'project_id' => $project->id,
            'action' => 'inspection.completed',
        ]);
    }

    public function test_duplicate_completed_callback_is_idempotent()
    {
        [, $project, $session] = $this->seedSession('INSPECTING');
        $payload = [
            'session_id' => $session->id,
            'status' => 'COMPLETED',
            'profile' => ['row_count' => 2],
            'parquet_key' => 'intermediate/dup.parquet',
        ];

        $this->withToken(config('app.internal_token'))->postJson('/api/v1/internal/jobs/callback', $payload)->assertStatus(200);
        $this->withToken(config('app.internal_token'))->postJson('/api/v1/internal/jobs/callback', $payload)
            ->assertStatus(200)
            ->assertJson(['message' => 'Duplicate callback ignored']);
        $this->assertEquals('AWAITING_MAPPING', $session->fresh()->state);
    }

    public function test_stale_failure_does_not_override_awaiting_mapping()
    {
        [, , $session] = $this->seedSession('INSPECTING');
        $this->withToken(config('app.internal_token'))->postJson('/api/v1/internal/jobs/callback', [
            'session_id' => $session->id,
            'status' => 'COMPLETED',
            'profile' => ['row_count' => 1],
            'parquet_key' => 'intermediate/ok.parquet',
        ])->assertStatus(200);

        $this->withToken(config('app.internal_token'))->postJson('/api/v1/internal/jobs/callback', [
            'session_id' => $session->id,
            'status' => 'FAILED',
            'error' => 'late retry',
        ])->assertStatus(200)->assertJson(['message' => 'Stale failure callback ignored']);

        $this->assertEquals('AWAITING_MAPPING', $session->fresh()->state);
    }

    public function test_completed_callback_rejected_from_created()
    {
        [, , $session] = $this->seedSession('CREATED');
        $this->withToken(config('app.internal_token'))->postJson('/api/v1/internal/jobs/callback', [
            'session_id' => $session->id,
            'status' => 'COMPLETED',
            'profile' => ['row_count' => 1],
            'parquet_key' => 'intermediate/stale.parquet',
        ])->assertStatus(409);
        $this->assertEquals('CREATED', $session->fresh()->state);
    }

    public function test_lifecycle_forbids_skipping_inspecting()
    {
        $session = new ImportSession(['state' => 'UPLOADED']);
        $this->assertFalse((new ImportLifecycle())::ALLOWED['UPLOADED'] === ['AWAITING_MAPPING']);
        $this->assertContains('INSPECTING', ImportLifecycle::ALLOWED['UPLOADED']);
        $this->assertContains('AWAITING_MAPPING', ImportLifecycle::ALLOWED['INSPECTING']);
        $this->assertNotContains('AWAITING_MAPPING', ImportLifecycle::ALLOWED['UPLOADED']);
        $this->assertSame('UPLOADED', $session->state);
    }
}
