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
                    <a href="{{ $isOwnerOnly ? route('workspace.my') : route('employees.index') }}" class="text-lg font-bold text-indigo-600">xHR Payroll</a>

                    @php
                        $navLink = 'text-sm text-gray-600 hover:text-indigo-600';
                        $navActive = 'text-indigo-600 font-semibold';
                        $dropItem = 'block px-4 py-2 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-600';
                    @endphp

                    @if($isOwnerOnly)
                        <a href="{{ route('workspace.my') }}" class="{{ $navLink }} {{ request()->routeIs('workspace.*') ? $navActive : '' }}">My Workspace</a>
                        <a href="{{ route('leave.index') }}" class="{{ $navLink }} {{ request()->routeIs('leave.*') ? $navActive : '' }}">ลา/สลับวัน</a>
                        <a href="{{ route('calendar.index') }}" class="{{ $navLink }} {{ request()->routeIs('calendar.*') ? $navActive : '' }}">ปฏิทินหลัก</a>
                    @elseif($isAdmin)
                        {{-- Primary (daily use) --}}
                        <a href="{{ route('employees.index') }}" class="{{ $navLink }} {{ request()->routeIs('employees.*') ? $navActive : '' }}">พนักงาน</a>
                        <a href="{{ route('work.index') }}" class="{{ $navLink }} {{ request()->routeIs('work.*') || request()->routeIs('settings.works.*') ? $navActive : '' }}">WORK Center</a>
                        <a href="{{ route('leave.index') }}" class="{{ $navLink }} {{ request()->routeIs('leave.*') ? $navActive : '' }}">การลา</a>
                        <a href="{{ route('payroll-batches.index') }}" class="{{ $navLink }} {{ request()->routeIs('payroll-batches.*') ? $navActive : '' }}">รอบบิลเงินเดือน</a>
                        <a href="{{ route('company.finance') }}" class="{{ $navLink }} {{ request()->routeIs('company.*') || request()->routeIs('expense-tracker.*') ? $navActive : '' }}">การเงิน</a>

                        {{-- รายงาน dropdown --}}
                        @php $reportsActive = request()->routeIs('calendar.*') || request()->routeIs('annual.*') || request()->routeIs('audit-logs.*'); @endphp
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button @click="open = !open" class="{{ $navLink }} flex items-center gap-1 {{ $reportsActive ? $navActive : '' }}">
                                รายงาน
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute left-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50">
                                <a href="{{ route('calendar.index') }}" class="{{ $dropItem }}">ปฏิทินหลัก</a>
                                <a href="{{ route('annual.index') }}" class="{{ $dropItem }}">สรุปรายปี</a>
                                <a href="{{ route('expense-tracker.index') }}" class="{{ $dropItem }}">รายรับ-จ่าย (Tracker)</a>
                                <a href="{{ route('audit-logs.index') }}" class="{{ $dropItem }}">Audit Log</a>
                            </div>
                        </div>

                        {{-- ตั้งค่า dropdown --}}
                        @php $settingsActive = request()->routeIs('settings.*'); @endphp
                        <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                            <button @click="open = !open" class="{{ $navLink }} flex items-center gap-1 {{ $settingsActive ? $navActive : '' }}">
                                ตั้งค่า
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute left-0 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50">
                                <a href="{{ route('settings.master-data') }}" class="{{ $dropItem }}">Master Data</a>
{{-- <a href="{{ route('settings.works.index') }}" class="{{ $dropItem }}">Work Types</a> --}}
                                <a href="{{ route('settings.bonus.index') }}" class="{{ $dropItem }}">Bonus Manager</a>
                                <a href="{{ route('settings.rules') }}" class="{{ $dropItem }}">กติกาคำนวณ</a>
                                <a href="{{ route('settings.company') }}" class="{{ $dropItem }}">ตั้งค่าบริษัท</a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center space-x-3">
                    {{-- OT Request quick link for all users --}}
                    @if($authUser)
                        <a href="{{ route('ot.request') }}" class="text-xs text-gray-500 hover:text-indigo-600 {{ request()->routeIs('ot.request') ? 'text-indigo-600 font-semibold' : '' }}" title="ขอ OT">📝 ขอ OT</a>
                        @if($isAdmin)
                            <a href="{{ route('ot.inbox') }}" class="text-xs text-gray-500 hover:text-indigo-600 {{ request()->routeIs('ot.inbox') ? 'text-indigo-600 font-semibold' : '' }}" title="OT Inbox">📥 OT</a>
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
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    </div>
    @endif

    @if($errors->any() || session('error'))
    <div class="max-w-7xl mx-auto px-4 mt-4">
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm flex items-start gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"></path></svg>
            <div>
                @if(session('error'))
                    {{ session('error') }}
                @else
                    @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
                @endif
            </div>
        </div>
    </div>
    @endif

    <!-- Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        @yield('content')
    </main>

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
