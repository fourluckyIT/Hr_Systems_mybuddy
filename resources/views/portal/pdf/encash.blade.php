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

    $leaveTypeLabel = \App\Models\Employee::LEAVE_TYPE_LABELS[$doc->leave_type] ?? $doc->leave_type;
    $days = rtrim(rtrim(number_format((float) $doc->days, 2), '0'), '.');
    $note = $doc->note ?: str_repeat('.', 60);

    $amount = (float) $doc->amount;
    $amountText = \App\Support\ThaiBaht::inWords($amount);

    $payoutMonthTh = isset($thMonths[(int) $doc->payout_month]) ? $thMonths[(int) $doc->payout_month] : sprintf('%02d', $doc->payout_month);
    $payoutYearBe  = (int) $doc->payout_year + 543;

    $statusLabel = match($doc->status) {
        'approved'  => '✓ อนุมัติแล้ว',
        'rejected'  => '✗ ไม่อนุมัติ',
        'cancelled' => '— ยกเลิกแล้ว',
        default     => '⏳ รออนุมัติ',
    };
@endphp

<div class="title">แบบฟอร์มขอแลกวันลาเป็นเงิน</div>
<div class="subtitle">Leave Encashment Request · เลขที่ {{ $doc->document_number }}</div>

<div class="header-meta">
    เขียนที่ <span class="bold">{{ $companyName }}</span><br>
    วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
</div>

<div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;ขอแลกวันลาเป็นเงิน</div>
<div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

<div class="body indent row">
    ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
    ตำแหน่ง: <span class="bold">{{ $position }}</span>
</div>
<div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

<div class="body row">
    เนื่องจากในปีงบประมาณที่ผ่านมา ข้าพเจ้ายังมีสิทธิวันลาคงเหลือที่ยังไม่ได้ใช้
    จึงขอแลกวันลาที่เหลือเป็นเงินตามนโยบายของบริษัท โดยมีรายละเอียดดังนี้
</div>

<div class="detail-box">
    <div class="detail-row">
        <div class="label">🏖️ ประเภทวันลา</div>
        <div class="value"><span class="bold">{{ $leaveTypeLabel }}</span></div>
    </div>
    <div class="detail-row">
        <div class="label">📅 ปีของสิทธิ</div>
        <div class="value"><span class="bold">{{ $doc->year }}</span> <span class="muted"> · พ.ศ. {{ $doc->year + 543 }}</span></div>
    </div>
    <div class="detail-row">
        <div class="label">📊 จำนวนวันที่แลก</div>
        <div class="value"><span class="bold">{{ $days }}</span> วัน</div>
    </div>
    <div class="detail-row">
        <div class="label">💵 อัตราต่อวัน</div>
        <div class="value"><span class="bold">{{ number_format((float) $doc->rate_per_day, 2) }}</span> บาท</div>
    </div>
    <div class="detail-row">
        <div class="label">💰 ยอดที่จะได้รับ</div>
        <div class="value"><span class="bold" style="font-size:17px;">{{ number_format($amount, 2) }}</span> บาท
            <div class="muted" style="font-size:13px; margin-top:2px;">{{ $amountText }}</div>
        </div>
    </div>
    <div class="detail-row">
        <div class="label">📆 เดือนที่จ่าย</div>
        <div class="value"><span class="bold">{{ $payoutMonthTh }} {{ $payoutYearBe }}</span></div>
    </div>
    <div class="detail-row">
        <div class="label">📝 หมายเหตุ</div>
        <div class="value">{{ $note }}</div>
    </div>
    <div class="detail-row">
        <div class="label">สถานะคำขอ</div>
        <div class="value"><span class="bold">{{ $statusLabel }}</span>
            @if($doc->approved_at)
                <span class="muted"> · ตรวจสอบเมื่อ {{ \Carbon\Carbon::parse($doc->approved_at)->format('d/m/') . (\Carbon\Carbon::parse($doc->approved_at)->year + 543) }}</span>
            @endif
        </div>
    </div>
    @if($doc->rejection_reason)
        <div class="detail-row">
            <div class="label">เหตุผลที่ไม่อนุมัติ</div>
            <div class="value">{{ $doc->rejection_reason }}</div>
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
            <div>ผู้ขอแลก</div>
            <div style="margin-top:8px;" class="muted">วันที่ ……… / ……………… / ………</div>
        </td>
        <td style="width:50%; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div>[ &nbsp; ] อนุมัติ &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุมัติ</div>
            <div style="height:18px;"></div>
            <div class="sig-line">(ลงชื่อ) ………………………………… ผู้อนุมัติ</div>
            <div>(…………………………………)</div>
            <div>ตำแหน่ง …………………………………</div>
            <div class="muted">วันที่ ……… / ……………… / ………</div>
        </td>
    </tr>
</table>

</body>
</html>
