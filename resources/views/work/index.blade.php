@extends('layouts.app')

@section('title', 'Work Pipeline')

@section('content')
<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Work Pipeline</h1>
            <p class="text-sm text-gray-500">ระบบติดตามงานและวัตถุดิบ (Consolidated)</p>
        </div>
    </div>

    {{-- Editing Jobs --}}
    <div class="space-y-4">
        <div class="flex justify-between items-center">
            <h2 class="text-lg font-semibold">Active Jobs</h2>
            <button @click="$dispatch('open-modal', 'add-job-modal')" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700">
                + มอบหมายงานใหม่
            </button>
        </div>

        <div class="bg-white shadow rounded-lg overflow-hidden border">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Job Name</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Game</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Assigned To</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left font-medium text-gray-500 uppercase tracking-wider">Deadline</th>
                        <th class="px-6 py-3 text-center font-medium text-gray-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($editingJobs as $job)
                    <tr>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $job->job_name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $job->game->game_name }}</td>
                        <td class="px-6 py-4">{{ $job->assignee->first_name }}</td>
                        <td class="px-6 py-4">
                            @php
                                $statusColors = [
                                    'assigned' => 'bg-gray-100 text-gray-800',
                                    'in_progress' => 'bg-blue-100 text-blue-800',
                                    'review_ready' => 'bg-yellow-100 text-yellow-800',
                                    'final' => 'bg-green-100 text-green-800',
                                ];
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColors[$job->status] ?? 'bg-gray-100' }}">
                                {{ strtoupper($job->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">
                            {{ $job->deadline_date ? $job->deadline_date->format('d/m/Y') : '7 days' }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($job->status === 'assigned')
                                <form action="{{ route('work.editing-job.start', $job) }}" method="POST">@csrf
                                    <button class="text-indigo-600 hover:text-indigo-900">Start</button>
                                </form>
                            @elseif($job->status === 'in_progress')
                                <form action="{{ route('work.editing-job.mark-ready', $job) }}" method="POST">@csrf
                                    <button class="text-blue-600 hover:text-blue-900">Finish Edit</button>
                                </form>
                            @elseif($job->status === 'review_ready')
                                <form action="{{ route('work.editing-job.finalize', $job) }}" method="POST">@csrf
                                    <button class="text-green-600 hover:text-green-900 font-bold">Approve / Finalize</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>

{{-- Add Job Modal (Simplified) --}}
<div x-data="{ open: false }" @open-modal.window="if($event.detail === 'add-job-modal') open = true" x-show="open" class="fixed inset-0 z-50 overflow-y-auto" style="display:none">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 transition-opacity" @click="open = false">
            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
        </div>
        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="{{ route('work.editing-job.store') }}" method="POST">
                @csrf
                <div class="space-y-4">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">มอบหมายงานใหม่</h3>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ชื่อโปรเจกต์</label>
                        <input type="text" name="job_name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">หมวดหมู่เกม</label>
                        <select name="game_id" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                            @foreach($games as $game)
                                <option value="{{ $game->id }}">{{ $game->game_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">ลิงก์เกม (ถ้ามี)</label>
                        <input type="url" name="game_link" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">กำหนดส่งภายใน (วัน)</label>
                        <input type="number" name="deadline_days" min="1" value="7" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Editor</label>
                        <select name="assigned_to" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm sm:text-sm">
                            @foreach($editors as $ed)
                                <option value="{{ $ed->id }}">{{ $ed->first_name }} {{ $ed->last_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">โน้ต</label>
                        <textarea name="notes" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="รายละเอียดเพิ่มเติม"></textarea>
                    </div>
                </div>
                <div class="mt-5 sm:mt-6 flex justify-end space-x-3">
                    <button type="button" @click="open = false" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm">ยกเลิก</button>
                    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md text-sm">สร้างงาน</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
