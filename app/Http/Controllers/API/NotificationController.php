<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     * Ambil 20 notifikasi terbaru milik user yang login
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $notifications = $user->notifications()
            ->with('contract:id,contract_number,title,status')
            ->latest()
            ->take(20)
            ->get();

        $unreadCount = $user->notifications()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PATCH /api/notifications/{id}/read
     * Tandai satu notifikasi sebagai dibaca
     */
    public function markRead(int $id): JsonResponse
    {
        $user = Auth::user();

        $notification = $user->notifications()->findOrFail($id);
        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai dibaca.']);
    }

    /**
     * PATCH /api/notifications/read-all
     * Tandai semua notifikasi sebagai dibaca
     */
    public function markAllRead(): JsonResponse
    {
        Auth::user()
            ->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai dibaca.']);
    }

    public function destroy(int $id): JsonResponse
{
    $notification = Notification::where('id', $id)
        ->where('user_id', Auth::id())
        ->firstOrFail();

    $notification->delete();

    return response()->json(['message' => 'Notifikasi dihapus.']);
}
}
