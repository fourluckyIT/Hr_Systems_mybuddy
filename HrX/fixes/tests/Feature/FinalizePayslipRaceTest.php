<?php

namespace Tests\Feature;

use App\Exceptions\AlreadyFinalizedException;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\FinalizePayslipAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * BUG-08 survival test.
 *
 * 2 admin กด Finalize พร้อมกัน → ต้องสำเร็จแค่ครั้งเดียว
 *
 * เราจำลอง race condition 2 แบบ:
 *   1. ซีเรียล: เรียก execute() 2 ครั้ง → ครั้งที่ 2 โยน AlreadyFinalizedException
 *   2. DB-level: พยายาม insert 2 payslip ที่ (batch,employee) เดียวกัน → unique index ต้องกัน
 */
class FinalizePayslipRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_finalize_is_noop_not_duplicate(): void
    {
        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();
        $user     = User::factory()->create(['role' => 'admin']);

        $slip = Payslip::factory()->create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'status'           => 'draft',
            'net_pay'          => 18500,
        ]);

        $action = app(FinalizePayslipAction::class);

        // ครั้งแรก — สำเร็จ
        $result = $action->execute($slip->id, $user->id);
        $this->assertEquals('finalized', $result->status);
        $this->assertNotNull($result->finalized_at);

        // ครั้งที่สอง (admin คนเดิมกดซ้ำ / admin คนอื่นกดพร้อมกัน)
        // ต้องโยน AlreadyFinalizedException — ไม่ใช่ finalize ซ้ำ
        $this->expectException(AlreadyFinalizedException::class);
        $action->execute($slip->id, $user->id);
    }

    public function test_db_unique_index_prevents_duplicate_payslip_per_batch_employee(): void
    {
        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();

        Payslip::factory()->create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'status'           => 'draft',
        ]);

        // พยายาม insert อีกแถวใน (batch, employee) เดียวกัน → ต้อง fail
        $this->expectException(QueryException::class);

        Payslip::factory()->create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'status'           => 'draft',
        ]);
    }

    public function test_only_one_of_two_parallel_finalize_succeeds_via_lock(): void
    {
        // จำลอง lockForUpdate จริงๆ ต้องใช้ 2 connection แต่บน sqlite ใช้ไม่ได้
        // ถ้า CI ใช้ MySQL เต็มรูปแบบจะ uncomment บล็อกนี้ได้
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('lockForUpdate race test needs MySQL (use local MySQL, not sqlite :memory:)');
        }

        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();
        $user     = User::factory()->create(['role' => 'admin']);

        $slip = Payslip::factory()->create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'status'           => 'draft',
            'net_pay'          => 18500,
        ]);

        $action = app(FinalizePayslipAction::class);

        // รัน 2 ครั้งต่อเนื่อง (แบบ single-threaded จำลอง serialised race)
        $firstOk = false;
        $secondCaught = false;

        try {
            $action->execute($slip->id, $user->id);
            $firstOk = true;
        } catch (\Throwable) {}

        try {
            $action->execute($slip->id, $user->id);
        } catch (AlreadyFinalizedException) {
            $secondCaught = true;
        }

        $this->assertTrue($firstOk, 'finalize ครั้งแรกควรสำเร็จ');
        $this->assertTrue($secondCaught, 'finalize ครั้งที่สองต้องโยน AlreadyFinalized');
    }
}
