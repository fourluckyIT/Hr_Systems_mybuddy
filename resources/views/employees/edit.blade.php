@extends('layouts.app')
@section('title', 'Edit Employee')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-900">แก้ไขพนักงาน</h1>
        <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-gray-900">กลับไปหน้า Employee Board</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <form method="POST" action="{{ route('employees.update', $employee->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อ *</label>
                    <input type="text" name="first_name" value="{{ old('first_name', $employee->first_name) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">นามสกุล *</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $employee->last_name) }}" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อเล่น</label>
                    <input type="text" name="nickname" value="{{ old('nickname', $employee->nickname) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">รหัสพนักงาน</label>
                    <input type="text" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payroll Mode *</label>
                    <select name="payroll_mode" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="monthly_staff" @selected(old('payroll_mode', $employee->payroll_mode) === 'monthly_staff')>พนักงานรายเดือน</option>
                        <option value="office_staff" @selected(old('payroll_mode', $employee->payroll_mode) === 'office_staff')>พนักงานออฟฟิศ (OFFICE)</option>
                        <option value="freelance_layer" @selected(old('payroll_mode', $employee->payroll_mode) === 'freelance_layer')>Freelance</option>
                        <option value="youtuber_salary" @selected(old('payroll_mode', $employee->payroll_mode) === 'youtuber_salary')>YouTuber เงินเดือน</option>
                        <option value="youtuber_settlement" @selected(old('payroll_mode', $employee->payroll_mode) === 'youtuber_settlement')>YouTuber Settlement</option>
                        <option value="custom_hybrid" @selected(old('payroll_mode', $employee->payroll_mode) === 'custom_hybrid')>รูปแบบผสม</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">แผนก</label>
                    <select name="department_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">-</option>
                        @foreach($departments as $dept)
                        <option value="{{ $dept->id }}" @selected((int) old('department_id', $employee->department_id) === $dept->id)>{{ $dept->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ตำแหน่ง</label>
                    <select name="position_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                        <option value="">-</option>
                        @foreach($positions as $pos)
                        <option value="{{ $pos->id }}" @selected((int) old('position_id', $employee->position_id) === $pos->id)>{{ $pos->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">เงินเดือน</label>
                    <input type="number" name="base_salary" step="0.01" value="{{ old('base_salary', $employee->salaryProfile?->base_salary) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                @php $isAdmin = auth()->user()?->hasRole('admin'); @endphp
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        ราคาเหมา/คลิป (Default)
                        @if(!$isAdmin)<span class="text-[10px] text-gray-400">(เฉพาะ admin)</span>@endif
                    </label>
                    <input type="number" name="fixed_rate_per_clip" step="0.01" min="0"
                        value="{{ old('fixed_rate_per_clip', $employee->fixed_rate_per_clip) }}"
                        @disabled(!$isAdmin)
                        class="w-full px-3 py-2 border rounded-lg text-sm {{ $isAdmin ? '' : 'bg-gray-100 text-gray-500' }}"
                        placeholder="เว้นว่างถ้าใช้ Price/min — ถ้าตั้งจะ seed เป็นราคาเหมาเมื่อปิดงาน">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">วันเริ่มงาน</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $employee->start_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">เบอร์โทร</label>
                    <input type="text" name="phone" value="{{ old('phone', $employee->profile?->phone) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">เลขบัตรประชาชน</label>
                    <input type="text" name="id_card" value="{{ old('id_card', $employee->profile?->id_card) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ธนาคาร</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $employee->bankAccount?->bank_name) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">เลขบัญชี</label>
                    <input type="text" name="account_number" value="{{ old('account_number', $employee->bankAccount?->account_number) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อบัญชี</label>
                    <input type="text" name="account_name" value="{{ old('account_name', $employee->bankAccount?->account_name) }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
            </div>

            {{-- บัญชีผู้ใช้สำหรับล็อกอิน --}}
            <div class="border-t border-gray-200 pt-4 mt-4">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-sm font-bold text-gray-700">บัญชีผู้ใช้ (สำหรับล็อกอิน)</span>
                    @if(!$employee->user)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700">ยังไม่มีบัญชี — กรอกเพื่อสร้าง</span>
                    @endif
                </div>
                <div class="grid md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">อีเมล {!! $employee->user ? '' : '<span class="text-red-500">*</span>' !!}</label>
                        <input type="email" name="email" {{ $employee->user ? '' : 'required' }}
                               value="{{ old('email', $employee->user?->email) }}"
                               class="w-full px-3 py-2 border rounded-lg text-sm" placeholder="user@example.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            รหัสผ่าน{{ $employee->user ? 'ใหม่ (เว้นว่างถ้าไม่เปลี่ยน)' : ' *' }}
                        </label>
                        <input type="text" name="password" minlength="6" {{ $employee->user ? '' : 'required' }}
                               class="w-full px-3 py-2 border rounded-lg text-sm"
                               placeholder="{{ $employee->user ? 'เว้นว่างเพื่อคงรหัสเดิม' : 'อย่างน้อย 6 ตัวอักษร' }}">
                    </div>
                </div>
            </div>

            @php
                $tiers = \App\Models\PerformanceTier::orderBy('id')->get();
                $useAttendance = in_array($employee->payroll_mode, ['monthly_staff', 'office_staff']);
            @endphp

            {{-- Tier override — only for payroll modes that use performance tiers --}}
            <div class="border-t border-gray-200 pt-4 mt-4">
                <div class="flex items-start gap-2 mb-3">
                    <span class="text-sm font-bold text-gray-700">Performance Tier</span>
                    @unless($useAttendance)
                        <span class="text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">ใช้ตาราง attendance: ไม่</span>
                    @endunless
                </div>
                <div class="grid md:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">แหล่งคำนวณ Tier</label>
                        <select name="tier_source" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="avg" @selected(old('tier_source', $employee->tier_source ?? 'avg') === 'avg')>เฉลี่ย 3 เดือน (default)</option>
                            <option value="monthly_total" @selected(old('tier_source', $employee->tier_source) === 'monthly_total')>นาทีรวมต่อเดือน</option>
                            <option value="manual" @selected(old('tier_source', $employee->tier_source) === 'manual')>Manual (override)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Tier Override (ถ้า Manual)</label>
                        <select name="tier_override_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">— ใช้ตามสูตร —</option>
                            @foreach($tiers as $t)
                                <option value="{{ $t->id }}" @selected((int) old('tier_override_id', $employee->tier_override_id) === $t->id)>
                                    {{ $t->name ?? $t->code }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">หมายเหตุ</label>
                        <input type="text" name="tier_override_note" maxlength="255"
                               value="{{ old('tier_override_note', $employee->tier_override_note) }}"
                               placeholder="เหตุผลที่ override"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
                <p class="text-[11px] text-gray-500 mt-2">ใช้ได้กับ monthly_staff / office_staff เท่านั้น (ที่ใช้ตาราง attendance)</p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('employees.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</a>
                <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">บันทึกการแก้ไข</button>
            </div>
        </form>
    </div>
</div>
@endsection
