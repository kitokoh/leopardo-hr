<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Enums;

/**
 * Farm OS — T5.2 Operation realisable HORS LIGNE.
 *
 * Registre FERME et VERSIONNE, volontairement RESTREINT. C'est la liste exacte
 * de ce que Farm OS promet de savoir faire sans reseau — rien de plus. Toute
 * operation absente de ce registre est refusee par le service de synchronisation
 * plutot que tentee « au mieux ».
 *
 * Ce qui est volontairement ABSENT :
 *   - aucune operation de facturation ou de paiement (elles exigent le service
 *     de ventes existant, donc le reseau) ;
 *   - aucune suppression (une suppression hors ligne est trop risquee a
 *     reconcilier) ;
 *   - aucune modification d'un montant deja enregistre.
 */
enum FarmOfflineOperationType: string
{
    case RecordObservation = 'record_observation';
    case RecordOperation = 'record_operation';
    case RecordInputMovement = 'record_input_movement';
    case RecordHarvestMovement = 'record_harvest_movement';
    case RecordLivestockEvent = 'record_livestock_event';
    case RecordEquipmentUsage = 'record_equipment_usage';
    case RecordExpense = 'record_expense';
    case AttachPhoto = 'attach_photo';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'farm.offline_operation.'.$this->value;
    }

    /**
     * L'operation touche-t-elle un MOUVEMENT DE STOCK ?
     *
     * Ces operations ne sont JAMAIS resolues en « dernier ecrit gagne » : une
     * divergence est remontee en conflit pour arbitrage humain, sinon un stock
     * pourrait etre fausse silencieusement.
     */
    public function affectsStock(): bool
    {
        return in_array($this, [self::RecordInputMovement, self::RecordHarvestMovement], true);
    }

    /**
     * L'operation touche-t-elle une DONNEE FINANCIERE ?
     *
     * Meme regle que le stock : jamais d'ecrasement silencieux, car une marge
     * faussee est pire qu'un conflit visible.
     */
    public function affectsFinance(): bool
    {
        return $this === self::RecordExpense;
    }

    /**
     * L'operation est-elle purement ADDITIVE (aucun risque d'ecrasement) ?
     *
     * Une observation, une photo ou un evenement de cheptel s'ajoutent a
     * l'historique : ils ne peuvent pas ecraser une donnee existante.
     */
    public function isAdditive(): bool
    {
        return in_array($this, [
            self::RecordObservation,
            self::RecordOperation,
            self::RecordLivestockEvent,
            self::RecordEquipmentUsage,
            self::AttachPhoto,
        ], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
