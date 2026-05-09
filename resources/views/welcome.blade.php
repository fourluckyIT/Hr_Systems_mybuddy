@extends('layouts.app')

@section('title', 'Welcome')

@section('content')
@php
    $user = auth()->user();
    $hour = now()->hour;
    $greeting = $hour < 12 ? 'อรุณสวัสดิ์' : ($hour < 18 ? 'สวัสดีบ่ายนี้' : 'สวัสดียามค่ำ');
    $primaryCta = $isAdmin
        ? ['url' => route('employees.index'), 'label' => 'เข้าสู่แดชบอร์ด']
        : ['url' => route('workspace.my'), 'label' => 'ไปที่ Workspace ของฉัน'];
    $nextHoliday = $upcomingHolidays->first();
@endphp

<div class="min-h-[78vh] flex items-center">
    <div class="w-full grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">

        {{-- LEFT — Hero --}}
        <div class="lg:col-span-7 space-y-6">
            <p class="text-sm font-semibold tracking-widest text-indigo-500 uppercase">{{ $greeting }}</p>

            <h1 class="text-5xl md:text-6xl font-black text-gray-900 leading-[1.05]">
                Welcome,<br>
                <span class="text-indigo-600">{{ $user->name }}</span>
            </h1>

            <p class="text-lg text-amber-500 font-bold tracking-wide">
                xHR Payroll System
            </p>

            <p class="text-sm text-gray-500 max-w-md leading-relaxed">
                ระบบจัดการเงินเดือนและทรัพยากรบุคคล —
                ตรวจสอบงานวันนี้, ดูวันหยุดที่กำลังจะมาถึง,
                และจัดการคำขอลาได้จากที่นี่
            </p>

            <div class="flex items-center gap-3 pt-2">
                <a href="{{ $primaryCta['url'] }}"
                   class="inline-flex items-center gap-2 px-7 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-full shadow-sm transition">
                    {{ $primaryCta['label'] }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                </a>
                <a href="{{ route('calendar.index') }}"
                   class="text-sm font-semibold text-gray-500 hover:text-indigo-600 transition">
                    ดูปฏิทิน →
                </a>
            </div>
        </div>

        {{-- RIGHT — Info card --}}
        <div class="lg:col-span-5 space-y-3">

            {{-- Warnings (only when present, compact) --}}
            @if($warnings->isNotEmpty())
                <div class="space-y-1.5">
                    @foreach($warnings as $w)
                        <a href="{{ $w['link'] ?? '#' }}"
                           class="flex items-center gap-3 px-4 py-2.5 bg-red-50 border border-red-100 hover:border-red-300 rounded-xl transition group">
                            <span class="text-base">{{ $w['icon'] }}</span>
                            <span class="flex-grow text-xs text-red-700 leading-snug">{!! $w['message'] !!}</span>
                            <svg class="w-3.5 h-3.5 text-red-400 group-hover:translate-x-0.5 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Next holiday hero card --}}
            @if($nextHoliday)
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-{{ $nextHoliday['color'] }}-50 via-white to-{{ $nextHoliday['color'] }}-50 border border-{{ $nextHoliday['color'] }}-100 p-6">
                    <div class="absolute -right-8 -top-8 w-32 h-32 rounded-full bg-{{ $nextHoliday['color'] }}-100 opacity-50"></div>
                    <div class="absolute -right-4 -bottom-4 w-20 h-20 rounded-full bg-{{ $nextHoliday['color'] }}-200 opacity-30"></div>

                    <div class="relative">
                        <p class="text-[10px] font-bold tracking-widest text-{{ $nextHoliday['color'] }}-500 uppercase mb-3">วันหยุดถัดไป</p>

                        <div class="flex items-baseline gap-2 mb-1">
                            <span class="text-6xl font-black text-{{ $nextHoliday['color'] }}-700 leading-none">
                                {{ $nextHoliday['days_until'] === 0 ? '0' : $nextHoliday['days_until'] }}
                            </span>
                            <span class="text-sm font-bold text-{{ $nextHoliday['color'] }}-600">
                                @if($nextHoliday['days_until'] === 0) วันนี้
                                @elseif($nextHoliday['days_until'] === 1) วัน · พรุ่งนี้
                                @else วัน
                                @endif
                            </span>
                        </div>

                        <p class="text-base font-bold text-gray-900 mt-2">{{ $nextHoliday['icon'] }} {{ $nextHoliday['name'] }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $nextHoliday['date']->locale('th')->isoFormat('dddd D MMMM YYYY') }}
                            · {{ $nextHoliday['type_name'] }}
                        </p>

                        @if($holidaysThisMonth > 1)
                            <p class="text-[11px] text-gray-400 mt-3 pt-3 border-t border-{{ $nextHoliday['color'] }}-100">
                                เดือนนี้มีวันหยุดทั้งหมด <strong class="text-{{ $nextHoliday['color'] }}-700">{{ $holidaysThisMonth }}</strong> วัน
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Compact upcoming list --}}
                @if($upcomingHolidays->count() > 1)
                    <div class="px-1 space-y-0">
                        @foreach($upcomingHolidays->skip(1)->take(3) as $h)
                            <div class="flex items-center gap-3 py-2 text-xs">
                                <span class="w-1.5 h-1.5 rounded-full bg-{{ $h['color'] }}-400 flex-shrink-0"></span>
                                <span class="text-gray-700 flex-grow truncate">{{ $h['name'] }}</span>
                                <span class="text-gray-400 flex-shrink-0">{{ $h['date']->locale('th')->isoFormat('D MMM') }}</span>
                                <span class="text-gray-400 text-[10px] w-12 text-right flex-shrink-0">อีก {{ $h['days_until'] }} วัน</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @else
                <div class="rounded-3xl border-2 border-dashed border-gray-200 p-10 text-center">
                    <div class="text-3xl mb-2 opacity-50">📅</div>
                    <p class="text-sm text-gray-400">ไม่มีวันหยุดในช่วงถัดไป</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
