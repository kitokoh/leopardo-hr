<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Support;

/**
 * Générateur de références publiques non séquentielles (verticale
 * FUNDRAISING — spec §6 anti-énumération) : `FC-…` pour les contributions,
 * `FP-…` pour les reversements. Alphabet sans ambiguïté (pas de 0/O, 1/I).
 */
final class ReferenceGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function contribution(): string
    {
        return 'FC-'.self::random(10);
    }

    public static function payout(): string
    {
        return 'FP-'.self::random(10);
    }

    private static function random(int $length): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= self::ALPHABET[random_int(0, $max)];
        }

        return $out;
    }
}
