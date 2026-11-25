<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Lonceng notifikasi admin. Halaman admin memanggil `feed` setiap ±10 detik (polling),
 * jadi notifikasi baru muncul tanpa refresh halaman.
 */
class NotificationController extends Controller
{
    private const FEED_LIMIT = 15;

    public function feed(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'pending_accounts' => User::query()->where('status', UserStatus::Pending)->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(self::FEED_LIMIT)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => [
                    'id' => $notification->id,
                    'kind' => $notification->data['kind'] ?? 'info',
                    'title' => $notification->data['title'] ?? 'Notifikasi',
                    'body' => $notification->data['body'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at?->toIso8601String(),
                    'created_at_human' => $notification->created_at?->diffForHumans(),
                ]),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
