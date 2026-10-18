<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T4.1 Qualite d'une donnee chiffree.
 *
 * C'est l'enum qui porte l'exigence d'HONNETETE de la mission : une rentabilite
 * calculee sur des donnees incompletes ne doit JAMAIS etre presentee comme un
 * resultat certain.
 *
 *   - `Real`      : montant declare sur une piece / un document reel ;
 *   - `Estimated` : montant ESTIME par l'exploitant (pas une facture) ;
 *   - `Missing`   : donnee ABSENTE — jamais remplacee par zero.
 */
enum FarmDataQuality: string
{
    case Real = 'real';
    case Estimated = 'estimated';
    case Missing = 'missing';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.data_quality.'.$this->value;
    }

    /**
     * Une donnee manquante ne peut pas etre additionnee a un total fiable.
     */
    public function isUsableInTotals(): bool
    {
        return $this !== self::Missing;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
