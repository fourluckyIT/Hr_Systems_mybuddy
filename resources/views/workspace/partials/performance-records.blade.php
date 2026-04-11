<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    @if(($panel ?? 'recording_queue') === 'edit_jobs')
    <div class="px-4 py-3 bg-indigo-700 text-white font-semibold text-sm flex items-center justify-between">
        <h3>งานตัดต่อที่ได้รับมอบหมาย (Assigned Edit Jobs)</h3>
        <span class="text-[10px] px-1.5 py-0.5 bg-white/20 text-white rounded uppercase font-bold">{{ ($assignedEditJobs ?? collect())->count() }}</span>
    </div>

    <div class="p-4">
    @php
        $activeAssignedEditJobs = ($assignedEditJobs ?? collect())->whereNotIn('status', ['done']);
        $completedAssignedEditJobs = ($assignedEditJobs ?? collect())->where('status', 'done');
        $statusColors = [
            'assigned' => 'bg-blue-100 text-blue-700',
            'editing' => 'bg-yellow-100 text-yellow-700',
            'submitted' => 'bg-purple-100 text-purple-700',
            'approved' => 'bg-emerald-100 text-emerald-700',
            'done' => 'bg-slate-200 text-slate-600',
        ];
        $statusTH = [
            'assigned' => 'ได้รับมอบหมาย',
            'editing' => 'กำลังตัดต่อ',
            'submitted' => 'ส่งตรวจแล้ว',
            'approved' => 'อนุมัติแล้ว',
            'done' => 'ปิดงาน',
        ];
    @endphp

    @if(($assignedEditJobs ?? collect())->isNotEmpty())
    <div class="overflow-x-auto rounded-xl border border-indigo-100">
        <table class="w-full text-xs">
            <thead class="bg-indigo-50 text-indigo-600">
                <tr>
                    <th class="px-3 py-2 text-left">ชื่องาน</th>
                    <th class="px-3 py-2 text-left">Resource</th>
                    <th class="px-3 py-2 text-left">Due Date</th>
                    <th class="px-3 py-2 text-left">Priority</th>
                    <th class="px-3 py-2 text-left">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activeAssignedEditJobs as $job)
                <tr class="border-t border-indigo-50 hover:bg-indigo-50/30 transition-colors">
                    <td class="px-3 py-2 font-semibold text-gray-800">{{ $job->title ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-500">
                        {{ $job->mediaResource?->footage_code ?? '-' }}
                        @if($job->mediaResource?->title)
                            <span class="text-gray-400">- {{ $job->mediaResource?->title }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        {{ $job->due_date?->format('d/m/Y') ?? '-' }}
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-block bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[10px] uppercase">{{ $job->priority }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusColors[$job->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusTH[$job->status] ?? ($job->status ?? '-') }}
                        </span>
                    </td>
                </tr>
                @endforeach

                @if($activeAssignedEditJobs->isEmpty())
                <tr>
                    <td colspan="5" class="px-3 py-5 text-center text-gray-400">ไม่มีงานตัดต่อที่กำลังดำเนินการ</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    @if($completedAssignedEditJobs->isNotEmpty())
    <div class="mt-4">
        <div class="flex items-center gap-2 mb-2">
            <h4 class="text-xs font-semibold text-slate-600">งานตัดต่อที่ปิดแล้ว</h4>
            <span class="bg-slate-200 text-slate-700 text-[10px] px-2 py-0.5 rounded-full font-medium">{{ $completedAssignedEditJobs->count() }} งาน</span>
        </div>
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-2 text-left">ชื่องาน</th>
                        <th class="px-3 py-2 text-left">Resource</th>
                        <th class="px-3 py-2 text-left">Due Date</th>
                        <th class="px-3 py-2 text-left">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedAssignedEditJobs as $job)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/60 transition-colors">
                        <td class="px-3 py-2 font-medium text-gray-700">{{ $job->title ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $job->mediaResource?->footage_code ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $job->due_date?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-200 text-slate-600">{{ $statusTH[$job->status] ?? ($job->status ?? '-') }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @else
    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
        <div class="text-sm font-semibold text-gray-600">ยังไม่มีงานตัดต่อที่ถูกมอบหมาย</div>
        <div class="mt-1 text-xs text-gray-400">ไปที่ WORK Center แล้วมอบหมายงานตัดต่อให้พนักงานคนนี้ ตารางนี้จะอัปเดตอัตโนมัติ</div>
    </div>
    @endif
    </div>
    @elseif(($panel ?? 'recording_queue') === 'recording_queue')
    <div class="px-4 py-3 bg-indigo-700 text-white font-semibold text-sm flex items-center justify-between">
        <h3>ตารางคิวถ่ายทำ (Recording Queue)</h3>
        <span class="text-[10px] px-1.5 py-0.5 bg-white/20 text-white rounded uppercase font-bold">{{ ($recordingAssignments ?? collect())->count() }}</span>
    </div>

    <div class="p-4">
    @if(($recordingAssignments ?? collect())->isNotEmpty())
    <div class="overflow-x-auto rounded-xl border border-indigo-100">
        <table class="w-full text-xs">
            <thead class="bg-indigo-50 text-indigo-600">
                <tr>
                    <th class="px-3 py-2 text-left">ชื่อคิวถ่าย</th>
                    <th class="px-3 py-2 text-left">Game Type / Game / Map</th>
                    <th class="px-3 py-2 text-left">วันถ่าย / เวลา</th>
                    <th class="px-3 py-2 text-left">บทบาท</th>
                    <th class="px-3 py-2 text-left">สถานะ</th>
                </tr>
            </thead>
            <tbody>
                @foreach($recordingAssignments as $ra)
                @php
                    $job = $ra->recordingJob;
                    $statusColors = [
                        'draft'     => 'bg-gray-100 text-gray-600',
                        'scheduled' => 'bg-blue-100 text-blue-700',
                        'recording' => 'bg-yellow-100 text-yellow-700',
                        'shot'      => 'bg-green-100 text-green-700',
                    ];
                    $statusTH = [
                        'draft'     => 'ร่าง',
                        'scheduled' => 'นัดถ่าย',
                        'recording' => 'กำลังถ่าย',
                        'shot'      => 'ถ่ายเสร็จ',
                    ];
                @endphp
                <tr class="border-t border-indigo-50 hover:bg-indigo-50/30 transition-colors">
                    <td class="px-3 py-2 font-semibold text-gray-800">{{ $job?->title ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-500">
                        @if($job?->game_type)
                            <span class="inline-block bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded text-[10px] mr-1">{{ $job->game_type }}</span>
                        @endif
                        {{ $job?->game ?? '-' }}{{ $job?->map ? ' / '.$job?->map : '' }}
                    </td>
                    <td class="px-3 py-2">
                        {{ $job?->scheduled_date?->format('d/m/Y') ?? '-' }}
                        @if($job?->scheduled_time)
                            <span class="text-indigo-500 ml-1">{{ \Illuminate\Support\Str::of($job->scheduled_time)->substr(0, 5) }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-block bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded text-[10px]">{{ $ra->role }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusColors[$job?->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusTH[$job?->status] ?? ($job?->status ?? '-') }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
        <div class="text-sm font-semibold text-gray-600">ยังไม่มีคิวถ่ายที่ถูก Assign</div>
        <div class="mt-1 text-xs text-gray-400">ไปที่ WORK Center เพื่อ assign คิวถ่ายให้พนักงาน แล้วตารางนี้จะขึ้นอัตโนมัติ</div>
    </div>
    @endif
    </div>
    @else
    <div class="px-4 py-3 bg-slate-700 text-white font-semibold text-sm flex items-center justify-between">
        <h3>งานที่ได้รับมอบหมาย</h3>
        <span class="text-[10px] px-1.5 py-0.5 bg-white/20 text-white rounded uppercase font-bold">0</span>
    </div>
    <div class="p-4">
        <div class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-4 py-8 text-center">
            <div class="text-sm font-semibold text-gray-600">ยังไม่มีงานที่ถูกมอบหมาย</div>
            <div class="mt-1 text-xs text-gray-400">เมื่อมีการ Assign งานตัดต่อหรือคิวถ่ายทำ รายการจะขึ้นที่นี่อัตโนมัติ</div>
        </div>
    </div>
    @endif
</div>
