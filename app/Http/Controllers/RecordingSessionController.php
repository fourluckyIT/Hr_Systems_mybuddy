<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Game;
use App\Models\RecordingSession;
use Illuminate\Http\Request;

class RecordingSessionController extends Controller
{
    public function index(Request $request)
    {
        $month = (int) ($request->input('month', now()->month));
        $year = (int) ($request->input('year', now()->year));

        $sessions = RecordingSession::with(['game', 'youtubers'])
            ->whereMonth('session_date', $month)
            ->whereYear('session_date', $year)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        $youtubers = Employee::where('payroll_mode', 'youtuber_salary')
            ->where('is_active', true)
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        $games = Game::where('is_active', true)->orderBy('game_name')->get(['id', 'game_name']);

        return view('work.recording-sessions', compact('sessions', 'youtubers', 'games', 'month', 'year'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'session_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'game_id' => ['nullable', 'exists:games,id'],
            'notes' => ['nullable', 'string'],
            'youtuber_ids' => ['required', 'array', 'min:1'],
            'youtuber_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $session = RecordingSession::create([
            'session_date' => $data['session_date'],
            'title' => $data['title'],
            'game_id' => $data['game_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $session->youtubers()->sync($data['youtuber_ids']);

        return redirect()->back()->with('success', 'บันทึกรายการถ่ายเรียบร้อย');
    }

    public function update(Request $request, RecordingSession $recordingSession)
    {
        $data = $request->validate([
            'session_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'game_id' => ['nullable', 'exists:games,id'],
            'notes' => ['nullable', 'string'],
            'youtuber_ids' => ['required', 'array', 'min:1'],
            'youtuber_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $recordingSession->update([
            'session_date' => $data['session_date'],
            'title' => $data['title'],
            'game_id' => $data['game_id'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        $recordingSession->youtubers()->sync($data['youtuber_ids']);

        return redirect()->back()->with('success', 'อัปเดตเรียบร้อย');
    }

    public function destroy(RecordingSession $recordingSession)
    {
        $recordingSession->delete();
        return redirect()->back()->with('success', 'ลบรายการเรียบร้อย');
    }
}
