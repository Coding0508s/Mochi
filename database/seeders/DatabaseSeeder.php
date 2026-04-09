<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // 로컬 관리자 계정: 이메일이 이미 있으면 비밀번호·관리자 플래그만 맞춰 갱신합니다.
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );

        $this->call(InstitutionDemoSeeder::class);
    }
}
