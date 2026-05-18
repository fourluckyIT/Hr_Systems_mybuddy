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

    $otDate = $doc->log_date ? \Carbon\Carbon::parse($doc->log_date) : null;
    $otDateTh = $otDate ? $otDate->format('d') . ' ' . ($thMonths[(int) $otDate->format('m')] ?? '') . ' ' . ((int) $otDate->format('Y') + 543) : '—';
    $otWeekday = $otDate ? $otDate->locale('th')->isoFormat('dddd') : '';

    $minutes = (int) ($doc->requested_minutes ?? 0);
    $hours   = $minutes > 0 ? rtrim(rtrim(number_format($minutes / 60, 2), '0'), '.') : '0';

    $reason   = $doc->reason ?: str_repeat('.', 60);
    $jobRef   = $doc->job_reference ?: str_repeat('.', 50);

    $statusLabel = match($doc->status) {
        'approved'  => 'อนุมัติแล้ว',
        'rejected'  => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิกแล้ว',
        default     => 'รออนุมัติ',
    };
@endphp

<div class="title">แบบฟอร์มขออนุมัติทำงานล่วงเวลา</div>
<div class="subtitle">Overtime Request Form · เลขที่ {{ $doc->document_number }}</div>

<div class="header-meta">
    เขียนที่ <span class="bold">{{ $companyName }}</span><br>
    วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
</div>

<div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขออนุมัติทำงานล่วงเวลา (OT)</div>
<div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

<div class="body indent row">
    ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
    ตำแหน่ง: <span class="bold">{{ $position }}</span>
</div>
<div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

<div class="body row">
    มีความจำเป็นต้องทำงานล่วงเวลานอกเหนือเวลาทำงานปกติ ตามรายละเอียดด้านล่าง
    จึงขออนุมัติทำงานล่วงเวลาตามที่ระบุ พร้อมขอเบิกค่าล่วงเวลาตามอัตราที่บริษัทกำหนด
</div>

<div class="detail-box">
    <div class="detail-row">
        <div class="label">วันที่ทำ OT</div>
        <div class="value"><span class="bold">{{ $otDateTh }}</span> @if($otWeekday)<span class="muted"> · {{ $otWeekday }}</span>@endif</div>
    </div>
    <div class="detail-row">
        <div class="label">จำนวนเวลาที่ขอ</div>
        <div class="value"><span class="bold">{{ $hours }}</span> ชั่วโมง <span class="muted">({{ $minutes }} นาที)</span></div>
    </div>
    <div class="detail-row">
        <div class="label">งานที่จะทำ / อ้างอิง</div>
        <div class="value">{{ $jobRef }}</div>
    </div>
    <div class="detail-row">
        <div class="label">เหตุผล</div>
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

<table class="sigs" style="margin-top:14px; border: 1px solid #000; border-collapse: collapse;">
    <tr>
        <td style="width:50%; border-right: 1px solid #000; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div class="bold">ขอแสดงความนับถือ</div>
            <div style="height:24px;"></div>
            <div class="sig-line">(ลงชื่อ) ……………………………………</div>
            <div>({{ $employeeName }})</div>
            <div>ผู้ขอ OT</div>
            <div style="margin-top:8px;">วันที่ ……… / ……………… / ………</div>
        </td>
        <td style="width:50%; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div>[ &nbsp; ] อนุมัติ &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุมัติ</div>
            <div style="height:14px;"></div>
            <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
            <div>(…………………………………)</div>
            <div>ตำแหน่ง …………………………………</div>
            <div>วันที่ ……… / ……………… / ………</div>
        </td>
    </tr>
</table>

</body>
</html>
