<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * LanguageSeeder — 4 langues supportées
 *
 * DÉCISION FIGÉE : fr/ar/en/tr — PAS es (espagnol)
 * Marchés cibles : Algérie, Maroc, Tunisie (fr+ar), Turquie (tr), Europe (fr+en)
 * RTL : uniquement l'arabe (is_rtl = true)
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement("SET search_path TO public");

        $languages = [
            [
                'code'        => 'fr',
                'name_fr'     => 'Français',
                'name_native' => 'Français',
                'is_rtl'      => false,
                'is_active'   => true,
            ],
            [
                'code'        => 'ar',
                'name_fr'     => 'Arabe',
                'name_native' => 'العربية',
                'is_rtl'      => true,   // ← Direction RTL — impact Vue.js + Flutter + PDF
                'is_active'   => true,
            ],
            [
                'code'        => 'en',
                'name_fr'     => 'Anglais',
                'name_native' => 'English',
                'is_rtl'      => false,
                'is_active'   => true,
            ],
            [
                'code'        => 'tr',
                'name_fr'     => 'Turc',
                'name_native' => 'Türkçe',
                'is_rtl'      => false,
                'is_active'   => true,
            ],
        ];

        foreach ($languages as $lang) {
            DB::table('languages')->updateOrInsert(
                ['code' => $lang['code']],
                $lang
            );
        }

        $this->command->info('✅ Langues créées : fr (LTR), ar (RTL), en (LTR), tr (LTR)');
    }
}
