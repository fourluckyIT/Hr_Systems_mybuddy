@extends('layouts.app')
@section('title', 'Payslip - ' . $employee->display_name)

@push('styles')
<style>
    body.th-font {
        font-family: 'Sarabun', 'Noto Sans Thai', sans-serif !important;
    }

    @page {
        size: A5 landscape;
        margin: 8mm;
    }

    @media print {
        * {
            font-family: 'Sarabun', 'Noto Sans Thai', sans-serif !important;
        }

        body {
            background: #fff !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        nav,
        .print-hide {
            display: none !important;
        }

        main {
            max-width: none !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        .payslip-container {
            max-width: none !important;
            width: 100% !important;
            padding: 0 !important;
            margin: 0 !important;
        }
    }

    .payslip-container {
        background: white;
        border: 1px solid #ccc;
        padding: 16px;
        font-size: 13px;
        line-height: 1.4;
    }

    .payslip-header {
        text-align: left;
        margin-bottom: 12px;
        border-bottom: 2px solid;
        padding-bottom: 8px;
    }

    .payslip-header h1 {
        font-size: 16px;
        font-weight: bold;
        margin: 0;
        padding: 0;
    }

    .payslip-header .tagline {
        font-size: 11px;
        color: #666;
        margin: 2px 0;
    }

    .payslip-header .descriptor {
        font-size: 10px;
        color: #999;
    }

    .info-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #ddd;
        font-size: 11px;
    }

    .info-row .item {
        display: flex;
        flex-direction: column;
    }

    .info-row label {
        color: #666;
        font-size: 10px;
        font-weight: 500;
        margin-bottom: 2px;
    }

    .info-row value {
        font-weight: bold;
        font-size: 12px;
    }

    .employee-info {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #ddd;
        font-size: 11px;
    }

    .employee-info .item {
        display: flex;
        flex-direction: column;
    }

    .employee-info label {
        color: #666;
        font-size: 10px;
        margin-bottom: 2px;
    }

    .employee-info value {
        font-weight: 600;
    }

    .month-metrics {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px dashed #ddd;
    }

    .month-metric {
        border: 1px solid #e5e7eb;
        background: #fafafa;
        padding: 6px 8px;
        border-radius: 4px;
    }

    .month-metric .label {
        font-size: 10px;
        color: #6b7280;
        margin-bottom: 2px;
    }

    .month-metric .value {
        font-size: 12px;
        font-weight: 700;
        color: #111827;
    }

    .tables-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 12px;
    }

    .income-table,
    .deduction-table {
        border: 1px solid;
        border-radius: 6px;
        overflow: hidden;
    }

    .table-header {
        background-color: #4f46e5;
        color: white;
        padding: 8px 12px;
        font-weight: bold;
        font-size: 12px;
        margin-bottom: 0;
    }

    .table-body {
        padding: 8px 12px;
        background: #fff;
    }

    .table-row {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px dashed #f0f0f0;
        font-size: 11px;
    }

    .table-row:last-child:not(.total) {
        border-bottom: none;
    }

    .table-row.total {
        border-top: 1.5px solid #e5e7eb;
        margin-top: 6px;
        padding-top: 8px;
        font-weight: bold;
        border-bottom: none;
        font-size: 12px;
    }

    .table-row span:first-child {
        flex: 1;
    }

    .table-row span:last-child {
        text-align: right;
        min-width: 60px;
    }

    .net-pay-box {
        border: 2px solid #4f46e5;
        background: rgba(79, 70, 229, 0.05);
        padding: 12px 16px;
        margin-bottom: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 6px;
    }

    .net-pay-box .label {
        font-size: 14px;
        font-weight: bold;
        color: #333;
    }

    .net-pay-box .amount {
        font-size: 18px;
        font-weight: bold;
        color: #4f46e5;
    }

    .summary-boxes {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .summary-box {
        border: 1px solid;
        padding: 10px;
        text-align: center;
        font-size: 10px;
        border-radius: 6px;
    }

    .summary-box.income {
        border-color: #4f46e5;
        background: rgba(79, 70, 229, 0.02);
    }

    .summary-box.deduction {
        border-color: #ef4444;
        background: rgba(239, 68, 68, 0.02);
    }

    .summary-box.net {
        border-color: #6366f1;
        background: rgba(99, 102, 241, 0.02);
    }

    .summary-box label {
        color: #666;
        display: block;
        margin-bottom: 4px;
        font-weight: 500;
    }

    .summary-box .amount {
        font-weight: bold;
        font-size: 13px;
        color: #333;
    }

    .summary-box.income .amount {
        color: #4f46e5;
    }

    .summary-box.deduction .amount {
        color: #ef4444;
    }

    .summary-box.net .amount {
        color: #6366f1;
    }

    .signatures {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 32px;
        margin-bottom: 12px;
        margin-top: 20px;
    }

    .signature-box {
        text-align: center;
    }

    .signature-line {
        border-top: 1px solid #333;
        margin-bottom: 4px;
    }

    .signature-label {
        font-size: 10px;
    }

    .payslip-footer {
        text-align: center;
        font-size: 9px;
        color: #999;
        padding-top: 8px;
        border-top: 1px dashed #ddd;
        margin-top: 8px;
    }
</style>
@endpush

@section('content')
@php
    $monthNames = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $primaryColor = $company?->primary_color ?? '#4f46e5';
    $canManagePayslip = auth()->user()?->hasRole('admin') ?? false;
@endphp

<div class="print-hide flex items-center justify-between mb-4">
    <a href="{{ route('workspace.show', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
       class="text-sm text-gray-500 hover:text-indigo-600">&larr; กลับ Workspace</a>
    <div class="flex gap-2">
        @if($canManagePayslip && (!$payslip || $payslip->status !== 'finalized'))
        <form method="POST" action="{{ route('payslip.finalize', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}">
            @csrf
            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Finalize</button>
        </form>
        @endif
        <a href="{{ route('payslip.pdf', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
           class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color: {{ $primaryColor }}">Export PDF</a>
        <button type="button" onclick="window.print()"
              class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800">Print A5</button>
    </div>
</div>

@if($canManagePayslip && $payslip && $payslip->status === 'finalized')
<div class="print-hide bg-white border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 flex justify-between items-center shadow-sm">
    <div class="flex items-center gap-2">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-bold">Finalized เมื่อ: {{ $payslip->finalized_at?->format('d/m/Y H:i') }}</span>
    </div>
    <form method="POST" action="{{ route('payslip.unfinalize', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" 
          onsubmit="return confirm('คุณแน่ใจหรือว่าต้องการยกเลิก Finalize?\n\nสลิปนี้จะถูกเปลี่ยนสถานะเป็น Draft และข้อมูลจะกลับไปคำนวณสดเพื่อแก้ไขได้ครับ');">
        @csrf
        <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition-all border border-red-100 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
            ยกเลิก Finalize
        </button>
    </form>
</div>
@else
<div class="print-hide bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm mb-4 flex items-center gap-2 font-medium">
    <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
    Draft - ยังไม่ได้ Finalize (ข้อมูลคำนวณสด)
</div>
@endif


<!-- Payslip Container -->
<div class="max-w-5xl mx-auto mb-6">
    <div class="payslip-container">

        <!-- Header -->
        <div class="payslip-header" style="border-color: {{ $primaryColor }};">
            <h1 style="color: {{ $primaryColor }}">{{ $company?->name ?? 'Pro One IT Co., Ltd.' }}
                @if($company?->payslip_header_subtitle)
                / {{ $company->payslip_header_subtitle }}
                @endif
            </h1>
            <div class="tagline">{{ $company?->tagline ?? 'LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์' }}</div>
            <div class="descriptor">สลิปเงินเดือน / Payslip</div>
        </div>

        <!-- Info Row -->
        <div class="info-row">
            <div class="item">
                <label>เลขประจำตัวผู้เสียภาษี:</label>
                <value>{{ $company?->tax_id ?? '-' }}</value>
            </div>
            <div class="item">
                <label>ประจำเดือน:</label>
                <value>{{ $monthNames[$month] }} {{ $year + 543 }}</value>
            </div>
            <div class="item">
                <label>วันที่พิมพ์:</label>
                <value>{{ now()->format('d/m/') . (now()->year + 543) }}</value>
            </div>
            <div class="item">
                <label>วันจ่ายเงิน:</label>
                <value>-</value>
            </div>
        </div>

        <!-- Employee Info -->
        <div class="employee-info">
            <div class="item">
                <label>ชื่อพนักงาน:</label>
                <value>{{ $employee->full_name }}</value>
            </div>
            <div class="item">
                <label>ตำแหน่ง:</label>
                <value>{{ $employee->position?->name ?? '-' }}</value>
            </div>
            <div class="item">
                <label>ธนาคาร:</label>
                <value>{{ $employee->bankAccount?->bank_name ?? '-' }}</value>
            </div>
            <div class="item" style="grid-column: span 2;">
                <label>เลขที่บัญชีเงินเดือน:</label>
                <value>{{ $employee->bankAccount?->account_number ?? '-' }}</value>
            </div>
        </div>



        <!-- Income & Deduction Tables -->
        <div class="tables-row">
            <!-- Income Table -->
            <div class="income-table" style="border-color: {{ $primaryColor }};">
                <div class="table-header" style="background-color: {{ $primaryColor }};">รายการได้</div>
                <div class="table-body">
                    @forelse($incomeItems as $item)
                    <div class="table-row">
                        <span>{{ is_array($item) ? $item['label'] : $item->label }}</span>
                        <span>{{ number_format(is_array($item) ? $item['amount'] : $item->amount, 2) }}</span>
                    </div>
                    @empty
                    <div class="table-row" style="color: #999;">
                        <span>ไม่มีรายการ</span>
                    </div>
                    @endforelse
                    <div class="table-row total" style="color: {{ $primaryColor }};">
                        <span>รวมเงินได้</span>
                        <span>{{ number_format($totalIncome, 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Deduction Table -->
            @php $deductionColor = '#ef4444'; @endphp
            <div class="deduction-table" style="border-color: {{ $deductionColor }};">
                <div class="table-header" style="background-color: {{ $deductionColor }};">รายการหัก</div>
                <div class="table-body">
                    @forelse($deductionItems as $item)
                    <div class="table-row">
                        <span>{{ is_array($item) ? $item['label'] : $item->label }}</span>
                        <span>{{ number_format(is_array($item) ? $item['amount'] : $item->amount, 2) }}</span>
                    </div>
                    @empty
                    <div class="table-row" style="color: #999;">
                        <span>ไม่มีรายการ</span>
                    </div>
                    @endforelse
                    <div class="table-row total" style="color: {{ $deductionColor }};">
                        <span>รวมรายการหัก</span>
                        <span>{{ number_format($totalDeduction, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Pay Box -->
        <div class="net-pay-box" style="border-color: {{ $primaryColor }};">
            <div class="label">รายได้สุทธิ (ที่จ่ายจริง)</div>
            <div class="amount" style="color: {{ $primaryColor }};">{{ number_format($netPay, 2) }}</div>
        </div>

        <!-- Summary Boxes -->
        <div class="summary-boxes">
            <div class="summary-box income">
                <label>สะสมเงินได้</label>
                <div class="amount">{{ number_format($yearToDate['total_income'], 2) }}</div>
            </div>
            <div class="summary-box deduction">
                <label>สะสมรายการหัก</label>
                <div class="amount">{{ number_format($yearToDate['total_deduction'], 2) }}</div>
            </div>
            <div class="summary-box net">
                <label>สะสมสุทธิ</label>
                <div class="amount">{{ number_format($yearToDate['net_pay'], 2) }}</div>
            </div>
        </div>

        <!-- Signatures -->
        <div class="signatures">
            <div class="signature-box">
                @if($payslip && $payslip->status === 'finalized' && $company?->signature_approver_image_path)
                    <div style="height: 40px; margin-bottom: 8px; display: flex; justify-content: center; align-items: flex-end;">
                        <img src="{{ asset('storage/' . $company->signature_approver_image_path) }}" 
                             alt="ลายเซ็นผู้จ่าย" 
                             style="max-height: 100%; width: auto;" />
                    </div>
                @else
                    <div style="height: 40px; margin-bottom: 8px;"></div>
                @endif
                <div class="signature-line" style="width: 60%; margin: 0 auto; border-top: 1px solid #aaa;"></div>
                <div class="signature-label" style="margin-top: 4px;">
                    ลายเซ็นผู้จ่าย
                    @if($company?->signature_approver_name)
                    <br /><span style="font-size: 10px; color: #555;">({{ $company->signature_approver_name }})</span>
                    @endif
                </div>
            </div>
            <div class="signature-box">
                @if($payslip && $payslip->status === 'finalized' && $company?->signature_receiver_image_path)
                    <div style="height: 40px; margin-bottom: 8px; display: flex; justify-content: center; align-items: flex-end;">
                        <img src="{{ asset('storage/' . $company->signature_receiver_image_path) }}" 
                             alt="ลายเซ็นผู้รับ" 
                             style="max-height: 100%; width: auto;" />
                    </div>
                @else
                    <div style="height: 40px; margin-bottom: 8px;"></div>
                @endif
                <div class="signature-line" style="width: 60%; margin: 0 auto; border-top: 1px solid #aaa;"></div>
                <div class="signature-label" style="margin-top: 4px;">
                    ลายเซ็นผู้รับ
                    @if($company?->signature_receiver_name)
                    <br /><span style="font-size: 10px; color: #555;">({{ $company->signature_receiver_name }})</span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="payslip-footer">
            {{ $company?->payslip_footer_text ?? 'เอกสารฉบับนี้เป็นของผู้มีรายชื่อข้างบนเท่านั้น ไม่สามารถเผยแพร่ให้กับผู้อื่นได้' }}
        </div>

    </div>
</div>

<!-- Rate Table & Payment Proofs (print-hide, below slip) -->
<div class="max-w-5xl mx-auto print-hide">
    <div class="grid grid-cols-1 {{ ($layerRates ?? collect())->count() > 0 ? 'md:grid-cols-2' : '' }} gap-6">
        @if(($layerRates ?? collect())->count() > 0)
        <!-- Layer Rate Reference -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 bg-indigo-50 border-b border-indigo-100">
                <h3 class="font-bold text-sm text-indigo-800 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V7.414A2 2 0 0015.414 6L12 2.586A2 2 0 0010.586 2H6zm2 10a1 1 0 10-2 0v3a1 1 0 102 0v-3zm2-3a1 1 0 011 1v5a1 1 0 11-2 0v-5a1 1 0 011-1zm4-1a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd" /></svg>
                    เรทต่อนาที (Layer Rate)
                </h3>
            </div>
            <div class="p-4">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-gray-500">
                            <th class="text-left px-3 py-2 font-medium">เลเยอร์</th>
                            <th class="text-right px-3 py-2 font-medium">บาท/นาที</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($layerRates as $lr)
                        <tr class="border-t border-gray-100 hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-700 font-medium">L{{ $lr->layer_from }}-{{ $lr->layer_to }}</td>
                            <td class="text-right px-3 py-2 font-bold text-indigo-600">{{ number_format($lr->rate_per_minute, 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <!-- Payment Proofs -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold text-sm text-gray-700 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    หลักฐานการโอนเงิน
                </h3>
                <span class="text-[10px] px-1.5 py-0.5 bg-gray-200 text-gray-600 rounded font-bold">{{ ($proofs ?? collect())->count() }}</span>
            </div>
            <div class="p-4">
                @if(($proofs ?? collect())->count() > 0)
                <div class="space-y-2 mb-4">
                    @foreach($proofs as $proof)
                    <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg group border border-transparent hover:border-indigo-100 transition-all">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <div class="w-8 h-8 rounded bg-indigo-100 flex items-center justify-center text-indigo-600 flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-sm font-medium text-gray-700 truncate">{{ $proof->original_filename }}</p>
                                <p class="text-xs text-gray-400">{{ $proof->created_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                        <a href="{{ asset('storage/' . $proof->file_path) }}" target="_blank" class="text-xs text-indigo-600 hover:text-indigo-800 font-bold">ดูรูป</a>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                    <p class="text-xs text-gray-400 italic">ยังไม่มีหลักฐานการโอนเงินในเดือนนี้</p>
                </div>
                @endif

                <!-- Upload form -->
                <form action="{{ route('workspace.proof.upload', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" method="POST" enctype="multipart/form-data" class="mt-4">
                    @csrf
                    <div class="relative">
                        <input type="file" name="proof" id="proof-upload-slip" class="hidden" onchange="this.form.submit()">
                        <label for="proof-upload-slip" class="flex items-center justify-center gap-2 w-full py-2.5 border-2 border-dashed border-gray-200 rounded-lg text-xs font-bold text-gray-500 hover:border-indigo-400 hover:bg-indigo-50 hover:text-indigo-600 cursor-pointer transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                            อัปโหลดสลิปการโอน
                        </label>
                    </div>
                    <p class="text-[10px] text-gray-400 text-center mt-1">รองรับไฟล์ภาพ JPG, PNG (ไม่เกิน 2MB)</p>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
