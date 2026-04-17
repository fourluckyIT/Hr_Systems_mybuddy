@extends('layouts.app')

@section('title', 'ปฏิทินหลักบริษัท')

@section('content')
@php
    // Serialize events to JSON for Alpine.js day-detail popup
    $eventsJson = collect($events)->map(function($dayEvents) {
        return collect($dayEvents)->map(function($ev) {
            return [
                'id' => $ev['id'],
                'type' => $ev['type'],
                'label' => $ev['label'],
                'color' => $ev['color'] ?? '',
            ];
        })->values()->toArray();
    })->toArray();
@endphp

<div class="max-w-full px-4 sm:px-6 lg:px-8 py-6"
    x-data="{
        selectedDate: '{{ old('scheduled_date', old('due_date', old('holiday_date', ''))) }}',
        showDayDetail: false,
        showActionPicker: false,
        showRecForm: {{ $errors->any() && old('_form') === 'recording' ? 'true' : 'false' }},
        showEditForm: {{ $errors->any() && old('_form') === 'edit_job' ? 'true' : 'false' }},
        showLeaveForm: {{ $errors->any() && old('_form') === 'holiday' ? 'true' : 'false' }},
        dayEvents: [],
        allEvents: {{ Js::from($eventsJson) }},

        openDay(dateStr) {
            this.selectedDate = dateStr;
            this.dayEvents = this.allEvents[dateStr] || [];
            this.showDayDetail = true;
        },
        getTypeLabel(type) {
            const labels = {
                'recording_job': '🎥 คิวถ่ายทำ',
                'edit_job': '✂️ งานตัดต่อ',
                'company_holiday': '🏢 วันหยุดบริษัท',
                'attendance_log': '📋 บันทึกการลา',
            };
            return labels[type] || type;
        },
        getTypeDot(type) {
            const dots = {
                'recording_job': 'bg-amber-400',
                'edit_job': 'bg-sky-400',
                'company_holiday': 'bg-purple-400',
                'attendance_log': 'bg-teal-400',
            };
            return dots[type] || 'bg-gray-400';
        },
        getTypeBg(type) {
            const bgs = {
                'recording_job': 'bg-amber-50 border-amber-200',
                'edit_job': 'bg-sky-50 border-sky-200',
                'company_holiday': 'bg-purple-50 border-purple-200',
                'attendance_log': 'bg-teal-50 border-teal-200',
            };
            return bgs[type] || 'bg-gray-50 border-gray-200';
        },
        formatDateThai(dateStr) {
            const d = new Date(dateStr);
            const days = ['อาทิตย์','จันทร์','อังคาร','พุธ','พฤหัสบดี','ศุกร์','เสาร์'];
            const months = ['มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
            return days[d.getDay()] + 'ที่ ' + d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
        },
        syncCalendarGutter() {
            const grid = document.getElementById('calendar-time-grid');
            const gutter = grid ? Math.max(0, grid.offsetWidth - grid.clientWidth) : 0;
            document.documentElement.style.setProperty('--calendar-scrollbar-gutter', `${gutter}px`);
        },
        init() {
            this.$nextTick(() => this.syncCalendarGutter());
            window.addEventListener('resize', () => this.syncCalendarGutter());
        }
    }">

    {{-- ============================================================ --}}
    {{-- HEADER (Week Navigation) — full width                        --}}
    {{-- ============================================================ --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">ปฏิทินหลักบริษัท</h1>
            <p class="text-sm text-gray-500">
                {{ $startDate->translatedFormat('d F') }} - {{ $endDate->translatedFormat('d F Y') }} — ตารางงานรายสัปดาห์
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('calendar.index', ['date' => $startDate->copy()->subWeek()->format('Y-m-d')]) }}"
               class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
            </a>
            <a href="{{ route('calendar.index') }}"
               class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-xs font-medium text-indigo-700 rounded-lg transition-colors">
                สัปดาห์นี้
            </a>
            <a href="{{ route('calendar.index', ['date' => $startDate->copy()->addWeek()->format('Y-m-d')]) }}"
               class="p-2 hover:bg-gray-100 rounded-lg transition-colors text-gray-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
            </a>
            <button @click="showActionPicker = true" class="ml-4 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-lg shadow-sm transition-colors">
                + เพิ่มรายการ
            </button>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- MAIN BODY: sidebar + time grid                               --}}
    {{-- ============================================================ --}}
    <div class="flex gap-4 items-start">

        {{-- ============================= --}}
        {{-- LEFT SIDEBAR                  --}}
        {{-- ============================= --}}
        <div class="w-64 flex-none space-y-5">

            {{-- Mini Calendar --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold text-gray-800">
                        {{ $currentDate->translatedFormat('F Y') }}
                    </h3>
                    <div class="flex gap-1">
                        <a href="{{ route('calendar.index', ['date' => $currentDate->copy()->subMonth()->format('Y-m-d')]) }}"
                           class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-xs">
                            ‹
                        </a>
                        <a href="{{ route('calendar.index', ['date' => $currentDate->copy()->addMonth()->format('Y-m-d')]) }}"
                           class="w-6 h-6 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 text-xs">
                            ›
                        </a>
                    </div>
                </div>

                {{-- Day-of-week headers --}}
                <div class="grid grid-cols-7 mb-1">
                    @foreach(['อา','จ','อ','พ','พฤ','ศ','ส'] as $wl)
                    <div class="text-center text-[9px] font-bold text-gray-400">{{ $wl }}</div>
                    @endforeach
                </div>

                {{-- Day cells --}}
                <div class="grid grid-cols-7 gap-y-0.5">
                    @foreach($miniCalendarDays as $md)
                    <a href="{{ route('calendar.index', ['date' => $md['date_str']]) }}"
                       class="flex flex-col items-center py-0.5 rounded-lg transition-colors group
                           {{ !$md['is_today'] ? 'hover:bg-gray-50' : '' }}">
                        {{-- Date number --}}
                        <span class="flex items-center justify-center w-7 h-7 text-[11px] rounded-full transition-colors
                            {{ $md['is_today'] ? 'bg-indigo-600 text-white font-bold' : '' }}
                            {{ $md['in_current_week'] && !$md['is_today'] ? 'bg-indigo-100 text-indigo-700 font-semibold' : '' }}
                            {{ !$md['is_current_month'] ? 'text-gray-300' : (!$md['is_today'] && !$md['in_current_week'] ? ($md['is_weekend'] ? 'text-gray-400' : 'text-gray-700') : '') }}">
                            {{ $md['date']->format('j') }}
                        </span>
                        {{-- Event dots --}}
                        @if(!empty($md['dots']) && $md['is_current_month'])
                        <div class="flex items-center gap-px mt-0.5 h-1.5">
                            @foreach(array_slice($md['dots'], 0, 3) as $dot)
                            <span class="w-1 h-1 rounded-full flex-none
                                {{ $dot === 'holiday'   ? 'bg-purple-400' : '' }}
                                {{ $dot === 'recording' ? 'bg-amber-400'  : '' }}
                                {{ $dot === 'edit'      ? 'bg-sky-400'    : '' }}
                            "></span>
                            @endforeach
                        </div>
                        @else
                        <div class="h-1.5"></div>
                        @endif
                    </a>
                    @endforeach
                </div>
            </div>

            {{-- Upcoming Events --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 bg-gray-50/80">
                    <h3 class="text-xs font-bold text-gray-700 tracking-wide uppercase">🗓 กำลังจะมาถึง</h3>
                    <p class="text-[10px] text-gray-400 mt-0.5">14 วันข้างหน้า</p>
                </div>

                @if($upcomingEvents->isEmpty())
                <div class="px-4 py-8 text-center">
                    <div class="text-2xl mb-2">📭</div>
                    <p class="text-xs text-gray-400">ไม่มีรายการที่กำลังจะมาถึง</p>
                </div>
                @else
                <div class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
                    @foreach($upcomingEvents as $ue)
                    @php
                        $isToday = $ue['date']->isToday();
                        $isTomorrow = $ue['date']->isTomorrow();
                        $daysAway = (int) now()->startOfDay()->diffInDays($ue['date']->startOfDay(), false);
                        if ($isToday) $dayLabel = 'วันนี้';
                        elseif ($isTomorrow) $dayLabel = 'พรุ่งนี้';
                        else $dayLabel = 'อีก ' . $daysAway . ' วัน';
                    @endphp
                    <a href="{{ route('calendar.index', ['date' => $ue['date']->format('Y-m-d')]) }}"
                       class="flex items-start gap-2.5 px-4 py-2.5 hover:bg-gray-50 transition-colors group">
                        <span class="w-2 h-2 rounded-full mt-1.5 flex-none {{ $ue['dot'] }}"></span>
                        <div class="min-w-0 flex-1">
                            <div class="text-[11px] font-semibold text-gray-800 truncate leading-snug group-hover:text-indigo-600 transition-colors">
                                {{ $ue['icon'] }} {{ $ue['label'] }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[9px] px-1.5 py-0.5 rounded-full {{ $ue['color'] }} font-medium">{{ $ue['sub'] }}</span>
                                <span class="text-[9px] text-gray-400">{{ $dayLabel }}</span>
                            </div>
                            <div class="text-[9px] text-gray-400 mt-px">{{ $ue['date']->translatedFormat('D j M') }}</div>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>

        </div>{{-- end sidebar --}}

        {{-- ============================= --}}
        {{-- MAIN: Weekly Time Grid        --}}
        {{-- ============================= --}}
        <div class="flex-1 min-w-0">

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col h-[800px]">

        {{-- Weekday Headers --}}
        <div class="flex border-b border-gray-200 bg-gray-50">
            <div class="w-16 flex-none border-r border-gray-200 py-3 text-center bg-gray-50">
                <span class="text-[10px] font-bold text-gray-400">GMT+7</span>
            </div>
            <div class="flex-1 flex" style="padding-right: var(--calendar-scrollbar-gutter, 0px);">
                @foreach($weekDays as $day)
                 <div class="flex-1 min-w-0 px-2 py-3 text-center border-r border-gray-200 last:border-r-0 cursor-pointer hover:bg-gray-100 transition-colors {{ $day['is_today'] ? 'bg-indigo-50' : '' }}"
                     @click="openDay('{{ $day['date_str'] }}')">
                    <div class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">{{ $day['date']->translatedFormat('D') }}</div>
                    <div class="text-xl mt-1 {{ $day['is_today'] ? 'w-8 h-8 mx-auto rounded-full bg-indigo-600 text-white flex items-center justify-center font-bold' : 'font-medium text-gray-900' }}">
                        {{ $day['date']->format('j') }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- All-Day Events Section --}}
        <div class="flex border-b border-gray-300 bg-white">
            <div class="w-16 flex-none border-r border-gray-200 flex items-center justify-center bg-gray-50">
                <span class="text-[10px] font-medium text-gray-500">All Day</span>
            </div>
            <div class="flex-1 flex" style="padding-right: var(--calendar-scrollbar-gutter, 0px);">
                @foreach($weekDays as $day)
                <div class="flex-1 min-w-0 border-r border-gray-100 p-1 min-h-[40px] last:border-r-0 cursor-pointer hover:bg-gray-50" @click="openDay('{{ $day['date_str'] }}')">
                    @php $dayEvents = collect($events[$day['date_str']] ?? [])->where('is_all_day', true); @endphp
                    @foreach($dayEvents as $ev)
                    <div class="block w-full max-w-full px-1.5 py-0.5 rounded mb-1 text-[10px] font-medium truncate {{ $ev['color'] }} border-l-2 text-left">
                        {{ $ev['label'] }}
                    </div>
                    @endforeach
                </div>
                @endforeach
            </div>
        </div>

        {{-- Scrollable Time Grid --}}
        <div class="flex-1 overflow-y-auto relative bg-white" id="calendar-time-grid">
            <div class="flex min-w-full">
                {{-- Time Scale --}}
                <div class="w-16 flex-none border-r border-gray-200 bg-white">
                    @for($i = 0; $i < 24; $i++)
                    <div class="h-16 relative">
                        <!-- visually offset the time text so it aligns with the border line -->
                        @if($i > 0)
                        <span class="text-[10px] text-gray-400 absolute block w-full text-center -top-2.5 font-medium">{{ sprintf("%02d:00", $i) }}</span>
                        @endif
                    </div>
                    @endfor
                </div>

                {{-- Grid Cells --}}
                <div class="flex-1 flex relative">
                    <!-- Horizontal lines for hours -->
                    <div class="absolute inset-0 pointer-events-none">
                        @for($i = 0; $i < 24; $i++)
                        <div class="h-16 border-b border-gray-100 w-full box-border"></div>
                        @endfor
                    </div>

                    <!-- Day Columns -->
                    @foreach($weekDays as $day)
                    <div class="flex-1 min-w-0 border-r border-gray-100 relative last:border-r-0 cursor-pointer group" @click="openDay('{{ $day['date_str'] }}')">
                        <div class="absolute inset-0 bg-transparent group-hover:bg-indigo-50/20 transition-colors pointer-events-none"></div>

                        @php 
                            $timeEvents = collect($events[$day['date_str']] ?? [])->where('is_all_day', false); 
                        @endphp
                        
                        @foreach($timeEvents as $ev)
                        @php
                            // Parse start time
                            $timeParts = explode(':', $ev['start_time']);
                            $hours = (int) $timeParts[0];
                            $mins = (int) $timeParts[1];
                            $topPx = ($hours * 64) + ($mins / 60 * 64); // 64px per hour
                            
                            $durMins = $ev['duration_minutes'];
                            $heightPx = ($durMins / 60 * 64);
                            // Ensure min height for visibility
                            if ($heightPx < 20) $heightPx = 20; 
                        @endphp
                        <div class="absolute inset-x-1 rounded-md text-[10px] p-1.5 shadow-sm overflow-hidden z-10 transition-transform hover:scale-[1.02] border border-opacity-50
                                    {{ $ev['color'] }}"
                             style="top: {{ $topPx }}px; height: {{ $heightPx }}px;"
                             @click.stop="openDay('{{ $day['date_str'] }}')">
                            <div class="font-bold leading-none truncate">{{ $ev['label'] }}</div>
                            <div class="opacity-75 mt-0.5">{{ $ev['start_time'] }}</div>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex items-center gap-5 mt-4 px-1">
        <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>คิวถ่ายทำ</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-2.5 h-2.5 rounded-full bg-sky-400 inline-block"></span>งานตัดต่อ</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-2.5 h-2.5 rounded-full bg-purple-400 inline-block"></span>วันหยุดบริษัท</span>
        <span class="flex items-center gap-1.5 text-xs text-gray-500"><span class="w-2.5 h-2.5 rounded-full bg-teal-400 inline-block"></span>ลา / อื่นๆ</span>
    </div>

        </div>{{-- end main grid wrapper --}}
    </div>{{-- end flex row --}}

{{-- ============================================================ --}}
{{-- DAY DETAIL POPUP (smooth slide-up)                            --}}
{{-- ============================================================ --}}
<div x-show="showDayDetail" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4"
     @click.self="showDayDetail = false">

    <div x-show="showDayDetail"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-4 scale-95"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden" @click.stop>

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-indigo-50 to-white">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-gray-900" x-text="formatDateThai(selectedDate)"></h3>
                    <p class="text-xs text-gray-400 mt-0.5">
                        <span x-text="dayEvents.length"></span> รายการ
                    </p>
                </div>
                <button @click="showDayDetail = false" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                </button>
            </div>
        </div>

        {{-- Event List --}}
        <div class="max-h-[360px] overflow-y-auto">
            {{-- No events state --}}
            <template x-if="dayEvents.length === 0">
                <div class="py-10 text-center">
                    <div class="text-4xl mb-3">📭</div>
                    <p class="text-sm text-gray-400">ไม่มีรายการในวันนี้</p>
                </div>
            </template>

            {{-- Event items --}}
            <template x-for="(ev, idx) in dayEvents" :key="idx">
                <div class="px-6 py-3 border-b border-gray-50 hover:bg-gray-50/50 transition-colors flex items-start gap-3">
                    <span class="w-3 h-3 rounded-full mt-0.5 flex-shrink-0" :class="getTypeDot(ev.type)"></span>
                    <div class="min-w-0 flex-1">
                        <div class="text-sm font-medium text-gray-800 leading-snug" x-text="ev.label"></div>
                        <div class="text-[10px] text-gray-400 mt-0.5" x-text="getTypeLabel(ev.type)"></div>
                    </div>
                    <template x-if="ev.type === 'recording_job' || ev.type === 'edit_job'">
                        <a :href="'{{ route('work.index') }}'" class="text-[10px] px-2 py-1 bg-indigo-50 text-indigo-600 rounded-md hover:bg-indigo-100 transition-colors flex-shrink-0 mt-0.5">
                            จัดการ →
                        </a>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer: Add Action --}}
        <div class="px-6 py-3 border-t border-gray-100 bg-gray-50/50">
            <button @click="showDayDetail = false; showActionPicker = true"
                class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition-colors shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                เพิ่มรายการในวันนี้
            </button>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- ACTION PICKER MODAL                                           --}}
{{-- ============================================================ --}}
<div x-show="showActionPicker" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4"
     @click.self="showActionPicker = false">
    <div x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden" @click.stop>
        <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
            <h3 class="font-bold text-gray-900">เพิ่มรายการวันที่ <span class="text-indigo-600" x-text="selectedDate"></span></h3>
            <button @click="showActionPicker = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <div class="p-4 space-y-2">
            <button @click="showActionPicker = false; showRecForm = true" class="w-full flex items-center gap-3 p-3 text-left rounded-xl hover:bg-amber-50 border border-transparent hover:border-amber-200 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-amber-100 group-hover:bg-amber-200 flex items-center justify-center text-amber-700 text-lg">🎥</div>
                <div>
                    <div class="font-bold text-gray-900 group-hover:text-amber-900 text-sm">นัดหมายคิวถ่ายทำ</div>
                    <div class="text-[10px] text-gray-500">สร้างงานใน Work Center ทันที</div>
                </div>
            </button>
            <button @click="showActionPicker = false; showEditForm = true" class="w-full flex items-center gap-3 p-3 text-left rounded-xl hover:bg-sky-50 border border-transparent hover:border-sky-200 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-sky-100 group-hover:bg-sky-200 flex items-center justify-center text-sky-700 text-lg">✂️</div>
                <div>
                    <div class="font-bold text-gray-900 group-hover:text-sky-900 text-sm">กำหนดงานตัดต่อ</div>
                    <div class="text-[10px] text-gray-500">มอบหมายงานตัดต่อให้ทีมงาน</div>
                </div>
            </button>
            <button @click="showActionPicker = false; showLeaveForm = true" class="w-full flex items-center gap-3 p-3 text-left rounded-xl hover:bg-purple-50 border border-transparent hover:border-purple-200 transition-colors group">
                <div class="w-10 h-10 rounded-full bg-purple-100 group-hover:bg-purple-200 flex items-center justify-center text-purple-700 text-lg">🏢</div>
                <div>
                    <div class="font-bold text-gray-900 group-hover:text-purple-900 text-sm">เพิ่มวันหยุดบริษัท</div>
                    <div class="text-[10px] text-gray-500">เพิ่มวันหยุดเข้าระบบของทุกคน</div>
                </div>
            </button>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- FORM MODALS                                                    --}}
{{-- ============================================================ --}}

{{-- Add Company Holiday --}}
<div x-show="showLeaveForm" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showLeaveForm = false">
    <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-xl w-full max-w-sm" @click.stop>
        <form action="{{ route('settings.holidays.add') }}" method="POST" class="p-6">
            @csrf
            <input type="hidden" name="_form" value="holiday">
            <input type="hidden" name="_redirect" value="{{ route('calendar.index', ['date' => $currentDate->format('Y-m-d')]) }}">
            <h3 class="font-bold text-lg mb-4 text-purple-900 flex items-center gap-2">🏢 เพิ่มวันหยุดบริษัท</h3>
            @if($errors->any() && old('_form') === 'holiday')
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <ul class="text-xs text-red-700 list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">วันที่</label>
                    <input type="date" name="holiday_date" x-model="selectedDate" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-50 text-gray-500 text-sm font-bold">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">ชื่อวันหยุด</label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="เช่น วันปีใหม่" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500 text-sm">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="showLeaveForm = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700">บันทึกวันหยุด</button>
            </div>
        </form>
    </div>
</div>

{{-- Add Recording Job --}}
<div x-show="showRecForm" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showRecForm = false">
    <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
        <div class="p-6">
            <h3 class="font-bold text-lg mb-4 text-amber-900 flex items-center gap-2">🎥 นัดหมายคิวถ่ายทำ</h3>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                ฟีเจอร์คิวถ่ายถูกปิดใช้งานชั่วคราวตาม workflow ใหม่ ให้ใช้ Work Pipeline เฉพาะงานตัดต่อแทน
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="showRecForm = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ปิด</button>
            </div>
        </div>
    </div>
</div>

{{-- Add Edit Job --}}
<div x-show="showEditForm" x-cloak
     x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="showEditForm = false">
    <div x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
         class="bg-white rounded-2xl shadow-xl w-full max-w-lg" @click.stop>
        <form action="{{ route('work.editing-job.store') }}" method="POST" class="p-6">
            @csrf
            <input type="hidden" name="_form" value="editing_job">
            <input type="hidden" name="_redirect" value="{{ route('calendar.index', ['date' => $currentDate->format('Y-m-d')]) }}">
            <h3 class="font-bold text-lg mb-4 text-sky-900 flex items-center gap-2">✂️ มอบหมายงานตัดต่อ</h3>
            @if($errors->any() && old('_form') === 'editing_job')
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <ul class="text-xs text-red-700 list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
            @endif
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 mb-1">ชื่องาน (Job Name) *</label>
                    <input type="text" name="job_name" value="{{ old('job_name') }}" required placeholder="เช่น ตัดต่อคลิป XXX" class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-sky-500">
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 mb-1">เกม (Game) *</label>
                    <select name="game_id" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-sky-500">
                        <option value="">-- เลือกเกม --</option>
                        @foreach($games as $game)
                            <option value="{{ $game->id }}" {{ old('game_id') == $game->id ? 'selected' : '' }}>{{ $game->game_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-bold text-gray-500 mb-1">คนตัดต่อ (Editor)</label>
                    <select name="assigned_to" required class="w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-sky-500">
                        <option value="">-- เลือกผู้รับผิดชอบ --</option>
                        @foreach($employees as $emp)
                            <option value="{{ $emp->id }}" {{ old('assigned_to') == $emp->id ? 'selected' : '' }}>
                                {{ $emp->nickname ?: $emp->first_name }} ({{ $emp->payroll_mode }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-1">วันครบกำหนด (Deadline) *</label>
                    <input type="date" name="deadline_date" x-model="selectedDate" required class="w-full px-3 py-2 border rounded-lg text-sm bg-gray-50">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-6">
                <button type="button" @click="showEditForm = false" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" class="px-4 py-2 text-sm bg-sky-600 text-white rounded-lg hover:bg-sky-700">มอบหมายงาน</button>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
