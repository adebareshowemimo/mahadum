<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only viewer over the audit trail the AuditLogger writes on every sensitive
 * action (org lifecycle, role grants, payout approvals, status changes, …).
 */
class AuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query()->with('actorUser:id,first_name,last_name,email')->latest();

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('action', 'like', "%{$q}%")
                    ->orWhere('ip', 'like', "%{$q}%")
                    ->orWhereHas('actorUser', function ($u) use ($q) {
                        $u->where('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
            });
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to.' 23:59:59');
        }

        $page = $query->paginate(25);

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $log->actorUser
                    ? ['id' => $log->actorUser->id, 'name' => $log->actorUser->name, 'email' => $log->actorUser->email]
                    : null,
                'subject' => $log->subject_type
                    ? ['type' => class_basename($log->subject_type), 'id' => $log->subject_id]
                    : null,
                'ip' => $log->ip,
                'before' => $log->before,
                'after' => $log->after,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            // Distinct action names for the filter dropdown.
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
