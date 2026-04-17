<?php

namespace Tests\Feature;

use App\Models\EditingJob;
use App\Models\Employee;
use App\Models\Game;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditingJobFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Employee $editor;
    protected Employee $editor2;
    protected Employee $admin;
    protected Game $game;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('pw'),
        ]);

        $this->editor = Employee::create([
            'first_name' => 'Editor', 'last_name' => 'One',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $this->editor2 = Employee::create([
            'first_name' => 'Editor', 'last_name' => 'Two',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $this->admin = Employee::create([
            'first_name' => 'Admin', 'last_name' => 'Boss',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2024-01-01',
        ]);

        $this->game = Game::create([
            'game_name' => 'Elden Ring', 'game_slug' => 'elden-ring',
        ]);
    }

    // ─── Create ──────────────────────────────────────────────────────

    public function test_create_job_endpoint(): void
    {
        $response = $this->postJson('/api/jobs/create', [
            'job_name'      => 'Boss Fight Montage',
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
            'notes'         => 'Focus on epic moments',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('job.job_name', 'Boss Fight Montage')
            ->assertJsonPath('job.status', 'assigned');

        $this->assertDatabaseHas('editing_jobs', ['job_name' => 'Boss Fight Montage']);
    }

    public function test_create_job_validation_errors(): void
    {
        $response = $this->postJson('/api/jobs/create', [
            'job_name' => '',
        ]);

        $response->assertStatus(422);
    }

    // ─── Show ────────────────────────────────────────────────────────

    public function test_show_job_with_details(): void
    {
        $job = $this->createJob();

        $response = $this->getJson("/api/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('job_id', $job->id)
            ->assertJsonPath('status', 'assigned');
    }

    public function test_show_nonexistent_returns_404(): void
    {
        $this->getJson('/api/jobs/999999')
            ->assertStatus(404);
    }

    // ─── Index ───────────────────────────────────────────────────────

    public function test_list_jobs(): void
    {
        $this->createJob('Job 1');
        $this->createJob('Job 2');
        $this->createJob('Job 3');

        $response = $this->getJson('/api/jobs');

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonCount(3, 'jobs');
    }

    public function test_list_jobs_filtered_by_status(): void
    {
        $job = $this->createJob('In Progress Job');
        $this->createJob('Assigned Job');

        app(\App\Services\EditingJobService::class)->startJob($job->id, $this->editor->id);

        $response = $this->getJson('/api/jobs?status=in_progress');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.status', 'in_progress');
    }

    public function test_list_jobs_filtered_by_editor(): void
    {
        $this->createJob('Editor1 Job');
        $this->createJobForEditor2('Editor2 Job');

        $response = $this->getJson("/api/jobs?assigned_to={$this->editor->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_name', 'Editor1 Job');
    }

    public function test_list_jobs_filtered_by_game(): void
    {
        $game2 = Game::create(['game_name' => 'Dark Souls', 'game_slug' => 'dark-souls']);
        $this->createJob('Elden Ring Job');
        $this->createJobForGame($game2, 'Dark Souls Job');

        $response = $this->getJson("/api/jobs?game_id={$game2->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'jobs')
            ->assertJsonPath('jobs.0.job_name', 'Dark Souls Job');
    }

    // ─── Start ───────────────────────────────────────────────────────

    public function test_start_job_endpoint(): void
    {
        $job = $this->createJob();

        $response = $this->postJson("/api/jobs/{$job->id}/start", [
            'employee_id' => $this->editor->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('job.status', 'in_progress');
    }

    public function test_start_job_wrong_editor_returns_error(): void
    {
        $job = $this->createJob();

        $response = $this->postJson("/api/jobs/{$job->id}/start", [
            'employee_id' => $this->admin->id,
        ]);

        $response->assertStatus(422);
    }

    // ─── Mark Review Ready ───────────────────────────────────────────

    public function test_mark_review_ready_endpoint(): void
    {
        $job = $this->createJob();
        app(\App\Services\EditingJobService::class)->startJob($job->id, $this->editor->id);

        $response = $this->postJson("/api/jobs/{$job->id}/mark-ready", [
            'employee_id' => $this->editor->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('job.status', 'review_ready');
    }

    // ─── Finalize ────────────────────────────────────────────────────

    public function test_finalize_job_endpoint(): void
    {
        $job = $this->createJob();
        $service = app(\App\Services\EditingJobService::class);
        $service->startJob($job->id, $this->editor->id);
        $service->markReviewReady($job->id, $this->editor->id);

        $response = $this->postJson("/api/jobs/{$job->id}/finalize", [
            'employee_id' => $this->editor->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('job.status', 'final')
            ->assertJsonStructure(['job', 'performance']);
    }

    // ─── Reassign ────────────────────────────────────────────────────

    public function test_reassign_job_endpoint(): void
    {
        $job = $this->createJob();

        $response = $this->putJson("/api/jobs/{$job->id}/reassign", [
            'new_assignee'  => $this->editor2->id,
            'reassigned_by' => $this->admin->id,
            'reason'        => 'Workload balance',
        ]);

        $response->assertOk()
            ->assertJsonPath('job.assigned_to', $this->editor2->id);

        $this->assertDatabaseHas('job_reassignments', [
            'editing_job_id' => $job->id,
            'reason'         => 'Workload balance',
        ]);
    }

    // ─── Update ──────────────────────────────────────────────────────

    public function test_update_job_endpoint(): void
    {
        $job = $this->createJob();

        $response = $this->putJson("/api/jobs/{$job->id}/update", [
            'modified_by'   => $this->admin->id,
            'job_name'      => 'Updated Boss Fight',
            'deadline_days' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('job.job_name', 'Updated Boss Fight')
            ->assertJsonPath('job.deadline_days', 10);
    }

    // ─── Delete ──────────────────────────────────────────────────────

    public function test_delete_job_endpoint(): void
    {
        $job = $this->createJob();

        $response = $this->deleteJson("/api/jobs/{$job->id}");

        $response->assertOk()
            ->assertJsonPath('status', 'deleted');

        $job->refresh();
        $this->assertTrue($job->is_deleted);
    }

    // ─── Overdue ─────────────────────────────────────────────────────

    public function test_overdue_jobs_endpoint(): void
    {
        Carbon::setTestNow('2026-04-01 09:00:00');

        $job = $this->createJob();
        app(\App\Services\EditingJobService::class)->startJob($job->id, $this->editor->id);

        Carbon::setTestNow('2026-04-08 09:00:00');

        $response = $this->getJson('/api/jobs/overdue');

        $response->assertOk()
            ->assertJsonCount(1, 'jobs');

        Carbon::setTestNow();
    }

    // ─── Performance ─────────────────────────────────────────────────

    public function test_performance_endpoint(): void
    {
        Carbon::setTestNow('2026-04-01 09:00:00');

        $service = app(\App\Services\EditingJobService::class);

        $job1 = $this->createJob('Job 1');
        $service->startJob($job1->id, $this->editor->id);

        Carbon::setTestNow('2026-04-04 16:00:00');
        $service->markReviewReady($job1->id, $this->editor->id);

        $response = $this->getJson("/api/performance/{$this->editor->id}?year=2026&month=4");

        $response->assertOk()
            ->assertJsonPath('total_jobs', 1)
            ->assertJsonPath('early', 1);

        Carbon::setTestNow();
    }

    // ─── Full Workflow ───────────────────────────────────────────────

    public function test_full_workflow_from_create_to_finalize(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');

        // 1) Create
        $createResp = $this->postJson('/api/jobs/create', [
            'job_name'      => 'Full Flow Job',
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
        ]);
        $jobId = $createResp->json('job.job_id');

        // 2) Start
        $this->postJson("/api/jobs/{$jobId}/start", ['employee_id' => $this->editor->id])
            ->assertOk()
            ->assertJsonPath('job.status', 'in_progress');

        // 3) Mark review ready
        Carbon::setTestNow('2026-04-13 16:00:00');
        $this->postJson("/api/jobs/{$jobId}/mark-ready", ['employee_id' => $this->editor->id])
            ->assertOk()
            ->assertJsonPath('job.status', 'review_ready');

        // 4) Finalize
        $finalResp = $this->postJson("/api/jobs/{$jobId}/finalize", ['employee_id' => $this->editor->id])
            ->assertOk();

        $this->assertEquals('final', $finalResp->json('job.status'));
        $this->assertEquals('early', $finalResp->json('performance.deadline_compliance'));
        $this->assertEquals(3, $finalResp->json('performance.work_duration_days'));

        Carbon::setTestNow();
    }

    public function test_full_workflow_with_reassignment(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');

        $job = $this->createJob();
        $service = app(\App\Services\EditingJobService::class);

        // Start with editor 1
        $service->startJob($job->id, $this->editor->id);

        // Reassign to editor 2
        $this->putJson("/api/jobs/{$job->id}/reassign", [
            'new_assignee'  => $this->editor2->id,
            'reassigned_by' => $this->admin->id,
            'reason'        => 'Schedule conflict',
        ])->assertOk();

        // Editor 2 can mark ready
        Carbon::setTestNow('2026-04-13 16:00:00');
        $service->markReviewReady($job->id, $this->editor2->id);

        // Editor 2 can finalize
        $result = $service->finalizeJob($job->id, $this->editor2->id);
        $this->assertEquals('final', $result['job']->status);

        Carbon::setTestNow();
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    private function createJob(string $name = 'Test Job'): EditingJob
    {
        return app(\App\Services\EditingJobService::class)->createJob([
            'job_name'      => $name,
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
        ]);
    }

    private function createJobForEditor2(string $name = 'Test Job'): EditingJob
    {
        return app(\App\Services\EditingJobService::class)->createJob([
            'job_name'      => $name,
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor2->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
        ]);
    }

    private function createJobForGame(Game $game, string $name = 'Test Job'): EditingJob
    {
        return app(\App\Services\EditingJobService::class)->createJob([
            'job_name'      => $name,
            'game_id'       => $game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
        ]);
    }
}
