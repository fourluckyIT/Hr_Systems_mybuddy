<?php

namespace App\Http\Controllers;

use App\Models\PerformanceTier;
use Illuminate\Http\Request;

class PerformanceTierController extends Controller
{
    public function index()
    {
        $tiers = PerformanceTier::orderBy('display_order')->get();
        return view('settings.tiers', compact('tiers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tier_code' => 'required|string|max:20|unique:performance_tiers,tier_code',
            'tier_name' => 'required|string|max:50',
            'multiplier' => 'required|numeric|min:0|max:10',
            'min_clip_minutes_per_month' => 'nullable|integer|min:0',
            'max_clip_minutes_per_month' => 'nullable|integer|min:0',
            'min_qualified_months' => 'nullable|integer|min:0',
            'max_qualified_months' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'display_order' => 'required|integer',
            'is_active' => 'boolean',
            'auto_select_enabled' => 'boolean',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['auto_select_enabled'] = $request->has('auto_select_enabled');

        PerformanceTier::create($validated);

        return back()->with('success', 'เพิ่มระดับผลงานสำเร็จ');
    }

    public function update(Request $request, PerformanceTier $tier)
    {
        $validated = $request->validate([
            'tier_name' => 'required|string|max:50',
            'multiplier' => 'required|numeric|min:0|max:10',
            'min_clip_minutes_per_month' => 'nullable|integer|min:0',
            'max_clip_minutes_per_month' => 'nullable|integer|min:0',
            'min_qualified_months' => 'nullable|integer|min:0',
            'max_qualified_months' => 'nullable|integer|min:0',
            'description' => 'nullable|string',
            'display_order' => 'required|integer',
        ]);

        $validated['is_active'] = $request->has('is_active');
        $validated['auto_select_enabled'] = $request->has('auto_select_enabled');

        $tier->update($validated);

        return back()->with('success', 'อัปเดตระดับผลงานสำเร็จ');
    }

    public function destroy(PerformanceTier $tier)
    {
        if ($tier->calculations()->exists()) {
            return back()->withErrors(['error' => 'ไม่สามารถลบได้ เนื่องจากมีการนำไปใช้คำนวณโบนัสแล้ว แนะนำให้ปิดใช้งาน (Inactive) แทน']);
        }
        
        $tier->delete();
        return back()->with('success', 'ลบระดับผลงานสำเร็จ');
    }
}
