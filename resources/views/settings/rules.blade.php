@extends('layouts.app')

@section('title', 'การตั้งค่ากฎระบบ')

@php
    $whConfig = $rules['working_hours']?->config ?? [];
    $otConfig = $rules['ot_rate']?->config ?? [];
    $lateConfig = $rules['late_deduction']?->config ?? [];
    $moduleDefaults = $rules['module_defaults']?->config ?? [];
    $sso = $rules['social_security_config'];

    $tabs = [
        ['key' => 'time',       'label' => 'เวลาทำงาน',     'icon' => '⏰'],
        ['key' => 'ot',         'label' => 'OT',             'icon' => '💰'],
        ['key' => 'deductions', 'label' => 'หักเงิน',         'icon' => '➖'],
        ['key' => 'diligence',  'label' => 'เบี้ยขยัน',       'icon' => '🎁'],
        ['key' => 'sso',        'label' => 'ประกันสังคม',     'icon' => '🏛️'],
        ['key' => 'advanced',   'label' => 'ขั้นสูง',         'icon' => '⚙️'],
    ];

    $hoursPerDay = round(((int) ($whConfig['target_minutes_per_day'] ?? 540)) / 60, 2);
@endphp

@section('content')
<div x-data="{
    tab: (new URLSearchParams(window.location.search).get('tab')) || 'time',
    diligenceTiers: {{ json_encode($diligenceTiers) }},
    addTier() {
        this.diligenceTiers.push({
            amount: 0,
            check_lwop: true,         lwop_max: 0,
            check_late_count: true,   late_count_max: 0,
            check_late_minutes: false,late_minutes_max: 0,
            check_early_leave: false, early_leave_max: 0,
            check_min_attended: true, min_attended_days: 18,
        });
    },
    removeTier(i) { this.diligenceTiers.splice(i, 1); },
    setTab(t) { this.tab = t; const u = new URL(window.location); u.searchParams.set('tab', t); window.history.replaceState({}, '', u); }
}">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900 tracking-tight">การตั้งค่ากฎระบบ ⚙️</h1>
            <p class="text-gray-500 mt-1 text-sm">กฎคำนวณเงินเดือน, OT, เบี้ยขยัน และวันหยุดบริษัท</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('settings.master-data') }}" class="px-3 py-1.5 text-xs font-semibold bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Master Data</a>
            <a href="{{ route('settings.bonus.index') }}" class="px-3 py-1.5 text-xs font-semibold bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200">Bonus Manager</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm font-semibold">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- TABS --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex flex-wrap border-b border-gray-100 bg-gray-50/50 px-2 pt-2 gap-1">
            @foreach($tabs as $t)
            <button type="button" @click="setTab('{{ $t['key'] }}')"
                :class="tab === '{{ $t['key'] }}' ? 'bg-white border-gray-200 text-indigo-700 shadow-sm' : 'text-gray-500 hover:text-gray-800 border-transparent'"
                class="px-4 py-2.5 text-sm font-bold rounded-t-lg border border-b-0 transition-all flex items-center gap-2">
                <span>{{ $t['icon'] }}</span>{{ $t['label'] }}
            </button>
            @endforeach
        </div>

        <div class="p-6">

        {{-- ============ TAB: TIME ============ --}}
        <div x-show="tab === 'time'" x-cloak
            x-data="{
                checkIn:    '{{ $whConfig['target_check_in']  ?? '09:00' }}',
                checkOut:   '{{ $whConfig['target_check_out'] ?? '18:00' }}',
                lunchBreak: {{ (int)($whConfig['lunch_break_minutes'] ?? 60) }},
                hoursPerDay: {{ $hoursPerDay }},
                recalc() {
                    if (!this.checkIn || !this.checkOut) return;
                    const [inH,  inM]  = this.checkIn.split(':').map(Number);
                    const [outH, outM] = this.checkOut.split(':').map(Number);
                    let totalMinutes = (outH * 60 + outM) - (inH * 60 + inM);
                    if (totalMinutes < 0) totalMinutes += 24 * 60; // overnight
                    const netMinutes = Math.max(0, totalMinutes - Number(this.lunchBreak));
                    this.hoursPerDay = Math.round(netMinutes / 60 * 100) / 100;
                }
            }"
            x-init="recalc()">
            <form action="{{ route('settings.rules.update', 'working_hours') }}" method="POST" class="space-y-6 max-w-3xl">
                @csrf @method('PATCH')
                <div>
                    <h3 class="font-bold text-gray-900 mb-1">เวลาเข้า-ออกงานมาตรฐาน</h3>
                    <p class="text-xs text-gray-500 mb-3">ใช้เป็นเป้าหมายในการคำนวณ "สาย" และ "ออกก่อน"</p>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">เวลาเข้า</span>
                            <input type="time" name="target_check_in"
                                x-model="checkIn"
                                @change="recalc()"
                                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">เวลาออก</span>
                            <input type="time" name="target_check_out"
                                x-model="checkOut"
                                @change="recalc()"
                                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-bold text-indigo-600 focus:ring-2 focus:ring-indigo-500">
                        </label>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">ชั่วโมงทำงาน</h3>
                    <p class="text-xs text-gray-500 mb-3">ใช้เป็นฐานคำนวณ rate ต่อนาที และเป็นเส้นแบ่ง OT</p>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">ชั่วโมงทำงาน/วัน</span>
                            <div class="relative mt-1">
                                {{-- Auto-calculated from check-in/out/lunch; still editable manually --}}
                                <input type="number" step="0.5" min="1" max="24" x-model.number="hoursPerDay"
                                    class="w-full px-3 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-indigo-500 bg-indigo-50/40">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">ชม.</span>
                            </div>
                            <input type="hidden" name="target_minutes_per_day" :value="Math.round(hoursPerDay * 60)">
                            <p class="text-[10px] mt-1 text-indigo-500 font-semibold">
                                = <span x-text="Math.round(hoursPerDay * 60)"></span> นาที
                                <span class="text-gray-400 font-normal">(คำนวณจาก เข้า−ออก−พัก)</span>
                            </p>
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">พักเที่ยงอัตโนมัติ</span>
                            <div class="relative mt-1">
                                <input type="number" name="lunch_break_minutes"
                                    x-model.number="lunchBreak"
                                    @input="recalc()"
                                    class="w-full px-3 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-indigo-500">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">นาที</span>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1">หักจากเวลาเข้า-ออกตอนคำนวณ OT</p>
                        </label>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">วันหยุดประจำสัปดาห์</h3>
                    <p class="text-xs text-gray-500 mb-3">ระบบจะใช้สร้างตารางงานอัตโนมัติ (เช่น เสาร์-อาทิตย์ปิด)</p>
                    <div class="flex flex-wrap gap-3 p-3 bg-gray-50 rounded-xl border border-gray-100">
                        @php
                            $days = [0=>'อา', 1=>'จ', 2=>'อ', 3=>'พ', 4=>'พฤ', 5=>'ศ', 6=>'ส'];
                            $standardHolidays = $whConfig['standard_holidays'] ?? [0, 6];
                        @endphp
                        @foreach($days as $val => $label)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="checkbox" name="standard_holidays[]" value="{{ $val }}" {{ in_array($val, $standardHolidays) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm font-semibold {{ in_array($val, $standardHolidays) ? 'text-indigo-600' : 'text-gray-500' }}">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <input type="hidden" name="working_days_per_month" value="{{ $whConfig['working_days_per_month'] ?? 22 }}">
                <input type="hidden" name="allow_company_holiday_swap" value="{{ ($whConfig['allow_company_holiday_swap'] ?? false) ? 1 : 0 }}">

                <button type="submit" class="bg-indigo-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700 transition shadow-sm shadow-indigo-100">บันทึกเวลาทำงาน</button>
            </form>
        </div>

        {{-- ============ TAB: OT ============ --}}
        @php
            $legacyHolidayOt = $otConfig['rate_multiplier_holiday'] ?? 3.0;
            $legacyHolidayReg = $otConfig['holiday_regular_multiplier_monthly'] ?? 1.0;
            $dayTypes = [
                [
                    'key' => 'workday',
                    'label' => 'วันทำงาน',
                    'desc' => 'จันทร์–ศุกร์ปกติ',
                    'badge' => 'bg-green-100 text-green-700',
                    'ot_key' => 'rate_multiplier_workday',
                    'ot_default' => $otConfig['rate_multiplier_workday'] ?? ($otConfig['rate_multiplier'] ?? 1.5),
                    'ot_legal_min' => 1.5,
                    'ot_legal_ref' => '§61: ≥ 1.5×',
                    'has_regular' => false,
                    'has_allow_toggle' => false,
                    'allow_default' => true,
                ],
                [
                    'key' => 'weekly_off',
                    'label' => 'วันหยุดประจำสัปดาห์',
                    'desc' => 'เสาร์–อาทิตย์ (ตามที่ตั้งในแท็บเวลาทำงาน)',
                    'badge' => 'bg-blue-100 text-blue-700',
                    'ot_key' => 'rate_multiplier_weekly_off',
                    'ot_default' => $otConfig['rate_multiplier_weekly_off'] ?? $legacyHolidayOt,
                    'ot_legal_min' => 3.0,
                    'ot_legal_ref' => '§63: ≥ 3×',
                    'reg_key' => 'regular_multiplier_weekly_off',
                    'reg_default' => $otConfig['regular_multiplier_weekly_off'] ?? $legacyHolidayReg,
                    'reg_legal_ref' => '§62: รายเดือน 1× / รายวัน 2×',
                    'has_regular' => true,
                    'allow_key' => 'allow_ot_weekly_off',
                    'allow_default' => $otConfig['allow_ot_weekly_off'] ?? true,
                    'has_allow_toggle' => true,
                ],
                [
                    'key' => 'company_holiday',
                    'label' => 'วันหยุดบริษัท / ราชการ',
                    'desc' => 'สงกรานต์, ปีใหม่, วันหยุดที่กำหนดเอง',
                    'badge' => 'bg-purple-100 text-purple-700',
                    'ot_key' => 'rate_multiplier_company_holiday',
                    'ot_default' => $otConfig['rate_multiplier_company_holiday'] ?? $legacyHolidayOt,
                    'ot_legal_min' => 3.0,
                    'ot_legal_ref' => '§63: ≥ 3×',
                    'reg_key' => 'regular_multiplier_company_holiday',
                    'reg_default' => $otConfig['regular_multiplier_company_holiday'] ?? $legacyHolidayReg,
                    'reg_legal_ref' => '§62: รายเดือน 1× / รายวัน 2×',
                    'has_regular' => true,
                    'allow_key' => 'allow_ot_company_holiday',
                    'allow_default' => $otConfig['allow_ot_company_holiday'] ?? true,
                    'has_allow_toggle' => true,
                ],
            ];
        @endphp
        <div x-show="tab === 'ot'" x-cloak>
            <form action="{{ route('settings.rules.update', 'ot_rate') }}" method="POST" class="space-y-6">
                @csrf @method('PATCH')

                <label class="flex items-center justify-between p-4 bg-red-50/40 border border-red-100 rounded-xl">
                    <div>
                        <p class="text-sm font-bold text-red-900">เปิดคำนวณ OT</p>
                        <p class="text-xs text-red-600 font-bold">ปิดแล้ว OT ทุกคนจะเป็น 0 (เปิด/ปิดที่แท็บ "ขั้นสูง")</p>
                    </div>
                    <span class="text-xs font-bold {{ ($moduleDefaults['enable_overtime'] ?? true) ? 'text-green-600' : 'text-gray-400' }}">
                        {{ ($moduleDefaults['enable_overtime'] ?? true) ? 'ON' : 'OFF' }}
                    </span>
                </label>

                <details class="bg-blue-50/40 border border-blue-100 rounded-xl">
                    <summary class="cursor-pointer p-3 text-sm font-bold text-blue-900 flex items-center gap-2">
                        📖 อ้างอิงกฎหมายแรงงานไทย (พ.ร.บ.คุ้มครองแรงงาน 2541)
                    </summary>
                    <div class="px-4 pb-4 text-xs text-blue-800 space-y-1.5">
                        <p><strong>§24:</strong> OT ต้องได้รับความยินยอมลูกจ้างก่อน + รวมไม่เกิน <strong>36 ชม./สัปดาห์</strong></p>
                        <p><strong>§61:</strong> OT วันทำงานปกติ ≥ <strong>1.5×</strong> ของอัตราค่าจ้างต่อชั่วโมง</p>
                        <p><strong>§62:</strong> ค่าทำงานวันหยุด (ชั่วโมงปกติ) — พนักงานรายเดือน <strong>1×</strong> / รายวัน <strong>2×</strong></p>
                        <p><strong>§63:</strong> OT วันหยุด ≥ <strong>3×</strong> ของอัตราค่าจ้างต่อชั่วโมง</p>
                        <p><strong>§28:</strong> วันหยุดประจำสัปดาห์ ≥ 1 วัน/สัปดาห์</p>
                        <p><strong>§29:</strong> วันหยุดตามประเพณี ≥ 13 วัน/ปี</p>
                        <p class="text-[11px] text-blue-600 italic mt-2">* ตัวเลขด้านล่างที่ขึ้นเตือนสีเหลือง = ต่ำกว่ามาตรฐานกฎหมาย</p>
                    </div>
                </details>

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">อัตราจ่ายตามประเภทวัน</h3>
                    <p class="text-xs text-gray-500 mb-3">ตั้งตัวคูณแยกได้แต่ละประเภทวัน — กฎหมายไทย: ทำงาน 1.5x, วันหยุด 3x</p>
                    <div class="overflow-x-auto rounded-2xl border border-gray-100">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr class="text-[10px] font-bold text-gray-500 uppercase">
                                    <th class="text-left p-3">ประเภทวัน</th>
                                    <th class="p-3 w-32">ตัวคูณ OT</th>
                                    <th class="p-3 w-32">ตัวคูณ ชม.ปกติ</th>
                                    <th class="p-3 w-28">อนุญาต OT</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($dayTypes as $dt)
                                <tr class="hover:bg-gray-50/50" x-data="{ otVal: {{ $dt['ot_default'] }} }">
                                    <td class="p-3">
                                        <span class="inline-block px-2 py-0.5 text-[10px] font-bold rounded-full {{ $dt['badge'] }}">{{ $dt['label'] }}</span>
                                        <p class="text-[11px] text-gray-500 mt-1">{{ $dt['desc'] }}</p>
                                    </td>
                                    <td class="p-3 align-top">
                                        <div class="relative">
                                            <input type="number" step="0.1" min="0" name="{{ $dt['ot_key'] }}" x-model.number="otVal"
                                                class="w-full pl-3 pr-7 py-1.5 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500"
                                                :class="otVal < {{ $dt['ot_legal_min'] }} ? 'border-amber-400 bg-amber-50' : ''">
                                            <span class="absolute right-2 top-1.5 text-xs text-gray-400">×</span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $dt['ot_legal_ref'] }}</p>
                                        <p class="text-[10px] text-amber-600 font-bold mt-0.5" x-show="otVal < {{ $dt['ot_legal_min'] }}" x-cloak>⚠ ต่ำกว่ากฎหมาย</p>
                                    </td>
                                    <td class="p-3 align-top">
                                        @if($dt['has_regular'])
                                        <div class="relative">
                                            <input type="number" step="0.1" min="0" name="{{ $dt['reg_key'] }}" value="{{ $dt['reg_default'] }}"
                                                class="w-full pl-3 pr-7 py-1.5 border rounded-lg text-sm font-bold text-orange-600 focus:ring-2 focus:ring-orange-500">
                                            <span class="absolute right-2 top-1.5 text-xs text-gray-400">×</span>
                                        </div>
                                        <p class="text-[10px] text-gray-400 mt-1">{{ $dt['reg_legal_ref'] }}</p>
                                        @else
                                        <span class="text-xs text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center align-top">
                                        @if($dt['has_allow_toggle'])
                                        <input type="hidden" name="{{ $dt['allow_key'] }}" value="0">
                                        <input type="checkbox" name="{{ $dt['allow_key'] }}" value="1" {{ $dt['allow_default'] ? 'checked' : '' }}
                                            class="w-5 h-5 rounded border-gray-300 text-red-600">
                                        @else
                                        <span class="text-green-500 font-bold">✓</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-[11px] text-gray-500 mt-2">💡 "ตัวคูณ ชม.ปกติ" คือค่าจ้างของชั่วโมงทำงานเริ่มต้นในวันหยุด (พนักงานรายเดือน=1, พนักงานรายวัน=2 ตามกฎหมาย) — ใช้เมื่อเปิด "แยกตามกฎหมาย" ด้านล่าง</p>
                </div>

                <div x-data="{
                    daily:  {{ $otConfig['daily_ot_limit_hours'] ?? 0 }},
                    weekly: {{ $otConfig['weekly_ot_limit_hours'] ?? 36 }},
                    monthly:{{ $otConfig['max_ot_hours'] ?? 40 }}
                }">
                    <h3 class="font-bold text-gray-900 mb-1">เพดาน OT</h3>
                    <p class="text-xs text-gray-500 mb-3">ระบบจะตัดยอดเกินทิ้งอัตโนมัติ — ใส่ 0 = ไม่จำกัด</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">เพดาน/วัน (ชม.)</span>
                            <input type="number" step="0.5" min="0" name="daily_ot_limit_hours" x-model.number="daily" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                            <p class="text-[10px] text-gray-500 mt-1">0 = ไม่จำกัดต่อวัน</p>
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">เพดาน/สัปดาห์ (ชม.)</span>
                            <input type="number" step="0.5" min="0" name="weekly_ot_limit_hours" x-model.number="weekly"
                                class="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500"
                                :class="weekly > 36 ? 'border-amber-400 bg-amber-50' : ''">
                            <p class="text-[10px] text-gray-500 mt-1">§24: กฎหมายไทย ≤ 36 ชม./สัปดาห์</p>
                            <p class="text-[10px] text-amber-600 font-bold mt-0.5" x-show="weekly > 36" x-cloak>⚠ เกินเพดานกฎหมาย</p>
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">เพดาน/เดือน (ชม.)</span>
                            <input type="number" step="1" min="0" name="max_ot_hours" x-model.number="monthly" class="mt-1 w-full px-3 py-2 border rounded-lg text-sm font-bold text-red-600 focus:ring-2 focus:ring-red-500">
                            <p class="text-[10px] text-gray-500 mt-1">policy บริษัท (ไม่มีในกฎหมาย)</p>
                        </label>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">โหมดการคิดวันหยุด</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center justify-between p-4 bg-red-50/40 border border-red-100 rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-red-900">แยกตามกฎหมาย (§62/§63)</p>
                                <p class="text-[10px] text-red-600 mt-1">ชม.ปกติ × ตัวคูณปกติ + ชม.เกิน × ตัวคูณ OT</p>
                            </div>
                            <input type="hidden" name="enable_holiday_legal_split" value="0">
                            <input type="checkbox" name="enable_holiday_legal_split" value="1" {{ ($otConfig['enable_holiday_legal_split'] ?? true) ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-red-600">
                        </label>
                        <label class="flex items-center justify-between p-4 bg-red-50/40 border border-red-100 rounded-xl">
                            <div>
                                <p class="text-sm font-bold text-red-900">ต้องได้รับความยินยอมลูกจ้าง</p>
                                <p class="text-[10px] text-red-600 mt-1">policy flag — ใช้บันทึกข้อมูล HR</p>
                            </div>
                            <input type="hidden" name="requires_employee_consent" value="0">
                            <input type="checkbox" name="requires_employee_consent" value="1" {{ ($otConfig['requires_employee_consent'] ?? true) ? 'checked' : '' }} class="w-5 h-5 rounded border-gray-300 text-red-600">
                        </label>
                    </div>
                </div>

                <input type="hidden" name="rate_multiplier" value="{{ $otConfig['rate_multiplier'] ?? 1.5 }}">

                <button type="submit" class="bg-red-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-red-700 transition shadow-sm shadow-red-100">บันทึกกฎ OT</button>
            </form>
        </div>

        {{-- ============ TAB: DEDUCTIONS ============ --}}
        <div x-show="tab === 'deductions'" x-cloak>
            <form action="{{ route('settings.rules.update', 'late_deduction') }}" method="POST" class="space-y-6 max-w-3xl">
                @csrf @method('PATCH')

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">การหักมาสาย</h3>
                    <p class="text-xs text-gray-500 mb-3">หักตามสัดส่วนเงินเดือน (เงินเดือน ÷ นาทีในเดือน × นาทีที่สาย)</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="text-[11px] font-bold text-gray-500 uppercase">โหมด</span>
                            <div class="mt-1 px-3 py-2 bg-gray-50 border rounded-lg text-xs font-bold text-gray-700">Proportional (สัดส่วนเงินเดือน)</div>
                        </div>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">โควต้าอนุโลม/เดือน</span>
                            <div class="relative mt-1">
                                <input type="number" name="grace_period_minutes" value="{{ $lateConfig['grace_period_minutes'] ?? 0 }}" class="w-full px-3 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-gray-400">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">นาที</span>
                            </div>
                            <p class="text-[10px] text-gray-500 mt-1">หักฟรีจากยอดสายรวมของเดือน (ไม่ใช่ต่อครั้ง)</p>
                        </label>
                    </div>
                </div>

                <input type="hidden" name="type" value="per_minute">

                <div class="text-[11px] text-gray-500 bg-gray-50 border border-gray-100 rounded-lg p-3">
                    <strong class="text-gray-700">ออกก่อนเวลา / ขาดงาน:</strong> ใช้ rate เดียวกับมาสาย (เงินเดือน ÷ นาที) คำนวณอัตโนมัติจาก attendance — ไม่ต้องตั้งค่าเพิ่ม
                </div>

                <button type="submit" class="bg-gray-800 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-gray-900 transition shadow-sm">บันทึกกฎหักเงิน</button>
            </form>
        </div>

        {{-- ============ TAB: DILIGENCE ============ --}}
        <div x-show="tab === 'diligence'" x-cloak>
            <form action="{{ route('settings.rules.update', 'diligence') }}" method="POST">
                @csrf @method('PATCH')

                <div class="mb-4 flex items-start justify-between p-4 bg-orange-50/50 border border-orange-100 rounded-xl">
                    <div>
                        <p class="text-sm font-bold text-orange-900">เบี้ยขยันแบบขั้นบันได</p>
                        <p class="text-xs text-orange-700 mt-1">แต่ละขั้นเลือกได้ว่าจะใช้เงื่อนไขไหนบ้าง — ระบบจะจ่ายขั้นแรกที่ผ่านครบทุกเงื่อนไขที่ติ๊ก</p>
                    </div>
                    <span class="text-xs font-bold {{ ($moduleDefaults['enable_diligence'] ?? true) ? 'text-green-600' : 'text-gray-400' }}">
                        {{ ($moduleDefaults['enable_diligence'] ?? true) ? 'ON' : 'OFF' }}
                    </span>
                </div>

                <div class="space-y-4 mb-4">
                    <template x-for="(tier, index) in diligenceTiers" :key="index">
                        <div class="p-4 bg-white border-2 border-orange-100 rounded-2xl relative">
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-orange-50">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 bg-orange-100 rounded-full flex items-center justify-center text-xs font-black text-orange-600" x-text="index + 1"></div>
                                    <div>
                                        <p class="text-xs font-bold text-orange-900">ขั้นที่ <span x-text="index + 1"></span></p>
                                        <p class="text-[10px] text-gray-500">จ่ายเมื่อผ่านเงื่อนไขที่ติ๊กไว้</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <input type="number" :name="'tiers['+index+'][amount]'" x-model.number="tier.amount" class="w-32 pl-3 pr-8 py-1.5 border rounded-lg text-sm font-black text-orange-600 focus:ring-2 focus:ring-orange-500">
                                        <span class="absolute right-2 top-2 text-xs text-gray-400">฿</span>
                                    </div>
                                    <button type="button" @click="removeTier(index)" class="p-1.5 text-gray-300 hover:text-red-500 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6"/></svg>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-2">
                                {{-- LWOP --}}
                                <div class="flex items-center gap-3 p-2 rounded-lg" :class="tier.check_lwop ? 'bg-orange-50/60' : 'bg-gray-50/40'">
                                    <input type="hidden" :name="'tiers['+index+'][check_lwop]'" :value="tier.check_lwop ? '1' : '0'">
                                    <input type="checkbox" x-model="tier.check_lwop" class="rounded border-gray-300 text-orange-600">
                                    <span class="text-sm font-semibold flex-grow" :class="tier.check_lwop ? 'text-gray-900' : 'text-gray-400'">ขาดงาน (LWOP) ไม่เกิน</span>
                                    <input type="number" :name="'tiers['+index+'][lwop_max]'" x-model.number="tier.lwop_max" :disabled="!tier.check_lwop" class="w-20 px-2 py-1 border rounded text-sm font-bold text-right disabled:bg-gray-100 disabled:text-gray-300">
                                    <span class="text-xs w-10" :class="tier.check_lwop ? 'text-gray-600' : 'text-gray-300'">วัน</span>
                                </div>

                                {{-- LATE COUNT --}}
                                <div class="flex items-center gap-3 p-2 rounded-lg" :class="tier.check_late_count ? 'bg-orange-50/60' : 'bg-gray-50/40'">
                                    <input type="hidden" :name="'tiers['+index+'][check_late_count]'" :value="tier.check_late_count ? '1' : '0'">
                                    <input type="checkbox" x-model="tier.check_late_count" class="rounded border-gray-300 text-orange-600">
                                    <span class="text-sm font-semibold flex-grow" :class="tier.check_late_count ? 'text-gray-900' : 'text-gray-400'">มาสาย (จำนวนครั้ง) ไม่เกิน</span>
                                    <input type="number" :name="'tiers['+index+'][late_count_max]'" x-model.number="tier.late_count_max" :disabled="!tier.check_late_count" class="w-20 px-2 py-1 border rounded text-sm font-bold text-right disabled:bg-gray-100 disabled:text-gray-300">
                                    <span class="text-xs w-10" :class="tier.check_late_count ? 'text-gray-600' : 'text-gray-300'">ครั้ง</span>
                                </div>

                                {{-- LATE MINUTES --}}
                                <div class="flex items-center gap-3 p-2 rounded-lg" :class="tier.check_late_minutes ? 'bg-orange-50/60' : 'bg-gray-50/40'">
                                    <input type="hidden" :name="'tiers['+index+'][check_late_minutes]'" :value="tier.check_late_minutes ? '1' : '0'">
                                    <input type="checkbox" x-model="tier.check_late_minutes" class="rounded border-gray-300 text-orange-600">
                                    <span class="text-sm font-semibold flex-grow" :class="tier.check_late_minutes ? 'text-gray-900' : 'text-gray-400'">สายรวมทั้งเดือน ไม่เกิน</span>
                                    <input type="number" :name="'tiers['+index+'][late_minutes_max]'" x-model.number="tier.late_minutes_max" :disabled="!tier.check_late_minutes" class="w-20 px-2 py-1 border rounded text-sm font-bold text-right disabled:bg-gray-100 disabled:text-gray-300">
                                    <span class="text-xs w-10" :class="tier.check_late_minutes ? 'text-gray-600' : 'text-gray-300'">นาที</span>
                                </div>

                                {{-- EARLY LEAVE --}}
                                <div class="flex items-center gap-3 p-2 rounded-lg" :class="tier.check_early_leave ? 'bg-orange-50/60' : 'bg-gray-50/40'">
                                    <input type="hidden" :name="'tiers['+index+'][check_early_leave]'" :value="tier.check_early_leave ? '1' : '0'">
                                    <input type="checkbox" x-model="tier.check_early_leave" class="rounded border-gray-300 text-orange-600">
                                    <span class="text-sm font-semibold flex-grow" :class="tier.check_early_leave ? 'text-gray-900' : 'text-gray-400'">ออกก่อนเวลา (จำนวนครั้ง) ไม่เกิน</span>
                                    <input type="number" :name="'tiers['+index+'][early_leave_max]'" x-model.number="tier.early_leave_max" :disabled="!tier.check_early_leave" class="w-20 px-2 py-1 border rounded text-sm font-bold text-right disabled:bg-gray-100 disabled:text-gray-300">
                                    <span class="text-xs w-10" :class="tier.check_early_leave ? 'text-gray-600' : 'text-gray-300'">ครั้ง</span>
                                </div>

                                {{-- MIN ATTENDED --}}
                                <div class="flex items-center gap-3 p-2 rounded-lg border-2 border-dashed" :class="tier.check_min_attended ? 'bg-emerald-50/60 border-emerald-200' : 'bg-gray-50/40 border-gray-100'">
                                    <input type="hidden" :name="'tiers['+index+'][check_min_attended]'" :value="tier.check_min_attended ? '1' : '0'">
                                    <input type="checkbox" x-model="tier.check_min_attended" class="rounded border-gray-300 text-emerald-600">
                                    <span class="text-sm font-semibold flex-grow" :class="tier.check_min_attended ? 'text-emerald-900' : 'text-gray-400'">⭐ มาทำงานขั้นต่ำ (วันที่มี check-in)</span>
                                    <input type="number" :name="'tiers['+index+'][min_attended_days]'" x-model.number="tier.min_attended_days" :disabled="!tier.check_min_attended" class="w-20 px-2 py-1 border rounded text-sm font-bold text-right disabled:bg-gray-100 disabled:text-gray-300">
                                    <span class="text-xs w-10" :class="tier.check_min_attended ? 'text-emerald-700' : 'text-gray-300'">วัน ขึ้นไป</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="addTier()" class="w-full py-3 border-2 border-dashed border-orange-200 rounded-2xl text-orange-500 font-bold text-sm hover:bg-orange-50 transition">
                        + เพิ่มขั้นบันได
                    </button>

                    <div x-show="diligenceTiers.length === 0" class="p-6 text-center bg-gray-50 rounded-2xl border-2 border-dashed border-gray-200">
                        <p class="text-sm text-red-500 font-bold italic">ยังไม่มีขั้นบันได — กดปุ่ม "เพิ่มขั้น" เพื่อเริ่ม (ถ้าไม่มีขั้นเลย เบี้ยขยันจะเป็น 0 ทุกคน)</p>
                    </div>
                </div>

                <div class="p-3 bg-blue-50/50 border border-blue-100 rounded-xl text-[11px] text-blue-700 mb-4">
                    💡 <strong>แนะนำ:</strong> เปิด "มาทำงานขั้นต่ำ" ในทุกขั้นเสมอ เพื่อกันคนที่มีบันทึกแค่ 1-2 วันแล้วได้เบี้ยขยันเต็ม
                </div>

                <button type="submit" class="bg-orange-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-orange-700 transition shadow-sm shadow-orange-100">บันทึกกฎเบี้ยขยัน</button>
            </form>
        </div>

        {{-- ============ TAB: SSO ============ --}}
        <div x-show="tab === 'sso'" x-cloak>
            <form action="{{ route('settings.rules.update', 'social_security') }}" method="POST" class="space-y-6 max-w-2xl">
                @csrf @method('PATCH')

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">เพดานเงินเดือน</h3>
                    <p class="text-xs text-gray-500 mb-3">ยอดเงินเดือนสูงสุดที่ใช้คิดประกันสังคม (มาตรฐานไทย: 15,000 หรือ 17,500)</p>
                    <div class="relative max-w-xs">
                        <input type="number" name="salary_ceiling" value="{{ (int)($sso->salary_ceiling ?? 15000) }}" class="w-full pl-3 pr-12 py-2 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-blue-500">
                        <span class="absolute right-3 top-2.5 text-xs text-gray-400">บาท</span>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">อัตราการสมทบ</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">พนักงาน</span>
                            <div class="relative mt-1">
                                <input type="number" step="0.1" name="employee_contribution_rate" value="{{ (float)($sso->employee_rate ?? 5) }}" class="w-full pl-3 pr-8 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">%</span>
                            </div>
                        </label>
                        <label class="block">
                            <span class="text-[11px] font-bold text-gray-500 uppercase">นายจ้าง</span>
                            <div class="relative mt-1">
                                <input type="number" step="0.1" name="employer_contribution_rate" value="{{ (float)($sso->employer_rate ?? 5) }}" class="w-full pl-3 pr-8 py-2 border rounded-lg text-sm font-bold text-blue-600 focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-2.5 text-xs text-gray-400">%</span>
                            </div>
                        </label>
                    </div>
                </div>

                <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700 transition shadow-sm shadow-blue-100">บันทึกประกันสังคม</button>
            </form>
        </div>


        {{-- ============ TAB: ADVANCED ============ --}}
        <div x-show="tab === 'advanced'" x-cloak>
            <form action="{{ route('settings.rules.update', 'module_defaults') }}" method="POST" class="space-y-4 max-w-3xl">
                @csrf @method('PATCH')

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">เปิด/ปิด โมดูลทั้งระบบ</h3>
                    <p class="text-xs text-red-500 font-bold mb-3 underline">ปิดแล้วจะกระทบทั้งบริษัท</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                            <div>
                                <p class="text-sm font-bold text-gray-800">คำนวณ OT</p>
                                <p class="text-xs text-red-500 font-medium">ปิดแล้ว OT = 0 ทุกคน</p>
                            </div>
                            <input type="hidden" name="enable_overtime" value="0">
                            <input type="checkbox" name="enable_overtime" value="1" {{ ($moduleDefaults['enable_overtime'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-5 h-5">
                        </label>

                        <label class="flex items-center justify-between p-4 border rounded-xl bg-white">
                            <div>
                                <p class="text-sm font-bold text-gray-800">คำนวณเบี้ยขยัน</p>
                                <p class="text-xs text-red-500 font-medium">ปิดแล้วเบี้ยขยัน = 0 ทุกคน</p>
                            </div>
                            <input type="hidden" name="enable_diligence" value="0">
                            <input type="checkbox" name="enable_diligence" value="1" {{ ($moduleDefaults['enable_diligence'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-5 h-5">
                        </label>
                    </div>
                </div>

                <hr class="border-gray-100">

                <div>
                    <h3 class="font-bold text-gray-900 mb-1">ค่าเริ่มต้นพนักงานใหม่</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        ใช้เป็น default ตอนสร้างพนักงานใหม่ — <strong>ไม่กระทบของเดิม</strong> เว้นแต่จะติ๊ก
                        <span class="text-rose-600 font-semibold">"ผลักไปยังพนักงานทั้งหมด"</span>
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="p-4 border rounded-xl bg-white space-y-2">
                            <label class="flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-800">หักประกันสังคม</p>
                                <input type="hidden" name="default_sso_deduction" value="0">
                                <input type="checkbox" name="default_sso_deduction" value="1" {{ ($moduleDefaults['default_sso_deduction'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-5 h-5">
                            </label>
                            <label class="flex items-center gap-2 text-xs text-rose-600 font-medium cursor-pointer">
                                <input type="checkbox" name="apply_to_all[sso_deduction]" value="1" class="rounded border-gray-300 text-rose-600">
                                ผลักไปยังพนักงานทั้งหมด (existing employees)
                            </label>
                        </div>

                        <div class="p-4 border rounded-xl bg-white space-y-2">
                            <label class="flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-800">หักมาสาย</p>
                                <input type="hidden" name="default_deduct_late" value="0">
                                <input type="checkbox" name="default_deduct_late" value="1" {{ ($moduleDefaults['default_deduct_late'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-5 h-5">
                            </label>
                            <label class="flex items-center gap-2 text-xs text-rose-600 font-medium cursor-pointer">
                                <input type="checkbox" name="apply_to_all[deduct_late]" value="1" class="rounded border-gray-300 text-rose-600">
                                ผลักไปยังพนักงานทั้งหมด (existing employees)
                            </label>
                        </div>

                        <div class="p-4 border rounded-xl bg-white space-y-2">
                            <label class="flex items-center justify-between">
                                <p class="text-sm font-bold text-gray-800">หักออกก่อนเวลา</p>
                                <input type="hidden" name="default_deduct_early" value="0">
                                <input type="checkbox" name="default_deduct_early" value="1" {{ ($moduleDefaults['default_deduct_early'] ?? true) ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600 w-5 h-5">
                            </label>
                            <label class="flex items-center gap-2 text-xs text-rose-600 font-medium cursor-pointer">
                                <input type="checkbox" name="apply_to_all[deduct_early]" value="1" class="rounded border-gray-300 text-rose-600">
                                ผลักไปยังพนักงานทั้งหมด (existing employees)
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">
                        💡 ติ๊กข้อ "ผลักไปยังพนักงานทั้งหมด" เฉพาะข้อที่ต้องการเขียนทับการตั้งค่าส่วนตัวของพนักงานทุกคน
                    </p>
                </div>

                <button type="submit"
                        onclick="return !this.form.querySelector('input[name^=\'apply_to_all\']:checked') || confirm('แน่ใจ? การติ๊ก \'ผลักไปยังพนักงานทั้งหมด\' จะเขียนทับการตั้งค่าเฉพาะตัวของพนักงานทั้งบริษัท');"
                        class="bg-slate-700 text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-slate-800 transition shadow-sm">
                    บันทึกการตั้งค่าขั้นสูง
                </button>
            </form>
        </div>

        </div>
    </div>
</div>

<style>
    [x-cloak] { display: none !important; }
</style>
@endsection
