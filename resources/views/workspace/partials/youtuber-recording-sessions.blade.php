<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
        <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            งานที่ร่วมถ่าย (Recording Sessions)
        </h3>
        @if(auth()->user()?->hasRole('admin'))
            <a href="{{ route('work.recording-sessions.index', ['month' => $month, 'year' => $year]) }}" class="text-xs text-indigo-600 hover:underline">จัดการ →</a>
        @else
            <span class="text-xs text-gray-400">แสดงงานถ่ายของเดือนนี้</span>
        @endif
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50/50 text-gray-500 text-[10px] uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-5 py-3">วันที่</th>
                    <th class="px-5 py-3">ชื่องาน</th>
                    <th class="px-5 py-3">เกม</th>
                    <th class="px-5 py-3">ร่วมถ่ายกับ</th>
                    <th class="px-5 py-3">Notes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recordingSessions as $s)
                    <tr class="hover:bg-indigo-50/30">
                        <td class="px-5 py-3 whitespace-nowrap font-medium text-gray-800">{{ $s->session_date->format('d M') }}</td>
                        <td class="px-5 py-3 font-semibold text-gray-900">{{ $s->title }}</td>
                        <td class="px-5 py-3 text-gray-600">{{ $s->game?->game_name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <div class="flex flex-wrap gap-1">
                                @foreach($s->youtubers as $y)
                                    @if($y->id !== $employee->id)
                                        <span class="inline-flex items-center px-2 py-0.5 bg-gray-100 text-gray-700 rounded-full text-[11px]">{{ $y->first_name }}</span>
                                    @endif
                                @endforeach
                                @if($s->youtubers->count() === 1)
                                    <span class="text-[11px] text-gray-400">ถ่ายคนเดียว</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-[12px] max-w-xs truncate">{{ $s->notes }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400 text-sm italic">ไม่มีงานถ่ายในเดือนนี้</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
