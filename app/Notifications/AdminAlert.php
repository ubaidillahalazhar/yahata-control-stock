<?php

namespace App\Notifications;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Notifikasi untuk admin (lonceng di header). Disimpan di tabel `notifications`
 * lalu diambil halaman admin lewat polling (GET /notifications/feed).
 */
abstract class AdminAlert extends Notification
{
    /** Kategori untuk ikon & warna di UI: registration | damage_report | ... */
    abstract protected function kind(): string;

    abstract protected function title(): string;

    abstract protected function body(): string;

    /** Halaman yang dibuka saat notifikasi diklik (relatif, agar tetap benar saat diakses via IP LAN). */
    abstract protected function url(): string;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array{kind: string, title: string, body: string, url: string} */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    /**
     * Kirim ke semua admin aktif, kecuali `$except` (mis. admin yang memicu kejadiannya sendiri).
     */
    public static function toAdmins(self $notification, ?User $except = null): void
    {
        $admins = User::query()
            ->where('role', User::ROLE_ADMIN)
            ->where('status', UserStatus::Active)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->get();

        NotificationFacade::send($admins, $notification);
    }
}
