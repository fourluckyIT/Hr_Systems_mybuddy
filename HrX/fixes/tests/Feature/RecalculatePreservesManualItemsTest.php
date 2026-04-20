<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollItem;
use App\Services\Payroll\PayrollCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BUG-02 survival test.
 *
 * คน admin ปรับเงินเดือนเพิ่มเองทาง UI (source_flag='manual') → กด Recalculate
 * → แถว manual ต้องยังอยู่ครบ ไม่ถูกลบทิ้ง
 *
 * ถ้า test นี้ FAIL = ข้อมูลการปรับแต่ง payroll หายได้จริง อย่า deploy!
 */
class RecalculatePreservesManualItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalculate_does_not_delete_manual_items(): void
    {
        // Arrange — สร้าง batch + employee + 2 แถว (1 auto, 1 manual)
        $batch    = PayrollBatch::factory()->create(['year' => 2026, 'month' => 4]);
        $employee = Employee::factory()->create(['payroll_mode' => 'monthly_staff']);

        $autoItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'salary',
            'label'            => 'Base salary',
            'amount'           => 20000,
            'source_flag'      => 'auto',
        ]);

        $manualItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'bonus',
            'label'            => 'Special adjustment by admin',
            'amount'           => 3500,
            'source_flag'      => 'manual',
        ]);

        $overrideItem = PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'deduction',
            'label'            => 'Advance repay — overridden',
            'amount'           => -1000,
            'source_flag'      => 'override',
        ]);

        // Act — กด recalculate
        app(PayrollCalculationService::class)->recalculate($batch, $employee);

        // Assert — auto item ถูกสร้างใหม่ (เลข id เปลี่ยน), manual/override อยู่เหมือนเดิม
        $this->assertDatabaseHas('payroll_items', [
            'id'          => $manualItem->id,
            'source_flag' => 'manual',
            'amount'      => 3500,
        ]);

        $this->assertDatabaseHas('payroll_items', [
            'id'          => $overrideItem->id,
            'source_flag' => 'override',
            'amount'      => -1000,
        ]);

        // auto id เดิมควรถูกลบ (regeneration)
        $this->assertDatabaseMissing('payroll_items', ['id' => $autoItem->id]);

        // ต้องมี auto row ใหม่อย่างน้อย 1 แถว
        $this->assertGreaterThan(
            0,
            PayrollItem::where('payroll_batch_id', $batch->id)
                ->where('employee_id', $employee->id)
                ->where('source_flag', 'auto')
                ->count()
        );
    }

    public function test_reset_to_auto_does_destroy_manual_rows(): void
    {
        // เคสพิเศษ: resetToAuto() เป็น "nuclear option" → ต้องลบทุกอย่างจริง
        $batch    = PayrollBatch::factory()->create();
        $employee = Employee::factory()->create(['payroll_mode' => 'monthly_staff']);

        PayrollItem::create([
            'payroll_batch_id' => $batch->id,
            'employee_id'      => $employee->id,
            'category'         => 'bonus',
            'label'            => 'Manual',
            'amount'           => 5000,
            'source_flag'      => 'manual',
        ]);

        app(PayrollCalculationService::class)->resetToAuto($batch, $employee);

        // ไม่ควรมี manual เหลือแล้ว
        $this->assertEquals(
            0,
            PayrollItem::where('payroll_batch_id', $batch->id)
                ->where('employee_id', $employee->id)
                ->where('source_flag', 'manual')
                ->count()
        );
    }
}
