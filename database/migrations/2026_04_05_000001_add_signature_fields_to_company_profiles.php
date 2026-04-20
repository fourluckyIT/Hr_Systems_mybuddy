<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            // Payslip Header Customization
            $table->string('payslip_header_subtitle')->nullable()->after('tagline'); 
            // e.g., "LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์"
            
            // Signature Images
            $table->string('signature_approver_name')->nullable()->after('payslip_footer_text'); 
            // ชื่อผู้จ่ายเงินเดือน
            
            $table->string('signature_approver_image_path')->nullable()->after('signature_approver_name'); 
            // Path to approver signature PNG
            
            $table->string('signature_receiver_name')->nullable()->after('signature_approver_image_path'); 
            // ชื่อผู้รับ (optional)
            
            $table->string('signature_receiver_image_path')->nullable()->after('signature_receiver_name'); 
            // Path to receiver signature PNG
        });
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payslip_header_subtitle',
                'signature_approver_name',
                'signature_approver_image_path',
                'signature_receiver_name',
                'signature_receiver_image_path',
            ]);
        });
    }
};
