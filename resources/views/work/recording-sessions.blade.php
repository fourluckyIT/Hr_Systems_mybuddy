@extends('layouts.app')
@section('title', 'Recording Sessions')

@section('content')
@php
    $monthNames = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $prevMonth = $month == 1 ? 12 : $month - 1;
    $prevYear = $month == 1 ? $year - 1 : $year;
    $nextMonth = $month == 12 ? 1 : $month + 1;
    $nextYear = $month == 12 ? $year + 1 : $year;
@endphp
<div x-data="{
    modal: false,
    isEditing: false,
    form: { id: '', session_date: '{{ now()->toDateString() }}', title: '', game_id: '', notes: '', youtuber_ids: [] },
    openAdd() {
        this.isEditing = false;
        this.form = { id: '', session_date: '{{ now()->toDateString() }}', title: '', game_id: '', notes: '', youtuber_ids: [] };
        this.modal = true;
    },
    openEdit(s) {
        this.isEditing = true;
        this.form = {
            id: s.id,
            session_date: s.session_date,
            title: s.title,
            game_id: s.game_id ? String(s.game_id) : '',
            notes: s.notes || '',
            youtuber_ids: (s.youtubers || []).map(y => String(y.id))
        };
        this.modal = true;
    }
}" class="max-w-6xl mx-auto px-4 py-6 space-y-4">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">📹 Recording Sessions</h1>
            <p class="text-sm text-gray-500">Track งานถ่ายที่ YouTuber ร่วมถ่าย (ไม่เกี่ยวกับงานตัดต่อ)</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('work.recording-sessions.index', ['month' => $prevMonth, 'year' => $prevYear]) }}" class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&larr;</a>
            <span class="px-4 py-1 bg-indigo-50 text-indigo-700 rounded-lg font-semibold text-sm">{{ $monthNames[$month] }} {{ $year + 543 }}</span>
            <a href="{{ route('work.recording-sessions.index', ['month' => $nextMonth, 'year' => $nextYear]) }}" class="px-3 py-1 bg-gray-100 rounded-lg text-sm hover:bg-gray-200">&rarr;</a>
            <button @click="openAdd()" class="ml-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">+ เพิ่มรายการถ่าย</button>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 rounded-lg">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-[11px] uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-4 py-3 text-left">วันที่</th>
                    <th class="px-4 py-3 text-left">ชื่องาน</th>
                    <th class="px-4 py-3 text-left">เกม</th>
                    <th class="px-4 py-3 text-left">YouTubers ที่ร่วมถ่าย</th>
                    <th class="px-4 py-3 text-left">Notes</th>
                    <th class="px-4 py-3 text-right w-28">จัดการ</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($sessions as $s)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-800">{{ $s->session_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $s->title }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $s->game?->game_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($s->youtubers as $y)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-[11px] font-semibold">
                                        {{ $y->first_name }} {{ $y->last_name }}
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-[12px] max-w-xs truncate">{{ $s->notes }}</td>
                        <td class="px-4 py-3 text-right">
                            <button @click='openEdit(@json($s->load("youtubers")))' class="text-xs text-indigo-600 hover:underline">แก้ไข</button>
                            <form action="{{ route('work.recording-sessions.destroy', $s) }}" method="POST" class="inline" onsubmit="return confirm('ลบรายการนี้?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-600 hover:underline ml-2">ลบ</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">ไม่มีรายการถ่ายในเดือนนี้</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Modal -->
    <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="modal = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl mx-4">
            <form :action="isEditing ? `{{ url('work/recording-sessions') }}/${form.id}` : '{{ route('work.recording-sessions.store') }}'" method="POST">
                @csrf
                <template x-if="isEditing"><input type="hidden" name="_method" value="PATCH"></template>

                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h3 class="font-bold text-gray-900" x-text="isEditing ? 'แก้ไขรายการถ่าย' : 'เพิ่มรายการถ่าย'"></h3>
                    <button type="button" @click="modal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">วันที่ถ่าย *</label>
                            <input type="date" name="session_date" x-model="form.session_date" required class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">เกม</label>
                            <select name="game_id" x-model="form.game_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="">— ไม่ระบุ —</option>
                                @foreach($games as $g)
                                    <option value="{{ $g->id }}">{{ $g->game_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">ชื่องาน / หัวข้อ *</label>
                        <input type="text" name="title" x-model="form.title" required class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="เช่น Live Stream vs John, EP.5">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">YouTubers ที่ร่วมถ่าย * (เลือกได้หลายคน)</label>
                        <div class="border rounded-lg p-2 max-h-40 overflow-y-auto space-y-1">
                            @foreach($youtubers as $y)
                                <label class="flex items-center gap-2 text-sm cursor-pointer hover:bg-gray-50 px-2 py-1 rounded">
                                    <input type="checkbox" name="youtuber_ids[]" value="{{ $y->id }}" x-model="form.youtuber_ids">
                                    <span>{{ $y->first_name }} {{ $y->last_name }}</span>
                                </label>
                            @endforeach
                            @if($youtubers->isEmpty())
                                <div class="text-xs text-gray-400 px-2 py-1">ยังไม่มี YouTuber ในระบบ</div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                        <textarea name="notes" x-model="form.notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="หมายเหตุเพิ่มเติม..."></textarea>
                    </div>
                </div>

                <div class="px-5 py-4 border-t bg-gray-50 flex justify-end gap-2">
                    <button type="button" @click="modal = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">ยกเลิก</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
