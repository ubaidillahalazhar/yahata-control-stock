<?php

namespace App\Notifications;

use App\Models\User;

class PicRegistered extends AdminAlert
{
    public function __construct(private readonly User $pic) {}

    protected function kind(): string
    {
        return 'registration';
    }

    protected function title(): string
    {
        return 'Registrasi PIC baru';
    }

    protected function body(): string
    {
        return sprintf('%s (%s) · %s — menunggu persetujuan', $this->pic->name, $this->pic->username, $this->pic->machine_name ?? '—');
    }

    protected function url(): string
    {
        return route('accounts.index', absolute: false);
    }
}
