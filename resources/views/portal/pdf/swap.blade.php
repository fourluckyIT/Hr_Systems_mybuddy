<!DOCTYPE html>
<html>
@include('portal.pdf._thai_form_head', ['docNumber' => $doc->document_number])
<body>

@php
    $employeeName = trim($doc->employee->first_name . ' ' . $doc->employee->last_name);
    $position     = $doc->employee->position?->name ?? '—';
    $companyName  = $company?->name ?? 'บริษัท โลโพลีเกรด จำกัด';

    $thMonths = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];

    $cd = $doc->created_at;
    $cDay   = $cd ? $cd->format('d') : '—';
    $cMonth = $cd ? ($thMonths[(int) $cd->format('m')] ?? '—') : '—';
    $cYearBE= $cd ? ((int) $cd->format('Y') + 543) : '—';

    $workDate = $doc->work_date ? \Carbon\Carbon::parse($doc->work_date) : null;
    $offDate  = $doc->off_date  ? \Carbon\Carbon::parse($doc->off_date)  : null;
    $workDateTh = $workDate ? $workDate->format('d') . ' ' . ($thMonths[(int) $workDate->format('m')] ?? '') . ' ' . ((int) $workDate->format('Y') + 543) : '—';
    $offDateTh  = $offDate  ? $offDate->format('d')  . ' ' . ($thMonths[(int) $offDate->format('m')]  ?? '') . ' ' . ((int) $offDate->format('Y') + 543)  : '—';
    $workWeekday = $workDate ? $workDate->locale('th')->isoFormat('dddd') : '';
    $offWeekday  = $offDate  ? $offDate->locale('th')->isoFormat('dddd')  : '';

    $reason = $doc->reason ?: str_repeat('.', 60);

    $statusLabel = match($doc->status) {
        'approved'  => '✓ อนุมัติแล้ว',
        'rejected'  => '✗ ไม่อนุมัติ',
        'cancelled' => '— ยกเลิกแล้ว',
        default     => '⏳ รออนุมัติ',
    };
@endphp

<div class="title">แบบฟอร์มขอสลับวันทำงาน</div>
<div class="subtitle">Day Swap Request Form · เลขที่ {{ $doc->document_number }}</div>

<div class="header-meta">
    เขียนที่ <span class="bold">{{ $companyName }}</span><br>
    วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
</div>

<div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขอสลับวันทำงาน</div>
<div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

<div class="body indent row">
    ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
    ตำแหน่ง: <span class="bold">{{ $position }}</span>
</div>
<div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

<div class="body row">
    มีความประสงค์ขอสลับวันทำงาน เนื่องด้วยเหตุผลตามรายละเอียดด้านล่าง
    จึงเรียนมาเพื่อโปรดพิจารณาอนุญาต
</div>

<div class="detail-box">
    <div class="detail-row">
        <div class="label">📅 วันที่จะมาทำงาน</div>
        <div class="value"><span class="bold">{{ $workDateTh }}</span> @if($workWeekday)<span class="muted"> · {{ $workWeekday }}</span>@endif</div>
    </div>
    <div class="detail-row">
        <div class="label">🏖️ วันที่จะหยุดทดแทน</div>
        <div class="value"><span class="bold">{{ $offDateTh }}</span> @if($offWeekday)<span class="muted"> · {{ $offWeekday }}</span>@endif</div>
    </div>
    <div class="detail-row">
        <div class="label">📝 เหตุผล</div>
        <div class="value">{{ $reason }}</div>
    </div>
    <div class="detail-row">
        <div class="label">สถานะคำขอ</div>
        <div class="value"><span class="bold">{{ $statusLabel }}</span>
            @if($doc->reviewed_at)
                <span class="muted"> · ตรวจสอบเมื่อ {{ \Carbon\Carbon::parse($doc->reviewed_at)->format('d/m/') . (\Carbon\Carbon::parse($doc->reviewed_at)->year + 543) }}</span>
            @endif
        </div>
    </div>
    @if($doc->review_note)
        <div class="detail-row">
            <div class="label">หมายเหตุผู้อนุมัติ</div>
            <div class="value">{{ $doc->review_note }}</div>
        </div>
    @endif
</div>

<table class="sigs">
    <tr>
        <td style="width:50%; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div class="bold">ขอแสดงความนับถือ</div>
            <div style="height:34px;"></div>
            <div class="sig-line">(ลงชื่อ) ……………………………………</div>
            <div>({{ $employeeName }})</div>
            <div>ผู้ขอสลับวัน</div>
            <div style="margin-top:8px;" class="muted">วันที่ ……… / ……………… / ………</div>
        </td>
        <td style="width:50%; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
            <div style="height:18px;"></div>
            <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
            <div>(…………………………………)</div>
            <div>ตำแหน่ง …………………………………</div>
            <div class="muted">วันที่ ……… / ……………… / ………</div>
        </td>
    </tr>
</table>

@if($doc->attachments && $doc->attachments->isNotEmpty())
    <div style="margin-top: 14px; font-size: 13px; color: #666;">
        📎 มีไฟล์แนบ {{ $doc->attachments->count() }} ไฟล์ (ตรวจสอบในระบบ)
    </div>
@endif

</body>
</html>
