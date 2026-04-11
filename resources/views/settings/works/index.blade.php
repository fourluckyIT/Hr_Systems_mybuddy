@extends('layouts.app')
@section('title', 'Work Manager')

@section('content')
@php
    $formatDurationMinutes = fn ($minutes) => $minutes !== null
        ? \App\Support\DurationInput::formatMinutesAsHms($minutes)
        : '-';
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Work Manager</h1>
            <p class="text-sm text-gray-500">หน้าจัดการ Work Template สำหรับ Admin: คุม footage size / length / rate / module ได้ทั้งหมด</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">เพิ่ม Work Template</h2>
        <form method="POST" action="{{ route('settings.works.store') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            @csrf
            <input type="text" name="name" placeholder="ชื่อ Work" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="text" name="code" placeholder="Code (unique)" class="px-3 py-2 border rounded-lg text-sm" required>

            <select name="module_key" class="px-3 py-2 border rounded-lg text-sm" required>
                @foreach($moduleOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <select name="payroll_mode" class="px-3 py-2 border rounded-lg text-sm">
                <option value="">ทุก payroll mode</option>
                @foreach($payrollModeOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>

            <input type="text" name="footage_size" placeholder="Footage Size เช่น 1920x1080" class="px-3 py-2 border rounded-lg text-sm">
            <input type="time" step="1" name="target_length_hms" class="px-3 py-2 border rounded-lg text-sm">
            <input type="number" step="0.0001" min="0" name="default_rate_per_minute" placeholder="Default Rate/Minute" class="px-3 py-2 border rounded-lg text-sm">
            <input type="number" min="0" name="sort_order" value="0" placeholder="Sort" class="px-3 py-2 border rounded-lg text-sm">

            <div class="md:col-span-2 lg:col-span-4">
                <textarea name="description" rows="2" placeholder="รายละเอียดงาน" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
            </div>
            <div class="md:col-span-2 lg:col-span-4">
                <textarea name="config_json" rows="2" placeholder='Config JSON (optional) เช่น {"allow_override":true,"max_retry":2}' class="w-full px-3 py-2 border rounded-lg text-sm font-mono"></textarea>
            </div>

            <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600">
                เปิดใช้งานทันที
            </label>

            <div class="md:col-span-2 lg:col-span-3 flex justify-end">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">เพิ่ม Work</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Assign งานให้พนักงาน</h2>
        <form method="POST" action="{{ route('settings.works.assignments.store') }}" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3">
            @csrf
            <select name="employee_id" class="px-3 py-2 border rounded-lg text-sm" required>
                <option value="">เลือกพนักงาน</option>
                @foreach($employees as $employee)
                <option value="{{ $employee->id }}">{{ $employee->full_name }} ({{ $employee->payroll_mode }})</option>
                @endforeach
            </select>
            <select name="work_log_type_id" class="px-3 py-2 border rounded-lg text-sm" required>
                <option value="">เลือก Work</option>
                @foreach($workTypes->where('is_active', true) as $work)
                <option value="{{ $work->id }}">{{ $work->name }} ({{ $work->code }})</option>
                @endforeach
            </select>
            <input type="date" name="assigned_date" value="{{ now()->format('Y-m-d') }}" class="px-3 py-2 border rounded-lg text-sm" required>
            <input type="date" name="due_date" class="px-3 py-2 border rounded-lg text-sm">
            <select name="priority" class="px-3 py-2 border rounded-lg text-sm">
                <option value="normal">Normal</option>
                <option value="low">Low</option>
                <option value="high">High</option>
                <option value="urgent">Urgent</option>
            </select>
            <div class="md:col-span-2 lg:col-span-4">
                <textarea name="notes" rows="2" placeholder="โน้ตสำหรับ assignment" class="w-full px-3 py-2 border rounded-lg text-sm"></textarea>
            </div>
            <div class="flex justify-end lg:col-span-1">
                <button type="submit" class="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium hover:bg-black">Assign Work</button>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">รายการ Assignment</h2>
            <span class="text-xs text-gray-500">ล่าสุด {{ $assignments->count() }} รายการ</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-3 py-2">พนักงาน</th>
                        <th class="text-left px-3 py-2">Work</th>
                        <th class="text-left px-3 py-2">Assign</th>
                        <th class="text-left px-3 py-2">Due</th>
                        <th class="text-left px-3 py-2">Status</th>
                        <th class="text-left px-3 py-2">Priority</th>
                        <th class="text-right px-3 py-2">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assignments as $assignment)
                    <tr class="border-t align-top">
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $assignment->employee?->full_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $assignment->workType?->name ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $assignment->assigned_date?->format('d/m/Y') }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $assignment->due_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700 uppercase">{{ $assignment->status }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700 uppercase">{{ $assignment->priority }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <details class="relative">
                                    <summary class="list-none px-2 py-1 rounded text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 cursor-pointer">อัปเดต</summary>
                                    <div class="absolute right-0 mt-1 w-[320px] bg-white border rounded-lg shadow-lg p-3 z-10">
                                        <form method="POST" action="{{ route('settings.works.assignments.update', $assignment->id) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="w-full px-2 py-1.5 border rounded text-xs">
                                                <option value="action_select" @selected($assignment->status === 'action_select')>action_select</option>
                                                <option value="in_process" @selected($assignment->status === 'in_process')>in_process</option>
                                                <option value="finished" @selected($assignment->status === 'finished')>finished</option>
                                                <option value="rejected" @selected($assignment->status === 'rejected')>rejected</option>
                                            </select>
                                            <input type="date" name="due_date" value="{{ $assignment->due_date?->format('Y-m-d') }}" class="w-full px-2 py-1.5 border rounded text-xs">
                                            <select name="priority" class="w-full px-2 py-1.5 border rounded text-xs">
                                                <option value="low" @selected($assignment->priority === 'low')>Low</option>
                                                <option value="normal" @selected($assignment->priority === 'normal')>Normal</option>
                                                <option value="high" @selected($assignment->priority === 'high')>High</option>
                                                <option value="urgent" @selected($assignment->priority === 'urgent')>Urgent</option>
                                            </select>
                                            <textarea name="notes" rows="2" class="w-full px-2 py-1.5 border rounded text-xs">{{ $assignment->notes }}</textarea>
                                            <div class="flex justify-end">
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">บันทึก</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                <form method="POST" action="{{ route('settings.works.assignments.delete', $assignment->id) }}" onsubmit="return confirm('ลบ Assignment นี้?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 rounded text-xs bg-red-50 text-red-700 border border-red-200">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่มี Assignment</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">รายการ Work Template</h2>
            <span class="text-xs text-gray-500">ทั้งหมด {{ $workTypes->count() }} รายการ</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500">
                    <tr>
                        <th class="text-left px-3 py-2">Work</th>
                        <th class="text-left px-3 py-2">Module</th>
                        <th class="text-left px-3 py-2">Mode</th>
                        <th class="text-left px-3 py-2">Footage</th>
                        <th class="text-right px-3 py-2">Length</th>
                        <th class="text-right px-3 py-2">Rate/Min</th>
                        <th class="text-right px-3 py-2">Sort</th>
                        <th class="text-right px-3 py-2">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($workTypes as $work)
                    <tr class="border-t align-top {{ !$work->is_active ? 'bg-gray-50 opacity-70' : '' }}">
                        <td class="px-3 py-2">
                            <div class="font-semibold text-gray-800">{{ $work->name }}</div>
                            <div class="text-xs text-gray-500">{{ $work->code }}</div>
                            @if($work->description)
                            <div class="text-xs text-gray-500 mt-1">{{ $work->description }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $work->module_key }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $work->payroll_mode ?: 'all' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-700">{{ $work->footage_size ?: '-' }}</td>
                        <td class="px-3 py-2 text-right text-xs text-gray-700">{{ $formatDurationMinutes($work->target_length_minutes) }}</td>
                        <td class="px-3 py-2 text-right text-xs text-gray-700">{{ $work->default_rate_per_minute ?: '-' }}</td>
                        <td class="px-3 py-2 text-right text-xs text-gray-700">{{ $work->sort_order }}</td>
                        <td class="px-3 py-2">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('settings.works.toggle', $work->id) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="px-2 py-1 rounded text-xs {{ $work->is_active ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
                                        {{ $work->is_active ? 'ปิด' : 'เปิด' }}
                                    </button>
                                </form>
                                <details class="relative">
                                    <summary class="list-none px-2 py-1 rounded text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 cursor-pointer">แก้ไข</summary>
                                    <div class="absolute right-0 mt-1 w-[380px] bg-white border rounded-lg shadow-lg p-3 z-10">
                                        <form method="POST" action="{{ route('settings.works.update', $work->id) }}" class="space-y-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="name" value="{{ $work->name }}" class="w-full px-2 py-1.5 border rounded text-xs" required>
                                            <input type="text" name="code" value="{{ $work->code }}" class="w-full px-2 py-1.5 border rounded text-xs" required>
                                            <div class="grid grid-cols-2 gap-2">
                                                <select name="module_key" class="px-2 py-1.5 border rounded text-xs">
                                                    @foreach($moduleOptions as $key => $label)
                                                    <option value="{{ $key }}" @selected($work->module_key === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <select name="payroll_mode" class="px-2 py-1.5 border rounded text-xs">
                                                    <option value="">ทุก payroll mode</option>
                                                    @foreach($payrollModeOptions as $key => $label)
                                                    <option value="{{ $key }}" @selected($work->payroll_mode === $key)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="grid grid-cols-3 gap-2">
                                                <input type="text" name="footage_size" value="{{ $work->footage_size }}" placeholder="Footage" class="px-2 py-1.5 border rounded text-xs">
                                                <input type="time" step="1" name="target_length_hms" value="{{ \App\Support\DurationInput::formatMinutesAsHms($work->target_length_minutes) }}" class="px-2 py-1.5 border rounded text-xs">
                                                <input type="number" step="0.0001" min="0" name="default_rate_per_minute" value="{{ $work->default_rate_per_minute }}" placeholder="Rate" class="px-2 py-1.5 border rounded text-xs">
                                            </div>
                                            <input type="number" min="0" name="sort_order" value="{{ $work->sort_order }}" class="w-full px-2 py-1.5 border rounded text-xs" placeholder="Sort">
                                            <textarea name="description" rows="2" class="w-full px-2 py-1.5 border rounded text-xs" placeholder="รายละเอียด">{{ $work->description }}</textarea>
                                            <textarea name="config_json" rows="2" class="w-full px-2 py-1.5 border rounded text-xs font-mono" placeholder='{"allow_override":true}'>{{ $work->config ? json_encode($work->config, JSON_UNESCAPED_UNICODE) : '' }}</textarea>
                                            <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                                                <input type="checkbox" name="is_active" value="1" {{ $work->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-indigo-600">
                                                เปิดใช้งาน
                                            </label>
                                            <div class="flex justify-end">
                                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-xs hover:bg-indigo-700">บันทึก</button>
                                            </div>
                                        </form>
                                    </div>
                                </details>
                                <form method="POST" action="{{ route('settings.works.delete', $work->id) }}" onsubmit="return confirm('ลบ Work Template นี้?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-2 py-1 rounded text-xs bg-red-50 text-red-700 border border-red-200">ลบ</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-3 py-6 text-center text-sm text-gray-400">ยังไม่มี Work Template</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
