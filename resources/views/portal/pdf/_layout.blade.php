<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} - {{ $doc->document_number }}</title>
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
        .doc-info {
            width: 100%;
            margin-bottom: 20px;
        }
        .doc-info td {
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
            width: 50%;
            padding: 10px 40px;
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
        <h2>{{ $title }}</h2>
    </div>

    <table class="doc-info">
        <tr>
            <td class="label">เลขที่เอกสาร (No.):</td>
            <td>{{ $doc->document_number }}</td>
            <td class="label text-right">วันที่ออกเอกสาร (Date):</td>
            <td>{{ optional($doc->created_at)->format('d/m/Y') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">ชื่อพนักงาน (Name):</td>
            <td>{{ $doc->employee->first_name }} {{ $doc->employee->last_name }}</td>
            <td class="label text-right">ตำแหน่ง (Position):</td>
            <td>{{ $doc->employee->position?->name ?? '-' }}</td>
        </tr>
    </table>

    @yield('body')

    <table class="signatures">
        <tr>
            <td>
                <div class="sig-line"></div>
                <div class="sig-name">({{ $doc->employee->first_name }} {{ $doc->employee->last_name }})</div>
                <div>ผู้ขอ (Requested By)</div>
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
