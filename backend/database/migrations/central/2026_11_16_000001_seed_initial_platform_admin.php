<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $email = 'clemie90@gmail.com';

        if (! DB::table('users')->where('email', $email)->exists()) {
            $now = now();
            DB::table('users')->insert([
                'id' => (string) Str::ulid(),
                'name' => 'Platform Super Admin',
                'email' => $email,
                'email_verified_at' => $now,
                'password' => '$2y$12$RVpZ6bBqF/hMLdBwgJQK3uk5RpKMjAGRbeHwYy9ZktHZeWrTgYtvi',
                'locale' => 'en',
                'status' => 'active',
                'is_platform_admin' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep seeded admin account intact on rollback unless explicitly removed.
    }
};
