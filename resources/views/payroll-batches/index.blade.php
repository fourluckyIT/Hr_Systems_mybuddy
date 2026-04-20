@extends('layouts.app')

@section('title', 'ประวัติรอบบิลเงินเดือน')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between border-b pb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">รอบบิลเงินเดือน (Payroll Batches History)</h1>
            <p class="text-sm text-gray-500">ประวัติการจ่ายเงินเดือนทั้งหมดแยกตามเดือและปี</p>
        </div>
    </div>

    @if($months->isEmpty())
        <div class="bg-white rounded-lg border border-dashed border-gray-300 p-12 text-center text-gray-500">
            ยังไม่มีประวัติการทำเงินเดือน
        </div>
    @else
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">ปี / เดือน</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-700">จำนวนพนักงาน</th>
                            <th class="px-4 py-3 text-right font-medium text-green-700">รวมรายรับ (Income)</th>
                            <th class="px-4 py-3 text-right font-medium text-red-700">รวมรายหัก (Deduction)</th>
                            <th class="px-4 py-3 text-right font-medium text-indigo-700">ยอดจ่ายสุทธิ (Net Pay)</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-700">สถานะ</th>
                            <th class="px-4 py-3 text-center font-medium text-gray-700">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($months as $batch)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="font-bold text-gray-900">{{ $monthNames[$batch->month] }} {{ $batch->year }}</div>
                                    <div class="text-xs text-gray-500">{{ str_pad($batch->month, 2, '0', STR_PAD_LEFT) }}/{{ $batch->year }}</div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $batch->total_slips }} คน
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-green-700 font-medium">
                                    {{ number_format($batch->total_income, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-red-600 font-medium">
                                    {{ number_format($batch->total_deduction, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right text-indigo-600 font-bold">
                                    {{ number_format($batch->net_pay, 2) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($batch->total_slips == $batch->finalized_slips)
                                        <span class="inline-flex items-center space-x-1 text-green-600 text-xs font-medium bg-green-50 px-2 py-1 rounded border border-green-200">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                            <span>Finalized All ({{ $batch->finalized_slips }})</span>
                                        </span>
                                    @elseif($batch->finalized_slips > 0)
                                        <span class="inline-flex items-center space-x-1 text-orange-600 text-xs font-medium bg-orange-50 px-2 py-1 rounded border border-orange-200">
                                            <span>Partial ({{ $batch->finalized_slips }}/{{ $batch->total_slips }})</span>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center text-gray-500 text-xs font-medium bg-gray-50 px-2 py-1 rounded border border-gray-200">
                                            Draft Only
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('payroll-batches.show', ['year' => $batch->year, 'month' => $batch->month]) }}" class="inline-flex items-center px-3 py-1.5 border border-indigo-600 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded text-xs transition duration-150">
                                        ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
