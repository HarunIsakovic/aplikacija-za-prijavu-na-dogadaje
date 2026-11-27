<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew([
            'email' => 'admin@example.com',
        ]);

        $admin->name = 'Administrator';
        $admin->password = 'Admin12345!';
        $admin->email_verified_at = now();
        $admin->role = 'admin';

        $admin->save();
    }
}