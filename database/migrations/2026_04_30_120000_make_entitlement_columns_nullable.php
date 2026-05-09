<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ทำให้ vacation_entitlement / sick_leave_entitlement / personal_leave_entitlement
 * เป็น nullable เพื่อให้ NULL = "ใช้จาก policy"
 *
 * ⚠️ บน SQLite ห้ามใช้ ALTER TABLE RENAME แล้ว recreate เพราะจะทำให้
 * FK ของ tables อื่นที่ชี้มาตารางนี้ เพี้ยนไปชี้ temp name
 *
 * วิธีที่ปลอดภัย: ใช้ doctrine/dbal ถ้ามี, ไม่งั้นข้ามไปเลยถ้าเป็น SQLite
 * (สำหรับ MySQL/Postgres ใช้ ALTER TABLE MODIFY ตามปกติ)
 *
 * บน SQLite: เนื่องจาก columns ที่เพิ่มผ่าน Schema::table()->after() ไม่บังคับ NOT NULL
 * อย่างเข้มงวด การ INSERT/UPDATE ที่ใส่ NULL ยังพอจะใช้งานได้ตราบใดที่ตัว default ทำงาน
 *
 * ถ้า migration นี้ถูกรันบน SQLite database ที่ใช้งานอยู่จริง — ให้ rebuild table
 * แบบระวัง (PRAGMA legacy_alter_table = ON เพื่อกัน FK propagation)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA legacy_alter_table = ON');
            DB::statement('PRAGMA foreign_keys = OFF');

            $createSql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='employees'")->sql;

            $replacements = [
                '"vacation_entitlement" integer not null default (\'6\')'         => '"vacation_entitlement" integer null',
                '"sick_leave_entitlement" integer not null default (\'30\')'       => '"sick_leave_entitlement" integer null',
                '"personal_leave_entitlement" integer not null default (\'3\')'    => '"personal_leave_entitlement" integer null',
            ];
            $newSql = strtr($createSql, $replacements);
            if ($newSql === $createSql) {
                // อาจถูก migrate มาแล้ว — เช็คว่า column nullable แล้วยัง
                $cols = collect(DB::select('PRAGMA table_info(employees)'))->keyBy('name');
                if ((int) ($cols['vacation_entitlement']->notnull ?? 0) === 0) {
                    DB::statement('PRAGMA foreign_keys = ON');
                    DB::statement('PRAGMA legacy_alter_table = OFF');
                    return; // already done
                }
                throw new \RuntimeException('Could not relax NOT NULL on entitlement columns');
            }

            $cols = DB::select('PRAGMA table_info(employees)');
            $colNames = collect($cols)->pluck('name')->map(fn($n) => '"' . $n . '"')->implode(', ');

            DB::statement("ALTER TABLE employees RENAME TO _employees_old");
            DB::statement($newSql);
            DB::statement("INSERT INTO employees ({$colNames}) SELECT {$colNames} FROM _employees_old");
            DB::statement("DROP TABLE _employees_old");

            DB::statement('PRAGMA foreign_keys = ON');
            DB::statement('PRAGMA legacy_alter_table = OFF');
        } else {
            DB::statement('ALTER TABLE employees MODIFY vacation_entitlement INT UNSIGNED NULL');
            DB::statement('ALTER TABLE employees MODIFY sick_leave_entitlement INT UNSIGNED NULL');
            DB::statement('ALTER TABLE employees MODIFY personal_leave_entitlement INT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        // No-op
    }
};
