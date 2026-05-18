{{-- Shared <head> + CSS for Thai-government-style request forms.
     Include with: @include('portal.pdf._thai_form_head', ['docNumber' => $doc->document_number])
--}}
<head>
    <meta charset="UTF-8">
    <title>{{ $docNumber ?? 'document' }}</title>
    <style>
        @page { margin: 24px 36px; }
        * { box-sizing: border-box; }
        body { font-family: 'thsarabun', sans-serif; font-size: 15px; line-height: 1.3; color: #000; margin: 0; padding: 0; }

        .title    { font-size: 20px; font-weight: bold; text-align: center; margin: 0 0 4px 0; }
        .subtitle { font-size: 13px; text-align: center; color: #555; margin: 0 0 12px 0; }
        .right    { text-align: right; }
        .center   { text-align: center; }
        .bold     { font-weight: bold; }
        .muted    { color: #6b7280; }
        .red      { color: #c00; }

        .header-meta { text-align: right; margin-bottom: 8px; line-height: 1.4; }

        .row    { margin-bottom: 3px; }
        .indent { padding-left: 24px; }
        .body   { margin-top: 8px; }

        /* Bordered key/value detail box (looks like Thai gov form) */
        .detail-box { border: 1px solid #000; margin-top: 10px; }
        .detail-box .detail-row { display: table; width: 100%; border-bottom: 1px solid #000; }
        .detail-box .detail-row:last-child { border-bottom: none; }
        .detail-box .detail-row > .label,
        .detail-box .detail-row > .value { display: table-cell; padding: 6px 12px; vertical-align: middle; }
        .detail-box .detail-row > .label {
            width: 35%;
            font-weight: bold;
            background: #f9f9f9;
            border-right: 1px solid #000;
        }

        /* Itemized money table (for expense form) */
        table.money-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.money-table th, table.money-table td { border: 1px solid #000; padding: 6px 10px; font-size: 14px; }
        table.money-table th { background: #f3f4f6; font-weight: bold; text-align: center; font-size: 13px; }
        table.money-table td.no  { width: 8%;  text-align: center; }
        table.money-table td.amt { width: 25%; text-align: right; font-variant-numeric: tabular-nums; }
        table.money-table tr.total td { font-weight: bold; background: #f9fafb; }

        /* Signature blocks */
        table.sigs { width: 100%; border-collapse: collapse; margin-top: 14px; }
        table.sigs td { vertical-align: top; padding: 0 6px; }
        .sig-block { line-height: 1.4; }
        .sig-line  { letter-spacing: 0.5px; }

        .footnote { font-size: 13px; color: #c00; margin-top: 6px; }
    </style>
</head>
