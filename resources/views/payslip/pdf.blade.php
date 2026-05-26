<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 landscape; margin: 10px 20px; }
        body { font-family: 'thsarabun', sans-serif; font-size: 13px; color: #111; line-height: 1; }
        table { width: 100%; border-collapse: collapse; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .text-gray { color: #6b7280; }
        
        /* Zoho Header */
        .header-table { border-bottom: 2px solid; margin-bottom: 5px; padding-bottom: 2px; }
        .brand-name { font-size: 20px; font-weight: bold; margin: 0; }
        .brand-sub { font-size: 11px; color: #6b7280; margin-top: 1px; }
        .slip-title { font-size: 11px; color: #6b7280; text-align: right; letter-spacing: 0.5px; }
        .slip-month { font-size: 14px; font-weight: bold; text-align: right; color: #111827; }

        /* Employee Info & Net Pay */
        .top-section { margin-bottom: 5px; }
        .info-table td { padding: 1px 0; font-size: 12px; vertical-align: top; }
        .info-label { width: 85px; color: #6b7280; }
        .info-colon { width: 10px; color: #6b7280; }
        .info-val { font-weight: bold; color: #1f2937; }
        
        .netpay-box { border: 1.5px solid; border-radius: 6px; padding: 6px 10px; background-color: #f8fafc; }
        .netpay-amount { font-size: 22px; font-weight: bold; margin: 0; }
        .netpay-label { font-size: 11px; color: #6b7280; margin-top: 1px; }
        .netpay-divider { border-top: 1px dashed #d1d5db; margin: 2px 0; }
        .netpay-detail-table td { font-size: 10px; padding: 0; }
        
        .bank-strip { margin-top: 5px; margin-bottom: 5px; padding: 4px 0; border-top: 1px dashed #e5e7eb; border-bottom: 1px dashed #e5e7eb; font-size: 12px; }

        /* Earnings / Deductions Tables */
        .ztable th { padding: 2px 0; border-bottom: 1.5px solid #d1d5db; font-size: 10px; color: #6b7280; font-weight: bold; text-align: left; }
        .ztable th.num { text-align: right; width: 65px; }
        .ztable td { padding: 2px 0; border-bottom: 1px dashed #f0f0f0; font-size: 12px; vertical-align: top; }
        .ztable td.num { text-align: right; font-weight: bold; }
        .ztable td.ytd { text-align: right; color: #6b7280; }
        .ztable .is-zero td { color: #c0c4cc; font-weight: normal; }
        .ztable .subtotal-row td { padding: 4px 0 0; border-bottom: none; border-top: 1px solid #e5e7eb; font-weight: bold; font-size: 13px; }
        .ztable .subtotal-row td.red { color: #ef4444; }

        /* Total Bar */
        .total-wrapper { margin-top: 5px; border: 1.5px solid; border-radius: 6px; }
        .total-left { padding: 4px 10px; width: 60%; }
        .total-title { font-size: 11px; font-weight: bold; }
        .total-subtitle { font-size: 10px; color: #6b7280; }
        .total-right { padding: 4px 10px; width: 40%; text-align: right; font-size: 16px; font-weight: bold; }
        
        .amount-words { text-align: right; font-size: 11px; color: #6b7280; margin-top: 2px; margin-bottom: 5px; }
        .amount-words b { color: #1f2937; margin-left: 5px; }

        /* Signatures */
        .signatures td { text-align: center; font-size: 11px; padding-top: 0; width: 50%; }
        .sig-line { display: inline-block; width: 140px; border-top: 1px solid #333; margin-bottom: 2px; }
        
        .disclaimer { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 5px; }
    </style>
</head>
<body>
    @php
        $primaryColor = !empty($company) && $company->primary_color ? $company->primary_color : '#4f46e5';
        $payDateStr = $payslip && $payslip->payment_date
            ? \Carbon\Carbon::parse($payslip->payment_date)->format('d/m/') . (\Carbon\Carbon::parse($payslip->payment_date)->year + 543)
            : \Carbon\Carbon::create($year, $month)->endOfMonth()->format('d/m/') . ($year + 543);
    @endphp

    <table class="header-table" style="border-bottom-color: {{ $primaryColor }}">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="brand-name" style="color: {{ $primaryColor }}">{{ !empty($company) && !empty($company->name) ? $company->name : 'บริษัท' }}</div>
                @if(!empty($company) && $company->payslip_header_subtitle)
                    <div class="brand-sub">{{ $company->payslip_header_subtitle }}</div>
                @elseif(!empty($company) && $company->tagline)
                    <div class="brand-sub">{{ $company->tagline }}</div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="slip-title">สลิปเงินเดือน</div>
                <div class="slip-month">{{ $monthName }} {{ $year + 543 }}</div>
            </td>
        </tr>
    </table>

    <table class="top-section">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <table class="info-table">
                    <tr><td colspan="3" style="font-size:11px; color:#6b7280; font-weight:bold; padding-bottom:5px; text-transform:uppercase;">ข้อมูลพนักงาน</td></tr>
                    <tr><td class="info-label">ชื่อพนักงาน</td><td class="info-colon">:</td><td class="info-val">{{ $employee->full_name }}</td></tr>
                    <tr><td class="info-label">ตำแหน่ง</td><td class="info-colon">:</td><td class="info-val">{{ $employee->position?->name ?? '—' }}</td></tr>
                    <tr><td class="info-label">รหัสพนักงาน</td><td class="info-colon">:</td><td class="info-val">{{ $employee->employee_code ?? '—' }}</td></tr>
                    @if($employee->start_date)
                    <tr><td class="info-label">วันเริ่มงาน</td><td class="info-colon">:</td><td class="info-val">{{ $employee->start_date->format('d/m/') . ($employee->start_date->year + 543) }}</td></tr>
                    @endif
                    <tr><td class="info-label">ประจำเดือน</td><td class="info-colon">:</td><td class="info-val">{{ $monthName }} {{ $year + 543 }}</td></tr>
                    <tr><td class="info-label">วันจ่ายเงิน</td><td class="info-colon">:</td><td class="info-val">{{ $payDateStr }}</td></tr>
                </table>
            </td>
            <td style="width: 45%; vertical-align: top; padding-left: 20px;">
                <div class="netpay-box" style="border-color: {{ $primaryColor }}">
                    <div class="netpay-amount" style="color: {{ $primaryColor }}">฿{{ number_format($payslip->net_pay, 2) }}</div>
                    <div class="netpay-label">รายได้สุทธิ (Net Pay)</div>
                    <div class="netpay-divider"></div>
                    <table class="netpay-detail-table">
                        <tr>
                            <td class="info-label" style="width:85px;">วันที่จ่าย</td>
                            <td class="info-colon" style="width:10px;">:</td>
                            <td class="info-val text-right">{{ $monthlyStats['paid_days'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td class="info-label">วันลา/ขาด (LOP)</td>
                            <td class="info-colon">:</td>
                            <td class="info-val text-right">{{ $monthlyStats['lwop_days'] ?? 0 }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="bank-strip">
        <span class="info-label">ธนาคาร :</span> <span class="info-val" style="margin-right:20px;">{{ $employee->bankAccount?->bank_name ?? '—' }}</span>
        <span class="info-label">เลขที่บัญชี :</span> <span class="info-val">{{ $employee->bankAccount?->account_number ?? '—' }}</span>
    </div>

    <table class="main-tables" style="margin-bottom: 10px;">
        <tr>
            <td style="width: 48%; vertical-align: top;">
                <table class="ztable">
                    <tr>
                        <th>รายการได้ (EARNINGS)</th>
                        <th class="num">จำนวน</th>
                        <th class="num">YTD</th>
                    </tr>
                    @foreach($payslip->incomeItems as $item)
                        @php $ytdAmt = $yearToDate['per_line'][$item->label] ?? $item->amount; @endphp
                        @if($item->label === 'ค่าทำงานวันหยุด' && (float) $item->amount == 0 && (float) $ytdAmt == 0) @continue @endif
                    <tr class="{{ (float)$item->amount == 0 ? 'is-zero' : '' }}">
                        <td>
                            {{ $item->label }}
                            @if($item->label === 'ค่าทำงานวันหยุด')
                                <span style="font-size:9px; color:#9ca3af; background:#f3f4f6; padding:1px 3px; border-radius:3px; margin-left:3px;">ม.62</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($item->amount, 2) }}</td>
                        <td class="ytd">{{ number_format($ytdAmt, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td style="color: {{ $primaryColor }}">รวมเงินได้</td>
                        <td class="num" style="color: {{ $primaryColor }}">{{ number_format($payslip->total_income, 2) }}</td>
                        <td class="ytd" style="color: {{ $primaryColor }}">{{ number_format($yearToDate['total_income'], 2) }}</td>
                    </tr>
                </table>
            </td>
            <td style="width: 4%;"></td>
            <td style="width: 48%; vertical-align: top;">
                <table class="ztable">
                    <tr>
                        <th>รายการหัก (DEDUCTIONS)</th>
                        <th class="num">จำนวน</th>
                        <th class="num">YTD</th>
                    </tr>
                    @foreach($payslip->deductionItems as $item)
                        @php $ytdAmt = $yearToDate['per_line'][$item->label] ?? $item->amount; @endphp
                    <tr class="{{ (float)$item->amount == 0 ? 'is-zero' : '' }}">
                        <td>{{ $item->label }}</td>
                        <td class="num">{{ number_format($item->amount, 2) }}</td>
                        <td class="ytd">{{ number_format($ytdAmt, 2) }}</td>
                    </tr>
                    @endforeach
                    <tr class="subtotal-row">
                        <td class="red">รวมรายการหัก</td>
                        <td class="num red">{{ number_format($payslip->total_deduction, 2) }}</td>
                        <td class="ytd red">{{ number_format($yearToDate['total_deduction'], 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="total-wrapper" style="border-color: {{ $primaryColor }}">
        <tr>
            <td class="total-left">
                <div class="total-title">รายได้สุทธิที่ต้องจ่าย</div>
                <div class="total-subtitle">รวมเงินได้ − รวมรายการหัก</div>
            </td>
            <td class="total-right" style="color: {{ $primaryColor }}; background-color: rgba(79, 70, 229, 0.08);">
                ฿{{ number_format($payslip->net_pay, 2) }}
            </td>
        </tr>
    </table>

    <div class="amount-words">
        จำนวนเงิน (ตัวอักษร): <b>{{ \App\Support\ThaiBaht::inWords($payslip->net_pay) }}</b>
    </div>

    <table class="signatures">
        <tr>
            <td>
                @if($payslip->status === 'finalized' && !empty($company) && !empty($company->signature_approver_image_path) && file_exists(storage_path('app/public/' . $company->signature_approver_image_path)))
                    <div style="height: 40px; margin-bottom: 5px;">
                        <img src="{{ storage_path('app/public/' . $company->signature_approver_image_path) }}" style="max-height: 40px;">
                    </div>
                @else
                    <div style="height: 45px;"></div>
                @endif
                <div class="sig-line"></div><br>
                ลายเซ็นผู้จ่าย<br>
                <span class="text-gray">{{ !empty($company) && !empty($company->signature_approver_name) ? "({$company->signature_approver_name})" : '(............................................)' }}</span>
            </td>
            <td>
                @if($payslip->status === 'finalized' && !empty($company) && !empty($company->signature_receiver_image_path) && file_exists(storage_path('app/public/' . $company->signature_receiver_image_path)))
                    <div style="height: 40px; margin-bottom: 5px;">
                        <img src="{{ storage_path('app/public/' . $company->signature_receiver_image_path) }}" style="max-height: 40px;">
                    </div>
                @else
                    <div style="height: 45px;"></div>
                @endif
                <div class="sig-line"></div><br>
                ลายเซ็นผู้รับ<br>
                <span class="text-gray">({{ $employee->full_name }})</span>
            </td>
        </tr>
    </table>

    <div class="disclaimer">เอกสารฉบับนี้เป็นของผู้มีรายชื่อข้างบนเท่านั้น ไม่สามารถเผยแพร่ให้กับผู้อื่นได้</div>
</body>
</html>
