<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ใบเบิกค่าใช้จ่าย / ใบสำคัญจ่าย (Expense Claim)</title>
    <style>
        body {
            font-family: 'thsarabun', sans-serif;
            font-size: 18px;
            margin: 40px;
            line-height: 1.2;
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
            padding: 0;
        }
        .header h2 {
            font-size: 20px;
            font-weight: normal;
            margin: 5px 0 0 0;
            color: #333;
        }
        .claim-info {
            width: 100%;
            margin-bottom: 20px;
        }
        .claim-info td {
            padding: 5px;
            vertical-align: top;
        }
        .label {
            font-weight: bold;
            width: 150px;
        }
        table.details {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        table.details th, table.details td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
        }
        table.details th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        
        .signatures {
            width: 100%;
            margin-top: 60px;
        }
        .signatures td {
            text-align: center;
            width: 33.33%;
            padding: 10px;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            width: 80%;
            margin: 0 auto 10px auto;
            height: 30px;
        }
        .sig-name {
            font-size: 16px;
        }
        .footer {
            margin-top: 50px;
            font-size: 14px;
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company?->name ?? 'บริษัท' }}</h1>
        <h2>ใบสำคัญจ่าย / ใบเบิกค่าใช้จ่าย (Expense Claim Form)</h2>
    </div>

    <table class="claim-info">
        <tr>
            <td class="label">เลขที่เอกสาร (No.):</td>
            <td>EXP-{{ \Carbon\Carbon::parse($claim->claim_date)->format('Ym') }}-{{ str_pad($claim->id, 4, '0', STR_PAD_LEFT) }}</td>
            <td class="label text-right">วันที่ (Date):</td>
            <td>{{ \Carbon\Carbon::parse($claim->claim_date)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">ชื่อผู้เบิก (Name):</td>
            <td>{{ $claim->employee->full_name }}</td>
            <td class="label text-right">ตำแหน่ง (Position):</td>
            <td>{{ $claim->employee->position?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">ประเภทการเบิก (Type):</td>
            <td colspan="3">
                @if($claim->type === 'advance')
                    [ &#10003; ] เบิกเงินล่วงหน้า (Cash Advance) &nbsp;&nbsp;&nbsp; [ &nbsp;&nbsp; ] เบิกค่าใช้จ่าย (Reimbursement)
                @else
                    [ &nbsp;&nbsp; ] เบิกเงินล่วงหน้า (Cash Advance) &nbsp;&nbsp;&nbsp; [ &#10003; ] เบิกค่าใช้จ่าย (Reimbursement)
                @endif
            </td>
        </tr>
    </table>

    <table class="details">
        <thead>
            <tr>
                <th style="width: 10%;">ลำดับ<br>(No.)</th>
                <th style="width: 60%;">รายการ<br>(Description)</th>
                <th style="width: 30%;">จำนวนเงิน / บาท<br>(Amount / THB)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-center">1</td>
                <td>{{ $claim->description }}</td>
                <td class="text-right">{{ number_format($claim->amount, 2) }}</td>
            </tr>
            <!-- Empty rows for padding -->
            <tr><td style="height: 30px;"></td><td></td><td></td></tr>
            <tr><td style="height: 30px;"></td><td></td><td></td></tr>
            <tr>
                <td colspan="2" class="text-right" style="font-weight: bold;">รวมเงินทั้งสิ้น (Grand Total)</td>
                <td class="text-right" style="font-weight: bold; background-color: #f3f4f6;">{{ number_format($claim->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">({{ $claim->employee->full_name }})</div>
                <div>ผู้ขอเบิก (Requested By)</div>
                <div>วันที่: ____/____/______</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">(__________________________)</div>
                <div>ผู้ตรวจสอบ (Verified By)</div>
                <div>วันที่: ____/____/______</div>
            </td>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">(__________________________)</div>
                <div>ผู้อนุมัติ (Approved By)</div>
                <div>วันที่: ____/____/______</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        เอกสารพิมพ์เมื่อ {{ now()->format('d/m/Y H:i:s') }} โดยระบบ xHR
    </div>
</body>
</html>
