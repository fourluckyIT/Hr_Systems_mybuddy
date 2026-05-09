@extends('layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="space-y-6" x-data="{ showDiffModal: false, diffLog: { old: '', new: '', field: '', entity: '' } }">
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
                <a href="{{ route('audit-logs.index') }}" class="border px-3 py-1 rounded text-xs text-gray-600 hover:bg-gray-50 text-center flex-1">ล้าง</a>
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
                        <th class="px-3 py-2 text-right">เพิ่มเติม</th>
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
                        <td class="px-3 py-2 max-w-[150px] truncate text-red-600">{{ Str::limit($log->old_value, 50) }}</td>
                        <td class="px-3 py-2 max-w-[150px] truncate text-green-600">{{ Str::limit($log->new_value, 50) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if($log->old_value || $log->new_value)
                            <button @click="showDiffModal = true; diffLog = { old: '{{ e(str_replace(['\\', "'", "\r", "\n"], ['\\\\', "\\'", "\\r", "\\n"], $log->old_value ?? '')) }}', new: '{{ e(str_replace(['\\', "'", "\r", "\n"], ['\\\\', "\\'", "\\r", "\\n"], $log->new_value ?? '')) }}', field: '{{ $log->field }}', entity: '{{ class_basename($log->auditable_type) }} #{{ $log->auditable_id }}' }" 
                                    class="text-indigo-600 hover:text-indigo-900 border border-indigo-200 rounded px-2 py-0.5 hover:bg-indigo-50 transition-colors">
                                View Diff
                            </button>
                            @endif
                        </td>
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

    {{-- Diff Viewer Modal --}}
    <div x-show="showDiffModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showDiffModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75" aria-hidden="true" @click="showDiffModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showDiffModal" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-xl rounded-2xl">
                <div>
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                        <h3 class="text-lg font-medium leading-6 text-gray-900" id="modal-title">
                            Diff Viewer: <span x-text="diffLog.entity" class="font-bold"></span> 
                            <span class="text-sm text-gray-500 font-normal">Field: <span x-text="diffLog.field"></span></span>
                        </h3>
                        <div class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-semibold text-red-600 mb-2 border-b pb-1">Old Value</h4>
                                <pre class="bg-red-50 text-red-800 p-3 rounded-lg overflow-x-auto text-xs font-mono border border-red-100 whitespace-pre-wrap max-h-96" x-text="diffLog.old"></pre>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-green-600 mb-2 border-b pb-1">New Value</h4>
                                <pre class="bg-green-50 text-green-800 p-3 rounded-lg overflow-x-auto text-xs font-mono border border-green-100 whitespace-pre-wrap max-h-96" x-text="diffLog.new"></pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse">
                    <button type="button" class="inline-flex justify-center w-full px-4 py-2 text-base font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto sm:text-sm" @click="showDiffModal = false">
                        ปิดหน้าต่าง
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
