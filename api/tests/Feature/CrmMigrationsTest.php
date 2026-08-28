<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Console\PendingCommand;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #5708 (CRM-V0-04) — migrations tenant `crm_accounts` / `crm_contacts`.
 *
 * Vérifie :
 *  1. la création des tables par les migrations (fresh) — colonnes et
 *     indexes (scope company, status, owner, account) ;
 *  2. la contrainte « contact primaire unique par account » (index unique
 *     partiel) ;
 *  3. le rollback (down()) des deux migrations.
 *
 * Le nommage `YYYY_MM_DD_0000NN_5708_*` porte la référence d'issue (#5431)
 * et la garde de collision (#1962) est vérifiée avant push.
 */
class CrmMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    public function test_crm_tables_are_created_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('crm_accounts'));
        $this->assertTrue(Schema::hasTable('crm_contacts'));

        foreach (['company_id', 'name', 'status', 'owner_id', 'email', 'phone', 'notes', 'archived_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('crm_accounts', $column),
                "crm_accounts.{$column} manquante"
            );
        }

        foreach ([
            'company_id', 'account_id', 'first_name', 'last_name', 'email', 'phone',
            'title', 'status', 'owner_id', 'is_primary', 'opt_in_email', 'opt_in_sms',
            'opt_in_whatsapp', 'notes', 'archived_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('crm_contacts', $column),
                "crm_contacts.{$column} manquante"
            );
        }
    }

    public function test_crm_accounts_indexes_are_created(): void
    {
        $this->assertTrue(Schema::hasIndex('crm_accounts', 'crm_accounts_company_idx'));
        $this->assertTrue(Schema::hasIndex('crm_accounts', 'crm_accounts_company_status_idx'));
        $this->assertTrue(Schema::hasIndex('crm_accounts', 'crm_accounts_company_owner_idx'));
    }

    public function test_crm_contacts_indexes_are_created(): void
    {
        $this->assertTrue(Schema::hasIndex('crm_contacts', 'crm_contacts_company_idx'));
        $this->assertTrue(Schema::hasIndex('crm_contacts', 'crm_contacts_company_status_idx'));
        $this->assertTrue(Schema::hasIndex('crm_contacts', 'crm_contacts_company_owner_idx'));
        $this->assertTrue(Schema::hasIndex('crm_contacts', 'crm_contacts_company_account_idx'));
        $this->assertTrue(Schema::hasIndex('crm_contacts', 'crm_contacts_primary_account_unique'));
    }

    public function test_primary_contact_is_unique_per_account(): void
    {
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyB = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        $accountA = DB::table('crm_accounts')->insertGetId([
            'company_id' => $companyA->id,
            'name' => 'Compte A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $accountB = DB::table('crm_accounts')->insertGetId([
            'company_id' => $companyB->id,
            'name' => 'Compte B',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $base = [
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'status' => 'active',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Premier contact primaire sur le compte A : OK.
        DB::table('crm_contacts')->insert(['account_id' => $accountA, 'company_id' => $companyA->id] + $base);

        // Second contact primaire sur le MÊME compte : rejeté (index partiel).
        try {
            DB::table('crm_contacts')->insert(['account_id' => $accountA, 'company_id' => $companyA->id] + $base);
            $this->fail('L\'index unique partiel n\'a pas rejeté le second contact primaire du même compte.');
        } catch (QueryException) {
            // Attendu : violation de la contrainte crm_contacts_primary_account_unique.
        }

        // Contact primaire sur un AUTRE compte : OK (pas de fuite de contrainte).
        DB::table('crm_contacts')->insert(['account_id' => $accountB, 'company_id' => $companyB->id] + $base);
        $this->assertSame(2, DB::table('crm_contacts')->where('is_primary', true)->count());
    }

    public function test_crm_migrations_fresh_run_and_rollback(): void
    {
        // Simule une base fraîche pour les deux migrations : tables droppées,
        // entrées retirées du repository, re-run depuis un répertoire dédié.
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_accounts');
        DB::table('migrations')->where('migration', 'like', '%5708%')->delete();

        $migrationsDir = base_path('database/migrations/tenant');
        $tmpDir = sys_get_temp_dir().'/crm_migrations_'.uniqid('', false);
        mkdir($tmpDir);
        copy(
            $migrationsDir.'/2026_08_28_000001_5708_create_crm_accounts_table.php',
            $tmpDir.'/2026_08_28_000001_5708_create_crm_accounts_table.php'
        );
        copy(
            $migrationsDir.'/2026_08_28_000002_5708_create_crm_contacts_table.php',
            $tmpDir.'/2026_08_28_000002_5708_create_crm_contacts_table.php'
        );

        try {
            // Fresh run : up() des deux migrations.
            /** @var PendingCommand $migrate */
            $migrate = $this->artisan('migrate', ['--path' => $tmpDir]);
            $migrate->assertExitCode(0);

            $this->assertTrue(Schema::hasTable('crm_accounts'));
            $this->assertTrue(Schema::hasTable('crm_contacts'));

            // Rollback : down() des deux migrations (dernier batch = le nôtre).
            /** @var PendingCommand $rollback */
            $rollback = $this->artisan('migrate:rollback', ['--path' => $tmpDir, '--step' => 1]);
            $rollback->assertExitCode(0);

            $this->assertFalse(Schema::hasTable('crm_accounts'));
            $this->assertFalse(Schema::hasTable('crm_contacts'));
        } finally {
            // Nettoyage du répertoire temporaire (le batch de test est
            // annulé par la transaction du trait RefreshDatabase).
            foreach (glob($tmpDir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($tmpDir);
        }
    }
}
