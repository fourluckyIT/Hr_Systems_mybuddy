@extends('layouts.app')

@section('title', 'การตั้งค่ากฎระบบ')

@section('content')
<div x-data="{
    diligenceTiers: {{ json_encode($rules['diligence']?->config['tiers'] ?? []) }},
    addTier() {
        this.diligenceTiers.push({ late_count_max: 0, lwop_days_max: 0, amount: 0 });
    },
    removeTier(index) {
        this.diligenceTiers.splice(index, 1);
    }
}">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">การตั้งค่ากฎระบบ (Rule Dashboard) ⚙️</h1>
            <p class="text-gray-500 mt-1">จัดการเวลาทำงาน, ประกันสังคม และกฎเกณฑ์ต่างๆ ของบริษัท</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.master-data') }}" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Master Data</a>
            <a href="{{ route('settings.bonus.index') }}" class="px-3 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200">Bonus Manager</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-xl flex items-center gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" /></svg>
        <span class="font-semibold">{{ session('success') }}</span>
    </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        <!-- Unified Payroll Rule Panel -->
        <div class="md:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
            <div class="px-6 py-4 bg-gradient-to-r from-indigo-50 via-blue-50 to-orange-50 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-bold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M11.49 3.17a1 1 0 011.42 0l3.41 3.41a1 1 0 010 1.42l-7.78 7.78a1 1 0 01-.39.24l-3.5 1.17a1 1 0 01-1.26-1.26l1.17-3.5a1 1 0 01.24-.39l7.78-7.78z" clip-rule="evenodd" /></svg>
                    กฎคำนวณเงินเดือน (รวมทุกส่วนที่เกี่ยวข้อง)
                </h3>
                <span class="text-[10px] text-gray-500 font-bold uppercase">single workspace</span>
            </div>

            <div class="p-6 space-y-8">
                @php $moduleDefaults = $rules['module_defaults']?->config ?? []; @endphp
                <section class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
                    <h4 class="text-sm font-bold text-slate-800 mb-3">1) ค่าเริ่มต้นโมดูล</h4>
                    <form action="{{ route('settings.rules.update', 'module_defaults') }}" method="POST" class="space-y-4">
                        @csrf @method('PATCH')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">เปิดคำนวณ OT</p>
                                    <p class="text-xs text-gray-500">ปิดแล้ว OT จะเป็น 0 ใน payroll</p>
                                </div>
                                <input type="hidden" name="enable_overtime" value="0">
                                <input type="checkbox" name="enable_overtime" value="1" {{ ($moduleDefaults['enable_overtime'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            </label>

                            <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">เปิดคำนวณเบี้ยขยัน</p>
                                    <p class="text-xs text-gray-500">ปิดแล้วรายการ diligence จะเป็น 0</p>
                                </div>
                                <input type="hidden" name="enable_diligence" value="0">
                                <input type="checkbox" name="enable_diligence" value="1" {{ ($moduleDefaults['enable_diligence'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            </label>

                            <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">ค่าเริ่มต้น SSO (พนักงานใหม่)</p>
                                    <p class="text-xs text-gray-500">กำหนด default ของ toggle sso_deduction</p>
                                </div>
                                <input type="hidden" name="default_sso_deduction" value="0">
                                <input type="checkbox" name="default_sso_deduction" value="1" {{ ($moduleDefaults['default_sso_deduction'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            </label>

                            <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">ค่าเริ่มต้นหักมาสาย/ออกเร็ว (พนักงานใหม่)</p>
                                    <p class="text-xs text-gray-500">กำหนด default ของ deduct_late และ deduct_early</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-[10px] text-gray-500">Late</span>
                                    <input type="hidden" name="default_deduct_late" value="0">
                                    <input type="checkbox" name="default_deduct_late" value="1" {{ ($moduleDefaults['default_deduct_late'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                    <span class="text-[10px] text-gray-500">Early</span>
                                    <input type="hidden" name="default_deduct_early" value="0">
                                    <input type="checkbox" name="default_deduct_early" value="1" {{ ($moduleDefaults['default_deduct_early'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                </div>
                            </label>
                        </div>
                        <button type="submit" class="w-full bg-slate-700 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-slate-800 transition">บันทึกค่าเริ่มต้นโมดูล</button>
                    </form>
                </section>

                @php $rule = $rules['working_hours']; $config = $rule?->config; @endphp
                <section class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                    <h4 class="text-sm font-bold text-indigo-900 mb-3">2) เวลาทำงาน และวันทำงาน</h4>
                    <form action="{{ route('settings.rules.update', 'working_hours') }}" method="POST" class="space-y-4">
                        @csrf @method('PATCH')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                                    เวลาเข้างาน (In)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="เวลาเป้าหมายที่พนักงานต้องเข้างาน"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </label>
                                <input type="time" name="target_check_in" value="{{ $config['target_check_in'] ?? '09:00' }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">เวลาออกงาน (Out)</label>
                                <input type="time" name="target_check_out" value="{{ $config['target_check_out'] ?? '18:00' }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ชั่วโมงทำงานจริง/วัน</label>
                                <input type="number" name="target_minutes_per_day" value="{{ $config['target_minutes_per_day'] ?? 540 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">พักเที่ยงอัตโนมัติ (นาที/วัน)</label>
                                <input type="number" name="lunch_break_minutes" value="{{ $config['lunch_break_minutes'] ?? 60 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-700 focus:ring-2 focus:ring-indigo-500">
                                <p class="text-[10px] text-gray-500 mt-1">ระบบจะหักเวลานี้จากเวลาเข้า-ออกตอนคำนวณ OT</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                                    วันทำงานเฉลี่ย (Standard)
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="ใช้เป็นตัวแสดงผลเบื้องต้น (การคำนวณจริงจะใช้จำนวนวัน จ-ศ ในเดือนนั้นๆ)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </label>
                                <input type="number" name="working_days_per_month" value="{{ $config['working_days_per_month'] ?? 22 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-gray-400 bg-gray-50 cursor-not-allowed" readonly>
                            </div>
                        </div>
                        <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl mb-4">
                            <p class="text-[10px] font-bold text-slate-400 uppercase mb-2">วันหยุดประจำสัปดาห์ (Standard Weekend)</p>
                            <div class="flex flex-wrap gap-3">
                                @php 
                                    $days = [
                                        0 => 'อา', 1 => 'จ', 2 => 'อ', 3 => 'พ', 4 => 'พฤ', 5 => 'ศ', 6 => 'ส'
                                    ];
                                    $standardHolidays = $config['standard_holidays'] ?? [0, 6];
                                @endphp
                                @foreach($days as $val => $label)
                                <label class="flex items-center gap-1 cursor-pointer group">
                                    <input type="checkbox" name="standard_holidays[]" value="{{ $val }}" 
                                        {{ in_array($val, $standardHolidays) ? 'checked' : '' }}
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span class="text-xs {{ in_array($val, $standardHolidays) ? 'text-indigo-600 font-bold' : 'text-gray-500' }} group-hover:text-indigo-400 transition-colors">{{ $label }}</span>
                                </label>
                                @endforeach
                            </div>
                            <p class="text-[9px] text-gray-400 mt-2 italic">* ระบบจะใช้ค่านี้ในการสร้างตารางงานอัตโนมัติ (เช่น เสาร์-อาทิตย์)</p>
                        </div>
                        <label class="flex items-center justify-between p-3 bg-indigo-50/60 border border-indigo-100 rounded-xl">
                            <div>
                                <p class="text-xs font-bold text-indigo-800 uppercase">อนุญาตสลับวันหยุดตามประเพณี</p>
                                <p class="text-[11px] text-indigo-600">เปิดเฉพาะกิจการที่กฎหมายยกเว้น เช่น โรงแรม/ร้านอาหาร/ขนส่ง</p>
                            </div>
                            <div>
                                <input type="hidden" name="allow_company_holiday_swap" value="0">
                                <input type="checkbox" name="allow_company_holiday_swap" value="1" {{ ($config['allow_company_holiday_swap'] ?? false) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            </div>
                        </label>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition">บันทึกเวลาทำงาน</button>
                    </form>
                </section>

                <section class="rounded-xl border border-red-100 bg-red-50/40 p-4">
                    <h4 class="text-sm font-bold text-red-900 mb-3">3) OT และการหักสาย</h4>
                <!-- OT Rate Card -->
                @php $rule = $rules['ot_rate']; $config = $rule?->config; @endphp
                <form action="{{ route('settings.rules.update', 'ot_rate') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ตัวคูณ OT วันทำงานปกติ</label>
                            <input type="number" step="0.1" name="rate_multiplier_workday" value="{{ $config['rate_multiplier_workday'] ?? ($config['rate_multiplier'] ?? 1.5) }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ตัวคูณ OT วันหยุด</label>
                            <input type="number" step="0.1" name="rate_multiplier_holiday" value="{{ $config['rate_multiplier_holiday'] ?? 3.0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">เพดานชั่วโมง OT / สัปดาห์</label>
                            <input type="number" step="0.5" name="weekly_ot_limit_hours" value="{{ $config['weekly_ot_limit_hours'] ?? 36 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">เพดานชั่วโมง OT / เดือน (internal)</label>
                            <input type="number" name="max_ot_hours" value="{{ $config['max_ot_hours'] ?? 40 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ตัวคูณค่าทำงานวันหยุด (ชั่วโมงปกติ / รายเดือน)</label>
                            <input type="number" step="0.1" name="holiday_regular_multiplier_monthly" value="{{ $config['holiday_regular_multiplier_monthly'] ?? 1.0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                        </div>
                        <label class="flex items-center justify-between p-3 bg-white border rounded-xl">
                            <div>
                                <p class="text-[10px] font-bold text-gray-500 uppercase">เปิดโหมดแยกค่าทำงานวันหยุดตามกฎหมาย</p>
                                <p class="text-[11px] text-gray-500">ชั่วโมงปกติวันหยุด + ชม.เกิน (OT 3 เท่า)</p>
                            </div>
                            <div>
                                <input type="hidden" name="enable_holiday_legal_split" value="0">
                                <input type="checkbox" name="enable_holiday_legal_split" value="1" {{ ($config['enable_holiday_legal_split'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-red-600">
                            </div>
                        </label>
                    </div>
                    <label class="flex items-center justify-between p-3 bg-red-50/60 border border-red-100 rounded-xl">
                        <div>
                            <p class="text-xs font-bold text-red-800 uppercase">ต้องได้รับความยินยอมลูกจ้าง</p>
                            <p class="text-[11px] text-red-600">ใช้เป็น policy flag เพื่อบันทึกกฎองค์กร/กฎหมาย</p>
                        </div>
                        <div>
                            <input type="hidden" name="requires_employee_consent" value="0">
                            <input type="checkbox" name="requires_employee_consent" value="1" {{ ($config['requires_employee_consent'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-red-600">
                        </div>
                    </label>
                    <div class="text-[11px] text-red-500 bg-red-50 border border-red-100 rounded-lg px-3 py-2">
                        กฎหมายแรงงานไทยทั่วไป: OT วันทำงานปกติ 1.5 เท่า, วันหยุด 3 เท่า และไม่ควรเกิน 36 ชั่วโมงต่อสัปดาห์โดยไม่มีความยินยอม
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded-xl font-bold text-xs hover:bg-red-700 transition">อัปเดตกฎ OT</button>
                </form>

                <hr class="border-gray-100">

                <!-- Late Deduction Card -->
                @php $rule = $rules['late_deduction']; $config = $rule?->config; @endphp
                <form action="{{ route('settings.rules.update', 'late_deduction') }}" method="POST" class="space-y-4">
                    @csrf @method('PATCH')
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">โหมดการหักมาสาย</label>
                            <div class="px-3 py-2 bg-gray-50 border rounded-lg text-xs font-bold text-red-600">อิงตามสัดส่วนเงินเดือน (Proportional)</div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">โควต้าอนุโลมมาสาย/เดือน (นาที)</label>
                            <input type="number" name="grace_period_minutes" value="{{ $config['grace_period_minutes'] ?? 0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-gray-700 focus:ring-2 focus:ring-gray-400">
                            <p class="text-[10px] text-gray-500 mt-1">หักฟรีจากยอดสายรวมทั้งเดือนครั้งเดียว (ไม่ใช่ต่อครั้ง)</p>
                        </div>
                    </div>
                    <button type="submit" class="w-full bg-gray-100 text-gray-700 py-2 rounded-xl font-bold text-xs hover:bg-gray-200 transition">อัปเดตอัตราหักมาสาย</button>
                </form>
                </section>

                <section class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                    <h4 class="text-sm font-bold text-orange-900 mb-3">4) กฎเบี้ยขยันแบบขั้นบันได</h4>
                    <form action="{{ route('settings.rules.update', 'diligence') }}" method="POST">
                        @csrf @method('PATCH')

                        <div class="space-y-3 mb-6">
                            <template x-for="(tier, index) in diligenceTiers" :key="index">
                                <div class="flex items-end gap-4 p-4 bg-orange-50/50 rounded-xl border border-orange-100 relative group animate-in slide-in-from-top duration-200">
                                    <div class="w-12 text-[10px] font-black text-orange-400 uppercase">ขั้นที่ <span x-text="index + 1"></span></div>
                                    <div class="flex-grow grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">สายไม่เกิน (ครั้ง)</label>
                                            <input type="number" :name="'tiers['+index+'][late_count_max]'" x-model="tier.late_count_max" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold focus:ring-1 focus:ring-orange-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">ลา/ขาดไม่เกิน (วัน)</label>
                                            <input type="number" :name="'tiers['+index+'][lwop_days_max]'" x-model="tier.lwop_days_max" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold focus:ring-1 focus:ring-orange-500">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">จำนวนเงิน</label>
                                            <input type="number" :name="'tiers['+index+'][amount]'" x-model="tier.amount" class="w-full px-3 py-1.5 border rounded-lg text-sm font-bold text-orange-600 focus:ring-1 focus:ring-orange-500">
                                        </div>
                                    </div>
                                    <button type="button" @click="removeTier(index)" class="p-2 text-gray-300 hover:text-red-500 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </template>

                            <button type="button" @click="addTier()" class="w-full py-3 border-2 border-dashed border-orange-100 rounded-xl text-orange-400 font-bold text-sm hover:bg-orange-50 hover:border-orange-200 transition-all">
                                + เพิ่มขั้นบันไดเบี้ยขยัน
                            </button>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-dashed border-gray-200 mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center text-orange-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold text-orange-800 uppercase leading-none mb-1">โหมดการจ่าย</p>
                                    <p class="text-xs text-gray-500">ระบบจะคำนวณเบี้ยขยันให้อัตโนมัติในหน้า Workspace แต่คุณสามารถ<b>คลิกที่ยอดเงินเพื่อแก้เอง (Manual)</b> ได้ทุกเมื่อ</p>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-orange-600 text-white py-3 rounded-xl font-bold text-sm hover:bg-orange-700 transition">บันทึกกฎเบี้ยขยันทั้งหมด</button>
                    </form>
                </section>

                @php $sso = $rules['social_security_config']; @endphp
                <section class="rounded-xl border border-blue-100 bg-blue-50/40 p-4">
                    <h4 class="text-sm font-bold text-blue-900 mb-3">5) ประกันสังคม</h4>
                    <form action="{{ route('settings.rules.update', 'social_security') }}" method="POST" class="space-y-4">
                        @csrf @method('PATCH')
                        <div>
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1 flex items-center gap-1">
                                เพดานเงินเดือน (Salary Ceiling)
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="ยอดเงินเดือนสูงสุดที่ใช้คิดประกันสังคม (เช่น 15,000 หรือ 17,500)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                            </label>
                            <div class="relative">
                                <input type="number" name="salary_ceiling" value="{{ (int)($sso->salary_ceiling ?? 15000) }}" class="w-full pl-3 pr-10 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-2 text-xs text-gray-400">บาท</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">อัตราหักพนักงาน (%)</label>
                                <input type="number" step="0.1" name="employee_contribution_rate" value="{{ (float)($sso->employee_rate ?? 5) }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">อัตราสมทบนายจ้าง (%)</label>
                                <input type="number" step="0.1" name="employer_contribution_rate" value="{{ (float)($sso->employer_rate ?? 5) }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700 transition">บันทึกตั้งค่าประกันสังคม</button>
                    </form>
                </section>
            </div>
        </div>

        <!-- 5. Holiday Management -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col md:col-span-2">
            <div class="px-6 py-4 bg-purple-50 border-b border-purple-100 flex items-center justify-between">
                <h3 class="font-bold text-purple-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-purple-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" /></svg>
                    จัดการวันหยุดบริษัท (Company Holidays)
                </h3>
                <form action="{{ route('settings.holidays.load-legal') }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <input type="number" name="year" value="{{ now()->year }}" class="w-20 px-2 py-1 border rounded text-xs font-bold focus:ring-1 focus:ring-purple-500">
                    <button type="submit" class="px-3 py-1 bg-purple-600 text-white rounded-lg text-[10px] font-bold hover:bg-purple-700 transition flex items-center gap-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        ดึงข้อมูลวันหยุดราชการ
                    </button>
                </form>
            </div>
            <div class="p-6">
                <!-- Add Holiday Form -->
                <form action="{{ route('settings.holidays.add') }}" method="POST" class="mb-8 p-4 bg-gray-50 rounded-2xl border border-gray-100 flex flex-col md:flex-row gap-4 items-end">
                    @csrf
                    <div class="flex-grow w-full">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">ชื่อวันหยุด</label>
                        <input type="text" name="name" required placeholder="ระบุชื่อวันหยุด..." class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                    <div class="w-full md:w-48">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-2">วันที่หยุด</label>
                        <input type="date" name="holiday_date" required class="w-full px-4 py-2 border rounded-xl focus:ring-2 focus:ring-purple-500">
                    </div>
                    <button type="submit" class="w-full md:w-auto bg-gray-800 text-white px-8 py-2 rounded-xl font-bold hover:bg-gray-900 transition shadow-lg shadow-gray-100">เพิ่มรายการ</button>
                </form>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    @forelse($holidays as $holiday)
                    <div class="p-4 bg-white border border-gray-100 rounded-xl shadow-sm flex items-center justify-between group hover:border-purple-200 transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex flex-col items-center justify-center text-purple-600">
                                <span class="text-[8px] font-bold leading-none">{{ $holiday->holiday_date->format('M') }}</span>
                                <span class="text-sm font-black">{{ $holiday->holiday_date->format('d') }}</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-gray-800">{{ $holiday->name }}</p>
                                <p class="text-[10px] text-gray-400">{{ $holiday->holiday_date->format('Y') }}</p>
                            </div>
                        </div>
                        <form action="{{ route('settings.holidays.delete', $holiday->id) }}" method="POST" onsubmit="return confirm('ลบวันหยุดนี้?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="p-2 text-gray-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </form>
                    </div>
                    @empty
                    <div class="col-span-full py-8 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                        <p class="text-sm text-gray-400 italic">ไม่มีข้อมูลวันหยุดส่วนตัว ลองกด "ดึงข้อมูลวันหยุดราชการ" ด้านบนครับ</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
