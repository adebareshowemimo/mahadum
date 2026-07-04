<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The email log (§7): a searchable record of every outbound message — transactional
 * and campaign. `emails.log.view` (super-admin-only).
 */
class EmailLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EmailLog::query()->latest();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where('to_email', 'like', "%{$q}%");
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($source = $request->query('source')) {
            $query->where('source', $source);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $page = $query->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (EmailLog $l) => [
                'id' => $l->id,
                'to_email' => $l->to_email,
                'type' => $l->type,
                'source' => $l->source,
                'subject' => $l->subject,
                'status' => $l->status,
                'sent_at' => $l->sent_at?->toIso8601String(),
                'created_at' => $l->created_at?->toIso8601String(),
            ]),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            // Distinct sources for the filter dropdown (small cardinality).
            'sources' => EmailLog::query()->whereNotNull('source')->distinct()->orderBy('source')->pluck('source'),
        ]);
    }
}
