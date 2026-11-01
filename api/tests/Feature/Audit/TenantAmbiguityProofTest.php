<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * PREUVE DU RISQUE DE SÉLECTION TENANT — contre-revue de l'audit IAM.
 *
 * Ce test ne dit pas ce que le rapport précédent affirmait. Il OBSERVE.
 *
 * Contexte : `public.user_lookups` porte le dispatch d'authentification
 * (`email` → `company_id` + `schema_name` + `employee_id`). `email` est
 * CLÉ PRIMAIRE (`2026_04_01_000003_create_public_support_tables.php:34`) :
 * une adresse ne peut désigner qu'UNE entreprise, par construction.
 *
 * `Employee::syncUserLookup()` (`Employee.php:757-790`) prétend se protéger
 * d'un écrasement cross-tenant :
 *
 *     $existing = DB::table(...)->where('email', $this->email)->first();
 *     if ($existing && (int) $existing->employee_id !== (int) $this->id) {
 *         return;   // « Never overwrite a lookup record belonging to a different employee »
 *     }
 *
 * Or `employees.id` est un `serial` **PAR SCHÉMA** : l'identifiant 1 existe
 * dans CHAQUE tenant. La garde compare donc deux entiers qui ne désignent pas
 * la même population — elle ne peut pas distinguer deux employés de deux
 * schémas. Deux comportements en résultent, tous deux silencieux :
 *
 *   A. ids ÉGAUX   → la garde est inopérante → `updateOrInsert` ÉCRASE la
 *      ligne : le dispatch bascule sur le dernier schéma synchronisé
 *      (« dernier arrivé gagne »). Le contexte change sous les pieds de la
 *      personne, sans qu'aucun de ses gestes ne l'ait demandé.
 *   B. ids DIFFÉRENTS → la garde `return` → la 2ᵉ appartenance est ABANDONNÉE
 *      sans journal : la personne ne peut jamais atteindre la seconde
 *      entreprise (déni d'accès silencieux).
 *
 * Aucune modification du code de production : ces tests OBSERVENT.
 */
#[Group('audit')]
class TenantAmbiguityProofTest extends TestCase
{
    use CreatesMvpSchema;

    private const SCHEMA_A = 't_alpha';

    private const SCHEMA_B = 't_beta';

    private const EMAIL = 'same.person@example.com';

    private const PASSWORD = 'UnMdp!Solide2026x';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Preuve multi-schémas : PostgreSQL uniquement.');
        }

        $this->dropProofSchemas();
    }

    protected function tearDown(): void
    {
        $this->dropProofSchemas();
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    // ==================================================================
    // SCÉNARIO A — identifiants qui COÏNCIDENT (le cas par défaut réel)
    // ==================================================================

    public function test_when_employee_ids_collide_the_dispatch_is_silently_overwritten(): void
    {
        // Deux schémas neufs : chacun démarre sa séquence à 1.
        $this->createProofSchema(self::SCHEMA_A, 'EMP-ALPHA', 1);
        $this->createProofSchema(self::SCHEMA_B, 'EMP-BETA', 1);

        $a = $this->seedMembership(self::SCHEMA_A, 'EMP-ALPHA', 'Alpha Corp');
        $rowAfterA = $this->lookupRow();

        fwrite(STDERR, sprintf(
            "\n[SCENARIO A] after alpha: schema=%s employee_id=%s\n",
            (string) $rowAfterA->schema_name,
            (string) $rowAfterA->employee_id,
        ));

        $this->assertSame(self::SCHEMA_A, (string) $rowAfterA->schema_name);

        $b = $this->seedMembership(self::SCHEMA_B, 'EMP-BETA', 'Beta Corp');
        $rowAfterB = $this->lookupRow();

        fwrite(STDERR, sprintf(
            "[SCENARIO A] after beta : schema=%s employee_id=%s\n[SCENARIO A] alpha_employee_id=%d beta_employee_id=%d collision=%s\n",
            (string) $rowAfterB->schema_name,
            (string) $rowAfterB->employee_id,
            $a['employee_id'],
            $b['employee_id'],
            $a['employee_id'] === $b['employee_id'] ? 'YES' : 'NO',
        ));

        // 1. Il n'y a JAMAIS deux lignes : la clé primaire l'interdit.
        $this->assertSame(
            1,
            DB::table('public.user_lookups')->where('email', self::EMAIL)->count(),
            'Une seule ligne peut exister : `email` est clé primaire.',
        );

        // 2. Le dispatch a basculé sur le DERNIER schéma synchronisé.
        $this->assertSame(
            self::SCHEMA_B,
            (string) $rowAfterB->schema_name,
            'La garde anti-écrasement est inopérante quand les ids coïncident : le dernier gagne.',
        );

        // 3. Aucune trace de l'écrasement : la personne n'a rien demandé.
        //    (Aucun paramètre `tenant`/`context` n'existe dans le login.)
        $this->assertNotSame(
            (string) $rowAfterA->schema_name,
            (string) $rowAfterB->schema_name,
            'Le contexte a changé sans action de l\'utilisateur.',
        );
    }

    // ==================================================================
    // SCÉNARIO B — identifiants DIFFÉRENTS (tenant au passé réel)
    // ==================================================================

    public function test_when_employee_ids_differ_the_second_membership_is_dropped_silently(): void
    {
        $this->createProofSchema(self::SCHEMA_A, 'EMP-ALPHA', 1);
        // t_beta a « vécu » : sa séquence ne repart pas de 1.
        $this->createProofSchema(self::SCHEMA_B, 'EMP-BETA', 42);

        $a = $this->seedMembership(self::SCHEMA_A, 'EMP-ALPHA', 'Alpha Corp');
        $firstRow = $this->lookupRow();

        $b = $this->seedMembership(self::SCHEMA_B, 'EMP-BETA', 'Beta Corp');
        $secondRow = $this->lookupRow();

        fwrite(STDERR, sprintf(
            "\n[SCENARIO B] alpha_employee_id=%d beta_employee_id=%d differ=%s\n[SCENARIO B] lookup schema=%s employee_id=%s\n",
            $a['employee_id'],
            $b['employee_id'],
            $a['employee_id'] !== $b['employee_id'] ? 'YES' : 'NO',
            (string) $secondRow->schema_name,
            (string) $secondRow->employee_id,
        ));

        // La ligne n'a pas bougé : la 2ᵉ appartenance est tombée à l'eau.
        $this->assertSame(
            (string) $firstRow->schema_name,
            (string) $secondRow->schema_name,
            'Le dispatch reste figé sur la 1ʳᵉ entreprise.',
        );

        $this->assertSame(
            (int) $firstRow->employee_id,
            (int) $secondRow->employee_id,
            'L\'employé de t_beta n\'a jamais été enregistré.',
        );

        // Et l'employé de t_beta existe bel et bien dans SON schéma : la perte
        // est purement celle du dispatch, pas des données.
        $betaExists = DB::selectOne(
            'SELECT count(*) AS n FROM '.self::SCHEMA_B.'.employees WHERE matricule = ?',
            ['EMP-BETA'],
        );

        $this->assertSame(1, (int) $betaExists->n, 'L\'employé de t_beta existe en base.');
    }

    // ==================================================================
    // SCÉNARIO C — le login ne permet AUCUNE sélection de contexte
    // ==================================================================

    public function test_login_offers_no_way_to_choose_the_tenant(): void
    {
        $this->createProofSchema(self::SCHEMA_A, 'EMP-ALPHA', 1);
        $this->createProofSchema(self::SCHEMA_B, 'EMP-BETA', 42);

        $this->seedMembership(self::SCHEMA_A, 'EMP-ALPHA', 'Alpha Corp');
        $this->seedMembership(self::SCHEMA_B, 'EMP-BETA', 'Beta Corp');

        // Tentative explicite : demander t_beta au login.
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'device_name' => 'proof',
            'tenant' => self::SCHEMA_B,
            'tenant_schema' => self::SCHEMA_B,
            'company_id' => 'whatever',
        ]);

        $status = $response->status();
        $json = $response->json() ?? [];

        fwrite(STDERR, sprintf(
            "[SCENARIO C] status=%d root_keys=%s tenant_schema=%s\n",
            $status,
            implode(',', array_keys($json)),
            var_export($json['tenant_schema'] ?? null, true),
        ));

        $this->assertSame(200, $status, 'Le login aboutit.');

        // Le contexte demandé est IGNORÉ — et, plus grave, la réponse ne dit
        // même PAS dans quelle entreprise la session a été ouverte :
        // `AuthController::login()` n'ajoute que token / token_type /
        // token_expires_at (`AuthController.php:105-110`), jamais
        // `tenant_schema`. Aucun champ du corps ne permet donc de savoir où
        // l'on est, ni de demander ailleurs.
        $this->assertArrayNotHasKey(
            'tenant_schema',
            $json,
            'La réponse du login n\'expose pas le schéma résolu.',
        );

        $this->assertArrayNotHasKey('available_tenants', $json);
        $this->assertArrayNotHasKey('contexts', $json);
        $this->assertArrayNotHasKey('companies', $json);

        // Le paramètre `tenant` envoyé n'a laissé aucune trace : il n'est lu
        // nulle part dans LoginRequest, donc silencieusement ignoré.
        $this->assertArrayNotHasKey('requested_tenant', $json);
    }

    // ==================================================================
    // Aides
    // ==================================================================

    /**
     * Ligne de dispatch `public.user_lookups` pour l'e-mail de preuve.
     *
     * Le type de retour est un *object shape* et non `object` : sans lui,
     * PHPStan niveau 8 refuse chaque `$row->schema_name` / `$row->employee_id`
     * (`property.notFound`). Le shape décrit exactement les deux colonnes lues.
     *
     * @return object{schema_name: string, employee_id: int}
     */
    private function lookupRow(): object
    {
        $row = DB::table('public.user_lookups')->where('email', self::EMAIL)->first();

        $this->assertNotNull($row, 'Une ligne de dispatch doit exister.');

        /** @var object{schema_name: string, employee_id: int} $row */
        return $row;
    }

    /**
     * Crée un schéma tenant réel ; `$startId` force la valeur de départ de la
     * séquence pour simuler un tenant dont les ids ne coïncident pas.
     */
    private function createProofSchema(string $schema, string $matricule, int $startId): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS '.$schema);

        DB::statement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s.employees (
                id serial PRIMARY KEY,
                matricule varchar(40) NOT NULL,
                first_name varchar(80) NOT NULL,
                last_name varchar(80) NOT NULL,
                email varchar(150) NOT NULL,
                phone varchar(40) NULL,
                password_hash varchar(255) NOT NULL,
                role varchar(20) NOT NULL DEFAULT \'employee\',
                manager_role varchar(30) NULL,
                status varchar(20) NOT NULL DEFAULT \'active\',
                company_id uuid NULL,
                two_fa_enabled_at timestamptz NULL,
                last_login_at timestamptz NULL,
                created_at timestamptz DEFAULT now(),
                updated_at timestamptz DEFAULT now()
            )',
            $schema,
        ));

        // Force l'identifiant du prochain INSERT.
        DB::statement(sprintf(
            "SELECT setval(pg_get_serial_sequence('%s.employees', 'id'), %d, false)",
            $schema,
            $startId,
        ));

        DB::statement(sprintf(
            'INSERT INTO %s.employees (matricule, first_name, last_name, email, phone, password_hash, role, manager_role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $schema,
        ), [
            $matricule,
            'Same',
            'Person',
            self::EMAIL,
            '+221770000001',
            Hash::make(self::PASSWORD),
            'manager',
            'principal',
            'active',
        ]);
    }

    private function dropProofSchemas(): void
    {
        foreach ([self::SCHEMA_A, self::SCHEMA_B] as $schema) {
            DB::statement('DROP SCHEMA IF EXISTS '.$schema.' CASCADE');
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::table('public.companies')
                ->whereIn('schema_name', [self::SCHEMA_A, self::SCHEMA_B])
                ->delete();
            DB::table('public.user_lookups')
                ->where('email', self::EMAIL)
                ->delete();
        } catch (\Throwable) {
            // Table absente selon le stade du harnais : sans objet.
        }
    }

    /**
     * @return array{schema: string, company_id: string, employee_id: int}
     */
    private function seedMembership(string $schema, string $matricule, string $companyName): array
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'name' => $companyName,
            'schema_name' => $schema,
        ]);

        $row = DB::selectOne(
            sprintf('SELECT id FROM %s.employees WHERE matricule = ?', $schema),
            [$matricule],
        );
        $employeeId = (int) $row->id;

        DB::statement(
            sprintf('UPDATE %s.employees SET company_id = ? WHERE id = ?', $schema),
            [$company->id, $employeeId],
        );

        // Rejoue le geste de production : modèle Employee résolu dans SON
        // schéma, puis syncUserLookup().
        $previous = DB::selectOne('SHOW search_path');
        $previousPath = (string) (($previous->search_path ?? '') ?: '');

        DB::statement('SET search_path TO '.$schema.', public');

        try {
            /** @var Employee $employee */
            $employee = Employee::withoutGlobalScopes()->findOrFail($employeeId);
            $employee->syncUserLookup();
        } finally {
            if ($previousPath !== '') {
                DB::statement('SET search_path TO '.$previousPath);
            }
        }

        return [
            'schema' => $schema,
            'company_id' => (string) $company->id,
            'employee_id' => $employeeId,
        ];
    }
}
