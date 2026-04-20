@extends('layouts.app')

@section('title', 'Bonus Manager')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Bonus Manager</h1>
            <p class="text-sm text-gray-500">จัดการรอบโบนัส, เลือกเดือนคำนวณ, คำนวณรายคน/รายกลุ่ม และอนุมัติการจ่าย</p>
        </div>
        <a href="{{ route('settings.rules') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">กลับหน้า Rules</a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl border shadow-sm p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4">สร้างรอบโบนัสใหม่</h3>
            <form action="{{ route('settings.bonus.cycles.store') }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Cycle Code</label>
                    <input type="text" name="cycle_code" required placeholder="เช่น 2026-JUN" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Year</label>
                        <input type="number" name="cycle_year" required value="{{ now()->year }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Period</label>
                        <select name="cycle_period" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            <option value="june">June</option>
                            <option value="december">December</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Payment Date</label>
                    <input type="date" name="payment_date" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Max Allocation</label>
                    <input type="number" step="0.01" min="0" max="1" name="max_allocation" value="0.40" required class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <button type="submit" class="w-full bg-indigo-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-indigo-700">สร้างรอบโบนัส</button>
            </form>
        </div>

        <div class="lg:col-span-2 bg-white rounded-2xl border shadow-sm p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-bold text-gray-700">เลือกรอบโบนัส</h3>
                <form method="GET" action="{{ route('settings.bonus.index') }}" class="flex items-center gap-2">
                    <select name="cycle_id" onchange="this.form.submit()" class="px-3 py-2 border rounded-lg text-sm min-w-[220px]">
                        @forelse($cycles as $cycle)
                            <option value="{{ $cycle->id }}" {{ ($selectedCycle?->id === $cycle->id) ? 'selected' : '' }}>
                                {{ $cycle->cycle_code }} ({{ $cycle->status }})
                            </option>
                        @empty
                            <option value="">ยังไม่มีรอบโบนัส</option>
                        @endforelse
                    </select>
                </form>
            </div>

            @if($selectedCycle)
            <form action="{{ route('settings.bonus.cycles.update', $selectedCycle) }}" method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Payment Date</label>
                    <input type="date" name="payment_date" value="{{ optional($selectedCycle->payment_date)->toDateString() }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Max Allocation</label>
                    <input type="number" step="0.01" min="0" max="1" name="max_allocation" value="{{ $selectedCycle->max_allocation }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 border rounded-lg text-sm">
                        @foreach(['draft','calculating','calculated','reviewed','approved','paid','closed','rejected'] as $status)
                            <option value="{{ $status }}" {{ $selectedCycle->status === $status ? 'selected' : '' }}>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">June Max Ratio</label>
                    <input type="number" step="0.001" min="0" max="1" name="june_max_ratio" value="{{ $selectedCycle->june_max_ratio }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">June Scale Months</label>
                    <input type="number" min="1" max="24" name="june_scale_months" value="{{ $selectedCycle->june_scale_months }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Full Scale Months</label>
                    <input type="number" min="1" max="36" name="full_scale_months" value="{{ $selectedCycle->full_scale_months }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Absent Penalty/Day</label>
                    <input type="number" step="0.0001" min="-1" max="0" name="absent_penalty_per_day" value="{{ $selectedCycle->absent_penalty_per_day }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Late Penalty/Occ.</label>
                    <input type="number" step="0.0001" min="-1" max="0" name="late_penalty_per_occurrence" value="{{ $selectedCycle->late_penalty_per_occurrence }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Leave Free Days</label>
                    <input type="number" min="0" max="30" name="leave_free_days" value="{{ $selectedCycle->leave_free_days }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Leave Penalty Rate</label>
                    <input type="number" step="0.0001" min="0" max="1" name="leave_penalty_rate" value="{{ $selectedCycle->leave_penalty_rate }}" class="w-full px-3 py-2 border rounded-lg text-sm">
                </div>
                <div class="md:col-span-2 flex items-end">
                    <button type="submit" class="w-full bg-emerald-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-emerald-700">บันทึกเงื่อนไขรอบโบนัส</button>
                </div>
            </form>
            @endif
        </div>
    </div>

    @if($selectedCycle)
    <div class="bg-white rounded-2xl border shadow-sm p-5">
        <h3 class="text-sm font-bold text-gray-700 mb-3">เดือนที่ใช้คำนวณรอบ {{ $selectedCycle->cycle_code }}</h3>
        <form action="{{ route('settings.bonus.cycles.months.update', $selectedCycle) }}" method="POST" class="space-y-3">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 md:grid-cols-6 gap-2">
                @foreach(($selectedMonths['candidate_months'] ?? []) as $candidate)
                <label class="inline-flex items-center gap-2 px-2 py-1.5 border rounded-lg text-xs">
                    <input type="checkbox" name="months[]" value="{{ $candidate['month_key'] }}" {{ $candidate['already_selected'] ? 'checked' : '' }}>
                    {{ $candidate['month_key'] }}
                </label>
                @endforeach
            </div>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">บันทึกเดือนที่เลือก</button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl border shadow-sm p-5">
            <h3 class="text-sm font-bold text-gray-700 mb-4">คำนวณโบนัสรายบุคคล</h3>
            <form action="{{ route('settings.bonus.calculate') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Employee</label>
                    <select name="employee_id" required class="w-full px-3 py-2 border rounded-lg text-sm">
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Base Reference</label>
                        <input type="number" step="0.01" min="0" name="base_reference" value="0" required class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Tier</label>
                        <select name="tier_code" required class="w-full px-3 py-2 border rounded-lg text-sm">
                            @foreach($tiers as $tier)
                                <option value="{{ $tier->tier_code }}">{{ $tier->tier_code }} ({{ $tier->multiplier }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Absent Days</label>
                        <input type="number" min="0" name="absent_days" value="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Late Count</label>
                        <input type="number" min="0" name="late_count" value="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Leave Days</label>
                        <input type="number" min="0" name="leave_days" value="0" class="w-full px-3 py-2 border rounded-lg text-sm">
                    </div>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-blue-700">คำนวณและบันทึก</button>
            </form>
        </div>

        <div class="bg-white rounded-2xl border shadow-sm p-5 space-y-4">
            <h3 class="text-sm font-bold text-gray-700">คำนวณโบนัสรายกลุ่ม</h3>
            <form action="{{ route('settings.bonus.batch-calculate') }}" method="POST" class="space-y-3">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Tier สำหรับ batch</label>
                    <select name="tier_code" class="w-full px-3 py-2 border rounded-lg text-sm">
                        @foreach($tiers as $tier)
                            <option value="{{ $tier->tier_code }}">{{ $tier->tier_code }} ({{ $tier->multiplier }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="max-h-44 overflow-y-auto border rounded-lg p-3 space-y-1">
                    @foreach($employees as $emp)
                    <label class="flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" name="employee_ids[]" value="{{ $emp->id }}">
                        <span>{{ $emp->full_name }} · ฐาน {{ number_format((float) ($emp->salaryProfile?->base_salary ?? 0), 2) }}</span>
                    </label>
                    @endforeach
                </div>
                <button type="submit" class="w-full bg-violet-600 text-white py-2.5 rounded-xl font-bold text-sm hover:bg-violet-700">คำนวณแบบกลุ่ม</button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-2xl border shadow-sm p-5">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-700">รายการคำนวณโบนัส</h3>
            <div class="text-xs text-gray-500">
                พนักงาน: {{ $cycleSummary['total_employees'] }} คน · อนุมัติแล้ว: {{ $cycleSummary['approved_count'] }} คน · รวมจ่าย: {{ number_format((float) $cycleSummary['total_payment'], 2) }}
            </div>
        </div>

        <form action="{{ route('settings.bonus.approve') }}" method="POST">
            @csrf
            <input type="hidden" name="cycle_id" value="{{ $selectedCycle->id }}">
            <div class="overflow-x-auto border rounded-xl">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-gray-500">
                            <th class="text-left px-3 py-2">เลือก</th>
                            <th class="text-left px-3 py-2">พนักงาน</th>
                            <th class="text-left px-3 py-2">Tier</th>
                            <th class="text-left px-3 py-2">Base</th>
                            <th class="text-left px-3 py-2">Net</th>
                            <th class="text-left px-3 py-2">Unlock %</th>
                            <th class="text-left px-3 py-2">Actual Payment</th>
                            <th class="text-left px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($calculations as $calc)
                        <tr>
                            <td class="px-3 py-2"><input type="checkbox" name="calculation_ids[]" value="{{ $calc->id }}"></td>
                            <td class="px-3 py-2 font-medium">{{ $calc->employee?->full_name ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $calc->tier?->tier_code ?? '-' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $calc->base_reference, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $calc->final_bonus_net, 2) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) $calc->unlock_percentage * 100, 2) }}%</td>
                            <td class="px-3 py-2 font-semibold text-indigo-700">{{ number_format((float) $calc->actual_payment, 2) }}</td>
                            <td class="px-3 py-2">{{ $calc->status }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-3 py-8 text-center text-gray-400 italic">ยังไม่มีข้อมูลคำนวณโบนัสในรอบนี้</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($calculations->count() > 0)
            <div class="mt-3 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-semibold hover:bg-emerald-700">อนุมัติรายการที่เลือก</button>
            </div>
            @endif
        </form>
    </div>
    @endif
</div>
@endsection
