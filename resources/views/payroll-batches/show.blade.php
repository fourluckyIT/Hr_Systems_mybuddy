@extends('layouts.app')

@section('title', "รายละเอียดรอบบิล - {$monthName} {$year}")

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="{{ route('payroll-batches.index') }}" class="text-gray-400 hover:text-gray-600 transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 border-l-4 border-indigo-600 pl-3">รายละเอียดรอบบิลเงินเดือน</h1>
                <p class="text-sm text-gray-500 pl-4 mt-1">ประจำเดือน {{ $monthName }} ปี {{ $year }}</p>
            </div>
        </div>
        <div>
           <!-- Future Bulk Download PDF Button can be placed here -->
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">พนักงานทั้งหมด (สลิป)</p>
            <p class="text-2xl font-bold text-gray-800">{{ $summary['total_slips'] }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">สถานะ Finalized</p>
            <p class="text-2xl font-bold {{ $summary['finalized_slips'] == $summary['total_slips'] ? 'text-green-600' : 'text-orange-500' }}">
                {{ $summary['finalized_slips'] }} / {{ $summary['total_slips'] }}
            </p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">รวมรายรับ (Income)</p>
            <p class="text-lg font-bold text-green-700">{{ number_format($summary['total_income'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <p class="text-xs text-gray-500 mb-1">รวมรายหัก (Deduction)</p>
            <p class="text-lg font-bold text-red-600">{{ number_format($summary['total_deduction'], 2) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center shadow-indigo-100/50">
            <p class="text-xs text-gray-500 mb-1">รวมจ่ายสุทธิ (Net Pay)</p>
            <p class="text-xl font-bold text-indigo-700">{{ number_format($summary['net_pay'], 2) }}</p>
        </div>
    </div>

    <!-- Details Table -->
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 bg-gray-50 border-b flex justify-between items-center">
            <h2 class="font-semibold text-gray-700 text-sm">รายชื่อพนักงานในรอบบิลนี้</h2>
            <span class="text-xs text-gray-500">* ยอดรวมด้านบนจะคิดจากสลิปที่ Finalized แล้วเท่านั้น</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-white border-b">
                    <tr>
                        <th class="px-4 py-3 font-medium text-gray-500">รหัสพนักงาน</th>
                        <th class="px-4 py-3 font-medium text-gray-500">ชื่อ - นามสกุล</th>
                        <th class="px-4 py-3 font-medium text-gray-500">แผนก (โหมด)</th>
                        <th class="px-4 py-3 text-right font-medium text-green-700">รายรับ</th>
                        <th class="px-4 py-3 text-right font-medium text-red-700">รายหัก</th>
                        <th class="px-4 py-3 text-right font-medium text-indigo-700">สุทธิ</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">สถานะ</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-500">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($payslips as $slip)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 font-mono text-gray-600 text-xs">{{ $slip->employee->employee_code }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-800">
                            {{ $slip->employee->full_name }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-900">{{ $slip->employee->department->name ?? '-' }}</div>
                            <div class="text-xs text-gray-500 mt-0.5">{{ $slip->employee->payroll_mode }}</div>
                        </td>
                        <td class="px-4 py-3 text-right text-green-700">{{ number_format($slip->total_income, 2) }}</td>
                        <td class="px-4 py-3 text-right text-red-600">{{ number_format($slip->total_deduction, 2) }}</td>
                        <td class="px-4 py-3 text-right font-bold text-indigo-600">{{ number_format($slip->net_pay, 2) }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($slip->status === 'finalized')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-green-100 text-green-800 uppercase tracking-widest">
                                    Finalized
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 uppercase tracking-widest">
                                    Draft
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center space-x-2">
                            <a href="{{ route('workspace.show', [$slip->employee_id, $slip->month, $slip->year]) }}" 
                               target="_blank"
                               class="inline-flex items-center text-xs text-gray-500 hover:text-indigo-600 underline">
                                Workspace
                            </a>
                            <a href="{{ route('payslip.preview', [$slip->employee_id, $slip->month, $slip->year]) }}" 
                               class="inline-flex items-center px-3 py-1 bg-indigo-50 text-indigo-700 hover:bg-indigo-600 hover:text-white border border-indigo-200 rounded text-xs transition duration-150">
                                สลิป
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
