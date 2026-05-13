<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>ใบลา - {{ $doc->document_number }}</title>
    <style>
        @page { margin: 50px 60px; }
        body { font-family: 'thsarabun', sans-serif; font-size: 22px; line-height: 1.2; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .title { font-size: 28px; font-weight: bold; text-align: center; margin-bottom: 25px; margin-top: 20px; }
        .indent { padding-left: 50px; }
        
        table { width: 100%; border-collapse: collapse; }
        table.layout td { padding: 3px 0; vertical-align: top; }
        
        /* Stats Table */
        table.stats-table { width: 100%; border-collapse: collapse; text-align: center; font-size: 20px; margin-top: 15px; }
        table.stats-table th, table.stats-table td { border: 1px solid #000; padding: 5px; font-weight: normal; }
        
        /* Signature Blocks using tables */
        table.sig-table { width: 100%; margin-top: 40px; text-align: center; }
        table.sig-table td { vertical-align: top; width: 50%; padding: 10px; }
        
        .box { border: 1px solid #000; padding: 10px; }
        .red-text { color: red; }
        
        .spacing-lg { margin-top: 25px; }
        .spacing-md { margin-top: 15px; }
    </style>
</head>
<body>

@php
    $type = $doc->leave_type;
    $isCancelled = $doc->status === 'cancelled';
    
    // Fallbacks
    $employeeName = $doc->employee->first_name . ' ' . $doc->employee->last_name;
    $position = $doc->employee->position?->name ?? '-';
    $companyName = $company?->name ?? 'บริษัท โลโพลีเกรด จำกัด';
    
    // Dates
    $createdDate = optional($doc->created_at)->locale('th');
    $d = $createdDate ? $createdDate->format('d') : '....';
    $m = $createdDate ? $createdDate->translatedFormat('F') : '................';
    $y = $createdDate ? $createdDate->format('Y') + 543 : '........';
    
    $leaveDateObj = optional($doc->leave_date)->locale('th');
    $ld = $leaveDateObj ? $leaveDateObj->format('d') : '....';
    $lm = $leaveDateObj ? $leaveDateObj->translatedFormat('F') : '................';
    $ly = $leaveDateObj ? $leaveDateObj->format('Y') + 543 : '........';
@endphp

{{-- ========================================================= --}}
{{-- 4. ขอยกเลิกวันลา (Cancelled) --}}
{{-- ========================================================= --}}
@if($isCancelled)
    <div class="title">แบบฟอร์มขอยกเลิกวันลา</div>
    <div class="text-right">เขียนที่ {{ $companyName }}</div>
    <div class="text-right spacing-md">วันที่ {{ $d }} เดือน {{ $m }} พ.ศ. {{ $y }}</div>
    
    <div class="spacing-lg"><span class="bold">เรื่อง</span> ขอยกเลิกวันลา</div>
    <div class="spacing-md"><span class="bold">เรียน</span> คุณ สาระวิน ยาสาสันต์</div>
    
    <div class="indent spacing-lg">
        ข้าพเจ้า (ชื่อ-สกุล): {{ $employeeName }} ตำแหน่ง: {{ $position }}
    </div>
    <div class="spacing-md">ภายใต้: {{ $companyName }}</div>
    
    <div class="indent spacing-lg">
        ตามที่ข้าพเจ้าได้รับอนุญาตให้ลา {{ $leaveTypes[$type] ?? $type }} ตั้งแต่วันที่ {{ $ld }} {{ $lm }} {{ $ly }}<br><br>
        ถึงวันที่ {{ $ld }} {{ $lm }} {{ $ly }} รวมกำหนดเวลา 1 วัน นั้น<br><br>
        เนื่องจาก {{ $doc->reason ?: '....................................................................................................' }}
    </div>
    
    <div class="spacing-lg">จึงขอยกเลิกวันลาดังกล่าว จำนวน 1 วัน</div>
    <div class="spacing-md">ตั้งแต่วันที่ {{ $ld }} {{ $lm }} {{ $ly }} ถึงวันที่ {{ $ld }} {{ $lm }} {{ $ly }}</div>

    <table class="sig-table spacing-lg">
        <tr>
            <td></td>
            <td>
                <div class="bold mb-4">ขอแสดงความนับถือ</div>
                <br><br>
                <div>(ลงชื่อ) ..............................................................</div>
                <div class="spacing-md">({{ $employeeName }})</div>
                <div class="spacing-md">ผู้ขอยกเลิกวันลา</div>
            </td>
        </tr>
    </table>
    
    <div style="width: 50%; float: right; border: 1px solid #000; padding: 15px; margin-top: 40px; text-align: center;">
        <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
        <br><br>
        <div>(ลงชื่อ) .............................................................. ผู้อนุมัติ</div>
        <div class="spacing-md">(..............................................................)</div>
        <div class="spacing-md">ตำแหน่ง ..............................................................</div>
        <div class="spacing-md">วันที่ .......... / .......................... / ..................</div>
    </div>

{{-- ========================================================= --}}
{{-- 1. ลาไปช่วยเหลือภริยาที่คลอดบุตร (Paternity Leave) --}}
{{-- ========================================================= --}}
@elseif($type === 'paternity_leave')

    <div class="title">แบบใบลาไปช่วยเหลือภริยาที่คลอดบุตร</div>
    <div class="text-right">เขียนที่ {{ $companyName }}</div>
    <div class="text-right spacing-md">วันที่ {{ $d }} เดือน {{ $m }} พ.ศ. {{ $y }}</div>
    
    <div class="spacing-lg"><span class="bold">เรื่อง</span> ขอลาไปช่วยเหลือภริยาที่คลอดบุตร</div>
    <div class="spacing-md"><span class="bold">เรียน</span> คุณ สาระวิน ยาสาสันต์</div>
    
    <div class="indent spacing-lg">
        ข้าพเจ้า (ชื่อ-สกุล): {{ $employeeName }} ตำแหน่ง: {{ $position }}
    </div>
    <div class="spacing-md">ภายใต้: {{ $companyName }}</div>
    
    <div class="spacing-lg">
        มีความประสงค์ลาไปช่วยเหลือภริยาโดยชอบด้วยกฎหมายชื่อ {{ $doc->reason ?: '........................................................................' }}<br><br>
        ซึ่งคลอดบุตรเมื่อวันที่ ............ เดือน ........................................ พ.ศ. ....................<br><br>
        จึงขออนุญาตลาไปช่วยเหลือภริยาที่คลอดบุตร ตั้งแต่วันที่ {{ $ld }} {{ $lm }} {{ $ly }} ถึงวันที่ {{ $ld }} {{ $lm }} {{ $ly }}<br><br>
        รวมกำหนดเวลา 1 วันทำการ
    </div>
    
    <div class="spacing-lg">
        ในระหว่างลา สามารถติดต่อข้าพเจ้าได้ที่เบอร์โทรศัพท์ ........................................................................................<br><br>
        ขอมอบหมายงานดังนี้ / ให้ ........................................................................ เป็นผู้รับผิดชอบแทน
    </div>
    
    <div class="red-text spacing-lg">
        หมายเหตุ: ต้องแนบสำเนาเอกสารการจดทะเบียนสมรส จำนวน 1 ฉบับ
    </div>

    <table class="sig-table">
        <tr>
            <td></td>
            <td>
                <div class="bold mb-4">ขอแสดงความนับถือ</div>
                <br><br>
                <div>(ลงชื่อ) ..............................................................</div>
                <div class="spacing-md">({{ $employeeName }})</div>
                <div class="spacing-md">ผู้ขออนุญาตลา</div>
            </td>
        </tr>
    </table>
    
    <div style="width: 50%; float: right; border: 1px solid #ddd; background: #fcfcfc; padding: 15px; margin-top: 40px; text-align: center;">
        <div>[ &nbsp; ] อนุญาต &nbsp;&nbsp;&nbsp;&nbsp; [ &nbsp; ] ไม่อนุญาต</div>
        <br><br>
        <div>(ลงชื่อ) .............................................................. ผู้อนุมัติ</div>
        <div class="spacing-md">(..............................................................)</div>
        <div class="spacing-md">ตำแหน่ง ..............................................................</div>
        <div class="spacing-md">วันที่ .......... / .......................... / ..................</div>
    </div>

{{-- ========================================================= --}}
{{-- 2. ลาป่วย / ลาคลอดบุตร / ลากิจส่วนตัว --}}
{{-- ========================================================= --}}
@elseif(in_array($type, ['sick_leave', 'maternity_leave', 'personal_leave']))

    <div class="title">แบบใบลาป่วย ลาคลอดบุตร ลากิจส่วนตัว</div>
    <div class="text-right">เขียนที่ {{ $companyName }}</div>
    <div class="text-right spacing-md">วันที่ {{ $d }} เดือน {{ $m }} พ.ศ. {{ $y }}</div>
    
    <div class="spacing-lg"><span class="bold">เรื่อง</span> ขออนุญาตลา</div>
    <div class="spacing-md"><span class="bold">เรียน</span> คุณ สาระวิน ยาสาสันต์</div>
    
    <div class="indent spacing-lg">
        ข้าพเจ้า (ชื่อ-สกุล): {{ $employeeName }} ตำแหน่ง: {{ $position }}
    </div>
    <div class="spacing-md">ภายใต้: {{ $companyName }}</div>
    
    <div class="bold spacing-lg">ประเภทการลาที่ขอ (ทำเครื่องหมาย / เลือกประเภท)</div>
    <div class="indent spacing-md">
        [ {{ $type === 'sick_leave' ? '/' : ' ' }} ] ป่วย &nbsp;&nbsp;&nbsp; [ {{ $type === 'maternity_leave' ? '/' : ' ' }} ] คลอดบุตร &nbsp;&nbsp;&nbsp; [ {{ $type === 'personal_leave' ? '/' : ' ' }} ] กิจส่วนตัว
    </div>
    
    <div class="bold spacing-lg">รายละเอียดการขอลา</div>
    <div class="indent spacing-md">
        ขอลา ตั้งแต่วันที่ {{ $ld }} {{ $lm }} {{ $ly }} ถึงวันที่ {{ $ld }} {{ $lm }} {{ $ly }} มีกำหนด 1 วัน<br><br>
        เนื่องจาก {{ $doc->reason ?: '....................................................................................................................' }}
    </div>
    
    <div class="spacing-lg">
        <span class="bold">ข้าพเจ้าได้ลา</span> [ ] ป่วย &nbsp;&nbsp;&nbsp; [ ] คลอดบุตร &nbsp;&nbsp;&nbsp; [ ] กิจส่วนตัว<br><br>
        ครั้งสุดท้ายตั้งแต่วันที่ ............................................................ ถึงวันที่ ............................................................
    </div>

    <table class="sig-table">
        <tr>
            <td style="width: 45%; padding: 0;">
                <div class="bold">สถิติการลาในปีงบประมาณนี้</div>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>ประเภทลา</th>
                            <th>ป่วย (วัน)</th>
                            <th>กิจส่วนตัว (วัน)</th>
                            <th>คลอดบุตร</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>ลาครั้งนี้</td>
                            <td>{{ $type === 'sick_leave' ? 1 : 0 }}</td>
                            <td>{{ $type === 'personal_leave' ? 1 : 0 }}</td>
                            <td>{{ $type === 'maternity_leave' ? 1 : 0 }}</td>
                        </tr>
                        <tr>
                            <td>ลามาแล้ว</td>
                            <td>{{ $stats['table']['sick_leave'] ?? 0 }}</td>
                            <td>{{ $stats['table']['personal_leave'] ?? 0 }}</td>
                            <td>{{ $stats['table']['maternity_leave'] ?? 0 }}</td>
                        </tr>
                        <tr>
                            <td>รวมเป็น</td>
                            <td>{{ ($stats['table']['sick_leave'] ?? 0) + ($type === 'sick_leave' ? 1 : 0) }}</td>
                            <td>{{ ($stats['table']['personal_leave'] ?? 0) + ($type === 'personal_leave' ? 1 : 0) }}</td>
                            <td>{{ ($stats['table']['maternity_leave'] ?? 0) + ($type === 'maternity_leave' ? 1 : 0) }}</td>
                        </tr>
                    </tbody>
                </table>
                <br><br>
                <div>(ลงชื่อ) ........................................................ ผู้ตรวจสอบ</div>
                <div class="spacing-md">(........................................................)</div>
                <div class="spacing-md">ตำแหน่ง ........................................................</div>
                <div class="spacing-md">วันที่ ........../........../..........</div>
            </td>
            <td style="width: 10%;"></td>
            <td style="width: 45%; padding: 0;">
                <div class="bold mb-4">ขอแสดงความนับถือ</div>
                <br><br>
                <div>(ลงชื่อ) ..............................................................</div>
                <div class="spacing-md">({{ $employeeName }})</div>
                
                <div class="text-left" style="margin-top: 30px;">[ ] อนุญาต &nbsp;&nbsp; [ ] ไม่อนุญาต</div>
                <div class="text-left spacing-lg">(ลงชื่อ) ........................................................ (ผู้อนุมัติ)</div>
                <div class="spacing-md">(........................................................)</div>
                <div class="text-left spacing-md">ตำแหน่ง ........................................................</div>
                <div class="spacing-md">วันที่ ........../........../..........</div>
            </td>
        </tr>
    </table>

{{-- ========================================================= --}}
{{-- 3. ลาพักผ่อน (Vacation Leave) --}}
{{-- ========================================================= --}}
@elseif($type === 'vacation_leave')

    <div class="title">แบบฟอร์มขออนุญาตลาพักผ่อน</div>
    <div class="text-right">เขียนที่ {{ $companyName }}</div>
    <div class="text-right spacing-md">วันที่ {{ $d }} เดือน {{ $m }} พ.ศ. {{ $y }}</div>
    
    <div class="spacing-lg"><span class="bold">เรื่อง</span> ขออนุญาตลา</div>
    <div class="spacing-md"><span class="bold">เรียน</span> คุณ สาระวิน ยาสาสันต์</div>
    
    <div class="indent spacing-lg">
        ข้าพเจ้า (ชื่อ-สกุล): {{ $employeeName }} ตำแหน่ง: {{ $position }}
    </div>
    <div class="spacing-md">ภายใต้: {{ $companyName }}</div>
    
    <div class="spacing-lg">
        มีสิทธิลาพักผ่อนสะสม ................ วัน ลาประจำปีนี้ ................ วัน รวมมีสิทธิลาพักผ่อนทั้งสิ้น ................ วัน<br><br>
        ขออนุญาตลาพักผ่อนตั้งแต่วันที่ {{ $ld }} {{ $lm }} {{ $ly }} ถึงวันที่ {{ $ld }} {{ $lm }} {{ $ly }}<br><br>
        รวมกำหนดเวลา 1 วันทำการ
    </div>

    <table class="sig-table" style="border: 1px solid #000; margin-top: 50px;">
        <tr>
            <td style="width: 50%; border-right: 1px solid #000; padding: 20px;">
                <div class="bold">สถิติการลาในปีงบประมาณนี้</div>
                <table class="stats-table">
                    <thead>
                        <tr>
                            <th>ลามาแล้ว (วัน)</th>
                            <th>ลาครั้งนี้ (วัน)</th>
                            <th>รวมเป็น (วัน)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $stats['used_before'] ?? 0 }}</td>
                            <td>{{ $stats['current'] ?? 1 }}</td>
                            <td>{{ $stats['total'] ?? 1 }}</td>
                        </tr>
                    </tbody>
                </table>
                <br><br>
                <div class="spacing-lg">(ลงชื่อ) ........................................................ ผู้ตรวจสอบ</div>
                <div class="spacing-md">(........................................................)</div>
                <div class="spacing-md">ตำแหน่ง ........................................................</div>
                <div class="spacing-md">วันที่ ........../........../..........</div>
            </td>
            <td style="width: 50%; padding: 20px;">
                <div class="bold">ขอแสดงความนับถือ</div>
                <br><br><br>
                <div>(ลงชื่อ) ..............................................................</div>
                <div class="spacing-md">({{ $employeeName }})</div>
                <div class="spacing-md">ผู้ขออนุญาตลา</div>
            </td>
        </tr>
    </table>

@endif

@if($doc->attachments && $doc->attachments->isNotEmpty())
    <div style="margin-top: 30px; font-size: 18px; color: #666;">
        📎 มีไฟล์แนบ {{ $doc->attachments->count() }} ไฟล์ (ตรวจสอบในระบบ)
    </div>
@endif

</body>
</html>
