<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** The caller's in-app notifications (newest first), with an unread count. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $paginated = $user->notifications()->paginate(20)->through(fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'] ?? $n->type,
            'data' => $n->data,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
        ]);

        return response()->json([
            'data' => $paginated->items(),
            'unread' => $user->unreadNotifications()->count(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }

    /** Mark a single notification read. */
    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['data' => ['id' => $notification->id, 'read_at' => $notification->read_at]]);
    }

    /** Mark every unread notification read. */
    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => ['unread' => 0]]);
    }
}
