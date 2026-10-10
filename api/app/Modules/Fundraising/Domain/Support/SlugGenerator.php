<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Support;

use App\Modules\Fundraising\Domain\Models\Fundraiser;
use Illuminate\Support\Str;

/**
 * Générateur de slug public de cagnotte (verticale FUNDRAISING).
 *
 * `{titre-slugifié}-{suffixe aléatoire}` — non séquentiel (anti-énumération,
 * spec §6), tronqué à 160 caractères, unicité globale vérifiée en base avec
 * quelques retries avant de laisser la contrainte unique trancher.
 */
final class SlugGenerator
{
    /**
     * Génère un slug unique global pour une cagnotte.
     */
    public static function generate(string $title): string
    {
        $base = Str::slug($title);

        if ($base === '') {
            $base = 'cagnotte';
        }

        // Réserve la place du suffixe (« -xxxxxxxx » = 9 caractères).
        $base = Str::limit($base, 150, '');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = $base.'-'.strtolower(Str::random(8));

            if (! Fundraiser::query()->where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        // Dernier recours : suffixe plus long, la contrainte unique en base
        // reste le garde-fou final (spec §3.1).
        return $base.'-'.strtolower(Str::random(16));
    }
}
