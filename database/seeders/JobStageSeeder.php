<?php

namespace Database\Seeders;

use App\Models\JobStage;
use Illuminate\Database\Seeder;

class JobStageSeeder extends Seeder
{
    public function run(): void
    {
        // Recording Stages (Core)
        $recordingStages = [
            ['code' => 'draft', 'name' => 'ร่าง (Draft)', 'color' => 'gray'],
            ['code' => 'scheduled', 'name' => 'กำหนดวันแล้ว', 'color' => 'blue'],
            ['code' => 'recording', 'name' => 'กำลังถ่ายทำ', 'color' => 'yellow'],
            ['code' => 'shot', 'name' => 'ถ่ายเสร็จ', 'color' => 'green'],
            ['code' => 'cancelled', 'name' => 'ยกเลิก', 'color' => 'red'],
        ];

        foreach ($recordingStages as $i => $stage) {
            JobStage::updateOrCreate(
                ['type' => 'recording', 'code' => $stage['code']],
                [
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'is_core' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }

        // Editing Stages (New Consolidated Workflow)
        $editStages = [
            ['code' => 'assigned', 'name' => 'มอบหมายแล้ว', 'color' => 'gray'],
            ['code' => 'in_progress', 'name' => 'กำลังตัดต่อ', 'color' => 'blue'],
            ['code' => 'review_ready', 'name' => 'พร้อมตรวจ', 'color' => 'yellow'],
            ['code' => 'final', 'name' => 'ปิดงาน (Final)', 'color' => 'green'],
        ];

        foreach ($editStages as $i => $stage) {
            JobStage::updateOrCreate(
                ['type' => 'edit', 'code' => $stage['code']],
                [
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'is_core' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
