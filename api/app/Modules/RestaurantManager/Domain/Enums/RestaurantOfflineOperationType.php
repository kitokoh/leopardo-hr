<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Enums;

/**
 * Restaurant OS — REST-OS-7 Operation realisable HORS LIGNE (#8609).
 *
 * Registre FERME et VERSIONNE, volontairement RESTREINT. C'est la liste exacte
 * de ce que la salle peut faire sans reseau — rien de plus. Toute operation
 * absente de ce registre est refusee par le service de synchronisation plutot
 * que tentee « au mieux ».
 *
 * Ce qui est volontairement ABSENT :
 *   - aucune SUPPRESSION (une suppression hors ligne est trop risquee a
 *     reconcilier : on ne sait pas si l'entite existait encore) ;
 *   - aucune modification de PRIX ni de TARIF (une divergence de prix entre
 *     deux terminaux fausserait une recette sans trace) ;
 *   - aucun REMBOURSEMENT (il exige le service de paiement, donc le reseau) ;
 *   - aucune modification d'un montant deja enregistre.
 */
enum RestaurantOfflineOperationType: string
{
    case RecordOrder = 'record_order';
    case RecordOrderItem = 'record_order_item';
    case RecordPayment = 'record_payment';
    case RecordStockMovement = 'record_stock_movement';
    case RecordWaste = 'record_waste';
    case RecordTableStatus = 'record_table_status';
    case CloseTableSession = 'close_table_session';
    case AttachReceiptPhoto = 'attach_receipt_photo';

    /**
     * Cle i18n (front) — jamais de libelle en dur cote backend.
     */
    public function translationKey(): string
    {
        return 'restaurant.offline_operation.'.$this->value;
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
        return in_array($this, [self::RecordStockMovement, self::RecordWaste], true);
    }

    /**
     * L'operation touche-t-elle une DONNEE FINANCIERE ?
     *
     * Meme regle que le stock : jamais d'ecrasement silencieux, car une recette
     * faussee est pire qu'un conflit visible.
     */
    public function affectsFinance(): bool
    {
        return $this === self::RecordPayment;
    }

    /**
     * L'operation est-elle purement ADDITIVE (aucun risque d'ecrasement) ?
     *
     * Une commande, une ligne de commande, une photo ou un statut de table
     * s'ajoutent a l'historique : ils ne peuvent pas ecraser une donnee
     * existante.
     */
    public function isAdditive(): bool
    {
        return in_array($this, [
            self::RecordOrder,
            self::RecordOrderItem,
            self::RecordTableStatus,
            self::AttachReceiptPhoto,
        ], true);
    }

    /**
     * L'operation vise-t-elle une ENTITE identifiable ?
     *
     * C'est la condition de la detection de conflit INTER-TERMINAUX : sans
     * entite ciblee, deux terminaux ne peuvent pas se contredire sur le meme
     * objet. `CloseTableSession` en est le cas d'ecole — deux postes deconnectes
     * peuvent tous deux cloturer la meme table.
     */
    public function targetsEntity(): bool
    {
        return in_array($this, [
            self::RecordPayment,
            self::RecordStockMovement,
            self::RecordWaste,
            self::RecordTableStatus,
            self::CloseTableSession,
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
