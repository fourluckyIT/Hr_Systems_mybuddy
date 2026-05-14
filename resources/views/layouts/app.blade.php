<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'xHR Payroll') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .th-font { font-family: 'Sarabun', 'Noto Sans Thai', sans-serif; }
    </style>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-gray-50 th-font text-gray-800 min-h-screen">
    @php
        $authUser = auth()->user();
        $isAdmin = $authUser?->hasRole('admin') ?? false;
        $isOwnerOnly = ($authUser?->hasRole('owner') ?? false) && !$isAdmin;
        $myEmployee = $authUser?->employee;
    @endphp

    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex justify-between h-14">
                <div class="flex items-center space-x-6">
                    <a href="{{ route('welcome') }}" class="text-lg font-bold text-indigo-600">xHR Payroll</a>

                    @php
                        $navLink = 'text-sm text-gray-600 hover:text-indigo-600';
                        $navActive = 'text-indigo-600 font-semibold';
                        $dropItem = 'block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600';
                    @endphp

                    @if($isOwnerOnly)
                        <a href="{{ route('workspace.my') }}" class="{{ $navLink }} {{ request()->routeIs('workspace.*') ? $navActive : '' }}">My Workspace</a>
                        <a href="{{ route('portal.index') }}" class="{{ $navLink }} {{ request()->routeIs('portal.*') || request()->routeIs('leave.*') ? $navActive : '' }}">📄 ศูนย์เอกสาร</a>
                        <a href="{{ route('calendar.index') }}" class="{{ $navLink }} {{ request()->routeIs('calendar.*') ? $navActive : '' }}">ปฏิทินบริษัท</a>
                    @elseif($isAdmin)
                        {{-- Primary (daily use) --}}
                        <a href="{{ route('employees.index') }}" class="{{ $navLink }} {{ request()->routeIs('employees.*') ? $navActive : '' }}">พนักงาน</a>
                        <a href="{{ route('work.index') }}" class="{{ $navLink }} {{ request()->routeIs('work.*') ? $navActive : '' }}">WORK Center</a>
                        <a href="{{ route('leave-management.index') }}" class="{{ $navLink }} {{ request()->routeIs('leave-management.*') ? $navActive : '' }}">สิทธิวันลา (Batch)</a>
                        <a href="{{ route('payroll-batches.index') }}" class="{{ $navLink }} {{ request()->routeIs('payroll-batches.*') ? $navActive : '' }}">รอบบิลเงินเดือน</a>
                        <a href="{{ route('portal.index') }}" class="{{ $navLink }} {{ request()->routeIs('portal.*') || request()->routeIs('leave.*') ? $navActive : '' }}">📄 ศูนย์เอกสาร</a>
                        <a href="{{ route('company.finance') }}" class="{{ $navLink }} {{ request()->routeIs('company.*') || request()->routeIs('expense-tracker.*') ? $navActive : '' }}">การเงิน</a>

                        {{-- รายงาน dropdown (ERP Style) --}}
                        @php $reportsActive = request()->routeIs('calendar.*') || request()->routeIs('annual.*') || request()->routeIs('audit-logs.*') || request()->routeIs('leave-management.*') || request()->routeIs('expense-tracker.*'); @endphp
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button @click="open = !open" class="{{ $navLink }} flex items-center gap-1 {{ $reportsActive ? $navActive : '' }}">
                                รายงาน
                                <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95"
                                 class="absolute left-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-gray-100">

                                <!-- ปฏิทินบริษัท -->
                                <a href="{{ route('calendar.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-sky-50 text-sky-600 rounded-lg group-hover:bg-sky-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-sky-700">ปฏิทินบริษัท</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">ตารางงานรายสัปดาห์/เดือน, วันหยุด, คิวถ่ายทำ</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- สรุปรายปี -->
                                <a href="{{ route('annual.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-emerald-700">สรุปรายปี</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">ภาพรวมเงินเดือน, OT, ลา ตลอดทั้งปี</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- จัดการวันลา -->
                                <a href="{{ route('leave-management.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-teal-50 text-teal-600 rounded-lg group-hover:bg-teal-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-teal-700">จัดการวันลา (Batch)</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">อนุมัติ/ปรับยอด/ดูสิทธิวันลาคงเหลือรายคน</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- รายรับ-จ่าย Tracker -->
                                <a href="{{ route('expense-tracker.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-amber-50 text-amber-600 rounded-lg group-hover:bg-amber-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-amber-700">รายรับ-จ่าย (Tracker)</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">บันทึกและสรุปรายรับ/รายจ่ายของบริษัท</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- Audit Log -->
                                <a href="{{ route('audit-logs.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-slate-50 text-slate-600 rounded-lg group-hover:bg-slate-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-slate-700">Audit Log</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">ประวัติการแก้ไขข้อมูลทั้งระบบ</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>

                        {{-- ตั้งค่า dropdown (ERP Style) --}}
                        @php $settingsActive = request()->routeIs('settings.*'); @endphp
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button @click="open = !open" class="{{ $navLink }} flex items-center gap-1 {{ $settingsActive ? $navActive : '' }}">
                                ตั้งค่า
                                <svg class="w-3 h-3 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" 
                                 class="absolute left-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden divide-y divide-gray-100">
                                
                                <!-- Master Data (รวมข้อมูลบริษัท, แผนก, ตำแหน่ง, วันลา/วันหยุด ฯลฯ) -->
                                <a href="{{ route('settings.master-data') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-emerald-50 text-emerald-600 rounded-lg group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-emerald-700">Master Data</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">บริษัท, แผนก/ตำแหน่ง, รายการเงินเดือน, วันลา/วันหยุด</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- Calculation -->
                                <a href="{{ route('settings.rules') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-amber-50 text-amber-600 rounded-lg group-hover:bg-amber-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-amber-700">กติกาคำนวณ (Rules)</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">ตั้งเพดานประกันสังคม, หักมาสาย, เรทโอที</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- Performance -->
                                <a href="{{ route('settings.bonus.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-rose-50 text-rose-600 rounded-lg group-hover:bg-rose-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-rose-700">Bonus Manager</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">การตั้งเป้าหมาย, รอบโบนัสและ Tier ต่างๆ</div>
                                        </div>
                                    </div>
                                </a>

                                <!-- Document Templates -->
                                <a href="{{ route('document-templates.index') }}" class="block p-4 hover:bg-slate-50 transition-colors group">
                                    <div class="flex items-start gap-3">
                                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <div>
                                            <div class="text-sm font-bold text-gray-800 group-hover:text-indigo-700">แม่แบบเอกสาร</div>
                                            <div class="text-[11px] text-gray-500 mt-0.5">อัปโหลดภาพฟอร์ม & กำหนดตำแหน่งฟิลด์สำหรับการพิมพ์</div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center space-x-3">
                    {{-- OT Request quick link --}}
                    @if($authUser)
                        @if($isAdmin)
                            <a href="{{ route('ot.inbox') }}" class="text-xs text-gray-500 hover:text-indigo-600 {{ request()->routeIs('ot.inbox') ? 'text-indigo-600 font-semibold' : '' }}" title="OT Inbox">📥 จัดการ OT</a>
                        @else
                            <a href="{{ route('ot.request') }}" class="text-xs text-gray-500 hover:text-indigo-600 {{ request()->routeIs('ot.request') ? 'text-indigo-600 font-semibold' : '' }}" title="ประวัติ OT">📋 ประวัติ OT</a>
                        @endif

                        {{-- Notification Bell --}}
                        <div x-data="notificationBell()" x-init="load()" @click.outside="open = false" class="relative">
                            <button @click="open = !open; if(open) load()" class="relative p-1.5 text-gray-500 hover:text-indigo-600" title="การแจ้งเตือน">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                <span x-show="unread > 0" x-text="unread" class="absolute -top-0.5 -right-0.5 w-4 h-4 text-[10px] font-bold bg-red-500 text-white rounded-full flex items-center justify-center"></span>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-96 overflow-y-auto">
                                <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                                    <div class="font-bold text-sm text-gray-800">การแจ้งเตือน</div>
                                    <button @click="markAllRead()" class="text-[11px] text-indigo-600 hover:underline">อ่านทั้งหมด</button>
                                </div>
                                <template x-if="items.length === 0">
                                    <div class="p-6 text-center text-xs text-gray-400">ยังไม่มีการแจ้งเตือน</div>
                                </template>
                                <template x-for="n in items" :key="n.id">
                                    <div :class="n.read_at ? 'bg-white' : 'bg-sky-50'" class="px-4 py-2 border-b border-gray-100 cursor-pointer hover:bg-gray-50" @click="go(n)">
                                        <div class="text-[13px] font-semibold text-gray-800" x-text="n.title"></div>
                                        <div class="text-[11px] text-gray-500 mt-0.5" x-text="n.body"></div>
                                        <div class="text-[10px] text-gray-400 mt-1" x-text="new Date(n.sent_at).toLocaleString('th-TH')"></div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif

                    <span class="text-sm text-gray-500">{{ $authUser?->name ?? 'Admin' }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-sm text-red-500 hover:text-red-700">ออกจากระบบ</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    @if(session('success'))
    <div class="max-w-7xl mx-auto px-4 mt-4"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 4000)"
         x-transition:leave="transition ease-in duration-500"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                {{ session('success') }}
            </div>
            <button @click="show = false" class="opacity-40 hover:opacity-80 transition-opacity ml-2 shrink-0">&times;</button>
        </div>
    </div>
    @endif

    @if($errors->any() || session('error'))
    <div class="max-w-7xl mx-auto px-4 mt-4"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 6000)"
         x-transition:leave="transition ease-in duration-500"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-2">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-start justify-between gap-2">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"></path></svg>
                <div>
                    @if(session('error'))
                        {{ session('error') }}
                    @else
                        @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                    @endif
                </div>
            </div>
            <button @click="show = false" class="opacity-40 hover:opacity-80 transition-opacity ml-2 shrink-0">&times;</button>
        </div>
    </div>
    @endif

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-grow">
        @yield('content')
    </main>

    <!-- Footer / Version Tracker -->
    <footer class="mt-auto border-t border-gray-200 bg-white shadow-sm print:hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center text-[11px] text-gray-500">
            <div class="font-medium">
                xHR Payroll System v1.3.0 (beta) &copy; {{ date('Y') }}
            </div>
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-500"></span> System Active</span>
                <span class="font-bold text-gray-700 bg-gray-100 px-2 py-0.5 rounded border border-gray-200">v1.2.0 (Stable)</span>
            </div>
        </div>
    </footer>

    {{-- Toast container --}}
    <div x-data="{ toasts: [] }"
         x-init="window.toast = (msg, type='success') => { if(!msg) return; const id = Date.now(); toasts.push({ id, msg, type }); setTimeout(() => toasts = toasts.filter(t => t.id !== id), 3000); }"
         x-show="toasts.length > 0"
         x-cloak
         class="fixed top-4 right-4 z-[9999] space-y-2">
        <template x-for="t in toasts" :key="t.id">
            <div x-transition
                 :class="t.type === 'success' ? 'bg-green-600' : (t.type === 'error' ? 'bg-red-600' : 'bg-gray-800')"
                 class="text-white text-sm px-4 py-2 rounded-lg shadow-lg min-w-[240px] flex items-center justify-between gap-3">
                <span x-text="t.msg"></span>
                <button @click="toasts = toasts.filter(item => item.id !== t.id)" class="opacity-50 hover:opacity-100">&times;</button>
            </div>
        </template>
    </div>

    <script>
        // Notification Bell
        function notificationBell() {
            return {
                open: false,
                unread: 0,
                items: [],
                load() {
                    fetch('{{ route('notifications.index') }}', { headers: { 'Accept': 'application/json' } })
                        .then(r => r.json())
                        .then(d => { this.unread = d.unread; this.items = d.items; })
                        .catch(() => {});
                },
                markAllRead() {
                    fetch('{{ route('notifications.read-all') }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        }
                    }).then(() => this.load());
                },
                go(n) {
                    fetch(`/notifications/${n.id}/read`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        }
                    }).finally(() => {
                        if (n.link_url) window.location.href = n.link_url;
                        else this.load();
                    });
                }
            }
        }

        // Global keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            const t = e.target;
            const inField = t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable);

            // "/" focuses a search input on the page (if present)
            if (e.key === '/' && !inField) {
                const search = document.querySelector('input[data-search-focus], input[name=q], input[type=search]');
                if (search) { e.preventDefault(); search.focus(); }
            }

            // Esc closes any open modal via Alpine dispatch
            if (e.key === 'Escape') {
                document.dispatchEvent(new CustomEvent('close-modal'));
            }

            // Ctrl+S → trigger save button on page
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const btn = document.querySelector('[data-shortcut-save]');
                if (btn) { e.preventDefault(); btn.click(); }
            }

            // Ctrl+R inside workspace → recalculate
            if ((e.ctrlKey || e.metaKey) && e.key === 'r' && !inField) {
                const btn = document.querySelector('[data-shortcut-recalculate]');
                if (btn) { e.preventDefault(); btn.click(); }
            }
        });
    </script>

    @stack('scripts')
</body>
</html>
