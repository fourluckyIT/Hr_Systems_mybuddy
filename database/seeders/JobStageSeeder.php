<?php

namespace Database\Seeders;

use App\Models\JobStage;
use Illuminate\Database\Seeder;

class JobStageSeeder extends Seeder
{
    public function run(): void
    {
        $recordingStages = [
            ['code' => 'draft', 'name' => 'ร่าง (Draft)', 'color' => 'gray', 'is_core' => true],
            ['code' => 'scheduled', 'name' => 'กำหนดวันแล้ว', 'color' => 'blue', 'is_core' => true],
            ['code' => 'recording', 'name' => 'กำลังถ่ายทำ', 'color' => 'yellow', 'is_core' => true],
            ['code' => 'shot', 'name' => 'ถ่ายเสร็จ', 'color' => 'green', 'is_core' => true],
            ['code' => 'cancelled', 'name' => 'ยกเลิก', 'color' => 'red', 'is_core' => true],
        ];

        foreach ($recordingStages as $i => $stage) {
            JobStage::updateOrCreate(
                ['code' => $stage['code']],
                [
                    'type' => 'recording',
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'is_core' => $stage['is_core'],
                    'sort_order' => $i + 1,
                ]
            );
        }

        $editStages = [
            ['code' => 'pending_resource', 'name' => 'รอฟุตเทจ', 'color' => 'gray', 'is_core' => true],
            ['code' => 'assigned', 'name' => 'มอบหมายแล้ว', 'color' => 'blue', 'is_core' => true],
            ['code' => 'editing', 'name' => 'กำลังตัดต่อ', 'color' => 'yellow', 'is_core' => true],
            ['code' => 'submitted', 'name' => 'ส่งงานแล้ว', 'color' => 'indigo', 'is_core' => true],
            ['code' => 'approved', 'name' => 'ผ่านแล้ว (รออัป)', 'color' => 'emerald', 'is_core' => true],
            ['code' => 'done', 'name' => 'ปิดงาน (Publish)', 'color' => 'green', 'is_core' => true],
        ];

        foreach ($editStages as $i => $stage) {
            JobStage::updateOrCreate(
                ['code' => $stage['code']],
                [
                    'type' => 'edit',
                    'name' => $stage['name'],
                    'color' => $stage['color'],
                    'is_core' => $stage['is_core'],
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
