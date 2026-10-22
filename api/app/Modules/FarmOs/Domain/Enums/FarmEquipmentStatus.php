<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T2.2 Etat d'un materiel agricole.
 *
 * Registre FERME et VERSIONNE. L'etat est DECLARE par l'exploitant : Farm OS ne
 * deduit jamais un etat mecanique d'un compteur ou d'une telemetrie (aucune
 * integration capteur n'existe — exigence d'honnetete de la mission).
 */
enum FarmEquipmentStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';
    case Retired = 'retired';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.equipment_status.'.$this->value;
    }

    /**
     * Un materiel retire du registre n'accepte plus de nouvelle saisie
     * d'utilisation : on refuse plutot que d'ecrire dans un historique clos.
     */
    public function acceptsUsage(): bool
    {
        return $this !== self::Retired;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
