<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    @if(($panel ?? 'recording_queue') === 'edit_jobs')
    <div class="px-4 py-3 bg-indigo-700 text-white font-semibold text-sm flex items-center justify-between">
        <h3>งานตัดต่อที่ได้รับมอบหมาย (Assigned Edit Jobs)</h3>
        @php
            $activeCount = ($assignedEditJobs ?? collect())->whereNotIn('status', ['final'])->count();
        @endphp
        <span class="text-[10px] px-1.5 py-0.5 bg-white/20 text-white rounded uppercase font-bold">{{ $activeCount }}</span>
    </div>

    <div class="p-4">
    @php
        $activeAssignedEditJobs = ($assignedEditJobs ?? collect())->whereNotIn('status', ['final']);
        $completedAssignedEditJobs = ($assignedEditJobs ?? collect())->where('status', 'final');
        $currentEmployeeId = auth()->user()?->employee?->id;
        $canManage = $canManageWorkspace ?? false;
        $canEdit = $workspaceEditEnabled ?? true;
        $statusColors = [
            'assigned' => 'bg-blue-100 text-blue-700',
            'in_progress' => 'bg-yellow-100 text-yellow-700',
            'review_ready' => 'bg-purple-100 text-purple-700',
            'final' => 'bg-emerald-100 text-emerald-700',
        ];
        $statusTH = [
            'assigned' => 'ได้รับมอบหมาย',
            'in_progress' => 'กำลังตัดต่อ',
            'review_ready' => 'รอตรวจ',
            'final' => 'ปิดงาน/Final',
        ];
    @endphp

    @if(($assignedEditJobs ?? collect())->isNotEmpty())
    <div class="overflow-x-auto rounded-xl border border-indigo-100">
        <table class="w-full text-xs">
            <thead class="bg-indigo-50 text-indigo-600">
                <tr>
                    <th class="px-3 py-2 text-left">ชื่องาน</th>
                    <th class="px-3 py-2 text-left">เกม</th>
                    <th class="px-3 py-2 text-left">กำหนดส่ง</th>
                    <th class="px-3 py-2 text-left">สถานะ</th>
                    <th class="px-3 py-2 text-left">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($activeAssignedEditJobs as $job)
                @php
                    $canActOnJob = $canEdit && ($canManage || ((int) $job->assigned_to === (int) $currentEmployeeId));
                    $needsLayerCount = ($job->assignee?->payroll_mode === 'freelance_layer');
                @endphp
                <tr class="border-t border-indigo-50 hover:bg-indigo-50/30 transition-colors">
                    <td class="px-3 py-2 font-semibold text-gray-800">{{ $job->job_name ?? '-' }}</td>
                    <td class="px-3 py-2 text-gray-500">
                        {{ $job->game?->game_name ?? '-' }}
                    </td>
                    <td class="px-3 py-2 {{ $job->isOverdue() ? 'text-red-600 font-bold' : '' }}">
                        {{ $job->deadline_date?->format('d/m/Y') ?? '-' }}
                        @if($job->isOverdue()) ⚠️ @endif
                    </td>
                    <td class="px-3 py-2">
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusColors[$job->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ $statusTH[$job->status] ?? ($job->status ?? '-') }}
                        </span>
                    </td>
                    <td class="px-3 py-2">
                        @if($canActOnJob)
                            @if($job->status === 'assigned')
                                <form action="{{ route('work.editing-job.start', $job) }}" method="POST" class="inline">@csrf
                                    <button class="px-2 py-1 rounded bg-indigo-600 text-white text-[11px] font-semibold hover:bg-indigo-700">เริ่มงาน</button>
                                </form>
                            @elseif($job->status === 'in_progress')
                                <form action="{{ route('work.editing-job.mark-ready', $job) }}" method="POST" class="inline">@csrf
                                    <button class="px-2 py-1 rounded bg-blue-600 text-white text-[11px] font-semibold hover:bg-blue-700">ส่งงาน</button>
                                </form>
                            @elseif($job->status === 'review_ready')
                                <form action="{{ route('work.editing-job.finalize', $job) }}" method="POST" class="flex flex-col gap-1.5" x-data="{ mode: '{{ $job->assignee?->fixed_rate_per_clip > 0 ? 'custom' : 'layer' }}' }">
                                    @csrf
                                    <div class="flex items-center gap-2">
                                        @if($needsLayerCount)
                                            <select name="pricing_mode" x-model="mode" class="border border-gray-200 rounded px-1.5 py-1 text-[10px] bg-gray-50 focus:ring-0 cursor-pointer text-gray-600" title="เลือกวิธีคิดเงิน">
                                                <option value="layer">ตาม Layer</option>
                                                <option value="custom">เหมาคลิป (Fix)</option>
                                                <option value="custom_rate_per_min">เรท/นาที (อิสระ)</option>
                                            </select>
                                            
                                            <div x-show="mode === 'layer'" class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1.5 py-0.5">
                                                <span class="text-[10px] text-gray-400 font-bold">L</span>
                                                <input type="number" name="layer_count" min="1" value="{{ $job->layer_count ?? 1 }}" 
                                                       class="w-10 border-0 bg-transparent p-0 text-center text-[11px] font-bold text-indigo-600 focus:ring-0" 
                                                       title="จำนวนเลเยอร์ (Complexity Layer)">
                                            </div>

                                            <div x-show="mode === 'custom'" x-cloak class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1.5 py-0.5" title="ใส่ราคางานเหมาคลิป">
                                                <span class="text-[10px] text-gray-400 font-bold">฿</span>
                                                <input type="number" name="fix_amount" step="0.01" min="0" value="{{ $job->assignee?->fixed_rate_per_clip ?? 0 }}"
                                                       class="w-16 border-0 bg-transparent p-0 text-right text-[11px] font-bold text-orange-600 focus:ring-0" placeholder="ยอดเงิน">
                                            </div>

                                            <div x-show="mode === 'custom_rate_per_min'" x-cloak class="flex items-center gap-1 bg-white border border-gray-200 rounded px-1.5 py-0.5" title="ใส่เรทต่อนาที (แยกอิสระ)">
                                                <span class="text-[10px] text-gray-400 font-bold">เรท</span>
                                                <input type="number" name="custom_rate" step="0.0001" min="0"
                                                       class="w-16 border-0 bg-transparent p-0 text-right text-[11px] font-bold text-teal-600 focus:ring-0" placeholder="/ นาที">
                                            </div>
                                        @endif
                                        <div class="flex items-center gap-1 bg-gray-50 border border-gray-200 rounded px-1 py-0.5">
                                            <input type="number" name="video_duration_hours" class="w-8 border-0 bg-transparent p-0 text-center text-[11px] focus:ring-0" placeholder="ชม." title="ชั่วโมง">
                                            <span class="text-[10px] text-gray-400">:</span>
                                            <input type="number" name="video_duration_minutes" class="w-8 border-0 bg-transparent p-0 text-center text-[11px] focus:ring-0" placeholder="น." title="นาที">
                                            <span class="text-[10px] text-gray-400">:</span>
                                            <input type="number" name="video_duration_seconds" class="w-8 border-0 bg-transparent p-0 text-center text-[11px] focus:ring-0" placeholder="ว." min="0" max="59" title="วินาที">
                                        </div>
                                        <input type="date" name="finalized_at" value="{{ date('Y-m-d') }}" class="w-28 border border-gray-300 rounded px-2 py-1 text-[11px]" required title="วันที่ปิดงาน (Final)">
                                        <button class="px-3 py-1 rounded bg-emerald-600 text-white text-[11px] font-bold hover:bg-emerald-700 whitespace-nowrap shadow-sm transition">ปิดงาน</button>
                                    </div>
                                    @if($needsLayerCount)
                                        <div class="text-[9.5px] text-gray-400 italic">
                                            <span x-show="mode === 'layer'">💡 ระบุ Layer เพื่อให้ระบบคำนวณเงินจากเรท Price/min ที่ตั้งไว้</span>
                                            <span x-show="mode === 'custom'" x-cloak>💡 ใส่ยอดเงินเหมาจ่ายสำหรับคลิปนี้เลย (ควรใส่เวลาเก็บเป็นสถิติด้วย)</span>
                                            <span x-show="mode === 'custom_rate_per_min'" x-cloak>💡 ใส่เรทเงินแยกเฉพาะคลิปนี้ (รายได้ = เรทกำหนดเอง x ระยะเวลาคลิป)</span>
                                        </div>
                                    @endif
                                </form>
                            @endif
                        @else
                            <span class="text-[11px] text-gray-400">-</span>
                        @endif
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
                        <th class="px-3 py-2 text-left">เกม</th>
                        <th class="px-3 py-2 text-left">วันที่เสร็จ</th>
                        <th class="px-3 py-2 text-left">สถานะ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($completedAssignedEditJobs as $job)
                    <tr class="border-t border-slate-100 hover:bg-slate-50/60 transition-colors">
                        <td class="px-3 py-2 font-medium text-gray-700">{{ $job->job_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $job->game?->game_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-gray-500">{{ $job->finalized_at?->format('d/m/Y') ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700">{{ $statusTH[$job->status] ?? ($job->status ?? '-') }}</span>
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
