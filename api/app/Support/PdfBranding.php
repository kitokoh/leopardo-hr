<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Image de marque du tenant dans les PDF (issue #7713).
 *
 * L'API `PATCH /company/branding` (CompanyBrandingController) stocke le logo
 * uploadé dans `companies.metadata.branding` (`logo_path` + `logo_disk`) et la
 * couleur primaire (`primary_color`). Les vues dompdf (facture, reçu, bulletin
 * de paie) consomment ces valeurs via ce helper : dompdf ne peut pas suivre
 * une URL http (remote désactivé), on lui donne donc le CHEMIN FICHIER local
 * résolu via Storage.
 *
 * Robustesse absolue : branding absent, disque inconnu, fichier manquant ou
 * toute exception → `null` / valeur par défaut, jamais d'erreur — le rendu
 * reste strictement identique à celui d'avant #7713.
 */
final class PdfBranding
{
    /**
     * Chemin fichier local du logo du tenant, ou null s'il n'y en a pas
     * d'exploitable (le rendu retombe alors sur l'en-tête texte historique).
     */
    public static function logoPath(?Company $company): ?string
    {
        try {
            $branding = self::branding($company);

            /** @var mixed $path */
            $path = $branding['logo_path'] ?? null;
            /** @var mixed $disk */
            $disk = $branding['logo_disk'] ?? null;

            if (! is_string($path) || $path === '' || ! is_string($disk) || $disk === '') {
                return null;
            }

            $absolute = Storage::disk($disk)->path($path);

            return is_file($absolute) ? $absolute : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Couleur primaire du tenant (hex #RRGGBB validé), ou $default.
     */
    public static function primaryColor(?Company $company, string $default): string
    {
        try {
            $branding = self::branding($company);
            /** @var mixed $color */
            $color = $branding['primary_color'] ?? null;

            return is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color) === 1
                ? strtoupper($color)
                : $default;
        } catch (Throwable) {
            return $default;
        }
    }

    /** @return array<mixed> */
    private static function branding(?Company $company): array
    {
        if ($company === null) {
            return [];
        }

        /** @var mixed $metadata */
        $metadata = $company->getAttribute('metadata');
        if (! is_array($metadata)) {
            return [];
        }

        /** @var mixed $branding */
        $branding = $metadata['branding'] ?? null;

        return is_array($branding) ? $branding : [];
    }
}
