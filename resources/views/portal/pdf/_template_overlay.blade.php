{{-- Image-overlay PDF: background = template image, text = absolutely positioned divs --}}
@php
    $tpl = $template;
    // A4 portrait at 96dpi ≈ 794×1123 px. dompdf default is 96dpi.
    // We scale: image fills page width = 794px (printable), height scales proportionally.
    // All coordinates are in the source image's px-space → multiply by ($pageW / $imgW)
    $pageW = 794;
    $scale = $pageW / max(1, $tpl->image_width);
    $pageH = $tpl->image_height * $scale;

    $imgAbs = storage_path('app/public/' . $tpl->image_path);
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $doc->document_number ?? 'document' }}</title>
    <style>
        @page { margin: 0; size: {{ $pageW }}px {{ $pageH }}px; }
        body { margin: 0; padding: 0; font-family: 'thsarabun', sans-serif; color: #000; }
        .page { position: relative; width: {{ $pageW }}px; height: {{ $pageH }}px; }
        .bg { position: absolute; left: 0; top: 0; width: {{ $pageW }}px; height: {{ $pageH }}px; }
        .f { position: absolute; line-height: 1.0; white-space: nowrap; }
        .f.wrap { white-space: normal; }
    </style>
</head>
<body>
<div class="page">
    <img class="bg" src="{{ $imgAbs }}" alt="">
    @foreach($tpl->fields as $f)
        @php
            $rawValue = \App\Services\DocumentFieldCatalog::resolve($doc, $tpl->doc_type, $f->field_key);
            if ($f->is_checkbox) {
                $value = ($rawValue !== '' && $rawValue === ($f->checkbox_when ?? '')) ? '✓' : '';
            } else {
                $value = $rawValue;
            }
            if ($value === '') continue;
            $x  = $f->x * $scale;
            $y  = $f->y * $scale;
            $fs = $f->font_size * $scale;
            $w  = $f->width ? ($f->width * $scale) : null;
            $css = "left: {$x}px; top: {$y}px; font-size: {$fs}px; font-weight: {$f->font_weight}; text-align: {$f->align};";
            if ($w) $css .= " width: {$w}px;";
        @endphp
        <div class="f{{ $f->width ? ' wrap' : '' }}" style="{{ $css }}">{{ $value }}</div>
    @endforeach
</div>
</body>
</html>
