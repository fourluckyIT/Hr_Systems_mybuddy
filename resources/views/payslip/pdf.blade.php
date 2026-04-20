<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @font-face {
            font-family: 'NotoSansThai';
            font-style: normal;
            font-weight: 400;
            src: url('{{ storage_path("fonts/NotoSansThai-Regular.ttf") }}') format('truetype');
        }
        @font-face {
            font-family: 'NotoSansThai';
            font-style: normal;
            font-weight: 700;
            src: url('{{ storage_path("fonts/NotoSansThai-Bold.ttf") }}') format('truetype');
        }
        body { font-family: 'NotoSansThai', sans-serif; font-size: 14px; margin: 30px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 18px; }
        .header p { margin: 2px 0; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 2px 8px; font-size: 13px; }
        .info-label { color: #666; width: 100px; }
        .payslip-table { margin-top: 15px; }
        .payslip-table th { background: #f3f4f6; padding: 6px 8px; text-align: left; font-size: 12px; border: 1px solid #e5e7eb; }
        .payslip-table td { padding: 4px 8px; font-size: 13px; border: 1px solid #e5e7eb; }
        .metrics-table { margin-top: 8px; margin-bottom: 10px; }
        .metrics-table td { border: 1px solid #e5e7eb; padding: 4px 6px; }
        .metric-label { font-size: 11px; color: #6b7280; }
        .metric-value { font-size: 12px; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-row { font-weight: bold; background: #f0fdf4; }
        .deduction-total { font-weight: bold; background: #fef2f2; }
        .net-row { font-weight: bold; background: #eef2ff; font-size: 16px; }
        .signatures { margin-top: 40px; }
        .signatures td { text-align: center; padding-top: 40px; font-size: 12px; }
        .disclaimer { text-align: center; font-size: 10px; color: #999; margin-top: 30px; }
        hr { border: none; border-top: 1px solid #e5e7eb; margin: 10px 0; }
    </style>
</head>
<body>
    @php
        $formatHoursAsClock = function ($hours) {
            $totalMinutes = (int) round(((float) $hours) * 60);
            $h = intdiv($totalMinutes, 60);
            $m = $totalMinutes % 60;
            return sprintf('%d:%02d', $h, $m);
        };
    @endphp
    <div class="header">
        <h2>LowGrade โดย นิติบุคคล นายสรรวิน สาสาสันต์</h2>
        <p>สลิปเงินเดือน / Payslip</p>
        <p>ประจำเดือน {{ $monthName }} {{ $year + 543 }}</p>
    </div>

    <table class="info-table">
        <tr>
            <td class="info-label">ชื่อพนักงาน:</td>
            <td>{{ $employee->full_name }}</td>
            <td class="info-label">วันจ่ายเงิน:</td>
            <td>{{ $payslip->payment_date ? \Carbon\Carbon::parse($payslip->payment_date)->format('d/m/Y') : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">ตำแหน่ง:</td>
            <td>{{ $employee->position?->name ?? '-' }}</td>
            <td class="info-label">เลขที่บัญชี:</td>
            <td>{{ $employee->bankAccount?->account_number ?? '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">ธนาคาร:</td>
            <td colspan="3">{{ $employee->bankAccount?->bank_name ?? '-' }}</td>
        </tr>
    </table>

    <hr>

    <table class="metrics-table" style="width:100%">
        <tr>
                @if(in_array($employee->payroll_mode, ['monthly_staff', 'office_staff', 'youtuber_salary']))
                <td style="width:25%">
                    <div class="metric-label">ชั่วโมงรวม</div>
                    <div class="metric-value">{{ $formatHoursAsClock($monthlyStats['total_work_hours'] ?? 0) }} ชม.</div>
                </td>
                <td style="width:25%">
                    <div class="metric-label">OT</div>
                    <div class="metric-value">{{ $formatHoursAsClock($monthlyStats['total_ot_hours'] ?? 0) }} ชม.</div>
                </td>
                <td style="width:25%">
                    <div class="metric-label">มาสาย</div>
                    <div class="metric-value">{{ $monthlyStats['late_count'] ?? 0 }} ครั้ง ({{ $monthlyStats['late_minutes'] ?? 0 }} นาที)</div>
                </td>
                <td style="width:25%">
                    <div class="metric-label">ขาดงาน</div>
                    <div class="metric-value">{{ $monthlyStats['lwop_days'] ?? 0 }} วัน</div>
                </td>
                @endif
        </tr>
    </table>

    <table style="width:100%">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:10px;">
                <table class="payslip-table" style="width:100%">
                    <thead><tr><th>เงินได้</th><th class="text-right">จำนวน</th></tr></thead>
                    <tbody>
                        @foreach($payslip->incomeItems as $item)
                        <tr><td>{{ $item->label }}</td><td class="text-right">{{ number_format($item->amount, 2) }}</td></tr>
                        @endforeach
                        <tr class="total-row"><td>รวมเงินได้</td><td class="text-right">{{ number_format($payslip->total_income, 2) }}</td></tr>
                    </tbody>
                </table>
            </td>
            <td style="width:50%; vertical-align:top; padding-left:10px;">
                <table class="payslip-table" style="width:100%">
                    <thead><tr><th>รายการหัก</th><th class="text-right">จำนวน</th></tr></thead>
                    <tbody>
                        @foreach($payslip->deductionItems as $item)
                        <tr><td>{{ $item->label }}</td><td class="text-right">{{ number_format($item->amount, 2) }}</td></tr>
                        @endforeach
                        <tr class="deduction-total"><td>รวมรายหัก</td><td class="text-right">{{ number_format($payslip->total_deduction, 2) }}</td></tr>
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <table class="payslip-table" style="margin-top:20px">
        <tr class="net-row"><td>รายได้สุทธิ</td><td class="text-right">{{ number_format($payslip->net_pay, 2) }}</td></tr>
    </table>

    <table class="payslip-table" style="margin-top:12px">
        <thead>
            <tr>
                <th colspan="2">ยอดสะสมทั้งปี (ม.ค. - {{ $monthName }} {{ $year + 543 }})</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>สะสมเงินได้</td>
                <td class="text-right">{{ number_format($yearToDate['total_income'], 2) }}</td>
            </tr>
            <tr>
                <td>สะสมรายการหัก</td>
                <td class="text-right">{{ number_format($yearToDate['total_deduction'], 2) }}</td>
            </tr>
            <tr class="net-row">
                <td>สะสมสุทธิ</td>
                <td class="text-right">{{ number_format($yearToDate['net_pay'], 2) }}</td>
            </tr>
        </tbody>
    </table>

    @php
        // Try to fetch work logs for the snapshot month if it's a freelancer
        $workLogs = \App\Models\WorkLog::where('employee_id', $employee->id)
            ->where('is_disabled', false)
            ->where('month', $payslip->month)
            ->where('year', $payslip->year)
            ->get();
    @endphp

    @if($workLogs->count() > 0 && in_array($employee->payroll_mode, ['freelance_layer', 'freelance_fixed', 'youtuber_settlement', 'youtuber_salary']))
    <div style="margin-top:20px">
        <p style="font-size:12px; font-weight:bold; margin-bottom:5px;">รายละเอียดการทำงาน / สรุปรายการ (Details)</p>
        <table class="payslip-table" style="width:100%">
            <thead>
                <tr>
                    <th style="width:40px" class="text-center">ลำดับ</th>
                    <th>รายการ</th>
                    <th class="text-center">รายละเอียด / จำนวน</th>
                    <th class="text-right">หมายเหตุ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($workLogs as $idx => $log)
                @php
                    $isYoutuberSalary = $employee->payroll_mode === 'youtuber_salary';
                    $isSettlement = $employee->payroll_mode === 'youtuber_settlement';
                    $qty = $employee->payroll_mode === 'freelance_layer' 
                        ? (($log->hours * 60) + $log->minutes + ($log->seconds / 60))
                        : ($isSettlement || $isYoutuberSalary ? '-' : $log->quantity);
                    $unit = $employee->payroll_mode === 'freelance_layer' ? ' น.' : ($isSettlement ? ($log->notes === 'income' ? 'รายรับ' : 'รายจ่าย') : ($isYoutuberSalary ? '-' : ' ชิ้น'));
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>{{ $log->work_type ?: 'รายการทั่วไป' }}</td>
                    <td class="text-center">{{ $isSettlement || $isYoutuberSalary ? $log->notes : number_format($qty, 2) . $unit }}</td>
                    <td class="text-right">
                        @if($isYoutuberSalary)
                            -
                        @elseif($isSettlement)
                            {{ number_format($log->amount, 2) }}
                        @else
                            {{ number_format($log->amount, 2) }} (เรท {{ number_format($log->rate, 2) }})
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <table class="signatures" style="width:100%">
        <tr>
            <td style="text-align: center;">
                @if($payslip->status === 'finalized' && !empty($company) && !empty($company->signature_approver_image_path) && file_exists(storage_path('app/public/' . $company->signature_approver_image_path)))
                    <img src="{{ storage_path('app/public/' . $company->signature_approver_image_path) }}" style="max-height: 50px; display: block; margin: 0 auto 5px;"><br>
                @else
                    ___________________________<br>
                @endif
                ลายเซ็นผู้จ่าย<br>
                {{ !empty($company) && !empty($company->signature_approver_name) ? "({$company->signature_approver_name})" : '' }}
            </td>
            <td style="text-align: center;">
                @if($payslip->status === 'finalized' && !empty($company) && !empty($company->signature_receiver_image_path) && file_exists(storage_path('app/public/' . $company->signature_receiver_image_path)))
                    <img src="{{ storage_path('app/public/' . $company->signature_receiver_image_path) }}" style="max-height: 50px; display: block; margin: 0 auto 5px;"><br>
                @else
                    ___________________________<br>
                @endif
                ลายเซ็นผู้รับ<br>
                {{ !empty($company) && !empty($company->signature_receiver_name) ? "({$company->signature_receiver_name})" : '' }}
            </td>
        </tr>
    </table>

    <p class="disclaimer">เอกสารฉบับนี้เป็นของผู้มีรายชื่อข้างบนเท่านั้น ไม่สามารถเผยแพร่ให้กับผู้อื่นได้</p>
</body>
</html>
