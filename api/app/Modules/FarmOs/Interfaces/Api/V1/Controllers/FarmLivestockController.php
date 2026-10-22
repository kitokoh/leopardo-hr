<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalSex;
use App\Modules\FarmOs\Domain\Enums\FarmAnimalStatus;
use App\Modules\FarmOs\Domain\Enums\FarmCapability;
use App\Modules\FarmOs\Domain\Enums\FarmHealthEventType;
use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Modules\FarmOs\Domain\Enums\FarmProductionType;
use App\Modules\FarmOs\Domain\Enums\FarmReproductionEventType;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Modules\FarmOs\Domain\Exceptions\FarmLivestockException;
use App\Modules\FarmOs\Domain\Models\FarmAnimal;
use App\Modules\FarmOs\Domain\Models\FarmAnimalHealthEvent;
use App\Modules\FarmOs\Domain\Models\FarmFeedRecord;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmLivestockEvent;
use App\Modules\FarmOs\Domain\Models\FarmProductionRecord;
use App\Modules\FarmOs\Domain\Models\FarmReproductionEvent;
use App\Modules\FarmOs\Domain\Services\FarmCapabilityGate;
use App\Modules\FarmOs\Domain\Services\FarmLivestockService;
use App\Modules\FarmOs\Infrastructure\Context\FarmCompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Farm OS — T3.1 Elevage.
 *
 * Exigences d'honnetete portees par ce controleur :
 *   - l'outil `livestock` doit etre REELLEMENT active (fail-closed) ;
 *   - l'effectif present n'est jamais modifie directement : il decoule du
 *     journal d'evenements, et une sortie ne peut pas le rendre negatif ;
 *   - aucune duree de gestation n'est supposee : `expected_due_at` n'est
 *     calculee que depuis une duree SAISIE, sinon elle reste `null` ;
 *   - aucun diagnostic, aucun dosage, aucun delai d'attente calcule : Farm OS
 *     enregistre ce que l'exploitant declare et ne remplace pas un veterinaire ;
 *   - les couts sont separes en connus / manquants.
 */
final class FarmLivestockController
{
    public function __construct(
        private readonly FarmLivestockService $livestock,
        private readonly FarmCapabilityGate $gate,
    ) {}

    /**
     * Registre des lots de cheptel.
     */
    public function indexBatches(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $batches = FarmLivestockBatch::query()
            ->when($request->filled('species'), fn ($query) => $query->where('species', $request->string('species')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy('name')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $batches->getCollection()->map(fn (FarmLivestockBatch $batch): array => $this->batchPayload($batch))->all(),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'last_page' => $batches->lastPage(),
                'total' => $batches->total(),
                'reference' => [
                    'species' => FarmSpecies::values(),
                    'sexes' => FarmAnimalSex::values(),
                    'statuses' => FarmAnimalStatus::values(),
                    'event_types' => FarmLivestockEventType::values(),
                    'health_event_types' => FarmHealthEventType::values(),
                    'reproduction_event_types' => FarmReproductionEventType::values(),
                    'production_types' => FarmProductionType::values(),
                    'origins' => FarmAnimal::ORIGINS,
                ],
            ],
        ]);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'reference' => ['nullable', 'string', 'max:60'],
            'species' => ['required', 'string', Rule::in(FarmSpecies::values())],
            'breed' => ['nullable', 'string', 'max:80'],
            'sex' => ['nullable', 'string', Rule::in(FarmAnimalSex::values())],
            'farm_site_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:160'],
            'started_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_batch_id' => ['nullable', 'string', 'max:80'],
        ]);

        $batch = new FarmLivestockBatch($validated);
        // L'effectif de depart est TOUJOURS zero : il ne s'acquiert que par un
        // evenement du journal (naissance, achat, transfert entrant).
        $batch->head_count = 0;
        $batch->status = FarmAnimalStatus::Active->value;
        $batch->company_id = $this->company()->id;
        $batch->created_by = $request->user()?->id;
        $batch->save();

        return response()->json(['data' => $this->batchPayload($batch->refresh())], 201);
    }

    public function showBatch(FarmLivestockBatch $batch): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $batch->load([
            'animals',
            'events' => fn ($query) => $query->orderByDesc('occurred_at')->limit(100),
        ]);

        return response()->json([
            'data' => $this->batchPayload($batch) + [
                'animals' => $batch->animals->map(fn (FarmAnimal $animal): array => $this->animalPayload($animal))->all(),
                'events' => $batch->events->map(fn (FarmLivestockEvent $event): array => $this->eventPayload($event))->all(),
                'costs' => $this->livestock->knownCosts($this->company(), $batch),
            ],
        ]);
    }

    /**
     * Mouvement d'effectif (naissance, achat, vente, transfert, mortalite).
     */
    public function recordEvent(FarmLivestockBatch $batch, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(FarmLivestockEventType::values())],
            'quantity' => ['required', 'integer', 'min:1'],
            'counterparty_id' => ['nullable', 'integer'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['nullable', 'string', 'max:200'],
            'client_event_id' => ['nullable', 'string', 'max:80'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        return $this->run(fn (): FarmLivestockEvent => $this->livestock->recordEvent(
            $this->company(),
            FarmLivestockEventType::from($validated['event_type']),
            (int) $validated['quantity'],
            $batch,
            null,
            isset($validated['counterparty_id']) ? (int) $validated['counterparty_id'] : null,
            isset($validated['unit_price']) ? (string) $validated['unit_price'] : null,
            $validated['currency'] ?? null,
            $validated['reason'] ?? null,
            $request->user()?->id,
            $validated['client_event_id'] ?? null,
            isset($validated['occurred_at']) ? Carbon::parse($validated['occurred_at']) : null,
        ), $this->eventPayload(...));
    }

    /**
     * Registre des animaux identifies individuellement.
     */
    public function indexAnimals(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $animals = FarmAnimal::query()
            ->when($request->filled('species'), fn ($query) => $query->where('species', $request->string('species')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('batch_id'), fn ($query) => $query->where('farm_livestock_batch_id', $request->integer('batch_id')))
            ->orderBy('tag_number')
            ->paginate((int) $request->integer('per_page', 25));

        return response()->json([
            'data' => $animals->getCollection()->map(fn (FarmAnimal $animal): array => $this->animalPayload($animal))->all(),
            'meta' => [
                'current_page' => $animals->currentPage(),
                'last_page' => $animals->lastPage(),
                'total' => $animals->total(),
            ],
        ]);
    }

    public function storeAnimal(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'tag_number' => ['required', 'string', 'max:80'],
            'name' => ['nullable', 'string', 'max:120'],
            'species' => ['required', 'string', Rule::in(FarmSpecies::values())],
            'breed' => ['nullable', 'string', 'max:80'],
            'sex' => ['nullable', 'string', Rule::in(FarmAnimalSex::values())],
            'birth_date' => ['nullable', 'date'],
            'origin' => ['nullable', 'string', Rule::in(FarmAnimal::ORIGINS)],
            'farm_livestock_batch_id' => ['nullable', 'integer'],
            'farm_site_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_animal_id' => ['nullable', 'string', 'max:80'],
        ]);

        $animal = new FarmAnimal($validated);
        $animal->status = FarmAnimalStatus::Active->value;
        $animal->company_id = $this->company()->id;
        $animal->created_by = $request->user()?->id;
        $animal->save();

        return response()->json(['data' => $this->animalPayload($animal->refresh())], 201);
    }

    /**
     * Evenement sanitaire (vaccination, traitement, rappel). Aucun diagnostic,
     * aucun dosage calcule, aucun delai d'attente deduit.
     */
    public function storeHealthEvent(FarmLivestockBatch $batch, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(FarmHealthEventType::values())],
            'title' => ['required', 'string', 'max:160'],
            'product_name' => ['nullable', 'string', 'max:160'],
            'dosage' => ['nullable', 'string', 'max:80'],
            'administered_at' => ['required', 'date'],
            'next_due_at' => ['nullable', 'date'],
            'withdrawal_until' => ['nullable', 'date'],
            'veterinarian_id' => ['nullable', 'integer'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_health_id' => ['nullable', 'string', 'max:80'],
        ]);

        $event = new FarmAnimalHealthEvent($validated);
        $event->farm_livestock_batch_id = $batch->id;
        $event->company_id = $this->company()->id;
        $event->recorded_by = $request->user()?->id;
        $event->save();

        return response()->json(['data' => $this->healthPayload($event->refresh())], 201);
    }

    /**
     * Evenement de reproduction. `expected_due_at` n'est calculee que depuis une
     * duree de gestation SAISIE — sinon elle reste `null`.
     */
    public function storeReproductionEvent(FarmLivestockBatch $batch, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(FarmReproductionEventType::values())],
            'occurred_at' => ['required', 'date'],
            'gestation_days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'expected_due_at' => ['nullable', 'date'],
            'offspring_count' => ['nullable', 'integer', 'min:0'],
            'sire_animal_id' => ['nullable', 'integer'],
            'method' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_reproduction_id' => ['nullable', 'string', 'max:80'],
        ]);

        $occurredAt = Carbon::parse($validated['occurred_at']);

        $event = new FarmReproductionEvent($validated);
        $event->farm_livestock_batch_id = $batch->id;
        $event->occurred_at = $occurredAt;
        // Date declaree si fournie, sinon calculee depuis la duree SAISIE, sinon
        // `null` : Farm OS ne connait pas la duree de gestation d'une espece.
        $event->expected_due_at = isset($validated['expected_due_at'])
            ? Carbon::parse($validated['expected_due_at'])
            : $this->livestock->expectedDueDate($occurredAt, $validated['gestation_days'] ?? null);
        $event->company_id = $this->company()->id;
        $event->recorded_by = $request->user()?->id;
        $event->save();

        return response()->json(['data' => $this->reproductionPayload($event->refresh())], 201);
    }

    /**
     * Production animale DECLAREE (lait, oeufs, laine, miel...).
     */
    public function storeProduction(FarmLivestockBatch $batch, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'production_type' => ['required', 'string', Rule::in(FarmProductionType::values())],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:24'],
            'quality_grade' => ['nullable', 'string', 'max:40'],
            'produced_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_production_id' => ['nullable', 'string', 'max:80'],
        ]);

        $type = FarmProductionType::from($validated['production_type']);

        $record = new FarmProductionRecord($validated);
        $record->farm_livestock_batch_id = $batch->id;
        // Unite par defaut PROPOSEE selon le type, toujours surchargeable.
        $record->unit = $validated['unit'] ?? $type->defaultUnit();
        $record->company_id = $this->company()->id;
        $record->recorded_by = $request->user()?->id;
        $record->save();

        return response()->json(['data' => $this->productionPayload($record->refresh())], 201);
    }

    /**
     * Alimentation / ration (consommation et cout).
     */
    public function storeFeed(FarmLivestockBatch $batch, Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $validated = $request->validate([
            'feed_name' => ['required', 'string', 'max:160'],
            'farm_input_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:24'],
            'cost_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'fed_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'client_feed_id' => ['nullable', 'string', 'max:80'],
        ]);

        $feed = new FarmFeedRecord($validated);
        $feed->farm_livestock_batch_id = $batch->id;
        $feed->company_id = $this->company()->id;
        $feed->recorded_by = $request->user()?->id;
        $feed->save();

        return response()->json(['data' => $this->feedPayload($feed->refresh())], 201);
    }

    /**
     * Synthese d'elevage : effectif present par espece, productions declarees,
     * couts connus vs manquants, echeances declarees.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->gate->assertAllows($this->company(), FarmCapability::Livestock);

        $from = $request->filled('from') ? Carbon::parse($request->string('from')->toString()) : null;
        $to = $request->filled('to') ? Carbon::parse($request->string('to')->toString()) : null;

        return response()->json([
            'data' => [
                'present_head_count_by_species' => $this->livestock->presentHeadCountBySpecies($this->company()),
                'production' => $this->livestock->productionSummary($this->company(), $from, $to),
                'costs' => $this->livestock->knownCosts($this->company()),
                'alerts' => $this->livestock->upcomingAlerts($this->company()),
            ],
        ]);
    }

    /**
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  callable(): T  $action
     * @param  callable(T): array<string, mixed>  $payload
     */
    private function run(callable $action, callable $payload): JsonResponse
    {
        try {
            $result = $action();
        } catch (FarmLivestockException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->errorCode(),
            ], 422);
        }

        return response()->json(['data' => $payload($result)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(FarmLivestockBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'farm_site_id' => $batch->farm_site_id,
            'name' => $batch->name,
            'reference' => $batch->reference,
            'species' => $batch->species,
            'breed' => $batch->breed,
            'sex' => $batch->sex,
            // Effectif PRESENT, tenu par le journal d'evenements.
            'head_count' => (int) $batch->head_count,
            'location' => $batch->location,
            'started_on' => $batch->started_on?->toDateString(),
            'status' => $batch->status,
            'notes' => $batch->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function animalPayload(FarmAnimal $animal): array
    {
        return [
            'id' => $animal->id,
            'farm_livestock_batch_id' => $animal->farm_livestock_batch_id,
            'tag_number' => $animal->tag_number,
            'name' => $animal->name,
            'species' => $animal->species,
            'breed' => $animal->breed,
            'sex' => $animal->sex,
            'birth_date' => $animal->birth_date?->toDateString(),
            // Age `null` si la date de naissance n'est pas connue : jamais devine.
            'age_in_days' => $animal->ageInDays(),
            'origin' => $animal->origin,
            'location' => $animal->location,
            'status' => $animal->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(FarmLivestockEvent $event): array
    {
        return [
            'id' => $event->id,
            'farm_livestock_batch_id' => $event->farm_livestock_batch_id,
            'farm_animal_id' => $event->farm_animal_id,
            'event_type' => $event->event_type,
            'quantity' => (int) $event->quantity,
            'head_count_after' => $event->head_count_after,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            'unit_price' => $event->unit_price,
            'currency' => $event->currency,
            'reason' => $event->reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthPayload(FarmAnimalHealthEvent $event): array
    {
        return [
            'id' => $event->id,
            'farm_livestock_batch_id' => $event->farm_livestock_batch_id,
            'event_type' => $event->event_type,
            'title' => $event->title,
            'product_name' => $event->product_name,
            'dosage' => $event->dosage,
            'administered_at' => $event->administered_at->toIso8601String(),
            'next_due_at' => $event->next_due_at?->toIso8601String(),
            // Delai d'attente STOCKE tel que declare, jamais calcule.
            'withdrawal_until' => $event->withdrawal_until?->toDateString(),
            'cost_amount' => $event->cost_amount,
            'currency' => $event->currency,
            'is_due' => $event->isDueAt(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function reproductionPayload(FarmReproductionEvent $event): array
    {
        return [
            'id' => $event->id,
            'farm_livestock_batch_id' => $event->farm_livestock_batch_id,
            'event_type' => $event->event_type,
            'occurred_at' => $event->occurred_at->toIso8601String(),
            // `null` si aucune duree de gestation n'a ete saisie : jamais devinee.
            'expected_due_at' => $event->expected_due_at?->toIso8601String(),
            'gestation_days' => $event->gestation_days,
            'offspring_count' => $event->offspring_count,
            'method' => $event->method,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productionPayload(FarmProductionRecord $record): array
    {
        return [
            'id' => $record->id,
            'farm_livestock_batch_id' => $record->farm_livestock_batch_id,
            'production_type' => $record->production_type,
            'quantity' => $record->quantity,
            'unit' => $record->unit,
            'quality_grade' => $record->quality_grade,
            'produced_at' => $record->produced_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function feedPayload(FarmFeedRecord $feed): array
    {
        return [
            'id' => $feed->id,
            'farm_livestock_batch_id' => $feed->farm_livestock_batch_id,
            'feed_name' => $feed->feed_name,
            'farm_input_id' => $feed->farm_input_id,
            'quantity' => $feed->quantity,
            'unit' => $feed->unit,
            // Un cout inconnu reste `null` : jamais remplace par zero.
            'cost_amount' => $feed->cost_amount,
            'currency' => $feed->currency,
            'fed_at' => $feed->fed_at->toIso8601String(),
        ];
    }

    private function company(): Company
    {
        return app(FarmCompanyContext::class)->current();
    }
}
