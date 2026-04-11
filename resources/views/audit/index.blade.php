@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-900">Audit Logs</h1>

    {{-- Filters --}}
    <form method="GET" action="{{ route('audit-logs.index') }}" class="bg-white rounded-xl shadow-sm border p-4">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="text-xs text-gray-500">Entity</label>
                <select name="entity" class="w-full border rounded px-2 py-1 text-xs">
                    <option value="">ทั้งหมด</option>
                    @foreach($entityTypes as $type)
                    <option value="{{ $type }}" {{ request('entity') == $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Action</label>
                <select name="action" class="w-full border rounded px-2 py-1 text-xs">
                    <option value="">ทั้งหมด</option>
                    @foreach($actions as $act)
                    <option value="{{ $act }}" {{ request('action') == $act ? 'selected' : '' }}>{{ $act }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">จาก</label>
                <input type="date" name="from" value="{{ request('from') }}" class="w-full border rounded px-2 py-1 text-xs">
            </div>
            <div>
                <label class="text-xs text-gray-500">ถึง</label>
                <input type="date" name="to" value="{{ request('to') }}" class="w-full border rounded px-2 py-1 text-xs">
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-indigo-600 text-white px-4 py-1 rounded text-xs">กรอง</button>
                <a href="{{ route('audit-logs.index') }}" class="border px-3 py-1 rounded text-xs text-gray-600">ล้าง</a>
            </div>
        </div>
    </form>

    {{-- Log Table --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left">เวลา</th>
                        <th class="px-3 py-2 text-left">ผู้ดำเนินการ</th>
                        <th class="px-3 py-2 text-left">Action</th>
                        <th class="px-3 py-2 text-left">Entity</th>
                        <th class="px-3 py-2 text-left">ID</th>
                        <th class="px-3 py-2 text-left">Field</th>
                        <th class="px-3 py-2 text-left">Old Value</th>
                        <th class="px-3 py-2 text-left">New Value</th>
                        <th class="px-3 py-2 text-left">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td class="px-3 py-2">{{ $log->user?->name ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="px-1.5 py-0.5 rounded text-[10px]
                                @if($log->action === 'created') bg-green-100 text-green-700
                                @elseif($log->action === 'updated') bg-blue-100 text-blue-700
                                @elseif($log->action === 'deleted') bg-red-100 text-red-700
                                @else bg-gray-100 text-gray-700
                                @endif
                            ">{{ $log->action }}</span>
                        </td>
                        <td class="px-3 py-2">{{ class_basename($log->auditable_type) }}</td>
                        <td class="px-3 py-2 text-gray-400">#{{ $log->auditable_id }}</td>
                        <td class="px-3 py-2">{{ $log->field ?? '-' }}</td>
                        <td class="px-3 py-2 max-w-[200px] truncate text-red-600">{{ Str::limit($log->old_value, 80) }}</td>
                        <td class="px-3 py-2 max-w-[200px] truncate text-green-600">{{ Str::limit($log->new_value, 80) }}</td>
                        <td class="px-3 py-2 text-gray-400">{{ $log->reason ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-gray-400">ไม่พบ Audit Log</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
        <div class="px-4 py-3 border-t">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
