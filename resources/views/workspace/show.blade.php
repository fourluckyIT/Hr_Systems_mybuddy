@extends('layouts.app')
@section('title', $employee->display_name . ' - Workspace')

@section('content')
@php
    $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $summary = $result['summary'] ?? [];
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

<!-- Header -->
<div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-4">
        <a href="{{ route('employees.index') }}" class="text-gray-400 hover:text-gray-600">&larr;</a>
        <div>
            <h1 class="text-xl font-bold">{{ $employee->full_name }}</h1>
            <div class="flex items-center gap-2 text-sm text-gray-500">
                <span>{{ $employee->position?->name ?? '-' }}</span>
                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium
                    @switch($employee->payroll_mode)
                        @case('monthly_staff') bg-blue-100 text-blue-700 @break
                        @case('freelance_layer') bg-emerald-100 text-emerald-700 @break
                        @case('freelance_fixed') bg-teal-100 text-teal-700 @break
                        @case('youtuber_salary') bg-indigo-100 text-indigo-700 @break
                        @case('youtuber_settlement') bg-violet-100 text-violet-700 @break
                        @default bg-gray-100 text-gray-700
                    @endswitch">
                    {{ $employee->payroll_mode }}
                </span>
                @if(in_array($employee->payroll_mode, ['monthly_staff', 'youtuber_salary']))
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

    <!-- Month Selector -->
    <div class="flex items-center gap-2">
        <a href="{{ route('workspace.show', ['employee' => $employee->id, 'month' => $prevMonth, 'year' => $prevYear]) }}"
           class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&larr;</a>
        <span class="px-4 py-1 bg-indigo-50 text-indigo-700 rounded-lg font-semibold text-sm">
            {{ $monthNames[$month] }} {{ $year + 543 }}
        </span>
        <a href="{{ route('workspace.show', ['employee' => $employee->id, 'month' => $nextMonth, 'year' => $nextYear]) }}"
           class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&rarr;</a>

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
        <p class="text-2xl font-bold text-green-700">{{ number_format($summary['total_income'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4">
        <p class="text-xs text-red-600 font-medium">รายหัก</p>
        <p class="text-2xl font-bold text-red-700">{{ number_format($summary['total_deduction'] ?? 0, 2) }}</p>
    </div>
    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-4">
        <p class="text-xs text-indigo-600 font-medium">รายได้สุทธิ</p>
        <p class="text-2xl font-bold text-indigo-700">{{ number_format($summary['net_pay'] ?? 0, 2) }}</p>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Grid (2/3) -->
    <div class="lg:col-span-2 space-y-6">
        @if($employee->payroll_mode === 'monthly_staff')
            @include('workspace.partials.attendance-grid')
        @elseif($employee->payroll_mode === 'freelance_layer')
            @include('workspace.partials.freelance-layer-grid')
        @elseif($employee->payroll_mode === 'freelance_fixed')
            @include('workspace.partials.freelance-fixed-grid')
        @elseif($employee->payroll_mode === 'youtuber_settlement')
            @include('workspace.partials.youtuber-settlement-grid')
        @endif

        @include('workspace.partials.performance-records')
    </div>

    <!-- Right Panel (1/3) -->
    <div class="space-y-4">
        <!-- Payroll Summary Panel -->
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <h3 class="font-semibold text-sm mb-3">สรุปเงินเดือน</h3>

            @if(isset($summary['total_work_hours']))
            <div class="space-y-2 mb-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">ชั่วโมงรวม</span>
                    <span class="font-medium">{{ $formatHoursAsClock($summary['total_work_hours'] ?? 0) }} ชม.</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">OT</span>
                    <span class="font-medium">{{ $formatHoursAsClock($summary['total_ot_hours'] ?? 0) }} ชม.</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">มาสาย</span>
                    <span class="font-medium">{{ $summary['late_count'] ?? 0 }} ครั้ง ({{ $summary['late_minutes'] ?? 0 }} นาที)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">ขาดงาน</span>
                    <span class="font-medium">{{ $summary['lwop_days'] ?? 0 }} วัน</span>
                </div>
            </div>
            <hr class="my-3">
            @endif

            <h4 class="text-xs font-semibold text-green-600 mb-2 flex items-center gap-1">
                เงินได้
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="รายการรายรับทั้งหมด (แสดงผลจากการคำนวณ)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </h4>
            @foreach(($result['items'] ?? []) as $item)
                @if($item['category'] === 'income')
                @php 
                    $isManual = in_array($item['source_flag'], ['manual', 'override']);
                @endphp
                <div class="flex justify-between text-sm py-1 group">
                    <span class="text-gray-600 flex items-center gap-1">
                        {{ $item['label'] }}
                        @if($isManual)
                            <span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>
                        @endif
                    </span>
                    <span class="font-medium {{ $item['amount'] > 0 ? '' : 'text-gray-400' }}">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @endif
            @endforeach

            <hr class="my-3">
            <h4 class="text-xs font-semibold text-red-600 mb-2 flex items-center gap-1">
                รายหัก
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-300 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor" title="รายการหักทั้งหมด (แสดงผลจากการคำนวณ)"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </h4>
            @foreach(($result['items'] ?? []) as $item)
                @if($item['category'] === 'deduction')
                @php 
                    $isManual = in_array($item['source_flag'], ['manual', 'override']);
                @endphp
                <div class="flex justify-between text-sm py-1 group">
                    <span class="text-gray-600 flex items-center gap-1">
                        {{ $item['label'] }}
                        @if($isManual)
                            <span class="text-[8px] bg-amber-100 text-amber-700 px-1 rounded font-bold uppercase">Manual</span>
                        @endif
                    </span>
                    <span class="font-medium {{ $item['amount'] > 0 ? '' : 'text-gray-400' }}">{{ number_format($item['amount'], 2) }}</span>
                </div>
                @endif
            @endforeach

            <hr class="my-3">
            <div class="flex justify-between font-bold text-base">
                <span>รายได้สุทธิ</span>
                <span class="text-indigo-600">{{ number_format($summary['net_pay'] ?? 0, 2) }}</span>
            </div>
        </div>

        <!-- Recalculate Button -->
        <form method="POST" action="{{ route('workspace.recalculate', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
            @csrf
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition-colors">
                คำนวณใหม่
            </button>
        </form>

        @include('workspace.partials.claims-grid')
    </div>
</div>

@include('partials.grid-navigation')
@endsection
