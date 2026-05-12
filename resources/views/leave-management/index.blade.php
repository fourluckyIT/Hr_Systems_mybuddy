@extends('layouts.app')
@section('title', 'จัดการวันลา')

@section('content')
@php
    $leaveTypes = \App\Models\Employee::LEAVE_TYPES_TRACKED;
    $typeMeta = [
        'vacation_leave' => ['icon' => '🏖️', 'color' => 'teal', 'label' => 'พักร้อน'],
        'sick_leave'     => ['icon' => '🤒', 'color' => 'blue', 'label' => 'ป่วย'],
        'personal_leave' => ['icon' => '👤', 'color' => 'amber', 'label' => 'กิจ'],
    ];
@endphp

<div class="max-w-7xl mx-auto p-4"
     x-data="leaveMgmt({
        rows: {{ Js::from($rowsArr) }},
        departments: {{ Js::from($departments->map(fn($d) => ['id'=>$d->id,'name'=>$d->name])->values()) }},
        policies: {{ Js::from($policies->map(fn($p) => ['id'=>$p->id,'name'=>$p->name])->values()) }},
        year: {{ $year }}
     })">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">🏖️ จัดการวันลาทั้งบริษัท</h1>
            <p class="text-sm text-gray-500 mt-0.5">ภาพรวมสิทธิวันลาของพนักงานทุกคน • ปี {{ $year + 543 }}</p>
        </div>
        <div class="flex items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <label class="text-xs text-gray-500">ปี:</label>
                <select name="year" onchange="this.form.submit()" class="px-3 py-1.5 border rounded-lg text-sm">
                    @for($y = now()->year - 2; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" @selected($y === $year)>{{ $y + 543 }}</option>
                    @endfor
                </select>
            </form>
            <a href="{{ route('leave-management.export', ['year' => $year]) }}" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700">⬇️ Export CSV</a>
            <a href="{{ route('settings.master-data') }}?tab=leave_holidays" class="px-3 py-1.5 bg-white border border-gray-200 text-gray-700 rounded-lg text-xs font-bold hover:bg-gray-50">⚙️ จัดการนโยบาย</a>
        </div>
    </div>

    @if(session('success'))
    <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-xl text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
    @endif

    {{-- Year-end alert banner --}}
    @if($isYearEndPeriod && $unusedVacationCount > 0)
    <div class="mb-4 p-4 bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200 rounded-xl flex items-start gap-3">
        <div class="text-2xl">📅</div>
        <div class="flex-grow">
            <div class="text-sm font-bold text-amber-900">ใกล้สิ้นปี: ยังมี {{ $unusedVacationCount }} คน ที่มีพักร้อนเหลือและยกยอดได้</div>
            <div class="text-xs text-amber-700 mt-0.5">กรองด้วย "พักร้อนเหลือ > 0" แล้วใช้ Batch carryover เพื่อย้ายยอดไปปี {{ $year + 1 }}</div>
        </div>
        <button @click="quickFilter = 'has_unused_vacation'" class="px-3 py-1.5 bg-amber-600 text-white rounded-lg text-xs font-bold hover:bg-amber-700">กรองเลย</button>
    </div>
    @endif

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">พนักงาน</div>
            <div class="text-2xl font-extrabold text-gray-800 mt-1">{{ $stats['total_employees'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">นโยบายที่ใช้</div>
            <div class="text-2xl font-extrabold text-indigo-700 mt-1">{{ $stats['total_policies'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">ยอดยกข้ามปี</div>
            <div class="text-2xl font-extrabold text-emerald-700 mt-1">{{ rtrim(rtrim(number_format($stats['total_carryover_days'], 1), '0'), '.') }}</div>
            <div class="text-[10px] text-gray-400">วัน</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">แลกเป็นเงิน</div>
            <div class="text-2xl font-extrabold text-rose-700 mt-1">{{ rtrim(rtrim(number_format($stats['total_encash_days'], 1), '0'), '.') }}</div>
            <div class="text-[10px] text-gray-400">วัน</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="text-[10px] font-bold text-gray-500 uppercase">มูลค่าแลก</div>
            <div class="text-xl font-extrabold text-amber-600 mt-1">฿{{ number_format($stats['total_encash_amount'], 0) }}</div>
        </div>
    </div>

    {{-- Filter bar --}}
    <div class="bg-white rounded-xl border border-gray-200 p-4 mb-3 space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-grow min-w-[200px]">
                <input type="text" x-model="search" placeholder="🔍 ค้นหาชื่อ/รหัส..." class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
            </div>
            <select x-model="filterDept" class="px-3 py-2 border rounded-lg text-sm">
                <option value="">ทุกแผนก</option>
                @foreach($departments as $d)
                <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
            <select x-model="filterPolicy" class="px-3 py-2 border rounded-lg text-sm">
                <option value="">ทุกนโยบาย</option>
                <option value="__none__">— ไม่มีนโยบาย —</option>
                @foreach($policies as $p)
                <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            <button @click="resetFilters()" class="px-3 py-2 text-xs text-gray-600 hover:bg-gray-100 rounded-lg">↺ รีเซ็ต</button>
        </div>

        {{-- Quick filter chips --}}
        <div class="flex flex-wrap gap-2 items-center">
            <span class="text-[10px] font-bold text-gray-400 uppercase">กรองด่วน:</span>
            @php
                $chips = [
                    'all' => ['label' => 'ทั้งหมด', 'color' => 'gray', 'count' => $stats['total_employees']],
                    'low_vacation' => ['label' => '🔴 พักร้อนเหลือน้อย (<5)', 'color' => 'rose', 'count' => $stats['low_vacation_count']],
                    'no_policy' => ['label' => '⚠️ ไม่มีนโยบาย', 'color' => 'amber', 'count' => $stats['no_policy_count']],
                    'probation' => ['label' => '🆕 ทดลองงาน', 'color' => 'sky', 'count' => $stats['probation_count']],
                    'expiring' => ['label' => '⏰ ยกยอดใกล้หมดอายุ', 'color' => 'purple', 'count' => $stats['expiring_count']],
                    'has_unused_vacation' => ['label' => '🏖️ พักร้อนเหลือ + ยกยอดได้', 'color' => 'teal', 'count' => $unusedVacationCount],
                ];
            @endphp
            @foreach($chips as $key => $chip)
            <button @click="quickFilter = '{{ $key }}'"
                    :class="quickFilter === '{{ $key }}'
                        ? 'bg-{{ $chip['color'] }}-600 text-white border-{{ $chip['color'] }}-600'
                        : 'bg-white text-gray-700 border-gray-200 hover:bg-{{ $chip['color'] }}-50 hover:border-{{ $chip['color'] }}-300'"
                    class="px-2.5 py-1 border rounded-full text-[11px] font-semibold transition-colors">
                {{ $chip['label'] }}
                <span class="ml-1 opacity-70">{{ $chip['count'] }}</span>
            </button>
            @endforeach
        </div>
    </div>

    {{-- Batch action bar --}}
    <div class="flex items-center justify-between mb-3 p-3 bg-indigo-50 border border-indigo-100 rounded-xl">
        <div class="text-sm">
            <span class="font-bold text-indigo-700">เลือก: <span x-text="selected.length"></span> คน</span>
            <span class="text-gray-500 ml-2 text-xs">(<span x-text="filteredRows.length"></span> คนหลังกรอง)</span>
        </div>
        <div class="flex gap-2 flex-wrap">
            <button @click="showBatchCarry = true" :disabled="selected.length === 0"
                    :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-indigo-700'"
                    class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold transition-colors">
                📥 ยกยอด
            </button>
            <button @click="showBatchEncash = true" :disabled="selected.length === 0"
                    :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-rose-700'"
                    class="px-3 py-1.5 bg-rose-600 text-white rounded-lg text-xs font-bold transition-colors">
                💵 แลกเงิน
            </button>
            <button @click="showBulkPolicy = true" :disabled="selected.length === 0"
                    :class="selected.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-violet-700'"
                    class="px-3 py-1.5 bg-violet-600 text-white rounded-lg text-xs font-bold transition-colors">
                🎯 เปลี่ยนนโยบาย
            </button>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-3 py-2 text-left w-8">
                            <input type="checkbox" @change="toggleAll($event)" :checked="allSelected" class="rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2 text-left">
                            <button @click="setSort('name')" class="text-[10px] font-bold text-gray-500 uppercase hover:text-indigo-600 flex items-center gap-1">
                                พนักงาน
                                <span x-show="sortBy === 'name'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        <th class="px-3 py-2 text-left text-[10px] font-bold text-gray-500 uppercase">นโยบาย</th>
                        @foreach(['vacation_leave','sick_leave','personal_leave'] as $type)
                        @php $m = $typeMeta[$type]; @endphp
                        <th class="px-3 py-2 text-center min-w-[110px]">
                            <button @click="setSort('{{ $type }}')" class="text-[10px] font-bold text-{{ $m['color'] }}-600 uppercase hover:text-{{ $m['color'] }}-700 flex items-center gap-1 mx-auto">
                                {{ $m['icon'] }} {{ $m['label'] }}
                                <span x-show="sortBy === '{{ $type }}'" x-text="sortDir === 'asc' ? '↑' : '↓'"></span>
                            </button>
                        </th>
                        @endforeach
                        <th class="px-3 py-2 text-right text-[10px] font-bold text-gray-500 uppercase">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <template x-for="row in filteredRows" :key="row.id">
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <input type="checkbox" :value="row.id" x-model="selected" class="rounded border-gray-300">
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-center gap-1 flex-wrap">
                                    <span class="font-bold text-gray-800" x-text="row.name"></span>
                                    {{-- Warning badges --}}
                                    <template x-if="row.has_no_policy">
                                        <span class="px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-[9px] font-bold" title="ไม่มีนโยบาย">⚠️ ไม่มีนโยบาย</span>
                                    </template>
                                    <template x-if="row.is_probation">
                                        <span class="px-1.5 py-0.5 bg-sky-100 text-sky-700 rounded text-[9px] font-bold" title="ยังทดลองงาน">🆕 ทดลอง</span>
                                    </template>
                                    <template x-if="row.expiring_soon">
                                        <span class="px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded text-[9px] font-bold" :title="'ยกยอดหมดอายุ ' + row.expires_at">⏰ ใกล้หมดอายุ</span>
                                    </template>
                                </div>
                                <div class="text-[10px] text-gray-400">
                                    <span x-text="row.code"></span> · <span x-text="row.dept_name"></span>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <template x-if="row.policy_name">
                                    <span class="text-xs">
                                        <span x-show="row.policy_is_default" class="text-amber-500">⭐</span>
                                        <span x-text="row.policy_name"></span>
                                    </span>
                                </template>
                                <template x-if="!row.policy_name">
                                    <span class="text-xs text-rose-500">— ไม่มี —</span>
                                </template>
                            </td>
                            @foreach(['vacation_leave','sick_leave','personal_leave'] as $type)
                            @php $m = $typeMeta[$type]; @endphp
                            <td class="px-3 py-2 text-center">
                                <div class="text-sm font-bold"
                                     :class="row.balances.{{ $type }}.remaining <= 0 ? 'text-rose-600' : 'text-gray-800'">
                                    <span x-text="formatDays(row.balances.{{ $type }}.remaining)"></span>
                                    <span class="text-[10px] text-gray-400 font-normal">
                                        / <span x-text="formatDays(row.balances.{{ $type }}.total_available)"></span>
                                    </span>
                                </div>
                                {{-- Progress bar (used + encashed + carryover_out vs total_available) --}}
                                <div class="mt-1 h-1 bg-gray-100 rounded-full overflow-hidden mx-auto" style="max-width: 80px">
                                    <div class="h-full bg-{{ $m['color'] }}-400"
                                         :style="'width: ' + progressPct(row.balances.{{ $type }}) + '%'"></div>
                                </div>
                                <div class="text-[9px] mt-0.5 flex justify-center gap-1">
                                    <span x-show="row.balances.{{ $type }}.carryover > 0" class="text-emerald-600 font-bold"
                                          x-text="'+' + formatDays(row.balances.{{ $type }}.carryover) + ' ยก'"></span>
                                    <span x-show="row.balances.{{ $type }}.encashed > 0" class="text-rose-600 font-bold"
                                          x-text="'−' + formatDays(row.balances.{{ $type }}.encashed) + ' แลก'"></span>
                                </div>
                            </td>
                            @endforeach
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <button @click="openHistory(row)" class="px-2 py-1 text-[10px] font-bold text-indigo-600 bg-indigo-50 rounded border border-indigo-200 hover:bg-indigo-100">📜 ประวัติ</button>
                                <button @click="openAdjust(row)" class="px-2 py-1 text-[10px] font-bold text-amber-700 bg-amber-50 rounded border border-amber-200 hover:bg-amber-100">✏️ ปรับสิทธิ</button>
                                <a :href="'{{ url('workspace') }}/' + row.id + '/{{ now()->month }}/{{ now()->year }}'"
                                   class="ml-1 text-[10px] text-gray-500 hover:underline">Workspace ↗</a>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="filteredRows.length === 0">
                        <td colspan="7" class="px-3 py-12 text-center text-gray-400 text-sm">
                            <div class="text-3xl mb-2">🔍</div>
                            ไม่พบพนักงานตามเงื่อนไขที่กรอง
                            <div class="mt-2"><button @click="resetFilters()" class="text-indigo-600 text-xs underline">ล้าง filter ทั้งหมด</button></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Batch Encash Modal --}}
    <div x-show="showBatchEncash" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="showBatchEncash = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md my-6">
            <form method="POST" action="{{ route('leave-management.batch-encash') }}" class="p-6">
                @csrf
                <h3 class="text-lg font-bold mb-1">💵 แลกวันลาเป็นเงิน (Batch)</h3>
                <p class="text-xs text-gray-500 mb-4">สำหรับ <span class="font-bold text-rose-600" x-text="selected.length"></span> คนที่เลือก</p>

                <template x-for="empId in selected" :key="'enc-' + empId">
                    <input type="hidden" name="employee_ids[]" :value="empId">
                </template>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทวันลา</label>
                        <select name="leave_type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            @foreach($leaveTypes as $key => $cfg)
                            <option value="{{ $key }}" {{ $key === 'vacation_leave' ? 'selected' : '' }}>{{ $typeMeta[$key]['icon'] ?? '' }} {{ $cfg['label'] }}</option>
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">นโยบายต้องเปิด allow_encashment</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">โหมด</label>
                        <select name="mode" x-model="encashMode" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="all_remaining">แลกหมดที่เหลือ</option>
                            <option value="fixed">แลกจำนวนคงที่ (วัน)</option>
                        </select>
                    </div>

                    <div x-show="encashMode === 'fixed'">
                        <label class="block text-xs font-medium text-gray-600 mb-1">จำนวนวันที่แลก</label>
                        <input type="number" step="0.5" min="0.5" name="days" placeholder="เช่น 3" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ปีของยอดวันลา</label>
                        <input type="number" name="year" required value="{{ $year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">เดือนจ่าย</label>
                            <input type="number" name="payout_month" required min="1" max="12" value="{{ now()->month }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">ปีจ่าย</label>
                            <input type="number" name="payout_year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 p-2 bg-amber-50 border border-amber-100 rounded-lg">
                        <input type="checkbox" name="cap_to_max" value="1" checked>
                        <span class="text-xs">ตัดให้ไม่เกิน max_encash_days_per_year</span>
                    </label>

                    <p class="text-[11px] text-gray-500">
                        เรท/วันคำนวณจาก encash_rate_formula ของแต่ละ policy • คนที่แลกไม่ได้ (ไม่มีเรท/นโยบายห้าม/เหลือ 0) จะถูกข้าม
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" @click="showBatchEncash = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-rose-600 text-white rounded-lg hover:bg-rose-700 font-bold">แลกเงิน</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Bulk Assign Policy Modal --}}
    <div x-show="showBulkPolicy" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="showBulkPolicy = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md my-6">
            <form method="POST" action="{{ route('leave-management.bulk-assign-policy') }}" class="p-6">
                @csrf
                <h3 class="text-lg font-bold mb-1">🎯 เปลี่ยนนโยบายวันลา (Batch)</h3>
                <p class="text-xs text-gray-500 mb-4">สำหรับ <span class="font-bold text-violet-600" x-text="selected.length"></span> คนที่เลือก</p>

                <template x-for="empId in selected" :key="'pol-' + empId">
                    <input type="hidden" name="employee_ids[]" :value="empId">
                </template>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">นโยบายที่จะใช้</label>
                        <select name="leave_policy_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">— ไม่กำหนด (ใช้ Default) —</option>
                            @foreach($policies as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}@if($p->is_default) ⭐@endif</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-[11px] text-gray-500">
                        สิทธิเดิม (entitlement override) ที่ตั้งไว้รายคน <strong>ไม่ถูกล้าง</strong> — ถ้าต้องการล้าง ให้ใช้ ✏️ ปรับสิทธิ รายคน
                    </p>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" @click="showBulkPolicy = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 font-bold">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    {{-- History Modal --}}
    <div x-show="showHistory" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="showHistory = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl my-6 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold">📜 ประวัติวันลา</h3>
                    <p class="text-xs text-gray-500" x-show="historyData">
                        <span x-text="historyData?.employee?.name"></span> · <span x-text="historyData?.employee?.code"></span> · ปี <span x-text="(historyData?.year || 0) + 543"></span>
                    </p>
                </div>
                <button @click="showHistory = false" class="text-gray-400 hover:text-gray-700 text-xl">×</button>
            </div>
            <div class="p-6 space-y-5">
                <div x-show="historyLoading" class="py-12 text-center text-gray-400 text-sm">กำลังโหลด...</div>
                <template x-if="!historyLoading && historyData">
                    <div class="space-y-5">
                        {{-- Balance summary --}}
                        <div class="grid grid-cols-3 gap-3">
                            <template x-for="(b, key) in historyData.balances" :key="key">
                                <div class="p-3 bg-gray-50 rounded-xl">
                                    <div class="text-[10px] font-bold text-gray-500 uppercase" x-text="b.label"></div>
                                    <div class="text-xl font-extrabold text-gray-800 mt-1">
                                        <span x-text="formatDays(b.remaining)"></span>
                                        <span class="text-[10px] text-gray-400 font-normal">/ <span x-text="formatDays(b.total_available)"></span></span>
                                    </div>
                                    <div class="text-[10px] text-gray-500 mt-1">
                                        ใช้ <span x-text="formatDays(b.used)"></span> ·
                                        แลก <span x-text="formatDays(b.encashed)"></span> ·
                                        ยกเข้า <span x-text="formatDays(b.carryover)"></span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- Carryovers --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-2">📥 ยกยอด (<span x-text="historyData.carryovers.length"></span>)</h4>
                            <template x-if="historyData.carryovers.length === 0">
                                <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-400 text-center">ไม่มีรายการยกยอด</div>
                            </template>
                            <div class="space-y-1.5">
                                <template x-for="c in historyData.carryovers" :key="c.id">
                                    <div class="p-2.5 bg-emerald-50 border border-emerald-100 rounded-lg flex items-start justify-between text-xs">
                                        <div>
                                            <div class="font-bold text-emerald-900">
                                                <span x-text="c.leave_label"></span>:
                                                <span x-text="formatDays(c.days)"></span> วัน
                                                <span class="text-[10px] text-gray-500"
                                                      x-text="(c.source_year || '?') + ' → ' + c.target_year"></span>
                                            </div>
                                            <div class="text-[10px] text-gray-500" x-show="c.note" x-text="c.note"></div>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                              :class="c.status === 'approved' ? 'bg-emerald-200 text-emerald-700' : 'bg-amber-200 text-amber-700'"
                                              x-text="c.status"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Encashments --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-2">💵 แลกเงิน (<span x-text="historyData.encashments.length"></span>)</h4>
                            <template x-if="historyData.encashments.length === 0">
                                <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-400 text-center">ไม่มีรายการแลกเงิน</div>
                            </template>
                            <div class="space-y-1.5">
                                <template x-for="e in historyData.encashments" :key="e.id">
                                    <div class="p-2.5 bg-rose-50 border border-rose-100 rounded-lg flex items-start justify-between text-xs">
                                        <div>
                                            <div class="font-bold text-rose-900">
                                                <span x-text="e.leave_label"></span>:
                                                <span x-text="formatDays(e.days)"></span> วัน
                                                = ฿<span x-text="Number(e.amount).toLocaleString()"></span>
                                            </div>
                                            <div class="text-[10px] text-gray-500">
                                                จ่ายงวด <span x-text="e.payout_month + '/' + e.payout_year"></span>
                                                <span x-show="e.note"> · <span x-text="e.note"></span></span>
                                            </div>
                                        </div>
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                              :class="e.status === 'approved' ? 'bg-rose-200 text-rose-700' : 'bg-amber-200 text-amber-700'"
                                              x-text="e.status"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Used logs --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-2">📋 วันที่ลาในปีนี้ (<span x-text="historyData.used_logs.length"></span>)</h4>
                            <template x-if="historyData.used_logs.length === 0">
                                <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-400 text-center">ไม่มีบันทึกการลาในปีนี้</div>
                            </template>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-1.5">
                                <template x-for="l in historyData.used_logs" :key="l.id">
                                    <div class="px-2 py-1 bg-gray-50 border border-gray-100 rounded text-[10px]">
                                        <span class="font-bold" x-text="l.log_date"></span>
                                        <span class="text-gray-500" x-text="' · ' + l.leave_label"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Audit trail --}}
                        <div>
                            <h4 class="text-sm font-bold text-gray-700 mb-2">🔍 Audit Trail (<span x-text="historyData.audits?.length || 0"></span>)</h4>
                            <template x-if="(historyData.audits?.length || 0) === 0">
                                <div class="p-3 bg-gray-50 rounded-lg text-xs text-gray-400 text-center">ไม่มีประวัติการแก้ไข</div>
                            </template>
                            <div class="space-y-1">
                                <template x-for="a in (historyData.audits || [])" :key="a.id">
                                    <div class="p-2 bg-slate-50 border border-slate-100 rounded text-[11px] flex items-start gap-2">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-slate-200 text-slate-700 whitespace-nowrap" x-text="a.subject"></span>
                                        <div class="flex-grow">
                                            <div>
                                                <span class="font-bold text-gray-700" x-text="a.action"></span>
                                                <span class="text-gray-500" x-show="a.field"> · <span x-text="a.field"></span></span>
                                                <span class="text-gray-400 ml-1" x-show="a.reason" x-text="'— ' + a.reason"></span>
                                            </div>
                                            <div class="text-[9px] text-gray-400">โดย <span x-text="a.user"></span> · <span x-text="new Date(a.at).toLocaleString('th-TH')"></span></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Adjust Entitlement Modal --}}
    <div x-show="showAdjust" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 overflow-y-auto" @click.self="showAdjust = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg my-6" x-show="adjustEmp">
            <form :action="adjustEmp ? '{{ url('leave-management') }}/' + adjustEmp.id + '/adjust' : ''" method="POST" class="p-6">
                @csrf
                @method('PATCH')
                <h3 class="text-lg font-bold mb-1">✏️ ปรับสิทธิวันลา</h3>
                <p class="text-xs text-gray-500 mb-4">
                    <span x-text="adjustEmp?.name"></span> · <span x-text="adjustEmp?.code"></span>
                </p>
                <p class="text-[11px] text-amber-700 mb-4 p-2 bg-amber-50 rounded-lg border border-amber-100">
                    💡 เว้นช่อง = ใช้ค่าจากนโยบาย · ใส่ตัวเลข = override สิทธิเฉพาะคนนี้
                </p>

                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">นโยบายวันลา</label>
                        <select name="leave_policy_id" class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="">— ใช้ default —</option>
                            @foreach($policies as $p)
                            <option value="{{ $p->id }}" x-bind:selected="adjustEmp?.policy_id == {{ $p->id }}">{{ $p->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    @foreach(['vacation','sick_leave','personal_leave'] as $field)
                    @php
                        $fieldName = $field === 'vacation' ? 'vacation_entitlement' : $field . '_entitlement';
                        $labelKey = $field === 'vacation' ? 'vacation_leave' : $field;
                        $meta = $typeMeta[$labelKey];
                    @endphp
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            {{ $meta['icon'] }} {{ $meta['label'] }} (วัน/ปี)
                        </label>
                        <input type="number" name="{{ $fieldName }}" min="0" max="365"
                               :value="adjustEmp?.balances?.{{ $labelKey }}?.is_override ? adjustEmp.balances.{{ $labelKey }}.limit : ''"
                               :placeholder="'default: ' + (adjustEmp?.balances?.{{ $labelKey }}?.limit || 0)"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    @endforeach

                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เหตุผล (audit log)</label>
                        <input type="text" name="note" maxlength="255" placeholder="เช่น โบนัสพิเศษ, แก้ข้อมูลผิด"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-4">
                    <button type="button" @click="showAdjust = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-bold">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Batch Carryover Modal --}}
    <div x-show="showBatchCarry" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showBatchCarry = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold mb-4">📥 ยกยอดวันลาแบบ Batch</h3>
            <form method="POST" action="{{ route('leave-management.batch-carryover') }}" class="space-y-3">
                @csrf
                <template x-for="empId in selected" :key="empId">
                    <input type="hidden" name="employee_ids[]" :value="empId">
                </template>
                <p class="text-sm text-gray-700">จะยกยอดให้ <span class="font-bold text-indigo-700" x-text="selected.length"></span> คน</p>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทวันลา</label>
                    <select name="leave_type" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        @foreach($leaveTypes as $key => $cfg)
                        <option value="{{ $key }}" {{ $key === 'vacation_leave' ? 'selected' : '' }}>{{ $typeMeta[$key]['icon'] ?? '' }} {{ $cfg['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-[10px] text-gray-400 mt-1">หมายเหตุ: ระบบจะข้ามคนที่นโยบายห้ามยก/ประเภทนั้นยกไม่ได้</p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ปีต้นทาง</label>
                        <input type="number" name="source_year" required value="{{ $year - 1 }}"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ปีปลายทาง</label>
                        <input type="number" name="target_year" required value="{{ $year }}"
                               class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
                <label class="flex items-center gap-2 p-2 bg-amber-50 border border-amber-100 rounded-lg">
                    <input type="checkbox" name="cap_to_max" value="1" checked>
                    <span class="text-xs">ตัดให้ไม่เกินเพดาน max_carryover_days ของแต่ละนโยบาย</span>
                </label>
                <p class="text-[11px] text-gray-500">ระบบจะคำนวณยอดที่ยกได้ของแต่ละคนจากยอดคงเหลือปีต้นทาง — คนที่เหลือ 0 จะข้าม</p>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showBatchCarry = false" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">ยกยอด</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function leaveMgmt({ rows, departments, policies, year }) {
    return {
        rows, departments, policies, year,
        search: '',
        filterDept: '',
        filterPolicy: '',
        quickFilter: 'all',
        sortBy: 'name',
        sortDir: 'asc',
        selected: [],
        showBatchCarry: false,
        showBatchEncash: false,
        showBulkPolicy: false,
        encashMode: 'all_remaining',
        showHistory: false,
        historyLoading: false,
        historyData: null,
        showAdjust: false,
        adjustEmp: null,

        get filteredRows() {
            let arr = this.rows;
            const s = this.search.trim().toLowerCase();
            if (s) arr = arr.filter(r =>
                (r.name || '').toLowerCase().includes(s) ||
                (r.code || '').toLowerCase().includes(s)
            );
            if (this.filterDept) arr = arr.filter(r => String(r.dept_id) === String(this.filterDept));
            if (this.filterPolicy === '__none__') arr = arr.filter(r => r.has_no_policy);
            else if (this.filterPolicy) arr = arr.filter(r => String(r.policy_id) === String(this.filterPolicy));

            switch (this.quickFilter) {
                case 'low_vacation':       arr = arr.filter(r => r.low_vacation); break;
                case 'no_policy':          arr = arr.filter(r => r.has_no_policy); break;
                case 'probation':          arr = arr.filter(r => r.is_probation); break;
                case 'expiring':           arr = arr.filter(r => r.expiring_soon); break;
                case 'has_unused_vacation':
                    arr = arr.filter(r =>
                        (r.balances?.vacation_leave?.remaining || 0) > 0 &&
                        (r.balances?.vacation_leave?.allow_carryover || false)
                    );
                    break;
            }

            const dir = this.sortDir === 'asc' ? 1 : -1;
            arr = [...arr].sort((a, b) => {
                let av, bv;
                if (this.sortBy === 'name') { av = a.name || ''; bv = b.name || ''; }
                else {
                    av = a.balances?.[this.sortBy]?.remaining ?? 0;
                    bv = b.balances?.[this.sortBy]?.remaining ?? 0;
                }
                if (av < bv) return -1 * dir;
                if (av > bv) return 1 * dir;
                return 0;
            });
            return arr;
        },
        get allSelected() {
            const ids = this.filteredRows.map(r => r.id);
            return ids.length > 0 && ids.every(id => this.selected.includes(id));
        },

        setSort(col) {
            if (this.sortBy === col) { this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc'; }
            else { this.sortBy = col; this.sortDir = 'asc'; }
        },
        toggleAll(e) {
            const ids = this.filteredRows.map(r => r.id);
            if (e.target.checked) {
                this.selected = [...new Set([...this.selected, ...ids])];
            } else {
                this.selected = this.selected.filter(id => !ids.includes(id));
            }
        },
        resetFilters() {
            this.search = '';
            this.filterDept = '';
            this.filterPolicy = '';
            this.quickFilter = 'all';
        },
        formatDays(n) {
            const v = Number(n) || 0;
            return (Math.round(v * 10) / 10).toString().replace(/\.0$/, '');
        },
        async openHistory(row) {
            this.showHistory = true;
            this.historyData = null;
            this.historyLoading = true;
            try {
                const url = `{{ url('leave-management') }}/${row.id}/history?year=${this.year}`;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) throw new Error('Failed to fetch history');
                this.historyData = await res.json();
            } catch (e) {
                alert('โหลดประวัติไม่สำเร็จ: ' + e.message);
                this.showHistory = false;
            } finally {
                this.historyLoading = false;
            }
        },
        openAdjust(row) {
            this.adjustEmp = row;
            this.showAdjust = true;
        },
        progressPct(b) {
            const total = (Number(b?.total_available) || 0);
            if (total <= 0) return 0;
            const used = (Number(b?.used) || 0) + (Number(b?.encashed) || 0) + (Number(b?.carryover_out) || 0);
            return Math.min(100, Math.max(0, (used / total) * 100));
        }
    }
}
</script>
@endpush
@endsection
