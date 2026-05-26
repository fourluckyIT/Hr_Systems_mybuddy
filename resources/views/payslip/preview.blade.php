@extends('layouts.app')
@section('title', 'Payslip - ' . $employee->display_name)

@push('styles')
<style>
    body.th-font {
        font-family: 'Sarabun', 'Noto Sans Thai', sans-serif !important;
    }

    @page {
        size: A4 landscape;
        margin: 5mm;
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
            zoom: 0.9;
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
            padding: 10px 15px !important;
            margin: 0 !important;
            border: none !important;
            box-shadow: none !important;
        }
    }

    .payslip-container {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 24px 28px;
        font-size: 13px;
        line-height: 1.45;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    /* ─── Zoho-style header ─────────────────────────────── */
    .zoho-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding-bottom: 14px;
        margin-bottom: 18px;
        border-bottom: 2px solid;
    }
    .zoho-header .brand h1 {
        font-size: 22px;
        font-weight: 800;
        margin: 0;
        letter-spacing: -0.3px;
    }
    .zoho-header .brand .subline {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    .zoho-header .caption {
        text-align: right;
    }
    .zoho-header .caption-line {
        font-size: 11px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }
    .zoho-header .caption-month {
        font-size: 16px;
        font-weight: 700;
        color: #111827;
        margin-top: 2px;
    }

    /* ─── Employee block + Net Pay card ────────────────── */
    .employee-and-net {
        display: grid;
        grid-template-columns: 1fr 280px;
        gap: 24px;
        margin-bottom: 16px;
    }
    .block-title {
        font-size: 10px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        margin-bottom: 8px;
    }
    .kv {
        display: grid;
        grid-template-columns: 100px 12px 1fr;
        column-gap: 4px;
        align-items: baseline;
        padding: 3px 0;
        font-size: 12px;
    }
    .kv > span:first-child { color: #6b7280; }
    .kv > b { color: #9ca3af; font-weight: normal; }
    .kv > value { color: #1f2937; font-weight: 600; }
    .kv.inline { display: inline-grid; grid-template-columns: auto 12px auto; margin-right: 24px; }
    .netpay-card {
        border: 1.5px solid;
        border-radius: 10px;
        padding: 14px 18px;
        background: rgba(79, 70, 229, 0.04);
    }
    .netpay-amount {
        font-size: 26px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.5px;
        line-height: 1.1;
    }
    .netpay-label {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    .netpay-divider {
        border-top: 1px dashed #d1d5db;
        margin: 10px 0;
    }
    .netpay-stat {
        display: grid;
        grid-template-columns: 1fr 12px auto;
        column-gap: 4px;
        align-items: baseline;
        font-size: 11.5px;
        padding: 2px 0;
    }
    .netpay-stat > span:first-child { color: #6b7280; }
    .netpay-stat > b { color: #9ca3af; font-weight: normal; }
    .netpay-stat > value { color: #1f2937; font-weight: 700; }

    /* ─── Bank strip ───────────────────────────────────── */
    .bank-strip {
        padding: 10px 0;
        border-top: 1px dashed #e5e7eb;
        border-bottom: 1px dashed #e5e7eb;
        margin-bottom: 16px;
        font-size: 11.5px;
    }

    /* ─── Earnings / Deductions side-by-side tables ─── */
    .zoho-tables {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 14px;
    }
    .ztable {
        background: #fff;
    }
    .ztable-head {
        display: grid;
        grid-template-columns: 1fr 100px 100px;
        column-gap: 12px;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1.5px solid #d1d5db;
        font-size: 10.5px;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .ztable-head .col-amt,
    .ztable-head .col-ytd { text-align: right; }
    .ztable-head .col-amt { padding-right: 10px; }
    .ztable-row {
        display: grid;
        grid-template-columns: 1fr 100px 100px;
        column-gap: 12px;
        align-items: baseline;
        padding: 8px 0;
        border-bottom: 1px dashed #f0f0f0;
        font-size: 12px;
    }
    .ztable-row .col-label { color: #1f2937; }
    .ztable-row .col-amt {
        text-align: right;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: #111827;
        padding-right: 10px;
    }
    .ztable-row .col-ytd {
        text-align: right;
        color: #6b7280;
        font-variant-numeric: tabular-nums;
    }
    .ztable-row.is-zero .col-label,
    .ztable-row.is-zero .col-amt,
    .ztable-row.is-zero .col-ytd { color: #c0c4cc; font-weight: 400; }
    .ztable-subtotal {
        display: grid;
        grid-template-columns: 1fr 100px 100px;
        column-gap: 12px;
        align-items: baseline;
        padding: 10px 0 4px;
        border-top: 1px solid #e5e7eb;
        font-size: 12.5px;
        font-weight: 800;
    }
    .ztable-subtotal .col-amt,
    .ztable-subtotal .col-ytd {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }
    .ztable-subtotal .col-amt { padding-right: 10px; }
    .legal-tag {
        display: inline-block;
        margin-left: 4px;
        padding: 1px 5px;
        background: #f3f4f6;
        color: #9ca3af;
        font-size: 9px;
        border-radius: 3px;
        font-weight: 600;
    }

    /* ─── Total payable bar + amount in words ─── */
    .total-bar {
        display: flex;
        justify-content: space-between;
        align-items: stretch;
        border: 1.5px solid;
        border-radius: 8px;
        padding: 0;
        overflow: hidden;
        margin-bottom: 8px;
    }
    .total-bar-left {
        padding: 12px 16px;
        flex: 1;
    }
    .total-bar-title {
        font-size: 11px;
        font-weight: 700;
        color: #1f2937;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .total-bar-formula {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    .total-bar-amount {
        padding: 14px 20px;
        font-size: 18px;
        font-weight: 800;
        font-variant-numeric: tabular-nums;
        display: flex;
        align-items: center;
        min-width: 180px;
        justify-content: flex-end;
    }
    .amount-in-words {
        text-align: right;
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 18px;
    }
    .amount-in-words .aiw-text {
        color: #1f2937;
        font-weight: 600;
        margin-left: 4px;
    }

    .payslip-header {
        text-align: left;
        margin-bottom: 14px;
        padding-bottom: 12px;
        border-bottom: 2px solid;
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        gap: 16px;
    }

    .payslip-header .brand h1 {
        font-size: 17px;
        font-weight: 800;
        margin: 0;
        padding: 0;
        letter-spacing: -0.2px;
    }

    .payslip-header .tagline {
        font-size: 11px;
        color: #6b7280;
        margin: 2px 0 0 0;
    }

    .payslip-header .descriptor {
        font-size: 10px;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 1px;
        white-space: nowrap;
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
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }

    .table-header {
        background-color: #4f46e5;
        color: white;
        padding: 9px 14px;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.2px;
    }

    .table-body {
        padding: 6px 14px 10px;
        background: #fff;
    }

    .table-row {
        display: grid;
        grid-template-columns: 1fr auto;
        column-gap: 16px;
        align-items: baseline;
        padding: 7px 0;
        border-bottom: 1px dashed #f0f0f0;
        font-size: 11.5px;
    }

    .table-row:last-child:not(.total) {
        border-bottom: none;
    }

    .table-row.total {
        border-top: 1.5px solid #e5e7eb;
        margin-top: 4px;
        padding-top: 9px;
        font-weight: 800;
        border-bottom: none;
        font-size: 12.5px;
    }

    .table-row > span:first-child { color: #374151; }
    .table-row > span:last-child {
        text-align: right;
        min-width: 70px;
        font-variant-numeric: tabular-nums;
        font-weight: 600;
        color: #111827;
    }
    .table-row.is-zero > span { color: #c0c4cc; font-weight: 400; }

    .net-pay-box {
        border: 2px solid #4f46e5;
        background: linear-gradient(135deg, rgba(79, 70, 229, 0.06), rgba(79, 70, 229, 0.02));
        padding: 14px 20px;
        margin-bottom: 14px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 10px;
        box-shadow: 0 2px 6px rgba(79, 70, 229, 0.08);
    }

    .net-pay-box .label {
        font-size: 14px;
        font-weight: 700;
        color: #1f2937;
    }

    .net-pay-box .amount {
        font-size: 22px;
        font-weight: 800;
        color: #4f46e5;
        font-variant-numeric: tabular-nums;
        letter-spacing: -0.3px;
    }

    .summary-boxes {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }

    .summary-box {
        border: 1px solid;
        padding: 12px;
        text-align: center;
        font-size: 10px;
        border-radius: 8px;
        background: #fff;
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
        font-weight: 800;
        font-size: 14px;
        color: #333;
        font-variant-numeric: tabular-nums;
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
        <form method="POST" action="{{ route('payslip.finalize', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" class="flex items-center gap-2">
            @csrf
            <div class="flex flex-col items-end">
                <span class="text-[9px] text-gray-400 font-bold uppercase mr-1">วันจ่ายเงิน</span>
                <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" 
                       class="border border-gray-300 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-green-500 focus:border-green-500 outline-none">
            </div>
            <button type="submit" class="bg-green-600 text-white px-4 py-2.5 rounded-lg text-sm font-bold hover:bg-green-700 shadow-sm transition-all flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                Finalize
            </button>
        </form>
        @endif
        <a href="{{ route('payslip.pdf', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}"
           class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color: {{ $primaryColor }}">Export PDF</a>
        <button type="button" onclick="window.print()"
              class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-gray-800">Print A5</button>
    </div>
</div>

@if($payslip && $payslip->status === 'finalized')
<div class="print-hide bg-white border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm mb-4 flex justify-between items-center shadow-sm">
    <div class="flex items-center gap-2">
        <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
        <span class="font-bold">Finalized เมื่อ: {{ $payslip->finalized_at?->format('d/m/Y H:i') }}</span>
    </div>
    @if($canManagePayslip)
    <div class="flex items-center gap-3">
        <!-- Update Payment Date Form -->
        <form method="POST" action="{{ route('payslip.update-payment-date', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" 
              class="flex items-center gap-2 pr-3 border-r border-green-100">
            @csrf
            <span class="text-[10px] text-green-600 font-bold whitespace-nowrap">แก้ตัววันจ่ายเงิน:</span>
            <input type="date" name="payment_date" value="{{ $payslip->payment_date }}" 
                   class="border border-green-200 rounded-lg px-2 py-1 text-xs text-green-700 focus:ring-2 focus:ring-green-500 outline-none">
            <button type="submit" class="p-1.5 bg-green-100 text-green-700 hover:bg-green-200 rounded-lg transition-all" title="บันทึกวันจ่ายเงิน">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </button>
        </form>

        <form method="POST" action="{{ route('payslip.unfinalize', ['employee' => $employee->id, 'month' => $month, 'year' => $year]) }}" 
              onsubmit="return confirm('คุณแน่ใจหรือว่าต้องการยกเลิก Finalize?\n\nสลิปนี้จะถูกเปลี่ยนสถานะเป็น Draft และข้อมูลจะกลับไปคำนวณสดเพื่อแก้ไขได้ครับ');">
            @csrf
            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-600 hover:bg-red-100 rounded-lg text-xs font-bold transition-all border border-red-100 flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                ยกเลิก Finalize
            </button>
        </form>
    </div>
    @endif
</div>
@else
<div class="print-hide bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm mb-4 flex items-center gap-2 font-medium">
    <span class="w-2 h-2 bg-amber-500 rounded-full"></span>
    Draft - ยังไม่ได้ Finalize (ข้อมูลคำนวณสด)
</div>
@endif


<!-- Payslip Container -->
<div class="max-w-5xl mx-auto mb-6 overflow-x-auto pb-4">
    <div class="payslip-container" style="min-width: 800px;">

        @php
            $perLineYtd = $yearToDate['per_line'] ?? [];
            $payDateStr = $payslip && $payslip->payment_date
                ? \Carbon\Carbon::parse($payslip->payment_date)->format('d/m/') . (\Carbon\Carbon::parse($payslip->payment_date)->year + 543)
                : \Carbon\Carbon::create($year, $month)->endOfMonth()->format('d/m/') . ($year + 543);
            $deductionColor = '#ef4444';
        @endphp

        <!-- Header: brand left, payslip caption right -->
        <div class="zoho-header" style="border-color: {{ $primaryColor }};">
            <div class="brand">
                <h1 style="color: {{ $primaryColor }}">{{ $company?->name ?? 'Pro One IT Co., Ltd.' }}</h1>
                @if($company?->payslip_header_subtitle)
                    <div class="subline">{{ $company->payslip_header_subtitle }}</div>
                @elseif($company?->tagline)
                    <div class="subline">{{ $company->tagline }}</div>
                @endif
            </div>
            <div class="caption">
                <div class="caption-line">สลิปเงินเดือน</div>
                <div class="caption-month">{{ $monthNames[$month] }} {{ $year + 543 }}</div>
            </div>
        </div>

        <!-- Employee Summary (left) + Net Pay Card (right) -->
        <div class="employee-and-net">
            <div class="employee-block">
                <div class="block-title">ข้อมูลพนักงาน</div>
                <div class="kv"><span>ชื่อพนักงาน</span><b>:</b><value>{{ $employee->full_name }}</value></div>
                <div class="kv"><span>ตำแหน่ง</span><b>:</b><value>{{ $employee->position?->name ?? '—' }}</value></div>
                <div class="kv"><span>รหัสพนักงาน</span><b>:</b><value>{{ $employee->employee_code ?? '—' }}</value></div>
                @if($employee->start_date)
                    <div class="kv"><span>วันเริ่มงาน</span><b>:</b><value>{{ $employee->start_date->format('d/m/') . ($employee->start_date->year + 543) }}</value></div>
                @endif
                <div class="kv"><span>ประจำเดือน</span><b>:</b><value>{{ $monthNames[$month] }} {{ $year + 543 }}</value></div>
                <div class="kv"><span>วันจ่ายเงิน</span><b>:</b><value>{{ $payDateStr }}</value></div>
            </div>
            <div class="netpay-card" style="border-color: {{ $primaryColor }};">
                <div class="netpay-amount" style="color: {{ $primaryColor }};">฿{{ number_format($netPay, 2) }}</div>
                <div class="netpay-label">รายได้สุทธิ (Net Pay)</div>
                <div class="netpay-divider"></div>
                <div class="netpay-stat"><span>วันที่จ่าย</span><b>:</b><value>{{ $monthlyStats['paid_days'] ?? 0 }}</value></div>
                <div class="netpay-stat"><span>วันลา/ขาด (LOP)</span><b>:</b><value>{{ $monthlyStats['lwop_days'] ?? 0 }}</value></div>
            </div>
        </div>

        <!-- Bank info strip -->
        @if($employee->bankAccount)
            <div class="bank-strip">
                <div class="kv inline"><span>ธนาคาร</span><b>:</b><value>{{ $employee->bankAccount->bank_name ?? '—' }}</value></div>
                <div class="kv inline"><span>เลขที่บัญชี</span><b>:</b><value>{{ $employee->bankAccount->account_number ?? '—' }}</value></div>
                @if($company?->tax_id)
                    <div class="kv inline"><span>เลขผู้เสียภาษี (บริษัท)</span><b>:</b><value>{{ $company->tax_id }}</value></div>
                @endif
            </div>
        @endif

        <!-- Earnings / Deductions: side by side, each with AMOUNT + YTD columns -->
        <div class="zoho-tables">
            <div class="ztable">
                <div class="ztable-head ztable-head-income">
                    <span class="col-label">รายการได้ (EARNINGS)</span>
                    <span class="col-amt">จำนวน</span>
                    <span class="col-ytd">YTD</span>
                </div>
                @forelse($incomeItems as $item)
                    @php
                        $itemLabel = is_array($item) ? $item['label'] : $item->label;
                        $itemAmount = (float) (is_array($item) ? $item['amount'] : $item->amount);
                        if ($itemLabel === 'ค่าทำงานวันหยุด' && $itemAmount == 0) continue;
                        $ytd = (float) ($perLineYtd[$itemLabel] ?? 0);
                    @endphp
                    <div class="ztable-row {{ $itemAmount == 0 ? 'is-zero' : '' }}">
                        <span class="col-label">
                            {{ $itemLabel }}
                            @if($itemLabel === 'ค่าทำงานวันหยุด')
                                <span class="legal-tag" title="พรบ.คุ้มครองแรงงาน 2541 ม.62 — พนักงานรายเดือนทำงานในวันหยุดชั่วโมงปกติ ได้รับเพิ่ม 1× ของอัตรา/ชม.">ม.62</span>
                            @endif
                        </span>
                        <span class="col-amt">{{ number_format($itemAmount, 2) }}</span>
                        <span class="col-ytd">{{ $ytd > 0 ? number_format($ytd, 2) : '—' }}</span>
                    </div>
                @empty
                    <div class="ztable-row is-zero"><span class="col-label">ไม่มีรายการ</span><span class="col-amt">—</span><span class="col-ytd">—</span></div>
                @endforelse
                <div class="ztable-subtotal" style="color: {{ $primaryColor }};">
                    <span class="col-label">รวมเงินได้</span>
                    <span class="col-amt">{{ number_format($totalIncome, 2) }}</span>
                    <span class="col-ytd">{{ number_format($yearToDate['total_income'], 2) }}</span>
                </div>
            </div>

            <div class="ztable">
                <div class="ztable-head ztable-head-deduction">
                    <span class="col-label">รายการหัก (DEDUCTIONS)</span>
                    <span class="col-amt">จำนวน</span>
                    <span class="col-ytd">YTD</span>
                </div>
                @forelse($deductionItems as $item)
                    @php
                        $dLabel  = is_array($item) ? $item['label']  : $item->label;
                        $dAmount = (float) (is_array($item) ? $item['amount'] : $item->amount);
                        $dNote   = is_array($item) ? ($item['note'] ?? null) : ($item->note ?? null);
                        $dYtd    = (float) ($perLineYtd[$dLabel] ?? 0);
                    @endphp
                    <div class="ztable-row {{ $dAmount == 0 ? 'is-zero' : '' }}"
                         @if($dNote) title="{{ $dNote }}" style="cursor: help;" @endif>
                        <span class="col-label">{{ $dLabel }}</span>
                        <span class="col-amt">{{ number_format($dAmount, 2) }}</span>
                        <span class="col-ytd">{{ $dYtd > 0 ? number_format($dYtd, 2) : '—' }}</span>
                    </div>
                @empty
                    <div class="ztable-row is-zero"><span class="col-label">ไม่มีรายการ</span><span class="col-amt">—</span><span class="col-ytd">—</span></div>
                @endforelse
                <div class="ztable-subtotal" style="color: {{ $deductionColor }};">
                    <span class="col-label">รวมรายการหัก</span>
                    <span class="col-amt">{{ number_format($totalDeduction, 2) }}</span>
                    <span class="col-ytd">{{ number_format($yearToDate['total_deduction'], 2) }}</span>
                </div>
            </div>
        </div>

        <!-- TOTAL NET PAYABLE bar with formula -->
        <div class="total-bar" style="border-color: {{ $primaryColor }};">
            <div class="total-bar-left">
                <div class="total-bar-title">รายได้สุทธิที่ต้องจ่าย</div>
                <div class="total-bar-formula">รวมเงินได้ − รวมรายการหัก</div>
            </div>
            <div class="total-bar-amount" style="background: rgba(79,70,229,0.08); color: {{ $primaryColor }};">
                ฿{{ number_format($netPay, 2) }}
            </div>
        </div>

        <!-- Amount in Thai words -->
        <div class="amount-in-words">
            <span class="aiw-label">จำนวนเงิน (ตัวอักษร):</span>
            <span class="aiw-text">{{ \App\Support\ThaiBaht::inWords($netPay) }}</span>
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
                    <br /><span style="font-size: 10px; color: #555;">({{ $employee->full_name }})</span>
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
