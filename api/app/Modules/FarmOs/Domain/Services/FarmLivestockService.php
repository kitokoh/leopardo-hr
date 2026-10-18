<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalStatus;
use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Modules\FarmOs\Domain\Exceptions\FarmLivestockException;
use App\Modules\FarmOs\Domain\Models\FarmAnimal;
use App\Modules\FarmOs\Domain\Models\FarmAnimalHealthEvent;
use App\Modules\FarmOs\Domain\Models\FarmFeedRecord;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmLivestockEvent;
use App\Modules\FarmOs\Domain\Models\FarmProductionRecord;
use App\Modules\FarmOs\Domain\Models\FarmReproductionEvent;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T3.1 Effectif du cheptel et couts d'elevage.
 *
 * Invariants (exigences de la mission) :
 *   - l'effectif PRESENT d'un lot n'est modifie QUE par un evenement du journal
 *     `farm_livestock_events` : chaque changement laisse exactement une ligne ;
 *   - une SORTIE ne peut jamais rendre l'effectif negatif : on refuse (422)
 *     plutot que de laisser un effectif incoherent. C'est la garde qui empeche
 *     qu'une meme tete soit comptee deux fois (vente + mortalite) ;
 *   - la quantite d'un evenement est toujours POSITIVE : le sens est porte par
 *     le type d'evenement ;
 *   - IDEMPOTENT : le rejeu d'une ecriture mobile/hors ligne portant le meme
 *     `client_event_id` renvoie la ligne existante SANS re-decrementer
 *     l'effectif (c'est le point critique : un rejeu ne doit jamais doubler une
 *     mortalite) ;
 *   - un animal deja sorti (vendu, mort, transfere) ne peut pas ressortir ;
 *   - les couts sont separes en CONNUS et MANQUANTS : un total partiel n'est
 *     jamais presente comme un cout complet.
 *
 * Purete des couches (issue #6568) : aucune facade Laravel ici. La transaction
 * est portee par la connexion du modele (`getConnection()`).
 */
final class FarmLivestockService
{
    /**
     * Enregistre un mouvement d'effectif et met a jour l'effectif du lot.
     */
    public function recordEvent(
        Company $company,
        FarmLivestockEventType $type,
        int $quantity,
        ?FarmLivestockBatch $batch = null,
        ?FarmAnimal $animal = null,
        ?int $counterpartyId = null,
        ?string $unitPrice = null,
        ?string $currency = null,
        ?string $reason = null,
        ?int $recordedBy = null,
        ?string $clientEventId = null,
        ?Carbon $occurredAt = null,
    ): FarmLivestockEvent {
        if ($quantity <= 0) {
            throw FarmLivestockException::quantityMustBePositive((string) $quantity);
        }

        if ($batch !== null && $batch->company_id !== null && $batch->company_id !== $company->id) {
            throw FarmLivestockException::batchNotInCompany($batch->id);
        }

        if ($animal !== null && $animal->company_id !== null && $animal->company_id !== $company->id) {
            throw FarmLivestockException::animalNotInCompany($animal->id);
        }

        if ($batch !== null && $animal !== null && $animal->species !== $batch->species) {
            throw FarmLivestockException::speciesMismatch($batch->species, $animal->species);
        }

        $connection = FarmLivestockEvent::query()->getConnection();
        $occurred = $occurredAt ?? Carbon::now();

        return $connection->transaction(function () use (
            $company, $type, $quantity, $batch, $animal, $counterpartyId, $unitPrice,
            $currency, $reason, $recordedBy, $clientEventId, $occurred
        ): FarmLivestockEvent {
            // Idempotence terrain : un rejeu renvoie l'evenement deja ecrit et
            // NE re-applique PAS le mouvement d'effectif.
            if ($clientEventId !== null) {
                /** @var FarmLivestockEvent|null $existing */
                $existing = FarmLivestockEvent::query()
                    ->where('company_id', $company->id)
                    ->where('client_event_id', $clientEventId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $headCountAfter = null;

            if ($batch !== null) {
                /** @var FarmLivestockBatch $locked */
                $locked = FarmLivestockBatch::query()
                    ->where('company_id', $company->id)
                    ->whereKey($batch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $current = (int) $locked->head_count;

                if ($type->increasesHerd()) {
                    $headCountAfter = $current + $quantity;
                } else {
                    if ($quantity > $current) {
                        throw FarmLivestockException::insufficientHeadCount($locked->id, $current, $quantity);
                    }

                    $headCountAfter = $current - $quantity;
                }

                $locked->head_count = $headCountAfter;
                $locked->save();
            }

            // Un animal qui sort du cheptel change de statut : il reste dans le
            // registre (tracabilite) mais ne compte plus dans l'effectif present.
            if ($animal !== null) {
                $resulting = $type->resultingStatus();

                if ($resulting !== null) {
                    /** @var FarmAnimal $lockedAnimal */
                    $lockedAnimal = FarmAnimal::query()
                        ->where('company_id', $company->id)
                        ->whereKey($animal->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $lockedAnimal->statusEnum()->isPresent()) {
                        throw FarmLivestockException::animalAlreadyLeft($lockedAnimal->id);
                    }

                    $lockedAnimal->status = $resulting->value;
                    $lockedAnimal->save();
                }
            }

            $event = new FarmLivestockEvent([
                'farm_livestock_batch_id' => $batch?->id,
                'farm_animal_id' => $animal?->id,
                'event_type' => $type->value,
                'quantity' => $quantity,
                'head_count_after' => $headCountAfter,
                'occurred_at' => $occurred,
                'counterparty_id' => $counterpartyId,
                'unit_price' => $unitPrice !== null ? number_format((float) $unitPrice, 2, '.', '') : null,
                'currency' => $currency !== null ? strtoupper($currency) : null,
                'reason' => $reason,
                'recorded_by' => $recordedBy,
                'client_event_id' => $clientEventId,
            ]);
            $event->company_id = $company->id;
            $event->save();

            return $event;
        });
    }

    /**
     * Effectif PRESENT total, par espece. Seuls les lots actifs comptent : un lot
     * cloture ne gonfle pas l'effectif.
     *
     * @return array<string, int>
     */
    public function presentHeadCountBySpecies(Company $company): array
    {
        $batches = FarmLivestockBatch::query()
            ->where('company_id', $company->id)
            ->where('status', FarmAnimalStatus::Active->value)
            ->get();

        $totals = [];

        foreach ($batches as $batch) {
            $totals[$batch->species] = ($totals[$batch->species] ?? 0) + (int) $batch->head_count;
        }

        // Les animaux suivis individuellement et presents comptent aussi.
        $animals = FarmAnimal::query()
            ->where('company_id', $company->id)
            ->where('status', FarmAnimalStatus::Active->value)
            ->get();

        foreach ($animals as $animal) {
            $totals[$animal->species] = ($totals[$animal->species] ?? 0) + 1;
        }

        ksort($totals);

        return $totals;
    }

    /**
     * Couts d'elevage CONNUS, par nature, avec le nombre de lignes dont le cout
     * est INCONNU. Un total partiel n'est jamais presente comme complet.
     *
     * @return array{feed_known: string, feed_unknown_count: int, health_known: string, health_unknown_count: int, total_known: string, is_complete: bool}
     */
    public function knownCosts(Company $company, ?FarmLivestockBatch $batch = null): array
    {
        $feedQuery = FarmFeedRecord::query()->where('company_id', $company->id);
        $healthQuery = FarmAnimalHealthEvent::query()->where('company_id', $company->id);

        if ($batch !== null) {
            $feedQuery->where('farm_livestock_batch_id', $batch->id);
            $healthQuery->where('farm_livestock_batch_id', $batch->id);
        }

        $feedKnown = 0.0;
        $feedUnknown = 0;

        foreach ($feedQuery->get() as $feed) {
            if ($feed->cost_amount === null) {
                $feedUnknown++;

                continue;
            }

            $feedKnown += (float) $feed->cost_amount;
        }

        $healthKnown = 0.0;
        $healthUnknown = 0;

        foreach ($healthQuery->get() as $health) {
            if ($health->cost_amount === null) {
                $healthUnknown++;

                continue;
            }

            $healthKnown += (float) $health->cost_amount;
        }

        return [
            'feed_known' => number_format($feedKnown, 2, '.', ''),
            'feed_unknown_count' => $feedUnknown,
            'health_known' => number_format($healthKnown, 2, '.', ''),
            'health_unknown_count' => $healthUnknown,
            'total_known' => number_format($feedKnown + $healthKnown, 2, '.', ''),
            'is_complete' => $feedUnknown === 0 && $healthUnknown === 0,
        ];
    }

    /**
     * Productions DECLAREES agregees par type et unite sur une periode.
     *
     * @return list<array{production_type: string, unit: string, quantity: string, records: int}>
     */
    public function productionSummary(Company $company, ?Carbon $from = null, ?Carbon $to = null): array
    {
        $query = FarmProductionRecord::query()->where('company_id', $company->id);

        if ($from !== null) {
            $query->where('produced_at', '>=', $from);
        }

        if ($to !== null) {
            $query->where('produced_at', '<=', $to);
        }

        /** @var array<string, array{production_type: string, unit: string, quantity: float, records: int}> $totals */
        $totals = [];

        foreach ($query->get() as $record) {
            $key = $record->production_type.'|'.$record->unit;

            if (! isset($totals[$key])) {
                $totals[$key] = [
                    'production_type' => $record->production_type,
                    'unit' => $record->unit,
                    'quantity' => 0.0,
                    'records' => 0,
                ];
            }

            $totals[$key]['quantity'] += (float) $record->quantity;
            $totals[$key]['records']++;
        }

        $rows = [];

        foreach ($totals as $total) {
            $rows[] = [
                'production_type' => $total['production_type'],
                'unit' => $total['unit'],
                'quantity' => number_format($total['quantity'], 3, '.', ''),
                'records' => $total['records'],
            ];
        }

        return $rows;
    }

    /**
     * Echeances DECLAREES a venir : rappels sanitaires et mises bas prevues.
     * Aucune echeance n'est inventee — seules les dates saisies remontent.
     *
     * @return list<array<string, mixed>>
     */
    public function upcomingAlerts(Company $company, ?Carbon $at = null): array
    {
        $now = $at ?? Carbon::now();
        $rows = [];

        $health = FarmAnimalHealthEvent::query()
            ->where('company_id', $company->id)
            ->whereNotNull('next_due_at')
            ->where('next_due_at', '<=', $now)
            ->orderBy('next_due_at')
            ->get();

        foreach ($health as $event) {
            $rows[] = [
                'kind' => 'health_reminder',
                'farm_livestock_batch_id' => $event->farm_livestock_batch_id,
                'farm_animal_id' => $event->farm_animal_id,
                'title' => $event->title,
                'due_at' => $event->next_due_at?->toIso8601String(),
            ];
        }

        $reproduction = FarmReproductionEvent::query()
            ->where('company_id', $company->id)
            ->whereNotNull('expected_due_at')
            ->where('expected_due_at', '<=', $now)
            ->orderBy('expected_due_at')
            ->get();

        foreach ($reproduction as $event) {
            $rows[] = [
                'kind' => 'expected_birth',
                'farm_livestock_batch_id' => $event->farm_livestock_batch_id,
                'farm_animal_id' => $event->farm_animal_id,
                'due_at' => $event->expected_due_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * Date de mise bas prevue, calculee UNIQUEMENT depuis une duree de gestation
     * SAISIE. Sans duree saisie, la reponse est `null` : Farm OS ne possede
     * aucune table de durees de gestation par espece et n'invente donc jamais
     * une date.
     */
    public function expectedDueDate(Carbon $matingAt, ?int $gestationDays): ?Carbon
    {
        if ($gestationDays === null || $gestationDays <= 0) {
            return null;
        }

        return $matingAt->copy()->addDays($gestationDays);
    }
}
