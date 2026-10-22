<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\FarmOs;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\FarmOs\Domain\Enums\FarmLivestockEventType;
use App\Modules\FarmOs\Domain\Enums\FarmProductionType;
use App\Modules\FarmOs\Domain\Enums\FarmSpecies;
use App\Modules\FarmOs\Domain\Exceptions\FarmLivestockException;
use App\Modules\FarmOs\Domain\Models\FarmAnimal;
use App\Modules\FarmOs\Domain\Models\FarmAnimalHealthEvent;
use App\Modules\FarmOs\Domain\Models\FarmFeedRecord;
use App\Modules\FarmOs\Domain\Models\FarmLivestockBatch;
use App\Modules\FarmOs\Domain\Models\FarmProductionRecord;
use App\Modules\FarmOs\Domain\Services\FarmLivestockService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Farm OS — T3.1 : comportement REEL du registre de cheptel.
 *
 * Ces tests exercent le service metier sur un schema tenant (effectif, refus de
 * sortie excessive, idempotence, isolation multi-tenant). Ils exigent
 * PostgreSQL et ne tournent donc qu'en CI.
 *
 * Exigence d'honnetete verifiee ici : aucune duree de gestation n'est supposee,
 * aucun cout inconnu n'est remplace par zero, et une meme tete ne peut pas etre
 * comptee deux fois (vente + mortalite).
 */
final class FarmLivestockTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        app()->instance('current_company', $this->company);
    }

    public function test_batch_starts_with_an_empty_herd(): void
    {
        $batch = $this->makeBatch();

        // L'effectif de depart est TOUJOURS zero : il ne s'acquiert que par un
        // evenement du journal.
        $this->assertSame(0, (int) $batch->head_count);
    }

    public function test_entry_events_increase_the_herd(): void
    {
        $batch = $this->makeBatch();
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 10, $batch);
        $this->assertSame(10, (int) $batch->refresh()->head_count);

        $service->recordEvent($this->company, FarmLivestockEventType::Birth, 3, $batch);
        $this->assertSame(13, (int) $batch->refresh()->head_count);
    }

    public function test_exit_events_decrease_the_herd(): void
    {
        $batch = $this->makeBatch();
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 10, $batch);
        $service->recordEvent($this->company, FarmLivestockEventType::Sale, 4, $batch);

        $this->assertSame(6, (int) $batch->refresh()->head_count);
    }

    /**
     * GARDE ANTI-DOUBLE-COMPTAGE : une sortie ne peut pas depasser l'effectif
     * present. Sans cette garde, une vente puis une mortalite sur les memes
     * tetes rendraient l'effectif negatif et fausseraient tous les rapports.
     */
    public function test_exit_cannot_exceed_the_present_herd(): void
    {
        $batch = $this->makeBatch();
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 5, $batch);

        $this->expectException(FarmLivestockException::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Sale, 6, $batch);
    }

    public function test_quantity_must_be_positive(): void
    {
        $batch = $this->makeBatch();

        $this->expectException(FarmLivestockException::class);

        app(FarmLivestockService::class)->recordEvent($this->company, FarmLivestockEventType::Purchase, 0, $batch);
    }

    public function test_event_records_the_herd_size_after_the_move(): void
    {
        $batch = $this->makeBatch();
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 7, $batch);
        $event = $service->recordEvent($this->company, FarmLivestockEventType::Death, 2, $batch);

        $this->assertSame(5, (int) $event->head_count_after);
    }

    /**
     * IDEMPOTENCE CRITIQUE : le rejeu d'une ecriture hors ligne ne doit JAMAIS
     * re-appliquer le mouvement d'effectif — sinon une mortalite saisie deux
     * fois tuerait deux fois le meme animal.
     */
    public function test_event_recording_is_idempotent_on_client_id(): void
    {
        $batch = $this->makeBatch();
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 10, $batch);

        $first = $service->recordEvent($this->company, FarmLivestockEventType::Death, 3, $batch, null, null, null, null, null, null, 'offline-death-1');
        $second = $service->recordEvent($this->company, FarmLivestockEventType::Death, 3, $batch, null, null, null, null, null, null, 'offline-death-1');

        $this->assertSame($first->id, $second->id);
        // L'effectif n'a ete decremente QU'UNE fois.
        $this->assertSame(7, (int) $batch->refresh()->head_count);
        $this->assertSame(1, DB::table('farm_livestock_events')->where('company_id', $this->company->id)->where('client_event_id', 'offline-death-1')->count());
    }

    public function test_an_animal_that_left_cannot_leave_twice(): void
    {
        $batch = $this->makeBatch();
        $animal = $this->makeAnimal($batch);
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Sale, 1, $batch, $animal);
        $this->assertSame('sold', $animal->refresh()->status);

        $this->expectException(FarmLivestockException::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Death, 1, $batch, $animal);
    }

    public function test_species_mismatch_between_animal_and_batch_is_refused(): void
    {
        $batch = $this->makeBatch(['species' => FarmSpecies::Cattle->value]);
        $animal = $this->makeAnimal($batch, ['species' => FarmSpecies::Sheep->value]);

        $this->expectException(FarmLivestockException::class);

        app(FarmLivestockService::class)->recordEvent($this->company, FarmLivestockEventType::Sale, 1, $batch, $animal);
    }

    public function test_present_head_count_aggregates_batches_and_individual_animals(): void
    {
        $batch = $this->makeBatch(['species' => FarmSpecies::Cattle->value]);
        $service = app(FarmLivestockService::class);
        $service->recordEvent($this->company, FarmLivestockEventType::Purchase, 12, $batch);

        $this->makeAnimal($batch);

        $totals = $service->presentHeadCountBySpecies($this->company);

        $this->assertSame(13, $totals[FarmSpecies::Cattle->value]);
    }

    public function test_sold_animals_do_not_count_in_the_present_herd(): void
    {
        $batch = $this->makeBatch();
        $animal = $this->makeAnimal($batch);
        $service = app(FarmLivestockService::class);

        $service->recordEvent($this->company, FarmLivestockEventType::Sale, 1, $batch, $animal);

        $totals = $service->presentHeadCountBySpecies($this->company);

        $this->assertArrayNotHasKey(FarmSpecies::Cattle->value, $totals);
    }

    /**
     * Aucune duree de gestation n'est supposee : sans duree SAISIE, Farm OS ne
     * produit AUCUNE date de mise bas.
     */
    public function test_expected_due_date_requires_a_declared_gestation_length(): void
    {
        $service = app(FarmLivestockService::class);
        $mating = Carbon::parse('2026-01-01 08:00:00');

        $this->assertNull($service->expectedDueDate($mating, null));
        $this->assertNull($service->expectedDueDate($mating, 0));

        $due = $service->expectedDueDate($mating, 283);
        $this->assertNotNull($due);
        $this->assertSame('2026-10-11', $due->toDateString());
    }

    public function test_known_costs_separate_known_from_missing(): void
    {
        $batch = $this->makeBatch();

        $feedWithCost = new FarmFeedRecord([
            'farm_livestock_batch_id' => $batch->id,
            'feed_name' => 'hay',
            'quantity' => '100.000',
            'unit' => 'kg',
            'cost_amount' => '80.00',
            'fed_at' => Carbon::now(),
        ]);
        $feedWithCost->company_id = $this->company->id;
        $feedWithCost->save();

        $feedWithoutCost = new FarmFeedRecord([
            'farm_livestock_batch_id' => $batch->id,
            'feed_name' => 'silage',
            'quantity' => '50.000',
            'unit' => 'kg',
            'fed_at' => Carbon::now(),
        ]);
        $feedWithoutCost->company_id = $this->company->id;
        $feedWithoutCost->save();

        $costs = app(FarmLivestockService::class)->knownCosts($this->company, $batch);

        $this->assertSame('80.00', $costs['feed_known']);
        $this->assertSame(1, $costs['feed_unknown_count']);
        // Un total partiel n'est JAMAIS presente comme un cout complet.
        $this->assertFalse($costs['is_complete']);
    }

    public function test_production_summary_aggregates_declared_output(): void
    {
        $batch = $this->makeBatch();

        foreach (['120.500', '80.000'] as $quantity) {
            $record = new FarmProductionRecord([
                'farm_livestock_batch_id' => $batch->id,
                'production_type' => FarmProductionType::Milk->value,
                'quantity' => $quantity,
                'unit' => 'l',
                'produced_at' => Carbon::now(),
            ]);
            $record->company_id = $this->company->id;
            $record->save();
        }

        $summary = app(FarmLivestockService::class)->productionSummary($this->company);

        $this->assertCount(1, $summary);
        $this->assertSame('200.500', $summary[0]['quantity']);
        $this->assertSame(2, $summary[0]['records']);
    }

    public function test_upcoming_alerts_only_include_declared_due_dates(): void
    {
        $batch = $this->makeBatch();

        $due = new FarmAnimalHealthEvent([
            'farm_livestock_batch_id' => $batch->id,
            'event_type' => 'vaccination',
            'title' => 'booster',
            'administered_at' => Carbon::now()->subMonths(6),
            'next_due_at' => Carbon::now()->subDay(),
        ]);
        $due->company_id = $this->company->id;
        $due->save();

        // Sans `next_due_at` declare, aucun rappel n'est invente.
        $noDue = new FarmAnimalHealthEvent([
            'farm_livestock_batch_id' => $batch->id,
            'event_type' => 'checkup',
            'title' => 'routine check',
            'administered_at' => Carbon::now()->subDay(),
        ]);
        $noDue->company_id = $this->company->id;
        $noDue->save();

        $alerts = app(FarmLivestockService::class)->upcomingAlerts($this->company);

        $this->assertCount(1, $alerts);
        $this->assertSame('health_reminder', $alerts[0]['kind']);
        $this->assertSame('booster', $alerts[0]['title']);
    }

    public function test_batch_of_another_tenant_is_refused(): void
    {
        $other = Company::factory()->create();

        // Le trait BelongsToCompany ECASE `company_id` avec le tenant actif sur
        // tout `creating` Eloquent (#7646) : un modele construit en PHP ne peut
        // pas appartenir a un autre tenant. La ligne etrangere est donc inseree
        // en SQL brut puis relue cross-tenant, afin d'eprouver REELLEMENT le
        // controle d'appartenance du service.
        DB::table('farm_livestock_batches')->insert([
            'id' => 900002,
            'company_id' => $other->id,
            'name' => 'foreign herd',
            'species' => FarmSpecies::Cattle->value,
            'head_count' => 0,
            'status' => 'active',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        /** @var FarmLivestockBatch $foreign */
        $foreign = FarmLivestockBatch::crossTenantForPlatformAdmin()->findOrFail(900002);

        $this->assertSame($other->id, $foreign->company_id);

        $this->expectException(FarmLivestockException::class);

        app(FarmLivestockService::class)->recordEvent($this->company, FarmLivestockEventType::Purchase, 1, $foreign);
    }

    public function test_livestock_data_is_isolated_between_tenants(): void
    {
        $other = Company::factory()->create();

        $this->makeBatch();

        $this->assertSame(1, DB::table('farm_livestock_batches')->where('company_id', $this->company->id)->count());
        $this->assertSame(0, DB::table('farm_livestock_batches')->where('company_id', $other->id)->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeBatch(array $attributes = []): FarmLivestockBatch
    {
        $batch = new FarmLivestockBatch(array_merge([
            'name' => 'herd 1',
            'species' => FarmSpecies::Cattle->value,
        ], $attributes));

        $batch->head_count = 0;
        $batch->company_id = $this->company->id;
        $batch->save();

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeAnimal(FarmLivestockBatch $batch, array $attributes = []): FarmAnimal
    {
        $animal = new FarmAnimal(array_merge([
            'tag_number' => 'TAG-'.random_int(1000, 9999),
            'species' => $batch->species,
            'farm_livestock_batch_id' => $batch->id,
        ], $attributes));

        $animal->company_id = $this->company->id;
        $animal->save();

        return $animal;
    }
}
