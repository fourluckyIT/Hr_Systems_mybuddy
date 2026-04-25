@php
    $cal = $ownerCalendar ?? [];
    $miniCalendarDays = $cal['miniCalendarDays'] ?? [];
    $upcomingEvents   = $cal['upcomingEvents']   ?? collect();
    $calendarDate     = $cal['calendarDate']     ?? now();

    $dayTypeLabelsLocal = [
        'sick_leave'     => 'ลาป่วย',
        'personal_leave' => 'ลากิจ',
        'vacation_leave' => 'ลาพักร้อน',
        'lwop'           => 'LWOP',
        'ot_full_day'    => 'OT เต็มวัน',
    ];
    $dayTypeBgLocal = [
        'sick_leave'     => 'bg-blue-200',
        'personal_leave' => 'bg-yellow-200',
        'vacation_leave' => 'bg-teal-200',
        'lwop'           => 'bg-red-200',
        'ot_full_day'    => 'bg-indigo-200',
    ];
    $dotColors = [
        'holiday'   => 'bg-purple-400',
        'leave'     => 'bg-blue-400',
        'ot'        => 'bg-indigo-400',
        'edit'      => 'bg-sky-400',
        'recording' => 'bg-amber-400',
    ];
    $eventBg = [
        'purple' => 'bg-purple-50 border-purple-200 text-purple-700',
        'blue'   => 'bg-blue-50 border-blue-200 text-blue-700',
        'indigo' => 'bg-indigo-50 border-indigo-200 text-indigo-700',
        'sky'    => 'bg-sky-50 border-sky-200 text-sky-700',
        'amber'  => 'bg-amber-50 border-amber-200 text-amber-700',
    ];
    $upcomingDotColors = [
        'purple' => 'bg-purple-400',
        'blue'   => 'bg-blue-400',
        'indigo' => 'bg-indigo-400',
        'sky'    => 'bg-sky-400',
        'amber'  => 'bg-amber-400',
    ];
    $upcomingTagColors = [
        'purple' => 'bg-purple-100 text-purple-700',
        'blue'   => 'bg-blue-100 text-blue-700',
        'indigo' => 'bg-indigo-100 text-indigo-700',
        'sky'    => 'bg-sky-100 text-sky-700',
        'amber'  => 'bg-amber-100 text-amber-700',
    ];
@endphp

<div class="bg-white rounded-xl shadow-sm border overflow-hidden"
     x-data="{ selectedDay: null, selectedEvents: [] }">

    {{-- Header --}}
    <div class="px-5 py-3.5 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                <svg class="w-4.5 h-4.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <h3 class="font-bold text-sm text-gray-800">ปฏิทินของฉัน</h3>
                <p class="text-[10px] text-gray-400 font-medium">{{ $calendarDate->translatedFormat('F Y') }} — ตารางงานและกำหนดการ</p>
            </div>
        </div>
        <a href="{{ route('calendar.index') }}" class="text-[10px] text-indigo-600 hover:text-indigo-800 font-bold uppercase tracking-wider flex items-center gap-1">
            ดูปฏิทินบริษัท
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </a>
    </div>

    <div class="flex flex-col lg:flex-row">
        {{-- Calendar Grid --}}
        <div class="flex-1 p-4">
            {{-- Day-of-week headers --}}
            <div class="grid grid-cols-7 mb-2">
                @foreach(['อา','จ','อ','พ','พฤ','ศ','ส'] as $idx => $wl)
                <div class="text-center text-[10px] font-bold {{ in_array($idx, [0, 6]) ? 'text-red-400' : 'text-gray-400' }} uppercase tracking-wider py-1">{{ $wl }}</div>
                @endforeach
            </div>

            {{-- Day cells --}}
            <div class="grid grid-cols-7 gap-px bg-gray-100 rounded-xl overflow-hidden border border-gray-100">
                @foreach($miniCalendarDays as $md)
                @php
                    $hasEvents = !empty($md['events']);
                    $isToday = $md['is_today'];
                    $isCurrentMonth = $md['is_current_month'];
                    $isWeekend = $md['is_weekend'];
                    $attBg = isset($md['att_status']) ? ($dayTypeBgLocal[$md['att_status']] ?? '') : '';
                @endphp
                <button type="button"
                    @click="selectedDay = '{{ $md['date_str'] }}'; selectedEvents = {{ Js::from($md['events']) }}"
                    class="relative flex flex-col items-center py-2 px-1 min-h-[52px] transition-all
                        {{ $isCurrentMonth ? 'bg-white' : 'bg-gray-50' }}
                        {{ $hasEvents ? 'cursor-pointer hover:bg-indigo-50' : 'cursor-default' }}
                        {{ $isToday ? 'ring-2 ring-inset ring-indigo-400' : '' }}"
                    :class="selectedDay === '{{ $md['date_str'] }}' ? 'bg-indigo-50' : ''">

                    {{-- Date number --}}
                    <span class="flex items-center justify-center w-7 h-7 rounded-full text-[12px] font-semibold leading-none transition-colors
                        {{ $isToday ? 'bg-indigo-600 text-white' : '' }}
                        {{ !$isToday && $attBg ? $attBg . ' font-bold' : '' }}
                        {{ !$isCurrentMonth ? 'text-gray-300' : (!$isToday && !$attBg ? ($isWeekend ? 'text-red-400' : 'text-gray-700') : '') }}">
                        {{ $md['date']->format('j') }}
                    </span>

                    {{-- Event dots --}}
                    @if(!empty($md['dots']) && $isCurrentMonth)
                    <div class="flex items-center gap-0.5 mt-1">
                        @foreach(array_slice($md['dots'], 0, 4) as $dot)
                        <span class="w-1.5 h-1.5 rounded-full flex-none {{ $dotColors[$dot] ?? 'bg-gray-400' }}"></span>
                        @endforeach
                    </div>
                    @endif

                    {{-- Holiday indicator --}}
                    @if(in_array('holiday', $md['dots'] ?? []) && $isCurrentMonth)
                    <div class="absolute top-0.5 right-0.5 w-1.5 h-1.5 bg-purple-500 rounded-full"></div>
                    @endif
                </button>
                @endforeach
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-3 mt-3 px-1">
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-purple-400"></span>
                    <span class="text-[9px] text-gray-400 font-medium">วันหยุด</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                    <span class="text-[9px] text-gray-400 font-medium">ลา</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-indigo-400"></span>
                    <span class="text-[9px] text-gray-400 font-medium">OT</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span>
                    <span class="text-[9px] text-gray-400 font-medium">งานตัดต่อ</span>
                </div>
                <div class="flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span class="text-[9px] text-gray-400 font-medium">ถ่ายทำ</span>
                </div>
            </div>

            {{-- Selected day detail --}}
            <div x-show="selectedDay && selectedEvents.length > 0" x-cloak x-transition
                 class="mt-3 p-3 bg-indigo-50/50 rounded-xl border border-indigo-100">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-[11px] font-bold text-indigo-700 uppercase tracking-wider" x-text="selectedDay"></h4>
                    <button @click="selectedDay = null; selectedEvents = []" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="space-y-1.5">
                    <template x-for="ev in selectedEvents" :key="ev.label">
                        <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg border text-[11px] font-medium"
                             :class="{
                                'bg-purple-50 border-purple-200 text-purple-700': ev.color === 'purple',
                                'bg-blue-50 border-blue-200 text-blue-700': ev.color === 'blue',
                                'bg-indigo-50 border-indigo-200 text-indigo-700': ev.color === 'indigo',
                                'bg-sky-50 border-sky-200 text-sky-700': ev.color === 'sky',
                                'bg-amber-50 border-amber-200 text-amber-700': ev.color === 'amber',
                             }">
                            <span x-text="ev.label"></span>
                            <template x-if="ev.status">
                                <span class="ml-auto px-1.5 py-0.5 rounded text-[9px] font-bold uppercase"
                                      :class="ev.status === 'approved' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                      x-text="ev.status"></span>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Upcoming Events Sidebar --}}
        <div class="w-full lg:w-64 border-t lg:border-t-0 lg:border-l border-gray-100 bg-gray-50/30">
            <div class="px-4 py-3 border-b border-gray-100">
                <h4 class="text-[10px] font-bold text-gray-500 uppercase tracking-widest">🗓 กำลังจะมาถึง</h4>
                <p class="text-[9px] text-gray-400 mt-0.5">14 วันข้างหน้า</p>
            </div>

            @if($upcomingEvents->isEmpty())
            <div class="px-4 py-8 text-center">
                <div class="text-2xl mb-2">📭</div>
                <p class="text-[10px] text-gray-400">ไม่มีรายการที่กำลังจะมาถึง</p>
            </div>
            @else
            <div class="divide-y divide-gray-50 max-h-[360px] overflow-y-auto">
                @foreach($upcomingEvents as $ue)
                @php
                    $isToday = $ue['date']->isToday();
                    $isTomorrow = $ue['date']->isTomorrow();
                    $daysAway = (int) now()->startOfDay()->diffInDays($ue['date']->startOfDay(), false);
                    if ($isToday) $dayLabel = 'วันนี้';
                    elseif ($isTomorrow) $dayLabel = 'พรุ่งนี้';
                    else $dayLabel = 'อีก ' . $daysAway . ' วัน';
                @endphp
                <div class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-white transition-colors">
                    <span class="w-2 h-2 rounded-full mt-1.5 flex-none {{ $upcomingDotColors[$ue['color']] ?? 'bg-gray-400' }}"></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] font-semibold text-gray-800 truncate leading-snug">
                            {{ $ue['icon'] }} {{ $ue['label'] }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-[9px] px-1.5 py-0.5 rounded-full {{ $upcomingTagColors[$ue['color']] ?? 'bg-gray-100 text-gray-600' }} font-medium">{{ $ue['sub'] }}</span>
                            <span class="text-[9px] text-gray-400">{{ $dayLabel }}</span>
                        </div>
                        <div class="text-[9px] text-gray-400 mt-px">{{ $ue['date']->translatedFormat('D j M') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
