<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Services;

use App\Support\SupportedCurrencies;

/**
 * Registre des devises supportées par le module Comptabilité — issue #5270.
 *
 * Source de vérité unique : l'union des devises du registre pays existant
 * (`CountryDefaults` : DZ, MA, TN, SN, CI, ML, BF, BJ, TG, NE, CM, GA, CG,
 * TD, CF, GQ, FR, TR, GB, US, CA). La validation de la devise des contacts,
 * des settings et de l'endpoint de conversion passe TOUTE par ce registre —
 * jamais de liste codée en dur ailleurs (règle #5270, anti-divergence).
 *
 * Depuis #8743, l'algorithme vit dans `App\Support\SupportedCurrencies` :
 * le BC-38 Paiements a besoin de exactement la même liste, et la garde
 * d'isolation des modules lui interdit d'importer `Modules/Accounting`.
 * Ce fichier reste l'API publique de son BC (aucun appelant ne change) et
 * délègue — la source est désormais partagée au lieu d'être dupliquée.
 */
final class AccountingCurrencies
{
    /**
     * Codes ISO 4217 (3 lettres) des devises supportées, triés.
     *
     * @return array<int, string>
     */
    public static function supported(): array
    {
        return SupportedCurrencies::all();
    }

    /**
     * Normalise un code devise (majuscules, sans espaces) ou null si le
     * format n'est pas un code ISO 4217 valide (3 lettres).
     */
    public static function normalize(?string $currency): ?string
    {
        return SupportedCurrencies::normalize($currency);
    }

    public static function isSupported(?string $currency): bool
    {
        return SupportedCurrencies::isSupported($currency);
    }
}
