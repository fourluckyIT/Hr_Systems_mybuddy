@extends('layouts.app')

@section('title', 'แก้ไขแม่แบบ — ' . $template->name)

@section('content')
@php
    $catalogKeys = array_keys($catalog);
    $wired = array_intersect($placeholdersInDoc, $catalogKeys);
    $unknownInDoc = array_diff($placeholdersInDoc, $catalogKeys);
    $missingFromDoc = array_diff($catalogKeys, $placeholdersInDoc);
@endphp

<div class="max-w-5xl mx-auto py-6 px-4">
    <a href="{{ route('document-templates.index') }}" class="inline-block mb-4 text-sm text-gray-500 hover:text-gray-700">← กลับไปรายการแม่แบบ</a>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    @if(!$libreOfficeOk)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <strong>⚠️ ยังไม่ได้ติดตั้ง LibreOffice</strong> — เวลาพิมพ์จะ error
            <p class="text-xs mt-1">macOS: <code class="bg-amber-100 px-1 rounded">brew install --cask libreoffice</code></p>
        </div>
    @endif

    <form method="POST" action="{{ route('document-templates.update', $template) }}" enctype="multipart/form-data">
        @csrf @method('PUT')

        <div class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
            <div class="flex items-center justify-between mb-3">
                <h1 class="text-lg font-bold text-gray-900">แม่แบบ DOCX — {{ $template->name }}</h1>
                <span class="px-2 py-0.5 text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full">.docx</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่อแม่แบบ</label>
                    <input type="text" name="name" value="{{ $template->name }}" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1">Variant</label>
                    <select name="variant_key" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="">— ทั่วไป —</option>
                        @foreach(\App\Services\DocumentFieldCatalog::variantChoices($template->doc_type) as $key => $label)
                            <option value="{{ $key }}" @selected($template->variant_key === $key)>{{ $label }} [{{ $key }}]</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="is_active" value="1" @checked($template->is_active) class="rounded border-gray-300">
                        เปิดใช้แม่แบบนี้
                    </label>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-4 mb-4">
            {{-- ── Placeholders status ── --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-3">สถานะ Placeholder ใน .docx</h2>

                @if(empty($placeholdersInDoc))
                    <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">
                        ⚠️ ไม่พบ placeholder ในไฟล์เลย — ตรวจสอบว่าใช้รูปแบบ <code class="bg-white px-1 rounded">${field_key}</code> (ดอลลาร์ + ปีกกา)
                    </p>
                @else
                    <p class="text-xs text-gray-500 mb-2">พบ placeholder ทั้งหมด {{ count($placeholdersInDoc) }} ตัวในไฟล์</p>
                @endif

                @if(count($wired) > 0)
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-emerald-700 mb-1">✓ ใช้งานได้ ({{ count($wired) }})</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($wired as $p)
                                <code class="text-xs px-2 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded">${{ '{' . $p . '}' }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(count($unknownInDoc) > 0)
                    <div class="mb-3">
                        <div class="text-xs font-semibold text-rose-700 mb-1">✗ ไม่รู้จัก ({{ count($unknownInDoc) }}) — สะกดผิด?</div>
                        <div class="flex flex-wrap gap-1">
                            @foreach($unknownInDoc as $p)
                                <code class="text-xs px-2 py-0.5 bg-rose-50 text-rose-700 border border-rose-200 rounded">${{ '{' . $p . '}' }}</code>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-4 border-t border-gray-100 pt-3">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">เปลี่ยนไฟล์ .docx (ถ้าต้องการ)</label>
                    <input type="file" name="file" accept=".docx" class="text-sm">
                    <p class="text-xs text-gray-400 mt-1">อัปโหลดเฉพาะเมื่อต้องการแทนที่ไฟล์เดิม</p>
                </div>
            </div>

            {{-- ── Available fields catalog ── --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5">
                <h2 class="text-base font-semibold text-gray-900 mb-3">Placeholder ที่ใช้ได้</h2>
                <p class="text-xs text-gray-500 mb-3">คัดลอกไปวางในไฟล์ Word ของคุณ — ตอนพิมพ์ระบบจะแทนที่ด้วยค่าจริง</p>

                <div class="space-y-1 max-h-[60vh] overflow-auto">
                    @foreach($catalog as $key => $meta)
                        <div class="flex items-center justify-between gap-2 p-2 border border-gray-100 rounded hover:bg-gray-50 text-xs">
                            <div class="min-w-0 flex-1">
                                <div class="text-gray-700">{{ $meta['label'] }}</div>
                                <code class="text-gray-500 font-mono text-[11px]" id="ph-{{ $key }}">${{ '{' . $key . '}' }}</code>
                            </div>
                            <button type="button"
                                    onclick="copyText('${{ '{' . $key . '}' }}', this)"
                                    class="px-2 py-0.5 text-[11px] border border-gray-200 rounded hover:bg-gray-100 whitespace-nowrap">คัดลอก</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">บันทึก</button>
        </div>
    </form>
</div>

<script>
function copyText(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ คัดลอกแล้ว';
        btn.classList.add('text-emerald-700');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('text-emerald-700'); }, 1200);
    });
}
</script>
@endsection
