<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
        <h3 class="font-bold text-gray-800 flex items-center gap-2">
            <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Production Pipeline (งานตัดต่อวิดีโอ)
        </h3>
        <span class="text-xs text-gray-400">แสดงงานที่กำลังดำเนินการสำหรับคุณ</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-50/50 text-gray-500 text-[10px] uppercase font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-3">วิดีโอ / โปรเจกต์</th>
                    <th class="px-6 py-3">Editor ผู้ตัดต่อ</th>
                    <th class="px-6 py-3">สถานะ</th>
                    <th class="px-6 py-3">กำหนดส่ง / ปิดงาน</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($assignedEditJobs as $job)
                <tr class="hover:bg-indigo-50/30 transition-colors">
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-900">{{ $job->job_name }}</div>
                        <div class="text-[10px] text-gray-400">{{ $job->game->game_name ?? '-' }}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-bold">
                                {{ substr($job->assignee->first_name, 0, 1) }}{{ substr($job->assignee->last_name, 0, 1) }}
                            </div>
                            <span class="font-medium">{{ $job->assignee->full_name }}</span>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $colors = [
                                'assigned' => 'bg-gray-100 text-gray-600',
                                'in_progress' => 'bg-amber-100 text-amber-700',
                                'review_ready' => 'bg-blue-100 text-blue-700 font-bold animate-pulse',
                                'final' => 'bg-green-100 text-green-700',
                            ];
                            $labels = [
                                'assigned' => 'รอดำเนินการ',
                                'in_progress' => 'กำลังตัดต่อ',
                                'review_ready' => 'รอคุณตรวจ!',
                                'final' => 'เสร็จสมบูรณ์',
                            ];
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold {{ $colors[$job->status] ?? 'bg-gray-100' }}">
                            {{ $labels[$job->status] ?? $job->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($job->status === 'final')
                            <div class="text-green-600 font-bold">{{ $job->finalized_at->format('d/m/Y') }}</div>
                            <div class="text-[10px] text-gray-400">{{ $job->video_duration_minutes }}:{{ str_pad($job->video_duration_seconds, 2, '0', STR_PAD_LEFT) }}</div>
                        @else
                            <div class="text-gray-500">{{ $job->deadline_date ? $job->deadline_date->format('d/m/Y') : '-' }}</div>
                            @if($job->deadline_date && $job->deadline_date->isPast() && $job->status !== 'final')
                                <div class="text-[10px] text-red-500 font-bold uppercase">ล่าช้า</div>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-400 italic bg-gray-50/20">
                        ไม่มีงานตัดต่อที่กำลังดำเนินการสำหรับคุณในขณะนี้
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
