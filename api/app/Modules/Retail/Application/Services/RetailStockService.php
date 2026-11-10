<?php

declare(strict_types=1);

namespace App\Modules\Retail\Application\Services;

use App\Modules\Retail\Domain\Enums\RetailStockReasonCode;
use App\Modules\Retail\Domain\Models\RetailInventoryMovement;
use App\Modules\Retail\Domain\Models\RetailLocation;
use App\Modules\Retail\Domain\Models\RetailProduct;
use App\Modules\Retail\Domain\Models\RetailStockLevel;
use App\Modules\Retail\Domain\Models\RetailStockTransfer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * BC-17 RETAIL (#7673) — Point d'ecriture UNIQUE des quantites de stock.
 *
 * Chaque mouvement est applique EN TRANSACTION avec verrou de ligne
 * `SELECT ... FOR UPDATE` sur le niveau de stock (les mouvements concurrents
 * sont serialises par le verrou — pattern RestaurantStockService #6198).
 * Invariant : le stock n'est JAMAIS negatif — un delta qui rendrait la
 * quantite negative est refuse (ValidationException -> 422) et rien n'est
 * persiste. Chaque application journalise un RetailInventoryMovement
 * (delta signe, reason_code controle, reference polymorphe) pour la
 * tracabilite.
 *
 * Trois garanties ajoutees apres l'audit de la gestion de stock multi-tenant :
 *
 *  - **#9270 — idempotence** : le POS tablette est offline-first, une
 *    operation mise en file est rejouee apres reconnexion. Une cle
 *    d'idempotence optionnelle rend le rejeu SANS EFFET (le mouvement
 *    existant est retourne, le delta n'est pas reapplique). Sans elle, une
 *    reception rejouee doublait la quantite.
 *  - **#9269 — cout moyen pondere** : `avg_cost_minor` etait declare depuis
 *    #7673 et jamais ecrit, si bien que la valorisation du stock utilisait le
 *    cout CATALOGUE courant (une hausse de prix fournisseur reecrivait
 *    retroactivement la valeur du stock deja detenu). Les entrees portant un
 *    `unit_cost_minor` mettent a jour la moyenne ponderee ; les sorties ne la
 *    changent pas (methode du cout moyen pondere, pas FIFO).
 *  - **#9268 — transferts** : `transfer_in` / `transfer_out` existaient dans
 *    l'enum sans producteur. `transfer()` cree les DEUX mouvements dans UNE
 *    transaction, lies par le meme `reference_id` : pas de sortie sans
 *    entree.
 *
 * Pas de facade Laravel ici (purete de couche Application, garde #6568) :
 * la connexion est injectee via ConnectionInterface.
 */
final class RetailStockService
{
    /** Reference polymorphe des mouvements issus d'un transfert (#9268). */
    public const TRANSFER_REFERENCE_TYPE = 'retail_transfer';

    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * Applique un mouvement de stock signe sur (emplacement, produit) du
     * tenant de l'emplacement, et retourne le mouvement cree (le niveau de
     * stock a jour est accessible via $movement->stockLevel).
     *
     * @param  int|null  $unitCostMinor  Cout d'acquisition unitaire (minor units) — n'a de sens que sur une ENTREE.
     * @param  string|null  $idempotencyKey  Cle de deduplication : un rejeu avec la meme cle ne reapplique rien.
     *
     * @throws ValidationException 422 si le delta rendrait la quantite negative.
     */
    public function applyMovement(
        RetailLocation $location,
        RetailProduct $product,
        float $quantityDelta,
        RetailStockReasonCode $reasonCode,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $note = null,
        ?int $userId = null,
        ?int $unitCostMinor = null,
        ?string $idempotencyKey = null,
    ): RetailInventoryMovement {
        $companyId = (string) $location->company_id;

        /** @var RetailInventoryMovement $movement */
        $movement = $this->connection->transaction(
            function () use ($location, $product, $companyId, $quantityDelta, $reasonCode, $referenceType, $referenceId, $note, $userId, $unitCostMinor, $idempotencyKey): RetailInventoryMovement {
                // #9270 — rejeu : le mouvement existe deja pour cette cle,
                // on le retourne TEL QUEL. Aucun delta n'est reapplique, et
                // le niveau de stock n'est pas retouche.
                if ($idempotencyKey !== null) {
                    $existing = $this->findByIdempotencyKey($companyId, $idempotencyKey);

                    if ($existing instanceof RetailInventoryMovement) {
                        return $existing;
                    }
                }

                $level = $this->lockedLevel($companyId, (int) $location->id, (int) $product->id);

                $newQuantity = round((float) $level->quantity + $quantityDelta, 3);

                if ($newQuantity < 0.0) {
                    throw ValidationException::withMessages([
                        'quantity_delta' => 'Stock insuffisant : le mouvement rendrait la quantite negative.',
                    ]);
                }

                $attributes = ['quantity' => number_format($newQuantity, 3, '.', '')];

                // #9269 — cout moyen pondere : seules les ENTREES portant un
                // cout unitaire le font bouger. Une sortie ne change pas le
                // cout moyen (elle consomme au cout deja etabli).
                $newAverage = $this->weightedAverageCost($level, $quantityDelta, $unitCostMinor);

                if ($newAverage !== null) {
                    $attributes['avg_cost_minor'] = $newAverage;
                }

                $level->forceFill($attributes)->save();

                return RetailInventoryMovement::query()->create([
                    'company_id' => $companyId,
                    'location_id' => (int) $location->id,
                    'product_id' => (int) $product->id,
                    'stock_level_id' => (int) $level->id,
                    'quantity_delta' => number_format($quantityDelta, 3, '.', ''),
                    'unit_cost_minor' => $unitCostMinor,
                    'reason_code' => $reasonCode->value,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'note' => $note,
                    'user_id' => $userId,
                    'idempotency_key' => $idempotencyKey,
                ]);
            }
        );

        return $movement;
    }

    /**
     * Transfere une quantite d'un emplacement vers un autre (#9268).
     *
     * UNE transaction, DEUX mouvements lies par le meme `reference_id` :
     *  - `transfer_out` (delta negatif) sur l'emplacement source ;
     *  - `transfer_in` (delta positif) sur l'emplacement destination.
     *
     * Si le stock source est insuffisant, la ValidationException annule la
     * transaction : AUCUN des deux mouvements n'est persiste. C'est la
     * difference avec deux ajustements manuels, qui laissaient un transfert
     * « a moitie fait ».
     *
     * @throws ValidationException 422 si source = destination, ou stock insuffisant.
     */
    public function transfer(
        RetailLocation $from,
        RetailLocation $to,
        RetailProduct $product,
        float $quantity,
        ?string $note = null,
        ?int $userId = null,
        ?int $unitCostMinor = null,
    ): RetailStockTransfer {
        if ((int) $from->id === (int) $to->id) {
            throw ValidationException::withMessages([
                'to_location_id' => 'Un transfert exige deux emplacements differents.',
            ]);
        }

        if ($quantity <= 0.0) {
            throw ValidationException::withMessages([
                'quantity' => 'La quantite transferee doit etre strictement positive.',
            ]);
        }

        $companyId = (string) $from->company_id;

        /** @var RetailStockTransfer $transfer */
        $transfer = $this->connection->transaction(
            function () use ($from, $to, $product, $quantity, $note, $userId, $unitCostMinor, $companyId): RetailStockTransfer {
                /** @var RetailStockTransfer $created */
                $created = RetailStockTransfer::query()->create([
                    'company_id' => $companyId,
                    'from_location_id' => (int) $from->id,
                    'to_location_id' => (int) $to->id,
                    'product_id' => (int) $product->id,
                    'quantity' => number_format($quantity, 3, '.', ''),
                    'note' => $note,
                    'user_id' => $userId,
                ]);

                // Sortie : le stock insuffisant leve ici et annule TOUT
                // (y compris la ligne de transfert creee juste au-dessus).
                $this->applyMovement(
                    location: $from,
                    product: $product,
                    quantityDelta: -1 * $quantity,
                    reasonCode: RetailStockReasonCode::TransferOut,
                    referenceType: self::TRANSFER_REFERENCE_TYPE,
                    referenceId: (int) $created->id,
                    note: $note,
                    userId: $userId,
                );

                // Entree : le cout unitaire du transfert est celui de la
                // source (on deplace de la valeur, on n'en cree pas).
                //
                // #9295 — le commentaire le disait, le code ne le faisait
                // pas : quand l'appelant ne fournit pas de cout, l'entree
                // destination n'en portait AUCUN, le niveau destination
                // gardait son cout precedent (ou `null`), et la valorisation
                // retombait sur le cout CATALOGUE. La valeur sortait de
                // l'entrepot sans arriver en boutique : le total valorise de
                // l'entreprise baissait a chaque transfert.
                //
                // On lit donc le cout moyen du niveau SOURCE (le verrou est
                // deja tenu par la sortie ci-dessus, dans la meme
                // transaction). Un cout explicitement fourni reste prioritaire.
                $transferCost = $unitCostMinor;

                if ($transferCost === null) {
                    $sourceLevel = $this->lockedLevel($companyId, (int) $from->id, (int) $product->id);
                    $transferCost = $sourceLevel->avg_cost_minor;
                }

                $this->applyMovement(
                    location: $to,
                    product: $product,
                    quantityDelta: $quantity,
                    reasonCode: RetailStockReasonCode::TransferIn,
                    referenceType: self::TRANSFER_REFERENCE_TYPE,
                    referenceId: (int) $created->id,
                    note: $note,
                    userId: $userId,
                    unitCostMinor: $transferCost,
                );

                return $created;
            }
        );

        return $transfer;
    }

    /**
     * Mouvement deja enregistre pour cette cle d'idempotence, dans ce tenant.
     */
    private function findByIdempotencyKey(string $companyId, string $idempotencyKey): ?RetailInventoryMovement
    {
        /** @var RetailInventoryMovement|null $movement */
        $movement = RetailInventoryMovement::query()
            ->where('company_id', $companyId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        return $movement;
    }

    /**
     * Cout moyen pondere apres une ENTREE portant un cout unitaire.
     *
     * `newAvg = (qty * avg + delta * unitCost) / (qty + delta)`
     *
     * Retourne `null` quand le cout moyen ne doit pas bouger : sortie, delta
     * nul, ou cout unitaire absent (on n'invente jamais un cout). Un niveau
     * sans cout moyen connu prend directement le cout de la premiere entree.
     */
    private function weightedAverageCost(RetailStockLevel $level, float $quantityDelta, ?int $unitCostMinor): ?int
    {
        if ($quantityDelta <= 0.0 || $unitCostMinor === null) {
            return null;
        }

        $currentQuantity = (float) $level->quantity;
        $currentAverage = $level->avg_cost_minor;

        if ($currentAverage === null || $currentQuantity <= 0.0) {
            return $unitCostMinor;
        }

        $totalQuantity = $currentQuantity + $quantityDelta;

        if ($totalQuantity <= 0.0) {
            return $unitCostMinor;
        }

        $weighted = (($currentQuantity * (int) $currentAverage) + ($quantityDelta * $unitCostMinor)) / $totalQuantity;

        return (int) round($weighted);
    }

    /**
     * Charge (ou cree) le niveau de stock avec verrou de ligne
     * `SELECT ... FOR UPDATE` — a appeler DANS une transaction.
     */
    private function lockedLevel(string $companyId, int $locationId, int $productId): RetailStockLevel
    {
        /** @var RetailStockLevel|null $level */
        $level = RetailStockLevel::query()
            ->where('company_id', $companyId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if ($level instanceof RetailStockLevel) {
            return $level;
        }

        // Ligne absente : creation dans la transaction courante.
        /** @var RetailStockLevel $created */
        $created = RetailStockLevel::query()->firstOrCreate([
            'company_id' => $companyId,
            'location_id' => $locationId,
            'product_id' => $productId,
        ], [
            'quantity' => '0.000',
        ]);

        // #9294 — `firstOrCreate` ne garantit PAS le verrou.
        //
        // Le commentaire precedent affirmait que « la ligne inseree est
        // verrouillee par l'INSERT jusqu'au commit » : c'est faux dans le cas
        // concurrent. Deux transactions qui ne trouvent aucune ligne ne
        // verrouillent rien (`SELECT ... FOR UPDATE` sur un ensemble vide) ;
        // l'une insere, l'autre viole la contrainte unique, et Laravel
        // rattrape la violation en relisant la ligne — SANS `lockForUpdate`.
        // L'appelant repartait donc avec une ligne non verrouillee et
        // ecrivait une quantite calculee sur une lecture non serialisee.
        //
        // On re-acquiert explicitement le verrou avant de retourner la ligne.
        /** @var RetailStockLevel|null $locked */
        $locked = RetailStockLevel::query()
            ->where('company_id', $companyId)
            ->where('location_id', $locationId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        return $locked ?? $created;
    }
}
