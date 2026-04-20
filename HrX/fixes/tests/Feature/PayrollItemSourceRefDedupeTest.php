<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-03 survival test.
 *
 * เดิม: dedupe ด้วย label+amount → ฟรีแลนซ์ตัดคลิป 2 ชิ้นราคาเท่ากัน = false positive
 * ใหม่: dedupe ด้วย (source_ref_type, source_ref_id) → 2 คลิปคนละ work_log_id = ผ่าน
 *
 * Test นี้ยืนยันว่า:
 *   (a) 2 แถวคนละ source_ref_id = อยู่ได้ทั้งคู่
 *   (b) 2 แถว source_ref_id ซ้ำกัน = DB kicks out
 */
class PayrollItemSourceRefDedupeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_label_and_amount_different_source_ref_both_allowed(): void
    {
        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();

        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'freelance',
            'label'            => 'Short edit',
            'amount'           => 500,
            'source_flag'      => 'auto',
            'source_ref_type'  => 'work_log',
            'source_ref_id'    => 101,
        ]);

        // label เหมือนกัน + amount เหมือนกัน แต่คนละ work_log_id → ต้อง insert ได้
        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'freelance',
            'label'            => 'Short edit',
            'amount'           => 500,
            'source_flag'      => 'auto',
            'source_ref_type'  => 'work_log',
            'source_ref_id'    => 102,
        ]);

        $this->assertEquals(2, PayrollItem::where('payroll_batch_id', $batch->id)->count());
    }

    public function test_duplicate_source_ref_is_blocked_by_unique_index(): void
    {
        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();

        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'freelance',
            'label'            => 'Short edit',
            'amount'           => 500,
            'source_flag'      => 'auto',
            'source_ref_type'  => 'work_log',
            'source_ref_id'    => 101,
        ]);

        // พยายาม insert source_ref_id เดิม → DB ต้อง reject
        $this->expectException(QueryException::class);

        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'freelance',
            'label'            => 'Short edit',
            'amount'           => 500,
            'source_flag'      => 'auto',
            'source_ref_type'  => 'work_log',
            'source_ref_id'    => 101,   // ซ้ำกับแถวด้านบน
        ]);
    }

    public function test_source_flag_check_constraint_rejects_bad_value(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('CHECK constraint test needs MySQL 8.0+');
        }

        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create();

        $this->expectException(QueryException::class);

        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'salary',
            'label'            => 'Base',
            'amount'           => 20000,
            'source_flag'      => 'robot',   // ไม่อยู่ใน (auto|manual|override)
        ]);
    }
}
