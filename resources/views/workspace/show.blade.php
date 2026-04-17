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
@endphp

@if(!($workspaceEditEnabled ?? true))
<div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-xl text-sm font-semibold">
    Workspace นี้ถูกปิดสิทธิ์การแก้ไขจาก Master Data (โหมดดูข้อมูลอย่างเดียว)
</div>
@endif

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
                        @case('freelance_fixed') bg-teal-100 text-teal-700 @break
                        @case('youtuber_salary') bg-indigo-100 text-indigo-700 @break
                        @case('youtuber_settlement') bg-violet-100 text-violet-700 @break
                        @default bg-gray-100 text-gray-700
                    @endswitch">
                    {{ $employee->payroll_mode }}
                </span>
                @if($canManageWorkspace && in_array($employee->payroll_mode, ['monthly_staff', 'office_staff', 'youtuber_salary']))
                <form action="{{ route('workspace.module.toggle', $employee->id) }}" method="POST" class="ml-2">
                    @csrf
                    <input type="hidden" name="module_name" value="sso_deduction">
                    <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border transition-all
                        {{ $employee->isModuleEnabled('sso_deduction') ? 'bg-indigo-50 border-indigo-200 text-indigo-700' : 'bg-gray-50 border-gray-200 text-gray-400' }}">
                        <span class="w-1.5 h-1.5 rounded-full {{ $employee->isModuleEnabled('sso_deduction') ? 'bg-indigo-500' : 'bg-gray-300' }}"></span>
                        ประกันสังคม
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Month Selector with Calendar Picker -->
    @php
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

    <div class="flex items-center gap-2 relative" x-data="{
        open: false,
        pickerYear: {{ $year }},
        currentMonth: {{ $month }},
        currentYear: {{ $year }},
        startMonth: {{ $startMonth }},
        startYear: {{ $startYear }},
        currentMonthReal: {{ $currentMonthReal }},
        currentYearReal: {{ $currentYearReal }},
        thaiMonths: ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'],
        isBeforeStart(m, y) {
            return (y < this.startYear) || (y === this.startYear && m < this.startMonth);
        },
        isFuture(m, y) {
            return (y > this.currentYearReal) || (y === this.currentYearReal && m > this.currentMonthReal);
        },
        goTo(m, y) {
            if (this.isBeforeStart(m, y) || this.isFuture(m, y)) return;
            window.location.href = '{{ route('workspace.show', ['employee' => $employee->id, 'month' => '__M__', 'year' => '__Y__']) }}'.replace('__M__', m).replace('__Y__', y);
        }
    }">
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

        <a href="{{ route('payslip.preview', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
           class="ml-4 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">
            Slip
        </a>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-green-50 border border-green-200 rounded-xl p-4">
        <p class="text-xs text-green-600 font-medium">รายรับ</p>
        <p id="summary-total-income" class="text-2xl font-bold text-green-700 transition-all">{{ number_format($summary['total_income'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-xs text-red-600 font-medium">รายหัก</p>
        <p id="summary-total-deduction" class="text-2xl font-bold text-red-700 transition-all">{{ number_format($summary['total_deduction'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <p class="text-xs text-indigo-600 font-medium">รายได้สุทธิ</p>
        <p id="summary-net-pay" class="text-2xl font-bold text-indigo-700 transition-all">{{ number_format($summary['net_pay'] ?? 0, 2) }}</p>
    </div>
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
        @elseif($employee->payroll_mode === 'freelance_layer')
            @include('workspace.partials.freelance-layer-grid')
        @elseif($employee->payroll_mode === 'freelance_fixed')
            @include('workspace.partials.freelance-fixed-grid')
        @elseif($employee->payroll_mode === 'youtuber_settlement')
            @include('workspace.partials.youtuber-settlement-grid')
        @elseif($employee->payroll_mode === 'youtuber_salary')
            @include('workspace.partials.youtuber-salary-grid')
        @else
            {{-- custom_hybrid or unknown mode --}}
            <div class="bg-white rounded-xl shadow-sm border p-6 text-center text-gray-400 text-sm">
                <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                <p class="font-medium text-gray-500">โหมด {{ $employee->payroll_mode }}</p>
                <p class="mt-1 text-xs text-gray-400">โหมดนี้ไม่มี Grid เฉพาะ — ใช้ส่วน "เงินได้/รายหัก" ด้านขวาเพื่อปรับรายการด้วยตนเอง</p>
            </div>
        @endif
        </div>

        @if(!in_array($employee->payroll_mode, ['youtuber_salary', 'youtuber_settlement'], true))
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
                        'freelance_layer'       => 'สรุปค่าจ้าง (Layer)',
                        'freelance_fixed'       => 'สรุปค่าจ้าง (Fixed)',
                        'youtuber_settlement'   => 'สรุปรายรับ-รายจ่าย',
                        default                 => 'สรุปรายได้',
                    };
                @endphp
                <h3 class="font-semibold text-sm mb-3">{{ $panelTitle }}</h3>

            @if(isset($summary['total_work_hours']) && in_array($employee->payroll_mode, ['monthly_staff', 'office_staff', 'youtuber_salary']))
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">ชั่วโมงรวม</span>
                    <span id="summary-work-hours" class="font-medium">{{ $formatHoursAsClock($summary['total_work_hours'] ?? 0) }} ชม.</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">OT</span>
                    <span id="summary-ot-hours" class="font-medium">{{ $formatHoursAsClock($summary['total_ot_hours'] ?? 0) }} ชม.</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">มาสาย</span>
                    <span id="summary-late-info" class="font-medium">{{ $summary['late_count'] ?? 0 }} ครั้ง ({{ $summary['late_minutes'] ?? 0 }} นาที)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">ขาดงาน</span>
                    <span id="summary-lwop-days" class="font-medium">{{ $summary['lwop_days'] ?? 0 }} วัน</span>
                </div>
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
            @elseif(isset($summary['work_log_count']) && $employee->payroll_mode === 'freelance_fixed')
            <div class="space-y-2 mb-4 text-sm">
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
                    <span class="text-gray-600 flex items-center gap-1">
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
                    <span class="text-gray-600 flex items-center gap-1">
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
            <button type="submit" {{ !($workspaceEditEnabled ?? true) ? 'disabled' : '' }} class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                คำนวณใหม่
            </button>
        </form>
        @endif

        <div class="{{ !($workspaceEditEnabled ?? true) ? 'opacity-60 pointer-events-none select-none' : '' }}">
            @include('workspace.partials.claims-grid')
        </div>
    </div>
</div>

@include('partials.grid-navigation')
@endsection
