<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Notifications\SupportReply;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Support triage queue. Lists inbound tickets (filter by status / search) and
 * lets a super-admin set status, priority, assignee, reply in a message thread,
 * and pick an assignee from the support staff.
 */
class SupportController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $query = SupportTicket::query()
            ->with(['user:id,first_name,last_name,email', 'assignedTo:id,first_name,last_name', 'messages.author:id,first_name,last_name'])
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(fn ($sub) => $sub->where('subject', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"));
        }

        $page = $query->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map($this->present(...)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'open_count' => SupportTicket::where('status', 'open')->count(),
            // Staff a ticket can be assigned to (support handlers).
            'assignees' => User::role(['super_admin', 'content_owner'])
                ->orderBy('first_name')
                ->get()
                ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
                ->values(),
        ]);
    }

    /**
     * Post a staff reply into the ticket thread. Auto-advances an untouched
     * `open` ticket to `in_progress` so the queue reflects that it's been picked up.
     */
    public function addMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'is_staff' => true,
            'body' => $validated['body'],
        ]);

        if ($ticket->status === 'open') {
            $ticket->update(['status' => 'in_progress']);
        }

        // Email the requester that support has replied.
        $ticket->user?->notify(new SupportReply($ticket, $validated['body']));

        $this->audit->record('support.ticket_replied', $ticket, [], ['status' => $ticket->status]);

        return response()->json(['data' => $this->present($ticket->fresh(['user', 'assignedTo', 'messages.author']))], 201);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'resolved'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high'])],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'response' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        $before = ['status' => $ticket->status, 'priority' => $ticket->priority, 'assigned_to' => $ticket->assigned_to];

        // Stamp resolved_at when moving into (or out of) the resolved state.
        if (array_key_exists('status', $validated)) {
            $validated['resolved_at'] = $validated['status'] === 'resolved' ? now() : null;
        }

        $ticket->fill($validated)->save();

        $this->audit->record('support.ticket_updated', $ticket, $before, [
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_to' => $ticket->assigned_to,
        ]);

        return response()->json(['data' => $this->present($ticket->fresh(['user', 'assignedTo', 'messages.author']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'subject' => $t->subject,
            'category' => $t->category,
            'message' => $t->message,
            'status' => $t->status,
            'priority' => $t->priority,
            'channel' => $t->channel,
            'email' => $t->email ?? $t->user?->email,
            'requester' => $t->user?->name,
            'assignee' => $t->assignedTo?->name,
            'assigned_to' => $t->assigned_to,
            'response' => $t->response,
            'resolved_at' => $t->resolved_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
            'messages' => $t->messages->map(fn (SupportTicketMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'is_staff' => $m->is_staff,
                'author' => $m->author?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
