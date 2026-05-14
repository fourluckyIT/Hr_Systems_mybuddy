@extends('layouts.app')

@section('title', 'แม่แบบเอกสาร')

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">แม่แบบเอกสาร (Document Templates)</h1>
            <p class="text-sm text-gray-500 mt-1">อัปโหลดภาพฟอร์มและกำหนดตำแหน่งฟิลด์ — เวลาพิมพ์ระบบจะวางข้อมูลทับภาพให้อัตโนมัติ</p>
        </div>
        <a href="{{ route('document-templates.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">+ อัปโหลดแม่แบบใหม่</a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    @if(!$libreOfficeOk)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
            <strong>⚠️ ยังไม่ได้ติดตั้ง LibreOffice</strong> — แม่แบบ .docx ใช้พิมพ์จริงไม่ได้จนกว่าจะติดตั้ง
            <p class="text-xs mt-1">macOS: <code class="bg-amber-100 px-1 rounded">brew install --cask libreoffice</code> · Linux: <code class="bg-amber-100 px-1 rounded">sudo apt install libreoffice</code></p>
        </div>
    @endif

    <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold w-20">ชนิด</th>
                    <th class="px-4 py-3 text-left font-semibold">ชื่อ</th>
                    <th class="px-4 py-3 text-left font-semibold w-32">ประเภท</th>
                    <th class="px-4 py-3 text-left font-semibold w-40">Variant</th>
                    <th class="px-4 py-3 text-center font-semibold w-24">สถานะ</th>
                    <th class="px-4 py-3 text-right font-semibold w-32">การดำเนินการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($templates as $tpl)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        @if($tpl->kind === 'docx')
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 rounded">📄 .docx</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200 rounded">🖼 image</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-800">{{ $tpl->name }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $docTypes[$tpl->doc_type] ?? $tpl->doc_type }}</td>
                    <td class="px-4 py-3 text-gray-700 font-mono text-xs">{{ $tpl->variant_key ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        @if($tpl->is_active)
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full">เปิดใช้</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-600 border border-gray-300 rounded-full">ปิด</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('document-templates.edit', $tpl) }}" class="px-3 py-1 text-xs text-gray-700 border border-gray-200 hover:bg-gray-100 rounded">แก้ไข</a>
                        <form action="{{ route('document-templates.destroy', $tpl) }}" method="POST" class="inline">
                            @csrf @method('DELETE')
                            <button type="submit" onclick="return confirm('ลบแม่แบบนี้?')" class="px-3 py-1 text-xs text-rose-700 border border-rose-200 hover:bg-rose-50 rounded">ลบ</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-16 text-center text-sm text-gray-400">ยังไม่มีแม่แบบ — เริ่มอัปโหลดไฟล์แรกของคุณ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
