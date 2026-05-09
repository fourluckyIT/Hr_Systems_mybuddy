<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แบบฟอร์มขอ{{ $action === 'encash' ? 'แลกวันลาพักร้อนเป็นเงิน' : 'ยกยอดวันลาพักร้อนข้ามปี' }}</title>
    <style>
        @font-face {
            font-family: 'thsarabun';
            font-style: normal;
            font-weight: normal;
            src: url('{{ storage_path('fonts/THSarabun.ttf') }}') format('truetype');
        }
        @font-face {
            font-family: 'thsarabun';
            font-style: normal;
            font-weight: bold;
            src: url('{{ storage_path('fonts/THSarabun Bold.ttf') }}') format('truetype');
        }
        body {
            font-family: 'thsarabun', sans-serif;
            font-size: 16pt;
            line-height: 1.2;
            color: #000;
        }
        @page {
            margin: 20mm 20mm 20mm 20mm;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .mt-4 { margin-top: 1rem; }
        .mt-8 { margin-top: 2rem; }
        .mb-4 { margin-bottom: 1rem; }
        .mb-2 { margin-bottom: 0.5rem; }
        .w-full { width: 100%; }
        table { border-collapse: collapse; }
        td, th { padding: 4px; vertical-align: top; }
        .border-b { border-bottom: 1px dotted #666; }
        
        .header-title { font-size: 20pt; font-weight: bold; }
        
        .box {
            border: 1px solid #333;
            padding: 15px;
            margin-top: 15px;
        }
        
        .signature-area {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            display: inline-block;
            text-align: center;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 80%;
            margin: 0 auto 5px auto;
            height: 30px;
        }
    </style>
</head>
<body>

    <div class="text-center mb-4">
        <div class="header-title">{{ $company->name ?? 'บริษัท ...........................................................' }}</div>
        <div class="font-bold mt-2" style="font-size: 18pt;">
            แบบฟอร์มขอ{{ $action === 'encash' ? 'แลกวันลาพักร้อนเป็นเงิน (Leave Encashment)' : 'ยกยอดวันลาพักร้อนข้ามปี (Leave Carryover)' }}
        </div>
    </div>

    <div class="text-right mb-4">
        วันที่: ....... / ........................ / .............
    </div>

    <div class="mb-2">
        <span class="font-bold">ข้อมูลพนักงาน:</span>
    </div>
    <table class="w-full mb-4">
        <tr>
            <td width="20%">รหัสพนักงาน:</td>
            <td width="30%" class="border-b">{{ $employee->employee_code }}</td>
            <td width="15%">ชื่อ-สกุล:</td>
            <td width="35%" class="border-b">{{ $employee->full_name }}</td>
        </tr>
        <tr>
            <td>แผนก:</td>
            <td class="border-b">{{ $employee->department?->name ?? '-' }}</td>
            <td>ตำแหน่ง:</td>
            <td class="border-b">{{ $employee->position?->name ?? '-' }}</td>
        </tr>
    </table>

    <div class="box">
        <div class="font-bold mb-2">ข้อมูลสิทธิวันลาพักร้อน (ปี {{ $year }}):</div>
        <table class="w-full">
            <tr>
                <td width="30%">สิทธิทั้งหมด (รวมทบ):</td>
                <td width="20%">{{ rtrim(rtrim(number_format($balance['total_available'], 1), '0'), '.') }} วัน</td>
                <td width="30%">คงเหลือสิทธิที่ใช้ได้:</td>
                <td width="20%" class="font-bold">{{ rtrim(rtrim(number_format($balance['remaining'], 1), '0'), '.') }} วัน</td>
            </tr>
        </table>
    </div>

    <div class="mt-4 mb-2">
        <span class="font-bold">ความประสงค์:</span>
    </div>
    
    @if($action === 'encash')
    <div style="margin-left: 20px;">
        ข้าพเจ้ามีความประสงค์ขอ <span class="font-bold">แลกสิทธิวันลาพักร้อนคงเหลือเป็นเงิน</span><br>
        จำนวน <span class="border-b" style="display:inline-block; width: 60px; text-align: center;"></span> วัน<br>
        เพื่อนำไปรวมกับรอบการจ่ายเงินเดือน: <span class="border-b" style="display:inline-block; width: 120px; text-align: center;"></span><br>
        เหตุผล/หมายเหตุ: <span class="border-b" style="display:inline-block; width: 300px; text-align: center;"></span>
    </div>
    @else
    <div style="margin-left: 20px;">
        ข้าพเจ้ามีความประสงค์ขอ <span class="font-bold">ยกยอดวันลาพักร้อนที่เหลือ ไปใช้ในปีถัดไป ({{ $year + 1 }})</span><br>
        จำนวน <span class="border-b" style="display:inline-block; width: 60px; text-align: center;"></span> วัน<br>
        เหตุผล/หมายเหตุ: <span class="border-b" style="display:inline-block; width: 300px; text-align: center;"></span>
    </div>
    @endif

    <div class="mt-4 text-center">
        ข้าพเจ้าขอรับรองว่าข้อมูลข้างต้นเป็นความจริงทุกประการ
    </div>

    <div class="signature-area">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            ( {{ $employee->full_name }} )<br>
            ผู้ยื่นคำร้อง<br>
            ....... / ........................ / .............
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            ( ........................................................... )<br>
            ผู้มีอำนาจอนุมัติ (HR / ผู้บริหาร)<br>
            ....... / ........................ / .............
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="mt-8" style="border: 1px solid #000; padding: 15px; background-color: #f9f9f9;">
        <div class="font-bold text-center mb-2">สำหรับฝ่ายบุคคล (HR Only)</div>
        <table class="w-full">
            <tr>
                <td width="30%">[ ] อนุมัติ</td>
                <td width="30%">[ ] ไม่อนุมัติ</td>
                <td width="40%">เหตุผล: .................................................</td>
            </tr>
            <tr>
                <td colspan="3" class="mt-2">
                    ผู้บันทึกข้อมูลเข้าระบบ: ................................................. วันที่: ....... / ........................ / .............
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
