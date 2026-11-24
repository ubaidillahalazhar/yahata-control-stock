<?php

namespace Tests;

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
            'password' => 'password',
        ]);
    }
}
