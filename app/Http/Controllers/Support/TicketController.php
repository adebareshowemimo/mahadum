<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consumer-facing support: an authenticated user raises a ticket, follows the
 * message thread with support, and can reply on their own tickets. Triage
 * happens in the admin console (Admin\SupportController).
 */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)
            ->with('messages.author:id,first_name,last_name')
            ->latest()
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'category' => $t->category,
                'status' => $t->status,
                'message' => $t->message,
                'response' => $t->response,
                'created_at' => $t->created_at?->toIso8601String(),
                'messages' => $t->messages->map(fn (SupportTicketMessage $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_staff' => $m->is_staff,
                    'author' => $m->author?->name,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->values()->all(),
            ]);

        return response()->json(['data' => $tickets]);
    }

    /**
     * Requester reply on their own ticket. Reopens a resolved ticket so support
     * sees the follow-up.
     */
    public function reply(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'is_staff' => false,
            'body' => $validated['body'],
        ]);

        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open', 'resolved_at' => null]);
        }

        return response()->json(['data' => ['id' => $ticket->id, 'status' => $ticket->status]], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'in:billing,account,technical,content,other'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'channel' => 'in_app',
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? null,
            'message' => $validated['message'],
            'status' => 'open',
            'priority' => 'normal',
        ]);

        return response()->json(['data' => ['id' => $ticket->id, 'status' => $ticket->status]], 201);
    }
}
