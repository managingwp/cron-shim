<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Create (or update) the bootstrap administrator account.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('cronshim.admin.email')],
            [
                'name' => 'Administrator',
                'password' => config('cronshim.admin.password'),
            ],
        );
    }
}
