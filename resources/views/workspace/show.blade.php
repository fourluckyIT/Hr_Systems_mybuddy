@extends('layouts.app')
@section('title', $employee->display_name . ' - Workspace')

@section('content')
@php
    $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $summary = $result['summary'] ?? [];
    $canManageWorkspace = auth()->user()?->hasRole('admin') ?? false;
    $formatHoursAsClock = function ($hours) {
        $totalMinutes = (int) round(((float) $hours) * 60);
        $h = intdiv($totalMinutes, 60);
        $m = $totalMinutes % 60;
        return sprintf('%d:%02d', $h, $m);
    };
    $prevMonth = $month == 1 ? 12 : $month - 1;
    $prevYear = $month == 1 ? $year - 1 : $year;
    $nextMonth = $month == 12 ? 1 : $month + 1;
    $nextYear = $month == 12 ? $year + 1 : $year;

    // Calendar navigation logic
    $startDate   = $employee->start_date;
    $startMonth  = $startDate ? (int) $startDate->format('n') : 1;
    $startYear   = $startDate ? (int) $startDate->format('Y') : 2000;

    // Is the previous month before start_date?
    $prevIsBeforeStart = ($prevYear < $startYear) || ($prevYear === $startYear && $prevMonth < $startMonth);

    // Is the next month in the future?
    $currentDate = now();
    $currentMonthReal = (int) $currentDate->format('n');
    $currentYearReal = (int) $currentDate->format('Y');

    $nextIsFuture = ($nextYear > $currentYearReal) || ($nextYear === $currentYearReal && $nextMonth > $currentMonthReal);
@endphp

<div x-data="{
    open: false,
    pickerYear: {{ $year }},
    currentMonth: {{ $month }},
    currentYear: {{ $year }},
    startMonth: {{ $startMonth }},
    startYear: {{ $startYear }},
    currentMonthReal: {{ $currentMonthReal }},
    currentYearReal: {{ $currentYearReal }},
    vacationBalance: {
        limit: {{ $vacationBalance['limit'] ?? 6 }},
        used: {{ $vacationBalance['used'] ?? 0 }},
        remaining: {{ $vacationBalance['remaining'] ?? 6 }}
    },
    thaiMonths: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
    
    // Unfinalize Confirm Modal
    unfinalizeConfirmOpen: false,

    // Swap Modal State
    modalSwapOpen: false,
    isSubmitting: false,
    swapError: '',
    swapData: {
        employee_id: {{ $employee->id }},
        work_date: '',
        off_date: '',
        reason: ''
    },

    isBeforeStart(m, y) {
        return (y < this.startYear) || (y === this.startYear && m < this.startMonth);
    },
    isFuture(m, y) {
        return (y > this.currentYearReal) || (y === this.currentYearReal && m > this.currentMonthReal);
    },
    goTo(m, y) {
        if (this.isBeforeStart(m, y) || this.isFuture(m, y)) return;
        window.location.href = '{{ route('workspace.show', ['employee' => $employee->id, 'month' => '__M__', 'year' => '__Y__']) }}'.replace('__M__', m).replace('__Y__', y);
    },

    async submitSwap() {
        this.isSubmitting = true;
        this.swapError = '';
        
        try {
            const response = await fetch('{{ route('leave.swap.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(this.swapData)
            });

            const result = await response.json();

            if (response.ok) {
                // Seamlessly refresh UI
                await this.refreshWorkspaceUI();
                this.modalSwapOpen = false;
                // Reset swap data
                this.swapData.work_date = '';
                this.swapData.off_date = '';
                this.swapData.reason = '';
            } else {
                this.swapError = result.message || 'เกิดข้อผิดพลาดในการบันทึกข้อมูล';
                if (result.errors) {
                    this.swapError = Object.values(result.errors).flat().join(' ');
                }
            }
        } catch (e) {
            this.swapError = 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้';
        } finally {
            this.isSubmitting = false;
        }
    },

    async refreshWorkspaceUI() {
        try {
            const response = await fetch('{{ route('workspace.grid.refresh', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}');
            if (response.ok) {
                const json = await response.json();
                
                // 1. Update Grid HTML
                const container = document.getElementById('attendance-grid-container');
                if (container && json.html) {
                    container.outerHTML = json.html;
                }

                // 2. Update Summary Cards & Sidebar
                if (json.summary && typeof updateSummary === 'function') {
                    updateSummary(json.summary, json.items);
                }

                // 3. Update Vacation Balance
                if (json.vacationBalance) {
                    this.vacationBalance = json.vacationBalance;
                }
            }
        } catch (e) {
            console.error('Refresh UI failed', e);
        }
    }
}">

@if(!($workspaceEditEnabled ?? true))
<div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-semibold">
    Workspace นี้ถูกปิดสิทธิ์การแก้ไขจาก Master Data (โหมดดูข้อมูลอย่างเดียว)
</div>
@endif

    {{-- Integrated Swap Modal --}}
    <div x-show="modalSwapOpen" 
         x-cloak 
         class="fixed inset-0 z-[100] flex items-center justify-center overflow-hidden"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        
        {{-- Backdrop with blur --}}
        <div class="absolute inset-0 bg-white/60 backdrop-blur-sm" @click="modalSwapOpen = false"></div>

        {{-- Modal Dialog --}}
        <div class="relative bg-white w-full max-w-lg mx-4 rounded-3xl shadow-[0_20px_50px_rgba(0,0,0,0.1)] border border-gray-100 overflow-hidden"
             x-show="modalSwapOpen"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
            
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800">Quick Swap</h2>
                        <p class="text-xs text-gray-500 mt-1">สลับวันหยุดรายสัปดาห์</p>
                    </div>
                    <button @click="modalSwapOpen = false" class="p-2 hover:bg-gray-100 rounded-full transition-colors">
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <form @submit.prevent="submitSwap" class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">มาทำงานวันที่ (วันหยุด)</label>
                            <input type="date" x-model="swapData.work_date" required
                                   class="w-full bg-gray-50 border-0 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500/20 transition-all">
                        </div>
                        <div class="space-y-1.5">
                            <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">หยุดแทนวันที่ (วันทำงาน)</label>
                            <input type="date" x-model="swapData.off_date" required
                                   class="w-full bg-gray-50 border-0 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500/20 transition-all">
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-gray-400 uppercase tracking-wider">เหตุผล / บันทึกเพิ่มเติม</label>
                        <textarea x-model="swapData.reason" rows="3" placeholder="ระบุเหตุผลในการขอสลับวัน (ถ้ามี)"
                                  class="w-full bg-gray-50 border-0 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-amber-500/20 transition-all resize-none"></textarea>
                    </div>

                    <div x-show="swapError" x-text="swapError" x-cloak
                         class="p-4 bg-rose-50 text-rose-600 rounded-2xl text-xs font-medium border border-rose-100 transition-all">
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="button" @click="modalSwapOpen = false"
                                class="flex-1 py-3 text-sm font-bold text-gray-500 hover:bg-gray-50 rounded-2xl transition-colors">
                            ยกเลิก
                        </button>
                        <button type="submit" :disabled="isSubmitting"
                                class="flex-[2] py-3 bg-indigo-600 text-white rounded-2xl text-sm font-bold shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-show="!isSubmitting">บันทึกการสลับวัน</span>
                            <span x-show="isSubmitting" class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                กำลังดำเนินการ...
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-4">
        <a href="{{ $canManageWorkspace ? route('employees.index') : route('workspace.my') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <div>
            <h1 class="text-xl font-bold">{{ $employee->full_name }}</h1>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>{{ $employee->position?->name ?? '-' }}</span>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                    @switch($employee->payroll_mode)
                        @case('monthly_staff') bg-blue-100 text-blue-700 @break
                        @case('office_staff') bg-cyan-100 text-cyan-700 @break
                        @case('freelance_layer') bg-emerald-100 text-emerald-700 @break
                        @case('youtuber_salary') bg-indigo-100 text-indigo-700 @break
                        @case('youtuber_settlement') bg-violet-100 text-violet-700 @break
                        @default bg-gray-100 text-gray-700
                    @endswitch">
                    {{ $employee->payroll_mode }}
                </span>
                @if($canManageWorkspace && in_array($employee->payroll_mode, ['monthly_staff', 'office_staff', 'youtuber_salary']))
                <div class="flex items-center gap-2 ml-2">
                    <form action="{{ route('workspace.module.toggle', $employee->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="module_name" value="sso_deduction">
                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border transition-all
                            {{ $employee->isModuleEnabled('sso_deduction') ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-400' }}" title="เปิด/ปิดการคิดเงินประกันสังคม">
                            <span class="w-1.5 h-1.5 rounded-full {{ $employee->isModuleEnabled('sso_deduction') ? 'bg-indigo-500' : 'bg-gray-300' }}"></span>
                            SSO
                        </button>
                    </form>

                    @if(in_array($employee->payroll_mode, ['monthly_staff', 'office_staff']))
                    <form action="{{ route('workspace.module.toggle', $employee->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="module_name" value="deduct_late">
                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border transition-all
                            {{ $employee->isModuleEnabled('deduct_late') ? 'bg-red-50 border-red-200 text-red-700' : 'bg-gray-50 border-gray-200 text-gray-400' }}" title="เปิด/ปิดการหักเงินมาสาย">
                            <span class="w-1.5 h-1.5 rounded-full {{ $employee->isModuleEnabled('deduct_late') ? 'bg-red-500' : 'bg-gray-300' }}"></span>
                            หักมาสาย
                        </button>
                    </form>

                    <form action="{{ route('workspace.module.toggle', $employee->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="module_name" value="deduct_early">
                        <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border transition-all
                            {{ $employee->isModuleEnabled('deduct_early') ? 'bg-amber-50 border-amber-200 text-amber-700' : 'bg-gray-50 border-gray-200 text-gray-400' }}" title="เปิด/ปิดการหักเงินออกก่อนเวลา">
                            <span class="w-1.5 h-1.5 rounded-full {{ $employee->isModuleEnabled('deduct_early') ? 'bg-amber-500' : 'bg-gray-300' }}"></span>
                            หักออกเร็ว
                        </button>
                    </form>
                    @endif
                </div>
                @endif
            </div>
        </div>
    </div>


    <div class="flex items-center gap-2 relative">
        {{-- ← Prev month arrow: disabled when before start_date --}}
        @if($prevIsBeforeStart)
            <span class="px-3 py-1 bg-gray-100 rounded-lg text-sm text-gray-300 cursor-not-allowed select-none" title="ไม่สามารถย้อนกลับก่อนวันเริ่มงาน">&larr;</span>
        @else
            <a href="{{ route('workspace.show', ['employee' => $employee->id, 'month' => $prevMonth, 'year' => $prevYear]) }}"
               class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&larr;</a>
        @endif

        {{-- Clickable month badge → opens picker --}}
        <button @click="open = !open" type="button"
                class="px-4 py-1 bg-indigo-50 text-indigo-700 rounded-lg font-semibold text-sm hover:bg-indigo-100 transition-colors relative cursor-pointer flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 opacity-60" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $monthNames[$month] }} {{ $year + 543 }}
            <svg class="w-3 h-3 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>

        {{-- Month Picker Dropdown --}}
        <div x-show="open" x-cloak x-transition @click.away="open = false"
             class="absolute z-50 mt-2 top-full bg-white border border-gray-200 rounded-xl shadow-xl p-4 w-[280px]">
            {{-- Year nav --}}
            <div class="flex items-center justify-between mb-3">
                {{-- ← Year back: disabled when already at start year --}}
                <button @click="if(pickerYear > startYear) pickerYear--" type="button"
                        :class="pickerYear <= startYear ? 'text-gray-200 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-500'"
                        class="p-1 rounded transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <span class="text-sm font-bold text-gray-800" x-text="'พ.ศ. ' + (pickerYear + 543)"></span>
                <button @click="if(pickerYear < currentYearReal) pickerYear++" type="button"
                        :class="pickerYear >= currentYearReal ? 'text-gray-200 cursor-not-allowed' : 'hover:bg-gray-100 text-gray-500'"
                        class="p-1 rounded transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            {{-- Start-date label hint --}}
            @if($startDate)
            <div class="mb-2 text-[10px] text-gray-400 text-center">
                วันเริ่มงาน: {{ $startDate->format('d/m/') }}{{ $startDate->year + 543 }}
                &nbsp;·&nbsp; เดือนก่อนหน้าไม่สามารถเลือกได้
            </div>
            @endif

            {{-- Month grid --}}
            <div class="grid grid-cols-3 gap-1.5">
                <template x-for="(name, idx) in thaiMonths" :key="idx">
                    <button
                        @click="if(!isBeforeStart(idx + 1, pickerYear) && !isFuture(idx + 1, pickerYear)) { goTo(idx + 1, pickerYear); open = false; }"
                        type="button"
                        :disabled="isBeforeStart(idx + 1, pickerYear) || isFuture(idx + 1, pickerYear)"
                        :class="{
                            'bg-indigo-600 text-white font-bold shadow-sm': (idx + 1) === currentMonth && pickerYear === currentYear,
                            'bg-gray-50 text-gray-200 cursor-not-allowed': isBeforeStart(idx + 1, pickerYear) || isFuture(idx + 1, pickerYear),
                            'bg-gray-50 text-gray-700 hover:bg-indigo-50 hover:text-indigo-700': !isBeforeStart(idx + 1, pickerYear) && !isFuture(idx + 1, pickerYear) && !((idx + 1) === currentMonth && pickerYear === currentYear)
                        }"
                        class="px-2 py-2 rounded-lg text-xs font-medium transition-all text-center"
                        x-text="name">
                    </button>
                </template>
            </div>

            {{-- Quick jump to current month --}}
            <div class="mt-3 pt-3 border-t border-gray-100 flex justify-center">
                <button @click="goTo({{ now()->month }}, {{ now()->year }}); open = false" type="button"
                        class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium">
                    ⏎ เดือนนี้
                </button>
            </div>
        </div>

        @if($nextIsFuture)
            <span class="px-3 py-1 bg-gray-100 rounded-lg text-sm text-gray-300 cursor-not-allowed select-none" title="ไม่สามารถเลือกเดือนล่วงหน้าได้">&rarr;</span>
        @else
            <a href="{{ route('workspace.show', ['employee' => $employee->id, 'month' => $nextMonth, 'year' => $nextYear]) }}"
               class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&rarr;</a>
        @endif

        @if($payslip && $payslip->status === 'finalized')
        <div class="flex items-center gap-2 group">
            <span class="px-2 py-1 bg-green-100 text-green-700 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-green-200">Finalized</span>
            
            @if($canManageWorkspace)
            <button type="button" @click="unfinalizeConfirmOpen = true"
                    class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all" title="ยกเลิก Finalize">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            </button>
            @endif
        </div>
        @else
        <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded-lg text-[10px] font-bold uppercase tracking-wider border border-yellow-200">Draft</span>
        @endif

        @if($canManageWorkspace)
        <button @click="modalSwapOpen = true"
           class="ml-2 px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600 transition-all shadow-lg shadow-amber-200 flex items-center gap-2" title="ส่งคำขอสลับวันทำงาน">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            ขอ Swap วันหยุด
        </button>
        @endif

        <a href="{{ route('payslip.preview', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
           class="ml-3 px-5 py-2 {{ ($payslip && $payslip->status === 'finalized') ? 'bg-green-600 hover:bg-green-700 shadow-green-200' : 'bg-indigo-600 hover:bg-indigo-700 shadow-indigo-200' }} text-white rounded-xl text-sm font-bold transition-all shadow-lg flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Slip
        </a>

    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <p class="text-xs text-green-600 font-medium">รายรับ</p>
        <p id="summary-total-income" class="text-2xl font-bold text-green-700 transition-all">{{ number_format($summary['total_income'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-xs text-red-600 font-medium">รายหัก</p>
        <p id="summary-total-deduction" class="text-2xl font-bold text-red-700 transition-all">{{ number_format($summary['total_deduction'] ?? 0, 2) }}</p>
    </div>
    <div id="summary-net-pay-card" class="bg-indigo-50 border border-indigo-200 rounded-xl p-4 transition-colors" x-data>
        <p class="text-xs text-indigo-600 font-medium">รายได้สุทธิ</p>
        <p id="summary-net-pay" class="text-2xl font-bold text-indigo-700 transition-all">{{ number_format($summary['net_pay'] ?? 0, 2) }}</p>
        <p id="summary-stale-flag" class="hidden mt-1 text-[10px] font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">⚠ ยังไม่ได้คำนวณใหม่</p>
    </div>
    <div class="bg-teal-50 border border-teal-200 rounded-xl p-4 relative group">
        <p class="text-xs text-teal-600 font-medium">ลาพักร้อน (ปีนี้)</p>
        <div class="flex items-baseline gap-1">
            <p class="text-2xl font-bold text-teal-700" x-text="vacationBalance.used"></p>
            <p class="text-sm font-bold text-teal-400" x-text="'/ ' + vacationBalance.limit"></p>
        </div>
        <div class="mt-1">
            <p class="text-[10px] text-teal-500 font-bold" x-text="'เหลือ ' + vacationBalance.remaining + ' วัน'"></p>
        </div>
    </div>
    @if($employee->payroll_mode !== 'youtuber_salary' && $employee->payroll_mode !== 'youtuber_settlement')
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4">
        <p class="text-xs text-amber-600 font-medium">ระยะเวลารวม (เดือนนี้)</p>
        <p class="text-2xl font-bold text-amber-700">{{ $performanceSummary['total_duration_hms'] ?? '00:00:00' }}</p>
    </div>
    @else
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <p class="text-xs text-indigo-600 font-medium">{{ $performanceSummary['revenue_label'] ?? 'รายได้สะสม' }}</p>
        <p class="text-2xl font-bold text-indigo-700">{{ number_format($performanceSummary['ytd_income'] ?? 0, 2) }}</p>
        <p class="text-[10px] text-indigo-400 font-bold uppercase mt-1">สรุปรายได้ที่ปิดยอดแล้ว</p>
    </div>
    @endif
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Grid (2/3) -->
    <div class="lg:col-span-2 space-y-6">
        @if($attendanceReadOnly ?? false)
        <div class="mb-3 flex items-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-200 rounded-xl text-amber-700 text-sm">
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>ประวัติเข้างานเดือนปัจจุบันจะแสดงหลังปิดเดือน — ดูข้อมูลเดือนก่อนหน้าได้จากเมนูเลือกเดือน</span>
        </div>
        @endif
        <div class="{{ (!($workspaceEditEnabled ?? true) || !$canManageWorkspace) ? 'opacity-60 pointer-events-none select-none' : '' }}">
        @if(in_array($employee->payroll_mode, ['monthly_staff', 'office_staff']))
            @if($attendanceReadOnly ?? false)
                {{-- owner sees placeholder for current month --}}
                <div class="bg-white rounded-xl shadow-sm border p-6 text-center text-gray-400 text-sm">
                    <svg class="w-10 h-10 mx-auto mb-2 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    ตารางเข้างานสำหรับเดือนนี้จะแสดงหลังสิ้นเดือน
                </div>
            @else
                @include('workspace.partials.attendance-grid')
            @endif
        @elseif($employee->payroll_mode === 'youtuber_salary')
            @include('workspace.partials.youtuber-recording-sessions')
        @elseif($employee->payroll_mode === 'freelance_layer')
            @include('workspace.partials.freelance-layer-grid')
        @elseif($employee->payroll_mode === 'youtuber_settlement')
            @include('workspace.partials.youtuber-settlement-grid')
        @else
            {{-- custom_hybrid or unknown mode --}}
            <div class="bg-white rounded-xl shadow-sm border p-6 text-center text-gray-400 text-sm">
                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <p class="font-medium text-gray-500">โหมด {{ $employee->payroll_mode }}</p>
                <p class="mt-1 text-xs text-gray-400">โหมดนี้ไม่มี Grid เฉพาะ — ใช้ส่วน "เงินได้/รายหัก" ด้านขวาเพื่อปรับรายการด้วยตนเอง</p>
            </div>
        @endif
        </div>

        {{-- YouTuber doesn't need to see production/performance pipeline here anymore --}}
        {{-- FL layer/fixed merge the Assigned Edit Jobs inside their own grid card --}}
        @if(!in_array($employee->payroll_mode, ['youtuber_salary', 'youtuber_settlement', 'freelance_layer'], true) && ($panel ?? '') !== 'none')
            @include('workspace.partials.performance-records')
        @endif
    </div>

    <!-- Right Panel (1/3) -->
    <div class="space-y-4">
        <!-- Payroll Summary Panel -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
                @php
                    $panelTitle = match($employee->payroll_mode) {
                        'monthly_staff'         => 'สรุปเงินเดือน',
                        'office_staff'          => 'สรุปเงินเดือน',
                        'youtuber_salary'       => 'สรุปเงินเดือน',
                        'freelance_layer'       => 'สรุปค่าจ้าง',
                        'youtuber_settlement'   => 'สรุปรายรับ-รายจ่าย',
                        default                 => 'สรุปรายได้',
                    };
                @endphp
                <h3 class="font-semibold text-sm mb-3">{{ $panelTitle }}</h3>

            @if(isset($summary['total_work_hours']) && in_array($employee->payroll_mode, ['monthly_staff', 'office_staff']))
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">ชั่วโมงรวม</span>
                    <span id="summary-work-hours" class="font-medium">{{ $formatHoursAsClock($summary['total_work_hours'] ?? 0) }} ชม.</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">OT</span>
                    <span id="summary-ot-hours" class="font-medium">{{ $formatHoursAsClock($summary['total_ot_hours'] ?? 0) }} ชม.</span>
                </div>
                @if($employee->payroll_mode !== 'youtuber_salary')
                <div class="flex justify-between">
                    <span class="text-gray-500">มาสาย</span>
                    <span id="summary-late-info" class="font-medium">{{ $summary['late_count'] ?? 0 }} ครั้ง ({{ $summary['late_minutes'] ?? 0 }} นาที)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">ขาดงาน</span>
                    <span id="summary-lwop-days" class="font-medium">{{ $summary['lwop_days'] ?? 0 }} วัน</span>
                </div>
                @endif
            </div>
            <hr class="my-3">
            @endif

            @if(isset($summary['total_minutes']) && $employee->payroll_mode === 'freelance_layer')
            @php
                $fl_total_sec = ($summary['total_minutes'] * 60) + ($summary['total_seconds'] ?? 0);
                $fl_h = intdiv((int)$fl_total_sec, 3600);
                $fl_m = intdiv((int)$fl_total_sec % 3600, 60);
                $fl_s = (int)$fl_total_sec % 60;
            @endphp
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">เวลารวม</span>
                    <span class="font-medium">{{ sprintf('%d:%02d:%02d', $fl_h, $fl_m, $fl_s) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">จำนวนรายการ</span>
                    <span class="font-medium">{{ $summary['work_log_count'] ?? 0 }} รายการ</span>
                </div>
            </div>
            <hr class="my-3">
            @endif

            <h4 class="text-xs font-semibold text-green-600 mb-2 flex items-center gap-1">
                เงินได้
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="รายการรายรับทั้งหมด (แสดงผลจากการคำนวณ)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </h4>
            <div id="payroll-income-items">
            @foreach(($result['items'] ?? []) as $item)
                @if($item['category'] === 'income')
                @php
                    $isManual = in_array($item['source_flag'], ['manual', 'override']);
                @endphp
                <div class="flex justify-between text-sm py-1 group">
                    <span class="text-gray-600 flex items-center gap-1 {{ ($item['notes'] ?? $item['note'] ?? '') ? 'cursor-help border-b border-dashed border-gray-300' : '' }}" 
                          title="{{ $item['notes'] ?? $item['note'] ?? '' }}">
                        {{ $item['label'] }}
                        @if($isManual && $canManageWorkspace)
                            <span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>
                        @endif
                    </span>
                    <span class="font-medium {{ $item['amount'] > 0 ? '' : 'text-gray-400' }}">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @endif
            @endforeach
            </div>

            <hr class="my-3">
            <h4 class="text-xs font-semibold text-red-600 mb-2 flex items-center gap-1">
                รายหัก
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="รายการหักทั้งหมด (แสดงผลจากการคำนวณ)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </h4>
            <div id="payroll-deduction-items">
            @foreach(($result['items'] ?? []) as $item)
                @if($item['category'] === 'deduction')
                @php
                    $isManual = in_array($item['source_flag'], ['manual', 'override']);
                @endphp
                <div class="flex justify-between text-sm py-1 group">
                    <span class="text-gray-600 flex items-center gap-1 {{ ($item['notes'] ?? $item['note'] ?? '') ? 'cursor-help border-b border-dashed border-gray-300' : '' }}" 
                          title="{{ $item['notes'] ?? $item['note'] ?? '' }}">
                        {{ $item['label'] }}
                        @if($isManual && $canManageWorkspace)
                            <span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>
                        @endif
                    </span>
                    <span class="font-medium {{ $item['amount'] > 0 ? '' : 'text-gray-400' }}">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @endif
            @endforeach
            </div>

            <hr class="my-3">
            <div class="flex justify-between font-bold text-base">
                <span>รายได้สุทธิ</span>
                <span id="summary-net-pay-bottom" class="text-indigo-600">{{ number_format($summary['net_pay'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Recalculate Button -->
        @if($canManageWorkspace)
        <form method="POST" action="{{ route('workspace.recalculate', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
            @csrf
            <button type="submit" data-shortcut-recalculate id="recalculate-btn" {{ !($workspaceEditEnabled ?? true) ? 'disabled' : '' }} class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                คำนวณใหม่ <span class="text-[10px] opacity-60 ml-1">Ctrl+R</span>
            </button>
        </form>
        @endif

        <div class="{{ !($workspaceEditEnabled ?? true) ? 'opacity-60 pointer-events-none select-none' : '' }}">
            @include('workspace.partials.payroll-adjustments')
        </div>
    </div>
</div>

@include('partials.grid-navigation')

{{-- Unfinalize Confirmation Modal --}}
<div x-show="unfinalizeConfirmOpen" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center"
     @keydown.escape.window="unfinalizeConfirmOpen = false">
    <div class="fixed inset-0 bg-black/50" @click="unfinalizeConfirmOpen = false"></div>
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm z-10 p-6 space-y-4">
        <h3 class="text-lg font-bold text-red-700">ยืนยันยกเลิก Finalize?</h3>
        <p class="text-sm text-gray-600">การกระทำนี้จะเปลี่ยนสถานะเป็น Draft และอนุญาตให้แก้ไขข้อมูลใหม่ได้</p>
        <div class="flex justify-end gap-3">
            <button type="button" @click="unfinalizeConfirmOpen = false"
                    class="px-4 py-2 border border-gray-300 text-gray-700 rounded-md text-sm hover:bg-gray-100">ยกเลิก</button>
            <form method="POST" action="{{ route('payslip.unfinalize', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">ยืนยันยกเลิก Finalize</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const card = document.getElementById('summary-net-pay-card');
    const flag = document.getElementById('summary-stale-flag');
    const recalcBtn = document.getElementById('recalculate-btn');
    if (!card || !flag) return;

    let isStale = false;
    const markStale = () => {
        if (isStale) return;
        isStale = true;
        flag.classList.remove('hidden');
        card.classList.add('ring-2', 'ring-amber-400');
        if (recalcBtn) recalcBtn.classList.add('animate-pulse');
    };

    document.addEventListener('change', (e) => {
        const t = e.target;
        if (!t) return;
        if (t.closest('#summary-net-pay-card')) return;
        if (t.matches('input, select, textarea')) markStale();
    }, true);

    try {
        const key = 'xhr_recent_employees';
        const current = {
            id: {{ (int) $employee->id }},
            name: {!! json_encode($employee->full_name ?? $employee->first_name) !!},
            url: {!! json_encode(route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year])) !!},
            opened_at: Date.now(),
        };
        const raw = localStorage.getItem(key);
        let list = raw ? JSON.parse(raw) : [];
        list = [current, ...list.filter(r => r.id !== current.id)].slice(0, 8);
        localStorage.setItem(key, JSON.stringify(list));
    } catch (e) { /* ignore */ }
})();
</script>
@endpush
