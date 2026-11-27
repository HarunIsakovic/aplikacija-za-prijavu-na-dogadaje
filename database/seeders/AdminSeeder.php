<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrNew([
            'email' => 'admin@example.com',
        ]);

        $admin->name = 'Administrator';
        $admin->password = 'Admin12345!';
        $admin->email_verified_at = Carbon::now();
        $admin->role = 'admin';

        $admin->save();
    }
}
