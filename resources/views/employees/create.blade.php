@extends('layouts.app')

@section('title', 'เพิ่มพนักงานใหม่')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">เพิ่มพนักงานใหม่</h1>
        <a href="{{ route('employees.index') }}" class="text-gray-500 hover:text-gray-700">กลับไปหน้ารายชื่อ</a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form action="{{ route('employees.store') }}" method="POST">
            @csrf

            <!-- ข้อมูลส่วนตัว -->
            <h2 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">ข้อมูลส่วนตัว</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รหัสพนักงาน</label>
                    <input type="text" name="employee_code" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="EMP001">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง <span class="text-red-500">*</span></label>
                    <input type="text" name="first_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="สมชาย">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">นามสกุล <span class="text-red-500">*</span></label>
                    <input type="text" name="last_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ใจดี">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อเล่น</label>
                    <input type="text" name="nickname" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ชาย">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทรศัพท์</label>
                    <input type="text" name="phone" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="08x-xxx-xxxx">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รหัสบัตรประชาชน</label>
                    <input type="text" name="id_card" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="x-xxxx-xxxxx-xx-x">
                </div>
            </div>

            <!-- ข้อมูลการทำงาน -->
            <h2 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">ข้อมูลการทำงานและเงินเดือน</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">แผนก</label>
                    <select name="department_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">- เลือกแผนก -</option>
                        @foreach($departments as $dept)
                            <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ตำแหน่ง</label>
                    <select name="position_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">- เลือกตำแหน่ง -</option>
                        @foreach($positions as $pos)
                            <option value="{{ $pos->id }}">{{ $pos->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">รูปแบบจ่ายเงิน (Payroll Mode) <span class="text-red-500">*</span></label>
                    <select name="payroll_mode" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="monthly_staff">พนักงานประจำ (เงินเดือน)</option>
                        <option value="freelance_layer">ฟรีแลนซ์ (Layer Rate)</option>
                        <option value="freelance_fixed">ฟรีแลนซ์ (Fixed Rate)</option>
                        <option value="youtuber_salary">Youtuber (เงินเดือน)</option>
                        <option value="youtuber_settlement">Youtuber (หักค่าใช้จ่าย)</option>
                        <option value="custom_hybrid">รูปแบบผสม</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ฐานเงินเดือนหลัก (Base Salary)</label>
                    <input type="number" name="base_salary" min="0" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">วันที่เริ่มงาน</label>
                    <input type="date" name="start_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>

            <!-- ข้อมูลบัญชีธนาคาร -->
            <h2 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">ข้อมูลบัญชีธนาคาร</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนาคาร</label>
                    <input type="text" name="bank_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="เช่น กสิกรไทย">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เลขที่บัญชี</label>
                    <input type="text" name="account_number" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อบัญชี (ถ้าไม่ตรงกับชื่อจริง)</label>
                    <input type="text" name="account_name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ชื่อ-นามสกุล บัญชี">
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('employees.index') }}" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50">
                    ยกเลิก
                </a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
                    บันทึกข้อมูลพนักงาน
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
