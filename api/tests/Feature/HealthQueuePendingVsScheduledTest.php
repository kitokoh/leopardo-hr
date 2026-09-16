<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #7540 — `/health` ne doit plus confondre « à traiter » et « planifié ».
 *
 * `DatabaseQueue::size()` — donc `checks.queue.size` et `checks.queue.queues`
 * — compte **toutes** les lignes d'une queue sans regarder `available_at` :
 * un job planifié à +7 jours y pèse autant qu'un job bloqué depuis 7 jours.
 * C'est ce qui a fait ouvrir #7540 (« la file `notifications` ne se draine
 * pas ») alors que les jobs en cause étaient le **planning du drip d'essai**
 * (`SendTrialDripEmailJob`, dispatché en +1/+3/+7 jours depuis
 * `VerifyTrialSignup`) et que le worker drainait correctement tout le reste.
 *
 * Ces cas verrouillent les **deux** signaux : `queues.*` (taille brute, contrat
 * historique conservé pour la garde de dérive et les tableaux de bord) **et**
 * `pending.*` / `scheduled.*` / `reserved.*` (la ventilation honnête).
 *
 * Le premier test est la non-régression stricte : un job planifié doit rester
 * compté dans `scheduled`, jamais dans `pending`.
 */
class HealthQueuePendingVsScheduledTest extends TestCase
{
    use RefreshTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('queue.default', 'database');

        DB::table('jobs')->delete();
    }

    public function test_a_scheduled_job_is_not_counted_as_pending(): void
    {
        // Le job du drip d'essai : disponible dans 7 jours.
        $this->insertJob('notifications', (int) now()->addDays(7)->timestamp, null);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        // Le signal historique (inchangé) voit le job…
        $response->assertJsonPath('checks.queue.queues.notifications', 1);
        $response->assertJsonPath('checks.queue.size', 1);

        // …et la ventilation dit la vérité : rien à traiter, tout est planifié.
        $response->assertJsonPath('checks.queue.pending.notifications', 0);
        $response->assertJsonPath('checks.queue.scheduled.notifications', 1);
        $response->assertJsonPath('checks.queue.pending_total', 0);
        $response->assertJsonPath('checks.queue.scheduled_total', 1);
    }

    public function test_a_runnable_job_is_pending_and_not_scheduled(): void
    {
        $this->insertJob('notifications', (int) now()->subMinute()->timestamp, null);

        $response = $this->getJson('/api/v1/health');

        $response->assertJsonPath('checks.queue.pending.notifications', 1);
        $response->assertJsonPath('checks.queue.scheduled.notifications', 0);
    }

    public function test_a_job_reserved_by_a_worker_is_reported_as_reserved(): void
    {
        $this->insertJob(
            'pdf',
            (int) now()->subMinutes(5)->timestamp,
            (int) now()->subMinutes(2)->timestamp,
        );

        $response = $this->getJson('/api/v1/health');

        $response->assertJsonPath('checks.queue.reserved.pdf', 1);
        $response->assertJsonPath('checks.queue.pending.pdf', 0);
        $response->assertJsonPath('checks.queue.scheduled.pdf', 0);
    }

    public function test_every_monitored_queue_is_present_in_the_breakdown(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertOk();

        // La ventilation couvre exactement les queues surveillées — une queue
        // oubliée serait un angle mort silencieux (le défaut d'origine).
        foreach (['default', 'documents', 'pdf', 'payroll', 'notifications', 'webhooks'] as $queue) {
            $response->assertJsonPath('checks.queue.pending.'.$queue, 0);
            $response->assertJsonPath('checks.queue.scheduled.'.$queue, 0);
        }
    }

    /**
     * Insère un job directement dans la table `jobs` (même convention que
     * `QueueSupervisionDatabaseTest`).
     */
    private function insertJob(string $queue, int $availableAt, ?int $reservedAt): void
    {
        DB::table('jobs')->insert([
            'queue' => $queue,
            'payload' => json_encode(['job' => 'Tests\\Fake'], JSON_THROW_ON_ERROR),
            'attempts' => 0,
            'reserved_at' => $reservedAt,
            'available_at' => $availableAt,
            'created_at' => $availableAt,
        ]);
    }
}
