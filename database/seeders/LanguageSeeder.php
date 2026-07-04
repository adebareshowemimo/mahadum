<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'yo',  'name' => 'Yoruba',          'script' => 'latin', 'rtl' => false, 'is_active' => true],
            ['code' => 'ig',  'name' => 'Igbo',            'script' => 'latin', 'rtl' => false, 'is_active' => true],
            ['code' => 'ha',  'name' => 'Hausa',           'script' => 'latin', 'rtl' => false, 'is_active' => true],
            ['code' => 'pcm', 'name' => 'Nigerian Pidgin', 'script' => 'latin', 'rtl' => false, 'is_active' => true],
        ];

        foreach ($languages as $language) {
            Language::updateOrCreate(['code' => $language['code']], $language);
        }
    }
}
