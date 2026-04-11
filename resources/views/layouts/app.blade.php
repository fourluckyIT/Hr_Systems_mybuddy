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
        $isAdmin = $authUser?->hasAnyRole(['admin']) ?? false;
        $isHr = $authUser?->hasAnyRole(['hr']) ?? false;
        $isManager = $authUser?->hasAnyRole(['manager']) ?? false;
        $isEmployeeOnly = ($authUser?->hasAnyRole(['employee', 'viewer']) ?? false) && !($isAdmin || $isHr || $isManager);
        $myEmployee = $authUser?->employee;
    @endphp

    <!-- Navigation -->
    <nav class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-14">
                <div class="flex items-center space-x-6">
                    <a href="{{ $isEmployeeOnly ? route('workspace.my') : route('employees.index') }}" class="text-lg font-bold text-indigo-600">xHR Payroll</a>

                    @if($isEmployeeOnly)
                        <a href="{{ route('workspace.my') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('workspace.*') ? 'text-indigo-600 font-semibold' : '' }}">My Workspace</a>
                    @else
                        @if($isAdmin || $isHr || $isManager)
                            <a href="{{ route('employees.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('employees.*') ? 'text-indigo-600 font-semibold' : '' }}">พนักงาน</a>
                            <a href="{{ route('calendar.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('calendar.*') ? 'text-indigo-600 font-semibold' : '' }}">ปฏิทินหลัก</a>
                        @endif

                        @if($isAdmin || $isHr)
                            <a href="{{ route('company.finance') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('company.*') ? 'text-indigo-600 font-semibold' : '' }}">การเงินบริษัท</a>
                            <a href="{{ route('annual.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('annual.*') ? 'text-indigo-600 font-semibold' : '' }}">สรุปรายปี</a>
                            <a href="{{ route('work.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('work.*') || request()->routeIs('settings.works.*') ? 'text-indigo-600 font-semibold' : '' }}">WORK Center</a>
                            <a href="{{ route('audit-logs.index') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('audit-logs.*') ? 'text-indigo-600 font-semibold' : '' }}">Audit Log</a>
                        @endif

                        @if($isAdmin)
                            <a href="{{ route('settings.master-data') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('settings.master-data*') ? 'text-indigo-600 font-semibold' : '' }}">Master Data</a>
                            <a href="{{ route('settings.rules') }}" class="text-sm text-gray-600 hover:text-indigo-600 {{ request()->routeIs('settings.rules*') ? 'text-indigo-600 font-semibold' : '' }}">ตั้งค่า</a>
                        @endif
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
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ session('success') }}
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
