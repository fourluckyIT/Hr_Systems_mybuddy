<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')->orderByDesc('created_at');

        if ($request->filled('entity')) {
            $query->where('auditable_type', 'like', '%' . $request->get('entity') . '%');
        }

        if ($request->filled('action')) {
            $query->where('action', $request->get('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->get('user_id'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->get('to') . ' 23:59:59');
        }

        $logs = $query->paginate(50)->withQueryString();

        // Distinct entity types for filter
        $entityTypes = AuditLog::select('auditable_type')->distinct()->pluck('auditable_type')
            ->map(fn($t) => class_basename($t))->unique()->sort()->values();

        $actions = AuditLog::select('action')->distinct()->pluck('action')->sort()->values();

        return view('audit.index', compact('logs', 'entityTypes', 'actions'));
    }
}
