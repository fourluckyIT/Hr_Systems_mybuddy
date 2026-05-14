<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $doc->document_number }}</title>
    <style>
        @page { margin: 24px 36px; }
        * { box-sizing: border-box; }
        body { font-family: 'thsarabun', sans-serif; font-size: 15px; line-height: 1.25; color: #000; margin: 0; padding: 0; }

        .title { font-size: 18px; font-weight: bold; text-align: center; margin: 0 0 6px 0; }
        .right { text-align: right; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .red { color: #c00; }

        .header-meta { text-align: right; margin-bottom: 6px; line-height: 1.35; }

        .row { margin-bottom: 2px; }
        .indent { padding-left: 24px; }
        .body { margin-top: 4px; }

        /* Stats table */
        table.stats { width: 100%; border-collapse: collapse; text-align: center; font-size: 13px; margin-top: 3px; }
        table.stats th, table.stats td { border: 1px solid #000; padding: 1px 4px; font-weight: normal; }
        table.stats th { font-weight: bold; }

        /* Stats grid (replaces nested table for dompdf-friendliness) */
        .stats-grid { font-size: 13px; margin-top: 3px; }
        .stats-grid .sg-row { display: block; }
        .stats-grid .sg-row > div { display: inline-block; border: 1px solid #000; padding: 1px 4px; text-align: center; }
        .stats-grid .sg-head { font-weight: bold; }

        /* 2-column signature layout */
        table.sigs { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.sigs td { vertical-align: top; padding: 0 6px; }
        .sig-block { line-height: 1.35; }
        .sig-line { letter-spacing: 0.5px; }

        .box { border: 1px solid #000; padding: 6px 10px; }
        .footnote { font-size: 12px; color: #c00; margin-top: 4px; }

        @php
            // Helpers used in every form
            $type = $doc->leave_type;
            $isCancelled = $doc->status === 'cancelled';

            $employeeName = trim($doc->employee->first_name . ' ' . $doc->employee->last_name);
            $position     = $doc->employee->position?->name ?? '—';
            $companyName  = $company?->name ?? 'บริษัท โลโพลีเกรด จำกัด';

            $thMonths = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];

            $cd = $doc->created_at;
            $cDay   = $cd ? $cd->format('d') : '—';
            $cMonth = $cd ? ($thMonths[(int) $cd->format('m')] ?? '—') : '—';
            $cYearBE= $cd ? ((int) $cd->format('Y') + 543) : '—';

            $ld = $doc->leave_date ? \Carbon\Carbon::parse($doc->leave_date) : null;
            $leaveDateTh = $ld ? $ld->format('d') . ' ' . ($thMonths[(int) $ld->format('m')] ?? '') . ' ' . ((int) $ld->format('Y') + 543) : '—';

            $leaveTypeLabel = \App\Models\Employee::LEAVE_TYPE_LABELS[$type] ?? $type;

            $reason = $doc->reason ?: str_repeat('.', 60);
        @endphp
    </style>
</head>
<body>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- 1. ใบยกเลิกวันลา (Cancelled) --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@if($isCancelled)

    <div class="title">แบบฟอร์มขอยกเลิกวันลา</div>

    <div class="header-meta">
        เขียนที่ <span class="bold">{{ $companyName }}</span><br>
        วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
    </div>

    <div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขอยกเลิกวันลา</div>
    <div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

    <div class="body indent row">
        ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
        ตำแหน่ง: <span class="bold">{{ $position }}</span>
    </div>
    <div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

    <div class="body row">
        ตามที่ข้าพเจ้าได้รับอนุญาตให้ลา <span class="bold">{{ $leaveTypeLabel }}</span>
        ตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
        รวมกำหนดเวลา <span class="bold">1</span> วัน นั้น
    </div>
    <div class="row">เนื่องจาก <span class="bold">{{ $reason }}</span></div>

    <div class="body row">
        จึงขอยกเลิกวันลาดังกล่าว จำนวน <span class="bold">1</span> วัน
        ตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
    </div>

    <table class="sigs" style="margin-top:14px; border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="width:50%; border-right: 1px solid #000; padding:10px 14px;" class="sig-block center">
                <div class="bold">ขอแสดงความนับถือ</div>
                <div style="height:24px;"></div>
                <div class="sig-line">(ลงชื่อ) ……………………………………</div>
                <div>({{ $employeeName }})</div>
                <div>ผู้ขอยกเลิกวันลา</div>
            </td>
            <td style="width:50%; padding:10px 14px;" class="sig-block center">
                <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
                <div style="height:14px;"></div>
                <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
                <div>(…………………………………)</div>
                <div>ตำแหน่ง …………………………………</div>
                <div>วันที่ ……… / ……………… / ………</div>
            </td>
        </tr>
    </table>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- 2. ลาไปช่วยเหลือภริยาที่คลอดบุตร (Paternity) --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@elseif($type === 'paternity_leave')

    <div class="title">แบบใบลาไปช่วยเหลือภริยาที่คลอดบุตร</div>

    <div class="header-meta">
        เขียนที่ <span class="bold">{{ $companyName }}</span><br>
        วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
    </div>

    <div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขอลาไปช่วยเหลือภริยาที่คลอดบุตร</div>
    <div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

    <div class="body indent row">
        ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
        ตำแหน่ง: <span class="bold">{{ $position }}</span>
    </div>
    <div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

    <div class="body row">
        มีความประสงค์ลาไปช่วยเหลือภริยาโดยชอบด้วยกฎหมายชื่อ <span class="bold">{{ $reason }}</span>
    </div>
    <div class="row">
        ซึ่งคลอดบุตรเมื่อวันที่ ………… เดือน ………………………… พ.ศ. ……………
    </div>
    <div class="row">
        จึงขออนุญาตลาไปช่วยเหลือภริยาที่คลอดบุตร
        ตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
    </div>
    <div class="row">รวมกำหนดเวลา <span class="bold">1</span> วันทำการ</div>

    <div class="body row">ในระหว่างลา สามารถติดต่อข้าพเจ้าได้ที่เบอร์โทรศัพท์ {{ str_repeat('.', 50) }}</div>
    <div class="row">ขอมอบหมายงานดังนี้ / ให้ {{ str_repeat('.', 50) }} เป็นผู้รับผิดชอบแทน</div>

    <div class="footnote">หมายเหตุ: ต้องแนบสำเนาเอกสารการจดทะเบียนสมรส จำนวน 1 ฉบับ</div>

    <table class="sigs" style="margin-top:10px; border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="width:50%; border-right: 1px solid #000; padding:10px 14px;" class="sig-block center">
                <div class="bold">ขอแสดงความนับถือ</div>
                <div style="height:24px;"></div>
                <div class="sig-line">(ลงชื่อ) ……………………………………</div>
                <div>({{ $employeeName }})</div>
                <div>ผู้ขออนุญาตลา</div>
            </td>
            <td style="width:50%; padding:10px 14px;" class="sig-block center">
                <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
                <div style="height:14px;"></div>
                <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
                <div>(…………………………………)</div>
                <div>ตำแหน่ง …………………………………</div>
                <div>วันที่ ……… / ……………… / ………</div>
            </td>
        </tr>
    </table>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- 3. ลาป่วย / ลาคลอดบุตร / ลากิจส่วนตัว --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@elseif(in_array($type, ['sick_leave', 'maternity_leave', 'personal_leave']))

    @php
        $cb = fn($t) => $type === $t ? '/' : ' ';
        $lastLeaveDate = isset($stats['last_leave_date']) && $stats['last_leave_date']
            ? $stats['last_leave_date']->format('d') . ' ' . ($thMonths[(int) $stats['last_leave_date']->format('m')] ?? '') . ' ' . ((int) $stats['last_leave_date']->format('Y') + 543)
            : '—';
        $sickPrior = $stats['table']['sick_leave'] ?? 0;
        $matPrior  = $stats['table']['maternity_leave'] ?? 0;
        $perPrior  = $stats['table']['personal_leave'] ?? 0;
    @endphp

    <div class="title">แบบใบลาป่วย ลาคลอดบุตร ลากิจส่วนตัว</div>

    <div class="header-meta">
        เขียนที่ <span class="bold">{{ $companyName }}</span><br>
        วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
    </div>

    <div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขออนุญาตลา</div>
    <div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

    <div class="body indent row">
        ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
        ตำแหน่ง: <span class="bold">{{ $position }}</span>
    </div>
    <div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

    <div class="body row">
        <span class="bold">ประเภทการลาที่ขอ:</span>
        [ <span class="bold">{{ $cb('sick_leave') }}</span> ] ป่วย
        &nbsp;&nbsp;&nbsp;
        [ <span class="bold">{{ $cb('maternity_leave') }}</span> ] คลอดบุตร
        &nbsp;&nbsp;&nbsp;
        [ <span class="bold">{{ $cb('personal_leave') }}</span> ] กิจส่วนตัว
    </div>

    <div class="row">
        ขอลาตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
        มีกำหนด <span class="bold">1</span> วัน
    </div>
    <div class="row">เนื่องจาก <span class="bold">{{ $reason }}</span></div>

    <div class="row">
        <span class="bold">ข้าพเจ้าได้ลา</span>
        [ {{ $sickPrior > 0 ? '/' : ' ' }} ] ป่วย
        &nbsp;&nbsp;&nbsp;
        [ {{ $matPrior > 0  ? '/' : ' ' }} ] คลอดบุตร
        &nbsp;&nbsp;&nbsp;
        [ {{ $perPrior > 0  ? '/' : ' ' }} ] กิจส่วนตัว
        ครั้งสุดท้าย <span class="bold">{{ $lastLeaveDate }}</span>
    </div>

    <table class="sigs" style="margin-top:10px; border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="width:55%; border-right: 1px solid #000; padding:8px 12px; vertical-align:top;" class="sig-block">
                <div class="bold center" style="margin-bottom:2px;">สถิติการลาในปีงบประมาณนี้</div>
                <div style="border:1px solid #000; font-size:13px;">
                    <div style="text-align:center; font-weight:bold; border-bottom:1px solid #000; padding:1px 0;">
                        <span style="display:inline-block; width:25%;">ประเภทลา</span><span style="display:inline-block; width:23%;">ป่วย</span><span style="display:inline-block; width:25%;">กิจส่วนตัว</span><span style="display:inline-block; width:25%;">คลอดบุตร</span>
                    </div>
                    <div style="text-align:center; border-bottom:1px solid #000; padding:1px 0;">
                        <span style="display:inline-block; width:25%;">ลาครั้งนี้</span><span style="display:inline-block; width:23%;">{{ $type === 'sick_leave' ? 1 : 0 }}</span><span style="display:inline-block; width:25%;">{{ $type === 'personal_leave' ? 1 : 0 }}</span><span style="display:inline-block; width:25%;">{{ $type === 'maternity_leave' ? 1 : 0 }}</span>
                    </div>
                    <div style="text-align:center; border-bottom:1px solid #000; padding:1px 0;">
                        <span style="display:inline-block; width:25%;">ลามาแล้ว</span><span style="display:inline-block; width:23%;">{{ $sickPrior }}</span><span style="display:inline-block; width:25%;">{{ $perPrior }}</span><span style="display:inline-block; width:25%;">{{ $matPrior }}</span>
                    </div>
                    <div style="text-align:center; padding:1px 0;">
                        <span style="display:inline-block; width:25%;">รวมเป็น</span><span style="display:inline-block; width:23%;">{{ $sickPrior + ($type === 'sick_leave' ? 1 : 0) }}</span><span style="display:inline-block; width:25%;">{{ $perPrior + ($type === 'personal_leave' ? 1 : 0) }}</span><span style="display:inline-block; width:25%;">{{ $matPrior + ($type === 'maternity_leave' ? 1 : 0) }}</span>
                    </div>
                </div>
                <div style="margin-top:8px;">
                    <div class="sig-line">(ลงชื่อ) ………………………………… ผู้ตรวจสอบ</div>
                    <div>(…………………………………)</div>
                    <div>ตำแหน่ง …………………………………</div>
                    <div>วันที่ ……… / ……… / ………</div>
                </div>
            </td>
            <td style="width:45%; padding:8px 12px; vertical-align:top;" class="sig-block">
                <div class="bold center">ขอแสดงความนับถือ</div>
                <div style="height:18px;"></div>
                <div class="center sig-line">(ลงชื่อ) ……………………………………………</div>
                <div class="center">({{ $employeeName }})</div>
                <div class="center">ผู้ขออนุญาตลา</div>

                <div style="margin-top:10px;">[ ] อนุญาต &nbsp;&nbsp; [ ] ไม่อนุญาต</div>
                <div style="margin-top:4px;">
                    <div class="sig-line">(ลงชื่อ) ………………………………… (ผู้อนุมัติ)</div>
                    <div>(…………………………………)</div>
                    <div>ตำแหน่ง …………………………………</div>
                    <div>วันที่ ……… / ……… / ………</div>
                </div>
            </td>
        </tr>
    </table>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- 4. ลาไม่รับค่าจ้าง (LWOP) --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@elseif($type === 'lwop')

    <div class="title">แบบฟอร์มขอลาไม่รับค่าจ้าง (Leave Without Pay)</div>

    <div class="header-meta">
        เขียนที่ <span class="bold">{{ $companyName }}</span><br>
        วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
    </div>

    <div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขออนุญาตลาไม่รับค่าจ้าง</div>
    <div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

    <div class="body indent row">
        ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
        ตำแหน่ง: <span class="bold">{{ $position }}</span>
    </div>
    <div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

    <div class="body row">
        ด้วยข้าพเจ้ามีความจำเป็นต้องลาโดยไม่รับค่าจ้าง (Leave Without Pay)
    </div>
    <div class="row">
        ตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
        รวมกำหนด <span class="bold">1</span> วันทำการ
    </div>
    <div class="row">เนื่องจาก <span class="bold">{{ $reason }}</span></div>

    <div class="body row" style="margin-top:8px;">
        ข้าพเจ้าได้รับทราบและยอมรับเงื่อนไขการลาไม่รับค่าจ้างนี้ว่า:
    </div>
    <div class="indent row">• ค่าจ้างของวันที่ลาจะถูกหักจากเงินเดือนตามจำนวนวันที่ลา</div>
    <div class="indent row">• ไม่นับเป็นวันลาในระบบสิทธิประโยชน์ (ลาพักร้อน/ลาป่วย/ลากิจ)</div>
    <div class="indent row">• สวัสดิการอื่น ๆ เช่น ประกันสังคม จะยังคงดำเนินการตามปกติ</div>

    <table class="sigs" style="margin-top:14px; border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="width:50%; border-right: 1px solid #000; padding:10px 14px; vertical-align:top;" class="sig-block center">
                <div class="bold">ขอแสดงความนับถือ</div>
                <div style="height:28px;"></div>
                <div class="sig-line">(ลงชื่อ) ……………………………………</div>
                <div>({{ $employeeName }})</div>
                <div>ผู้ขออนุญาตลา</div>
                <div style="margin-top:8px;">วันที่ ……… / ……………… / ………</div>
            </td>
            <td style="width:50%; padding:10px 14px; vertical-align:top;" class="sig-block center">
                <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
                <div style="height:14px;"></div>
                <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
                <div>(…………………………………)</div>
                <div>ตำแหน่ง …………………………………</div>
                <div>วันที่ ……… / ……………… / ………</div>
            </td>
        </tr>
    </table>

{{-- ════════════════════════════════════════════════════════════════ --}}
{{-- 5. ลาพักผ่อน (Vacation) --}}
{{-- ════════════════════════════════════════════════════════════════ --}}
@elseif($type === 'vacation_leave')

    @php
        $bal = $stats['balance'] ?? null;
        $fmtDays = fn($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
        $carryover = $bal ? $fmtDays($bal['carryover']) : '—';
        $annual    = $bal ? $fmtDays($bal['annual'])    : '—';
        $total     = $bal ? $fmtDays($bal['total'])     : '—';
        $usedBefore = $stats['used_before'] ?? 0;
        $totalWithCurrent = $stats['total'] ?? 1;
    @endphp

    <div class="title">แบบฟอร์มขออนุญาตลาพักผ่อน</div>

    <div class="header-meta">
        เขียนที่ <span class="bold">{{ $companyName }}</span><br>
        วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
    </div>

    <div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขออนุญาตลา</div>
    <div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

    <div class="body indent row">
        ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
        ตำแหน่ง: <span class="bold">{{ $position }}</span>
    </div>
    <div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

    <div class="body row">
        มีสิทธิลาพักผ่อนสะสม <span class="bold">{{ $carryover }}</span> วัน
        ลาประจำปีนี้ <span class="bold">{{ $annual }}</span> วัน
        รวมมีสิทธิลาพักผ่อนทั้งสิ้น <span class="bold">{{ $total }}</span> วัน
    </div>
    <div class="row">
        ขออนุญาตลาพักผ่อนตั้งแต่วันที่ <span class="bold">{{ $leaveDateTh }}</span>
        ถึงวันที่ <span class="bold">{{ $leaveDateTh }}</span>
    </div>
    <div class="row">รวมกำหนดเวลา <span class="bold">1</span> วันทำการ</div>

    <table class="sigs" style="margin-top:14px; border: 1px solid #000; border-collapse: collapse;">
        <tr>
            <td style="width:55%; border-right: 1px solid #000; padding:10px 14px;" class="sig-block">
                <div class="bold center" style="margin-bottom:4px;">สถิติการลาในปีงบประมาณนี้</div>
                <table class="stats">
                    <thead>
                        <tr>
                            <th>ลามาแล้ว (วัน)</th>
                            <th>ลาครั้งนี้ (วัน)</th>
                            <th>รวมเป็น (วัน)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $usedBefore }}</td>
                            <td>1</td>
                            <td>{{ $totalWithCurrent }}</td>
                        </tr>
                    </tbody>
                </table>
                <div style="margin-top:12px;">
                    <div class="sig-line">(ลงชื่อ) ………………………………… ผู้ตรวจสอบ</div>
                    <div>(…………………………………)</div>
                    <div>ตำแหน่ง …………………………………</div>
                    <div>วันที่ ……… / ……… / ………</div>
                </div>
            </td>
            <td style="width:45%; padding:10px 14px;" class="sig-block center">
                <div class="bold">ขอแสดงความนับถือ</div>
                <div style="height:34px;"></div>
                <div class="sig-line">(ลงชื่อ) ……………………………………………</div>
                <div>({{ $employeeName }})</div>
                <div>ผู้ขออนุญาตลา</div>
            </td>
        </tr>
    </table>

@endif

@if($doc->attachments && $doc->attachments->isNotEmpty())
    <div style="margin-top: 14px; font-size: 14px; color: #666;">
        📎 มีไฟล์แนบ {{ $doc->attachments->count() }} ไฟล์ (ตรวจสอบในระบบ)
    </div>
@endif

</body>
</html>
