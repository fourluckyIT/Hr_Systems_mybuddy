@extends('layouts.app')

@section('title', 'อัปโหลดแม่แบบใหม่')

@section('content')
<div class="max-w-2xl mx-auto py-6 px-4">
    <a href="{{ route('document-templates.index') }}" class="inline-block mb-4 text-sm text-gray-500 hover:text-gray-700">← กลับไปรายการแม่แบบ</a>
    <h1 class="text-xl font-bold text-gray-900 mb-2">อัปโหลดแม่แบบเอกสารใหม่</h1>
    <p class="text-sm text-gray-500 mb-6">เลือกประเภทเอกสาร อัปโหลดไฟล์ — ระบบจะเติมข้อมูลให้ตอนพิมพ์</p>

    @if(!$libreOfficeOk)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <strong>⚠️ ยังไม่ได้ติดตั้ง LibreOffice</strong> — แม่แบบแบบ <code class="bg-amber-100 px-1 rounded">.docx</code> จะอัปโหลดได้ แต่ตอนพิมพ์จะ error
            <p class="text-xs mt-1">ติดตั้ง: <code class="bg-amber-100 px-1 rounded">brew install --cask libreoffice</code> (macOS) · <code class="bg-amber-100 px-1 rounded">sudo apt install libreoffice</code> (Linux)</p>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg text-sm">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('document-templates.store') }}" enctype="multipart/form-data"
          class="bg-white border border-gray-200 rounded-lg p-6 space-y-4"
          x-data="{ docType: '{{ $docType }}', kind: 'docx' }">
        @csrf

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">ชนิดแม่แบบ</label>
            <div class="grid grid-cols-2 gap-2">
                <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer"
                       :class="kind === 'docx' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                    <input type="radio" name="kind" value="docx" x-model="kind" class="mt-1">
                    <div>
                        <div class="text-sm font-semibold text-gray-800">Word (.docx) <span class="text-emerald-600 text-xs">แนะนำ</span></div>
                        <div class="text-xs text-gray-500 mt-0.5">แก้ฟอร์มใน MS Word/Google Docs ได้ ใส่ <code>${employee_name}</code> เป็น placeholder</div>
                    </div>
                </label>
                <label class="flex items-start gap-2 p-3 border rounded-lg cursor-pointer"
                       :class="kind === 'image' ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'">
                    <input type="radio" name="kind" value="image" x-model="kind" class="mt-1">
                    <div>
                        <div class="text-sm font-semibold text-gray-800">ภาพ (PNG/JPG)</div>
                        <div class="text-xs text-gray-500 mt-0.5">วางข้อความทับภาพ ลากกล่องเอง (ทำได้แต่ปรับยาก)</div>
                    </div>
                </label>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">ประเภทเอกสาร</label>
            <select name="doc_type" x-model="docType"
                    @change="window.location.search = '?doc_type=' + docType"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                @foreach($docTypes as $key => $label)
                    <option value="{{ $key }}" @selected($docType === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Variant (ตัวจำแนกย่อย)</label>
            <select name="variant_key" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                <option value="">— ทั่วไป (default) —</option>
                @foreach($variantChoices as $key => $label)
                    <option value="{{ $key }}">{{ $label }} [{{ $key }}]</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">เลือกให้ตรง — เช่น "ใบลาพักร้อน" ใช้แม่แบบหนึ่ง "ใบลาคลอด" อีกแม่แบบ</p>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">ชื่อแม่แบบ</label>
            <input type="text" name="name" required maxlength="120"
                   placeholder="เช่น ใบลาพักร้อน v1"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">
                <span x-show="kind === 'docx'">ไฟล์ Word (.docx, ไม่เกิน 16 MB)</span>
                <span x-show="kind === 'image'">ภาพฟอร์ม (PNG/JPG, ไม่เกิน 16 MB)</span>
            </label>
            <input type="file" name="file" required
                   :accept="kind === 'docx' ? '.docx' : 'image/png,image/jpeg'"
                   class="w-full text-sm">
            <div x-show="kind === 'docx'" class="mt-2 text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded p-2 leading-relaxed">
                <strong>วิธีทำ .docx:</strong><br>
                1. เปิดฟอร์มใน MS Word หรือ Google Docs<br>
                2. ลบจุดไข่ปลา "...." ออก แทนที่ด้วย <code class="bg-white px-1 rounded">${employee_name}</code>, <code class="bg-white px-1 rounded">${leave_date_th}</code> ฯลฯ<br>
                3. Save as <code class="bg-white px-1 rounded">.docx</code> → อัปโหลด<br>
                4. หลังอัปโหลด ระบบจะแสดงรายการ placeholder ทั้งหมดให้คัดลอก
            </div>
        </div>

        <div class="flex justify-end gap-2 pt-2">
            <a href="{{ route('document-templates.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">ยกเลิก</a>
            <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">อัปโหลด</button>
        </div>
    </form>
</div>
@endsection
