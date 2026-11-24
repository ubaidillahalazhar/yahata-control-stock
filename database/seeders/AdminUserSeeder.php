<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Mas Sigit',
                'email' => 'sigit@yahata.co.id',
                'role' => 'admin',
                'machine_name' => 'All Lines / Admin',
                'password' => Hash::make('yahata123'),
            ],
            [
                'name' => 'Pak Apri',
                'email' => 'apri@yahata.co.id',
                'role' => 'admin',
                'machine_name' => 'All Lines / Admin',
                'password' => Hash::make('yahata123'),
            ],
        ];

        foreach ($admins as $admin) {
            User::updateOrCreate(
                ['email' => $admin['email']],
                $admin
            );
        }
    }
}