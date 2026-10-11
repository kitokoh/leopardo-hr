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
     *
     * Le contrôle d'unicité est volontairement GLOBAL (cross-tenant) : le
     * slug est une clé publique globale (annuaire + contrainte unique sur
     * la table partagée) — un contrôle scopé tenant laisserait passer une
     * collision inter-tenant en 500 (leçon revue statique).
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

            $exists = Fundraiser::query()
                ->crossTenantForSystemTask('Fundraising SlugGenerator : contrôle d\'unicité GLOBAL du slug public (clé cross-tenant)')
                ->where('slug', $candidate)
                ->exists();

            if (! $exists) {
                return $candidate;
            }
        }

        // Dernier recours : suffixe plus long dans la limite de la colonne
        // (160) — la contrainte unique en base reste le garde-fou final.
        return Str::limit($base, 143, '').'-'.strtolower(Str::random(16));
    }
}
