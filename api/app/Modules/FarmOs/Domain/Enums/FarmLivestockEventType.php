<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T3.1 Type d'evenement de cheptel.
 *
 * Registre FERME et VERSIONNE. Le SENS de l'evenement (entree ou sortie
 * d'effectif) est porte par le type, jamais par le signe d'une quantite : une
 * quantite d'evenement est toujours POSITIVE.
 */
enum FarmLivestockEventType: string
{
    case Birth = 'birth';
    case Purchase = 'purchase';
    case Sale = 'sale';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Death = 'death';
    case Slaughter = 'slaughter';
    case Cull = 'cull';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.livestock_event.'.$this->value;
    }

    /**
     * L'evenement augmente-t-il l'effectif present ?
     */
    public function increasesHerd(): bool
    {
        return match ($this) {
            self::Birth, self::Purchase, self::TransferIn => true,
            self::Sale, self::TransferOut, self::Death, self::Slaughter, self::Cull => false,
        };
    }

    /**
     * Statut resultant pour l'animal concerne (NULL = statut inchange).
     */
    public function resultingStatus(): ?FarmAnimalStatus
    {
        return match ($this) {
            self::Sale => FarmAnimalStatus::Sold,
            self::Death, self::Slaughter => FarmAnimalStatus::Dead,
            self::TransferOut => FarmAnimalStatus::Transferred,
            self::Cull => FarmAnimalStatus::Culled,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
