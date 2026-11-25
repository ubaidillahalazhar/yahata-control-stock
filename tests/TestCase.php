<?php

namespace Tests;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Semua halaman wajib login; test feature default login sebagai admin.
        if (in_array(RefreshDatabase::class, class_uses_recursive($this), true)) {
            $this->actingAs($this->createAdmin());
        }
    }

    protected function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin Test',
            'email' => 'admin@test.local',
            'role' => 'admin',
            'status' => UserStatus::Active,
            'password' => 'password',
        ]);
    }

    /** Akun PIC aktif (login username + PIN 6 digit). */
    protected function createPic(array $attributes = []): User
    {
        return User::query()->create([
            'name' => 'Budi Operator',
            'username' => 'budi',
            'role' => 'pic',
            'status' => UserStatus::Active,
            'machine_name' => 'Heading #01',
            'password' => '123456',
            ...$attributes,
        ]);
    }
}
