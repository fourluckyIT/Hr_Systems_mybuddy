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
                        <a href="{{ route('calendar.index') }}" class="{{ $navLink }} {{ request()->routeIs('calendar.*') ? $navActive : '' }}">ปฏิทินหลัก</a>
                    @elseif($isAdmin)
                        {{-- Primary (daily use) --}}
                        <a href="{{ route('employees.index') }}" class="{{ $navLink }} {{ request()->routeIs('employees.*') ? $navActive : '' }}">พนักงาน</a>
                        <a href="{{ route('work.index') }}" class="{{ $navLink }} {{ request()->routeIs('work.*') || request()->routeIs('settings.works.*') ? $navActive : '' }}">WORK Center</a>
                        <a href="{{ route('payroll-batches.index') }}" class="{{ $navLink }} {{ request()->routeIs('payroll-batches.*') ? $navActive : '' }}">รอบบิลเงินเดือน</a>
                        <a href="{{ route('company.finance') }}" class="{{ $navLink }} {{ request()->routeIs('company.*') ? $navActive : '' }}">การเงินบริษัท</a>

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
                            <div x-show="open" x-cloak x-transition class="absolute left-0 mt-1 w-48 bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-50">
                                <a href="{{ route('settings.master-data') }}" class="{{ $dropItem }}">Master Data</a>
                                <a href="{{ route('settings.bonus.index') }}" class="{{ $dropItem }}">Bonus Manager</a>
                                <a href="{{ route('settings.rules') }}" class="{{ $dropItem }}">กติกาคำนวณ</a>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="flex items-center space-x-3">
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

    @stack('scripts')
</body>
</html>
