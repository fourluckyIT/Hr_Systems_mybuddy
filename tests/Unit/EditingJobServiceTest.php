<?php

namespace Tests\Unit;

use App\Models\DeadlineNotification;
use App\Models\EditingJob;
use App\Models\Employee;
use App\Models\Game;
use App\Models\JobModification;
use App\Models\JobReassignment;
use App\Models\User;
use App\Services\EditingJobService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditingJobServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EditingJobService $service;
    protected Employee $editor;
    protected Employee $admin;
    protected Game $game;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EditingJobService::class);

        $this->user = User::create([
            'name' => 'Admin', 'email' => 'admin@test.com', 'password' => bcrypt('pw'),
        ]);

        $this->editor = Employee::create([
            'first_name' => 'Editor', 'last_name' => 'One',
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

    // ─── Employee Code Generation ────────────────────────────────────

    public function test_generate_employee_code_format(): void
    {
        $code = $this->service->generateEmployeeCode('EDIT');

        $this->assertMatchesRegularExpression('/^EDIT-\d{6}-001$/', $code);
    }

    public function test_generate_employee_code_increments(): void
    {
        // Create employee with today's code
        $datePart = now()->format('ymd');
        Employee::create([
            'first_name' => 'X', 'last_name' => 'Y',
            'employee_code' => "EDIT-{$datePart}-001",
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $code = $this->service->generateEmployeeCode('EDIT');
        $this->assertStringEndsWith('-002', $code);
    }

    // ─── Job Creation ────────────────────────────────────────────────

    public function test_create_job(): void
    {
        $job = $this->service->createJob([
            'job_name'      => 'Boss Fight Montage',
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
            'notes'         => 'Focus on epic moments',
        ]);

        $this->assertDatabaseHas('editing_jobs', [
            'job_name'    => 'Boss Fight Montage',
            'status'      => 'assigned',
            'assigned_to' => $this->editor->id,
        ]);

        $this->assertEquals('assigned', $job->status);
        $this->assertNull($job->deadline_date);
        $this->assertNotNull($job->game);
    }

    // ─── Status Transitions ──────────────────────────────────────────

    public function test_start_job(): void
    {
        $job = $this->createTestJob();

        Carbon::setTestNow('2026-04-13 10:00:00');

        $started = $this->service->startJob($job->id, $this->editor->id);

        $this->assertEquals('in_progress', $started->status);
        $this->assertNotNull($started->started_at);
        $this->assertEquals('2026-04-18', $started->deadline_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_start_job_fails_for_wrong_editor(): void
    {
        $job = $this->createTestJob();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only the assigned editor');

        $this->service->startJob($job->id, $this->admin->id);
    }

    public function test_start_job_fails_if_already_started(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Invalid transition');

        $this->service->startJob($job->id, $this->editor->id);
    }

    public function test_mark_review_ready(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        $ready = $this->service->markReviewReady($job->id, $this->editor->id);

        $this->assertEquals('review_ready', $ready->status);
        $this->assertNotNull($ready->review_ready_at);
    }

    public function test_mark_review_ready_fails_if_not_in_progress(): void
    {
        $job = $this->createTestJob();

        $this->expectException(\DomainException::class);

        $this->service->markReviewReady($job->id, $this->editor->id);
    }

    public function test_finalize_job(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);
        $this->service->markReviewReady($job->id, $this->editor->id);

        $result = $this->service->finalizeJob($job->id, $this->editor->id);

        $this->assertEquals('final', $result['job']->status);
        $this->assertNotNull($result['job']->finalized_at);
        $this->assertNotNull($result['performance']);
    }

    public function test_finalize_with_manual_date(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);
        $this->service->markReviewReady($job->id, $this->editor->id);

        $result = $this->service->finalizeJob($job->id, $this->editor->id, '2026-04-22 14:00:00');

        $this->assertEquals('2026-04-22', $result['job']->finalized_at->toDateString());
    }

    public function test_cannot_skip_states(): void
    {
        $job = $this->createTestJob();

        // Can't go assigned → review_ready
        $this->expectException(\DomainException::class);
        $this->service->markReviewReady($job->id, $this->editor->id);
    }

    public function test_cannot_finalize_from_in_progress(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        $this->expectException(\DomainException::class);
        $this->service->finalizeJob($job->id, $this->editor->id);
    }

    // ─── Reassignment ────────────────────────────────────────────────

    public function test_reassign_job(): void
    {
        $editor2 = Employee::create([
            'first_name' => 'Editor', 'last_name' => 'Two',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $job = $this->createTestJob();

        $updated = $this->service->reassignJob(
            $job->id, $editor2->id, $this->admin->id, 'Original editor on leave'
        );

        $this->assertEquals($editor2->id, $updated->assigned_to);

        $this->assertDatabaseHas('job_reassignments', [
            'editing_job_id' => $job->id,
            'old_assignee'   => $this->editor->id,
            'new_assignee'   => $editor2->id,
            'reason'         => 'Original editor on leave',
        ]);
    }

    public function test_reassign_at_any_status(): void
    {
        $editor2 = Employee::create([
            'first_name' => 'E2', 'last_name' => 'Test',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        // Reassign during in_progress
        $updated = $this->service->reassignJob($job->id, $editor2->id, $this->admin->id);
        $this->assertEquals($editor2->id, $updated->assigned_to);

        // Can continue workflow with new editor
        $ready = $this->service->markReviewReady($job->id, $editor2->id);
        $this->assertEquals('review_ready', $ready->status);
    }

    public function test_cannot_reassign_final_job(): void
    {
        $editor2 = Employee::create([
            'first_name' => 'E2', 'last_name' => 'X',
            'payroll_mode' => 'monthly_staff', 'status' => 'active',
            'is_active' => true, 'start_date' => '2025-01-01',
        ]);

        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);
        $this->service->markReviewReady($job->id, $this->editor->id);
        $this->service->finalizeJob($job->id, $this->editor->id);

        $this->expectException(\DomainException::class);
        $this->service->reassignJob($job->id, $editor2->id, $this->admin->id);
    }

    // ─── Detail Updates ──────────────────────────────────────────────

    public function test_update_job_details(): void
    {
        $job = $this->createTestJob();

        $updated = $this->service->updateJobDetails($job->id, $this->admin->id, [
            'job_name'      => 'Updated Title',
            'deadline_days' => 7,
        ]);

        $this->assertEquals('Updated Title', $updated->job_name);
        $this->assertEquals(7, $updated->deadline_days);

        $this->assertEquals(2, JobModification::where('editing_job_id', $job->id)->count());
    }

    public function test_update_deadline_recalculates_if_in_progress(): void
    {
        Carbon::setTestNow('2026-04-13 10:00:00');

        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        // Original: 5 days → 2026-04-18
        $job->refresh();
        $this->assertEquals('2026-04-18', $job->deadline_date->toDateString());

        // Update to 10 days → 2026-04-23
        $updated = $this->service->updateJobDetails($job->id, $this->admin->id, [
            'deadline_days' => 10,
        ]);

        $this->assertEquals('2026-04-23', $updated->deadline_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_cannot_update_finalized_job(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);
        $this->service->markReviewReady($job->id, $this->editor->id);
        $this->service->finalizeJob($job->id, $this->editor->id);

        $this->expectException(\DomainException::class);
        $this->service->updateJobDetails($job->id, $this->admin->id, [
            'job_name' => 'Changed',
        ]);
    }

    public function test_ignores_disallowed_fields(): void
    {
        $job = $this->createTestJob();

        $updated = $this->service->updateJobDetails($job->id, $this->admin->id, [
            'status' => 'final', // not allowed
            'assigned_to' => 999, // not allowed
        ]);

        $this->assertEquals('assigned', $updated->status);
        $this->assertEquals($this->editor->id, $updated->assigned_to);
    }

    // ─── Soft Delete ─────────────────────────────────────────────────

    public function test_delete_job_soft_deletes(): void
    {
        $job = $this->createTestJob();

        $this->service->deleteJob($job->id);

        $job->refresh();
        $this->assertTrue($job->is_deleted);
    }

    // ─── Performance Metrics ─────────────────────────────────────────

    public function test_performance_early_completion(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');
        $job = $this->createTestJob(); // 5 days deadline

        Carbon::setTestNow('2026-04-10 09:00:00');
        $this->service->startJob($job->id, $this->editor->id);
        // Deadline: April 15

        Carbon::setTestNow('2026-04-13 16:00:00');
        $this->service->markReviewReady($job->id, $this->editor->id);
        // Completed April 13, deadline April 15 → early by 2 days

        $job->refresh();
        $metrics = $this->service->calculateJobPerformance($job);

        $this->assertEquals('early', $metrics['deadline_compliance']);
        $this->assertEquals(2, $metrics['days_difference']);
        $this->assertEquals(3, $metrics['work_duration_days']);

        Carbon::setTestNow();
    }

    public function test_performance_late_completion(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');
        $job = $this->createTestJob();

        Carbon::setTestNow('2026-04-10 09:00:00');
        $this->service->startJob($job->id, $this->editor->id);
        // Deadline: April 15

        Carbon::setTestNow('2026-04-17 16:00:00');
        $this->service->markReviewReady($job->id, $this->editor->id);
        // Completed April 17, deadline April 15 → late by 2 days

        $job->refresh();
        $metrics = $this->service->calculateJobPerformance($job);

        $this->assertEquals('late', $metrics['deadline_compliance']);
        $this->assertEquals(2, $metrics['days_difference']);

        Carbon::setTestNow();
    }

    public function test_employee_monthly_performance(): void
    {
        // Create 3 jobs with different completion times
        Carbon::setTestNow('2026-04-01 09:00:00');

        $jobs = [];
        for ($i = 0; $i < 3; $i++) {
            $jobs[] = $this->createTestJob("Job {$i}");
        }

        // Start all jobs
        foreach ($jobs as $job) {
            $this->service->startJob($job->id, $this->editor->id);
        }

        // Complete job 0 early (day 3 of 5)
        Carbon::setTestNow('2026-04-04 16:00:00');
        $this->service->markReviewReady($jobs[0]->id, $this->editor->id);

        // Complete job 1 on time (day 5)
        Carbon::setTestNow('2026-04-06 16:00:00');
        $this->service->markReviewReady($jobs[1]->id, $this->editor->id);

        // Complete job 2 late (day 7)
        Carbon::setTestNow('2026-04-08 16:00:00');
        $this->service->markReviewReady($jobs[2]->id, $this->editor->id);

        $performance = $this->service->getEmployeePerformance($this->editor->id, 2026, 4);

        $this->assertEquals(3, $performance['total_jobs']);
        $this->assertEquals(1, $performance['early']);
        $this->assertEquals(1, $performance['on_time']);
        $this->assertEquals(1, $performance['late']);
        $this->assertEquals(66.7, $performance['deadline_compliance_rate']);

        Carbon::setTestNow();
    }

    // ─── Deadline Notifications ──────────────────────────────────────

    public function test_create_deadline_notification(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        $notification = $this->service->createDeadlineNotification(
            $job->id, $this->editor->id, '3_days'
        );

        $this->assertNotNull($notification);
        $this->assertEquals('3_days', $notification->notification_type);
        $this->assertFalse($notification->is_read);
    }

    public function test_skip_notification_if_review_ready(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);
        $this->service->markReviewReady($job->id, $this->editor->id);

        $notification = $this->service->createDeadlineNotification(
            $job->id, $this->editor->id, 'overdue'
        );

        $this->assertNull($notification);
    }

    public function test_mark_notification_read(): void
    {
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        $notification = $this->service->createDeadlineNotification(
            $job->id, $this->editor->id, '1_day'
        );

        $this->service->markNotificationRead($notification->id);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
    }

    // ─── Overdue Query ───────────────────────────────────────────────

    public function test_get_overdue_jobs(): void
    {
        Carbon::setTestNow('2026-04-01 09:00:00');
        $job = $this->createTestJob();
        $this->service->startJob($job->id, $this->editor->id);

        // Deadline is April 6. Move time to April 8
        Carbon::setTestNow('2026-04-08 09:00:00');

        $overdue = $this->service->getOverdueJobs();

        $this->assertCount(1, $overdue);
        $this->assertEquals($job->id, $overdue->first()->id);

        Carbon::setTestNow();
    }

    // ─── Helper ──────────────────────────────────────────────────────

    private function createTestJob(string $name = 'Test Montage'): EditingJob
    {
        return $this->service->createJob([
            'job_name'      => $name,
            'game_id'       => $this->game->id,
            'assigned_to'   => $this->editor->id,
            'assigned_by'   => $this->admin->id,
            'deadline_days' => 5,
        ]);
    }
}
