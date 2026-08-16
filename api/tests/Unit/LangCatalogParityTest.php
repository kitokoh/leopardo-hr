<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Garde de parité des catalogues de traduction (#4293).
 *
 * - Chaque fichier `api/lang/{locale}/*.php` doit être un PHP valide
 *   (un `return [...]` fermé — la résolution de conflit #4425 avait
 *   supprimé le `];` de errors.php ×4 → ParseError sur __('errors.*')).
 * - Chaque catalogue doit exposer le MÊME jeu de clés dans les 4 locales
 *   (fr/en/tr/ar) : une clé manquante en tr/ar renvoie la clé brute aux
 *   tenants.
 */
class LangCatalogParityTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'tr', 'ar'];

    public function test_all_lang_files_are_valid_php_and_close_their_array(): void
    {
        $files = File::allFiles(base_path('lang'));

        $this->assertNotEmpty($files, 'lang directory should contain catalogues');

        foreach ($files as $file) {
            $this->assertStringEndsWith(
                "];\n",
                (string) $file->getContents(),
                "{$file->getFilename()} doit se terminer par '];' (PHP valide)."
            );
        }
    }

    public function test_key_parity_across_locales_per_catalog(): void
    {
        $catalogs = collect(File::directories(base_path('lang')))
            ->map(fn (string $dir) => basename($dir))
            ->filter(fn (string $locale) => in_array($locale, self::LOCALES, true))
            ->values();

        $this->assertCount(4, $catalogs, 'les 4 locales doivent exister');

        foreach ($catalogs as $locale) {
            $files = File::files(base_path('lang/'.$locale));
            foreach ($files as $file) {
                $keys = $this->extractKeys((string) $file->getContents());
                $this->assertNotEmpty($keys, "{$locale}/{$file->getFilename()} contient 0 clé");

                foreach ($catalogs as $other) {
                    if ($other === $locale) {
                        continue;
                    }
                    $otherFile = base_path("lang/{$other}/{$file->getFilename()}");
                    $this->assertFileExists($otherFile);
                    $otherKeys = $this->extractKeys((string) File::get($otherFile));

                    $missing = array_diff($keys, $otherKeys);
                    $this->assertSame(
                        [],
                        $missing,
                        "{$other}/{$file->getFilename()} manque des clés présentes en {$locale}: "
                        .implode(', ', $missing)
                    );
                }
            }
        }
    }

    /** @return list<string> */
    private function extractKeys(string $php): array
    {
        // Clés en SCREAMING_SNAKE (errors.php, api_errors.php) comme en
        // snake_case (dashboard.php) — le padding d'alignement diffère selon
        // les locales, on ne compare que les noms de clés.
        preg_match_all("/'([A-Za-z][A-Za-z0-9_.]*)'\s*=>/", $php, $matches);

        $keys = array_values(array_unique($matches[1]));
        sort($keys);

        return $keys;
    }
}
