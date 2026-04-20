<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            
            // Company Identity
            $table->string('name')->default('Pro One IT Co., Ltd.'); // Pro One IT Co., Ltd.
            $table->string('tagline')->nullable()->default('LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์'); // Subtitle/Tagline
            
            // Contact & Legal
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_id')->nullable(); // เลขประจำตัวประเมิน
            
            // Design / Branding
            $table->string('primary_color')->default('#4f46e5'); // Indigo (neutral CI)
            $table->string('secondary_color')->default('#4338ca'); // Indigo darker
            $table->string('text_color')->default('#1f2937'); // Dark gray
            
            // Payslip Customization
            $table->text('payslip_header_note')->nullable(); // หมายเหตุเพิ่มเติมใต้ header
            $table->text('payslip_footer_text')->nullable()->default('เอกสารฉบับนี้เป็นของผู้มีรายชื่อข้างบนเท่านั้น ไม่สามารถเผยแพร่ให้กับผู้อื่นได้'); // คำเตือนท้าย
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Insert default company profile if not exists
        DB::table('company_profiles')->insertOrIgnore([
            'name' => 'Pro One IT Co., Ltd.',
            'tagline' => 'LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์',
            'primary_color' => '#4f46e5', // Indigo
            'secondary_color' => '#4338ca',
            'text_color' => '#1f2937',
            'payslip_footer_text' => 'เอกสารฉบับนี้เป็นของผู้มีรายชื่อข้างบนเท่านั้น ไม่สามารถเผยแพร่ให้กับผู้อื่นได้',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_profiles');
    }
};
