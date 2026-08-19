<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #2268 — la migration tenant `2026_08_03_000001_align_notification_preferences_unique_key`
 * ajoute UNIQUE(company_id, employee_id) sans dédupliquer d'abord : sur un
 * tenant dont la table `notification_preferences` a été créée SANS la
 * contrainte (avant 2026_05_22_000002), des doublons existants feraient
 * échouer le `ADD CONSTRAINT` (unique_violation) → `migrate` rouge → conteneur
 * Render en échec de boot.
 *
 * Cette suite verrouille le correctif (F-17, gardes resolveTableSchema
 * conservées) :
 *   1. déduplication préalable — une seule ligne par paire (company_id,
 *      employee_id), la plus récente conservée (`updated_at` max, puis `id`
 *      max en départage, `updated_at` NULL via `IS NOT DISTINCT FROM`) ;
 *   2. création effective de la contrainte UNIQUE ;
 *   3. idempotence (retry Render) ;
 *   4. down() qui retire la contrainte.
 */
class NotificationPreferencesUniqueMigrationTest extends TestCase
{
    use RefreshTenantDatabase;

    private const CONSTRAINT_NAME = 'notification_preferences_company_employee_unique';

    private const COMPANIES = [
        '11111111-1111-1111-1111-111111111111',
        '22222222-2222-2222-2222-222222222222',
        '33333333-3333-3333-3333-333333333333',
        '44444444-4444-4444-4444-444444444444',
    ];

    private function runMigration(): void
    {
        $migration = require database_path('migrations/tenant/2026_08_03_000001_align_notification_preferences_unique_key.php');
        $migration->up();
    }

    /**
     * Simule un tenant legacy : la contrainte (installée par le refresh via la
     * création de la table en 2026_05_22_000002) est retirée pour reproduire
     * l'état des tenants créés AVANT cette migration.
     */
    private function removeUniqueConstraint(): void
    {
        $schema = resolveTableSchema('notification_preferences');
        $this->assertNotNull($schema, 'table notification_preferences introuvable');

        DB::statement("ALTER TABLE \"{$schema}\".\"notification_preferences\" DROP CONSTRAINT IF EXISTS ".self::CONSTRAINT_NAME);
    }

    private function constraintExists(): bool
    {
        $schema = resolveTableSchema('notification_preferences');
        if ($schema === null) {
            return false;
        }

        return DB::selectOne(
            "SELECT 1
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE n.nspname = ?
                AND t.relname = 'notification_preferences'
                AND c.conname = ?",
            [$schema, self::CONSTRAINT_NAME]
        ) !== null;
    }

    /**
     * Insère une ligne de préférence (les colonnes métier dupliquables ne
     * servent pas ici — on verrouille la dédup sur la paire clé).
     */
    private function insertPreference(int $companyIndex, int $employeeId, ?string $updatedAt): int
    {
        return (int) DB::table('notification_preferences')->insertGetId([
            'company_id' => self::COMPANIES[$companyIndex],
            'employee_id' => $employeeId,
            'app_enabled' => true,
            'email_enabled' => true,
            'push_enabled' => true,
            'sms_enabled' => false,
            'whatsapp_enabled' => false,
            'created_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
    }

    /**
     * @return object{id: int|string, company_id: string, employee_id: int, updated_at: string|null}
     */
    private function preferencesForPair(int $companyIndex, int $employeeId): object
    {
        $row = DB::table('notification_preferences')
            ->where('company_id', self::COMPANIES[$companyIndex])
            ->where('employee_id', $employeeId)
            ->first();
        $this->assertNotNull($row, "aucune ligne pour la paire {$companyIndex}/{$employeeId}");

        /** @var object{id: int|string, company_id: string, employee_id: int, updated_at: string|null} $row */
        return $row;
    }

    public function test_migration_deduplicates_then_adds_unique_constraint(): void
    {
        $this->removeUniqueConstraint();

        // Paire A (0/1) — 3 lignes, updated_at distincts → la plus récente.
        $this->insertPreference(0, 1, '2026-01-01 10:00:00');
        $keptA = $this->insertPreference(0, 1, '2026-02-01 10:00:00'); // conservée
        $this->insertPreference(0, 1, '2026-01-15 10:00:00');

        // Paire B (1/2) — 2 lignes, même updated_at → départage par id (max).
        $this->insertPreference(1, 2, '2026-03-01 10:00:00');
        $keptB = $this->insertPreference(1, 2, '2026-03-01 10:00:00'); // conservée (id max)

        // Paire C (2/3) — 2 lignes, updated_at NULL → IS NOT DISTINCT FROM + id.
        $this->insertPreference(2, 3, null);
        $keptC = $this->insertPreference(2, 3, null); // conservée (id max)

        // Paire D (3/4) — ligne unique, intacte.
        $keptD = $this->insertPreference(3, 4, '2026-04-01 10:00:00');

        $this->runMigration();

        // 1. La contrainte UNIQUE(company_id, employee_id) est créée.
        $this->assertTrue($this->constraintExists(), 'UNIQUE doit exister après la migration');

        // 2. Une seule ligne par paire (company_id, employee_id).
        foreach ([0, 1, 2, 3] as $companyIndex) {
            $this->assertSame(
                1,
                DB::table('notification_preferences')
                    ->where('company_id', self::COMPANIES[$companyIndex])
                    ->where('employee_id', $companyIndex + 1)
                    ->count(),
                "une seule ligne doit rester pour la paire {$companyIndex}/".($companyIndex + 1),
            );
        }

        // 3. La ligne conservée est la plus récente (updated_at max, id max en départage).
        $this->assertSame($keptA, (int) $this->preferencesForPair(0, 1)->id, 'updated_at max conservé');
        $this->assertSame($keptB, (int) $this->preferencesForPair(1, 2)->id, 'même updated_at → id max conservé');
        $this->assertSame($keptC, (int) $this->preferencesForPair(2, 3)->id, 'updated_at NULL → id max conservé');
        $this->assertSame($keptD, (int) $this->preferencesForPair(3, 4)->id, 'ligne unique intacte');
    }

    public function test_migration_is_idempotent_on_retry(): void
    {
        $this->removeUniqueConstraint();
        $this->insertPreference(0, 1, '2026-01-01 10:00:00');
        $this->insertPreference(0, 1, '2026-02-01 10:00:00');

        $this->runMigration();
        $countAfterFirstRun = DB::table('notification_preferences')->count();

        // Retry Render : re-exécution → aucun doublon supprimé, aucune erreur.
        $this->runMigration();

        $this->assertSame($countAfterFirstRun, DB::table('notification_preferences')->count());
        $this->assertSame(1, DB::table('notification_preferences')->where('company_id', self::COMPANIES[0])->where('employee_id', 1)->count());
        $this->assertTrue($this->constraintExists());
    }

    public function test_unique_constraint_blocks_new_duplicates(): void
    {
        $this->removeUniqueConstraint();
        $this->insertPreference(3, 4, '2026-04-01 10:00:00');

        $this->runMigration();
        $this->assertTrue($this->constraintExists());

        try {
            // PostgreSQL aborts the current transaction after a constraint
            // violation; isolate the deliberate failure in a savepoint so the
            // shared test transaction remains usable for teardown.
            DB::transaction(function (): void {
                $this->insertPreference(3, 4, '2026-05-01 10:00:00');
                $this->fail('Un doublon (company_id, employee_id) doit être rejeté par la contrainte UNIQUE');
            });
        } catch (QueryException $e) {
            $this->assertSame('23505', (string) $e->getCode(), 'unique_violation attendue');
        }
    }

    public function test_down_drops_the_constraint(): void
    {
        $this->removeUniqueConstraint();
        $this->insertPreference(3, 4, '2026-04-01 10:00:00');

        $this->runMigration();
        $this->assertTrue($this->constraintExists());

        $migration = require database_path('migrations/tenant/2026_08_03_000001_align_notification_preferences_unique_key.php');
        $migration->down();

        $this->assertFalse($this->constraintExists(), 'down() doit retirer la contrainte UNIQUE');
    }
}
