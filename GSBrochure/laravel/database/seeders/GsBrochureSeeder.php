<?php

namespace Database\Seeders;

use App\Models\AdminUser;
use App\Models\Brochure;
use App\Models\Contact;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class GsBrochureSeeder extends Seeder
{
    public function run(): void
    {
        $brochures = [
            'LittleSEED Play in English',
            'Think in English, Speak in English',
            '어린이 영어교육, 왜 확실한 구어습득이 필요한가?',
            'GrapeSEED Elementary',
            'Information for Parents',
            'LittleSEED at Home Guide',
            '성공적인 GrapeSEED를 위한 가이드',
            'GS Baby',
            'GS Online 리플렛',
        ];
        foreach ($brochures as $name) {
            Brochure::firstOrCreate(['name' => $name], ['stock' => 0]);
        }

        $contacts = ['Addy Kim', 'Peter Kim', 'Ryan Koh', 'Daniel Kim', 'Ron Shin'];
        foreach ($contacts as $name) {
            Contact::firstOrCreate(['name' => $name]);
        }

        if (!AdminUser::where('username', 'admin')->exists()) {
            AdminUser::create([
                'username' => 'admin',
                'password_hash' => Hash::make('admin123'),
            ]);
        }
        if (!AdminUser::where('username', 'temp')->exists()) {
            AdminUser::create([
                'username' => 'temp',
                'password_hash' => Hash::make('temp123'),
            ]);
        }
    }
}
