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

    $claimDate = $doc->claim_date ? \Carbon\Carbon::parse($doc->claim_date) : null;
    $claimDateTh = $claimDate ? $claimDate->format('d') . ' ' . ($thMonths[(int) $claimDate->format('m')] ?? '') . ' ' . ((int) $claimDate->format('Y') + 543) : '—';

    $isAdvance = $doc->type === 'advance';
    $titleText = $isAdvance ? 'แบบฟอร์มขอเบิกเงินล่วงหน้า' : 'แบบฟอร์มขอเบิกค่าใช้จ่าย';
    $titleEn   = $isAdvance ? 'Cash Advance Form'        : 'Expense Reimbursement Form';
    $subject   = $isAdvance ? 'ขอเบิกเงินล่วงหน้า'        : 'ขอเบิกค่าใช้จ่าย';

    $amount      = (float) $doc->amount;
    $amountText  = \App\Support\ThaiBaht::inWords($amount);
    $description = $doc->description ?: str_repeat('.', 50);

    $statusLabel = match($doc->status) {
        'approved'  => 'อนุมัติแล้ว',
        'rejected'  => 'ไม่อนุมัติ',
        'cancelled' => 'ยกเลิกแล้ว',
        default     => 'รออนุมัติ',
    };
@endphp

<div class="title">{{ $titleText }}</div>
<div class="subtitle">{{ $titleEn }} · เลขที่ {{ $doc->document_number }}</div>

<div class="header-meta">
    เขียนที่ <span class="bold">{{ $companyName }}</span><br>
    วันที่ <span class="bold">{{ $cDay }}</span> / <span class="bold">{{ $cMonth }}</span> / พ.ศ. <span class="bold">{{ $cYearBE }}</span>
</div>

<div class="row"><span class="bold">เรื่อง</span>&nbsp;&nbsp;{{ $subject }}</div>
<div class="row"><span class="bold">เรียน</span>&nbsp;&nbsp;คุณ สาระวิน ยาสาสันต์</div>

<div class="body indent row">
    ข้าพเจ้า (ชื่อ-สกุล): <span class="bold">{{ $employeeName }}</span>&nbsp;&nbsp;&nbsp;
    ตำแหน่ง: <span class="bold">{{ $position }}</span>
</div>
<div class="row">ภายใต้ : <span class="bold">{{ $companyName }}</span></div>

<div class="body row">
    มีความประสงค์{{ $subject }} โดยมีรายละเอียดดังนี้
</div>

<div class="body row">
    <span class="bold">ประเภทการเบิก:</span>
    [ <span class="bold">{{ $isAdvance ? '/' : ' ' }}</span> ] เบิกเงินล่วงหน้า (Cash Advance)
    &nbsp;&nbsp;&nbsp;&nbsp;
    [ <span class="bold">{{ !$isAdvance ? '/' : ' ' }}</span> ] เบิกค่าใช้จ่าย (Reimbursement)
    &nbsp;&nbsp;&nbsp;&nbsp;
    <span class="muted">วันที่ขอเบิก: <span class="bold">{{ $claimDateTh }}</span></span>
</div>

<table class="money-table">
    <thead>
        <tr>
            <th style="width: 8%;">ลำดับ<br><span class="muted" style="font-weight:normal;">No.</span></th>
            <th>รายการ <span class="muted" style="font-weight:normal;">(Description)</span></th>
            <th style="width: 25%;">จำนวนเงิน (บาท)<br><span class="muted" style="font-weight:normal;">Amount / THB</span></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="no">1</td>
            <td>{{ $description }}</td>
            <td class="amt">{{ number_format($amount, 2) }}</td>
        </tr>
        <tr><td class="no">&nbsp;</td><td>&nbsp;</td><td class="amt">&nbsp;</td></tr>
        <tr><td class="no">&nbsp;</td><td>&nbsp;</td><td class="amt">&nbsp;</td></tr>
        <tr class="total">
            <td colspan="2" style="text-align:right;">รวมเงินทั้งสิ้น (Grand Total)</td>
            <td class="amt">{{ number_format($amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="row" style="margin-top:8px;">
    <span class="bold">จำนวนเงิน (ตัวอักษร):</span> <span class="muted">{{ $amountText }}</span>
</div>

<div class="body row">
    <span class="bold">สถานะคำขอ:</span> {{ $statusLabel }}
    @if($doc->status === 'approved' && $doc->approved_at)
        <span class="muted"> · อนุมัติเมื่อ {{ \Carbon\Carbon::parse($doc->approved_at)->format('d/m/') . (\Carbon\Carbon::parse($doc->approved_at)->year + 543) }}</span>
    @endif
</div>

<table class="sigs" style="margin-top:14px; border: 1px solid #000; border-collapse: collapse;">
    <tr>
        <td style="width:50%; border-right: 1px solid #000; padding:10px 14px; vertical-align:top;" class="sig-block center">
            <div class="bold">ขอแสดงความนับถือ</div>
            <div style="height:24px;"></div>
            <div class="sig-line">(ลงชื่อ) ……………………………………</div>
            <div>({{ $employeeName }})</div>
            <div>ผู้ขอเบิก</div>
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

@if($doc->attachments && $doc->attachments->isNotEmpty())
    <div style="margin-top: 12px; font-size: 13px; color: #666;">
        มีไฟล์แนบ {{ $doc->attachments->count() }} ไฟล์ (ตรวจสอบในระบบ)
    </div>
@endif

</body>
</html>
