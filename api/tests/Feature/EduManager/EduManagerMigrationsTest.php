<?php

declare(strict_types=1);

namespace Tests\Feature\EduManager;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5818 (EDU-002) — migrations EduManager : campus, élèves,
 * responsables légaux et relations autorisées.
 *
 * Verrouille :
 *   1. le placement des 4 tables dans le schéma tenant (shared_tenants) ;
 *   2. `company_id` NON nullable (isolation BelongsToCompany) ;
 *   3. les contraintes CHECK nommées (status / relationship_code) ;
 *   4. les UNIQUE composites par tenant (code campus, numéro élève) ;
 *   5. l'impossibilité STRUCTURELLE des relations cross-tenant (FK
 *      composites student_id/guardian_id + company_id) ;
 *   6. l'unicité de la paire (student_id, guardian_id) par tenant ;
 *   7. l'idempotence des gardes F-17 (up() rejoué) et le cycle down()/up().
 */
class EduManagerMigrationsTest extends TestCase
{
    use RefreshTenantDatabase;

    /** @var list<string> */
    private const TABLES = [
        // EDU-002 (issue #5818)
        'edu_campuses',
        'edu_students',
        'edu_guardians',
        'edu_student_guardians',
        // EDU-003 (issue #5819)
        'edu_academic_years',
        'edu_classes',
        'edu_subjects',
        'edu_teachers',
        'edu_teacher_subjects',
        // EDU-004 (issue #5820)
        'edu_admissions',
        // EDU-005 (issue #5821)
        'edu_attendance_records',
        'edu_attendance_corrections',
        // EDU-006 (issue #5822)
        'edu_timetable_slots',
        // EDU-007 (issue #5823)
        'edu_assessments',
        'edu_grades',
        'edu_grade_versions',
        // EDU-008 (issue #5824)
        'edu_report_cards',
    ];

    /** @var list<string> */
    private const MIGRATIONS = [
        // EDU-002 (issue #5818) — ordre chronologique des préfixes.
        '2026_08_30_000101_5818_create_edu_campuses_table',
        '2026_08_30_000102_5818_create_edu_students_table',
        '2026_08_30_000103_5818_create_edu_guardians_table',
        '2026_08_30_000104_5818_create_edu_student_guardians_table',
        // EDU-003 (issue #5819)
        '2026_08_30_000201_5819_create_edu_academic_years_table',
        '2026_08_30_000202_5819_create_edu_classes_table',
        '2026_08_30_000203_5819_create_edu_subjects_table',
        '2026_08_30_000204_5819_create_edu_teachers_table',
        '2026_08_30_000205_5819_create_edu_teacher_subjects_table',
        // EDU-004 (issue #5820)
        '2026_08_30_000301_5820_create_edu_admissions_table',
        // EDU-005 (issue #5821)
        '2026_08_30_000401_5821_create_edu_attendance_records_table',
        '2026_08_30_000402_5821_create_edu_attendance_corrections_table',
        // EDU-006 (issue #5822)
        // EDU-007 (issue #5823) — `000409` crée les trois tables d'évaluation
        // (assessments, grades, grade_versions) : c'est lui la déclaration
        // canonique, arbitrage #7571 (les fichiers individuels 000601/602/603
        // étaient la 2ᵉ génération, silencieusement ignorée).
        '2026_08_30_000409_5823_create_edu_assessment_tables',
        // EDU-008 (issue #5824),
        '2026_08_30_001516_5822_create_edu_timetable_slots_table',
        '2026_08_30_000410_5824_create_edu_report_card_tables',
    ];

    private function newCompany(): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);

        return $company;
    }

    private function tableSchema(string $table): ?string
    {
        $row = DB::selectOne(
            'SELECT t.table_schema
               FROM information_schema.tables t
              WHERE t.table_name = ?
              ORDER BY t.table_schema
              LIMIT 1',
            [$table]
        );

        return $row ? (string) $row->table_schema : null;
    }

    private function migration(string $basename): Migration
    {
        $path = database_path("migrations/tenant/{$basename}.php");
        $this->assertFileExists($path);

        $migration = require $path;

        $this->assertInstanceOf(Migration::class, $migration);

        return $migration;
    }

    public function test_tables_are_created_in_tenant_schema(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table), "{$table} doit être créée dans shared_tenants");
        }
    }

    public function test_company_id_is_not_nullable_on_all_tables(): void
    {
        foreach (self::TABLES as $table) {
            $row = DB::selectOne(
                'SELECT is_nullable
                   FROM information_schema.columns
                  WHERE table_schema = ? AND table_name = ? AND column_name = ?',
                ['shared_tenants', $table, 'company_id']
            );

            $this->assertNotNull($row, "{$table}.company_id absente");
            $this->assertSame('NO', (string) $row->is_nullable, "{$table}.company_id doit être NON nullable");
        }
    }

    public function test_campus_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_campuses')->insert([
                'company_id' => $this->newCompany()->id,
                'code' => 'MAIN',
                'name' => 'Campus invalide',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_student_status_check_rejects_unknown_status(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_students')->insert([
                'company_id' => $this->newCompany()->id,
                'student_number' => 'S-001',
                'display_name' => 'Élève invalide',
                'status' => 'bogus-status',
            ]);
        });
    }

    public function test_guardian_relationship_check_rejects_unknown_code(): void
    {
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function (): void {
            DB::table('edu_guardians')->insert([
                'company_id' => $this->newCompany()->id,
                'relationship_code' => 'uncle-unknown',
            ]);
        });
    }

    public function test_student_guardian_relationship_check_rejects_unknown_code(): void
    {
        /** @var Company $company */
        $company = $this->newCompany();
        $studentId = $this->createStudent($company->id, 'S-001');
        $guardianId = $this->createGuardian($company->id, 'guardien@exemple.test');

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company, $studentId, $guardianId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $company->id,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
                'relationship_code' => 'cousin-unknown',
            ]);
        });
    }

    public function test_campus_code_is_unique_per_tenant(): void
    {
        $company = $this->newCompany();
        $otherCompany = $this->newCompany();

        DB::table('edu_campuses')->insert([
            'company_id' => $company->id,
            'code' => 'MAIN',
            'name' => 'Campus A',
        ]);

        // Même code sur un AUTRE tenant : autorisé.
        DB::table('edu_campuses')->insert([
            'company_id' => $otherCompany->id,
            'code' => 'MAIN',
            'name' => 'Campus B',
        ]);

        // Même code sur le MÊME tenant : rejeté.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company): void {
            DB::table('edu_campuses')->insert([
                'company_id' => $company->id,
                'code' => 'MAIN',
                'name' => 'Campus doublon',
            ]);
        });
    }

    public function test_student_number_is_unique_per_tenant(): void
    {
        $company = $this->newCompany();

        DB::table('edu_students')->insert([
            'company_id' => $company->id,
            'student_number' => 'S-001',
            'display_name' => 'Élève A',
        ]);

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company): void {
            DB::table('edu_students')->insert([
                'company_id' => $company->id,
                'student_number' => 'S-001',
                'display_name' => 'Élève doublon',
            ]);
        });
    }

    public function test_cross_tenant_student_guardian_link_is_rejected_by_database(): void
    {
        $companyA = $this->newCompany();
        $companyB = $this->newCompany();
        $studentAId = $this->createStudent($companyA->id, 'S-A1');

        // Gardien du tenant B lié à un élève du tenant A : la FK composite
        // (guardian_id, company_id) doit rejeter l'insertion.
        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($companyB, $studentAId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $companyB->id,
                'student_id' => $studentAId,
                'guardian_id' => $this->createGuardian($companyB->id, 'gardienB@exemple.test'),
            ]);
        });
    }

    public function test_duplicate_student_guardian_link_is_rejected(): void
    {
        /** @var Company $company */
        $company = $this->newCompany();
        $studentId = $this->createStudent($company->id, 'S-001');
        $guardianId = $this->createGuardian($company->id, 'gardien@exemple.test');

        DB::table('edu_student_guardians')->insert([
            'company_id' => $company->id,
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
        ]);

        $this->expectException(QueryException::class);

        // Transaction imbriquée = savepoint (#4978) : le RAISE PostgreSQL
        // n'empoisonne pas la transaction RefreshDatabase (sinon 25P02
        // en cascade sur le tearDown).
        DB::transaction(function () use ($company, $studentId, $guardianId): void {
            DB::table('edu_student_guardians')->insert([
                'company_id' => $company->id,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
            ]);
        });
    }

    public function test_up_is_idempotent(): void
    {
        foreach (self::MIGRATIONS as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'up'); // rejouer up() sans erreur (gardes F-17)
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table));
        }
    }

    public function test_down_up_cycle_is_clean(): void
    {
        // Ce fichier ne possède pas les migrations voisines (edu_fees,
        // edu_admission_followups) qui portent une FK vers ses tables : les
        // laisser en place fait échouer `DROP TABLE edu_admissions` en 2BP01.
        // Les dépendantes sont supprimées d'abord, résolues dynamiquement.
        $this->dropTablesReferencing(self::TABLES);

        // down() dans l'ordre inverse des dépendances, puis up() complet.
        foreach (array_reverse(self::MIGRATIONS) as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'down');
        }

        foreach (self::TABLES as $table) {
            $this->assertNull($this->tableSchema($table), "{$table} doit être supprimée après down()");
        }

        foreach (self::MIGRATIONS as $basename) {
            $migration = $this->migration($basename);
            $this->callMigrationMethod($migration, 'up');
        }

        foreach (self::TABLES as $table) {
            $this->assertSame('shared_tenants', $this->tableSchema($table));
        }
    }

    private function createStudent(string $companyId, string $studentNumber): int
    {
        return (int) DB::table('edu_students')->insertGetId([
            'company_id' => $companyId,
            'student_number' => $studentNumber,
            'display_name' => 'Élève test',
        ]);
    }

    private function createGuardian(string $companyId, string $contactReference): int
    {
        return (int) DB::table('edu_guardians')->insertGetId([
            'company_id' => $companyId,
            'contact_reference' => $contactReference,
        ]);
    }

    /**
     * Supprime (dans la transaction de test) les tables du schéma tenant qui
     * portent une FK vers l'une des tables données — y compris en cascade de
     * dépendances (table dépendante d'une table dépendante).
     *
     * L'ORDRE de suppression est le point délicat : une table ne peut être
     * supprimée que lorsque plus AUCUNE table encore présente ne la référence.
     * La version précédente supprimait les dépendantes au fur et à mesure de
     * leur découverte, dans l'ordre où `information_schema` les rendait — ce
     * qui droppait parfois une table PARENTE avant sa fille. Cas observé :
     * `edu_fee_types` était supprimée alors que `edu_fee_charges` (FK
     * `edu_fee_charges_type_company_fk`) existait encore → 2BP01
     * « cannot drop table edu_fee_types because other objects depend on it ».
     *
     * On calcule donc d'abord la FERMETURE complète (aucune suppression), puis
     * on supprime par vagues : à chaque tour, seules les tables que plus rien
     * ne référence *dans l'ensemble restant* sont supprimées. C'est un tri
     * topologique inverse, et il est stable quel que soit l'ordre de retour de
     * `information_schema`.
     *
     * @param  list<string>  $tables
     */
    private function dropTablesReferencing(array $tables): void
    {
        // Le graphe de FK est lu en UNE requête : `information_schema` n'est pas
        // indexé, chaque appel coûte ~1,2 s sur ce schéma, et l'implémentation
        // « une requête par table et par tour » en émettait des centaines → à
        // elle seule elle expliquait la durée du test. La fermeture puis le tri
        // topologique se font en mémoire, sans plus toucher la base.
        $edges = $this->foreignKeyEdges();

        // 1. Fermeture transitive : toutes les tables à supprimer.
        $closure = array_fill_keys($tables, true);

        do {
            $added = false;
            foreach (array_keys($closure) as $table) {
                foreach ($edges[$table] ?? [] as $dependent) {
                    if (! isset($closure[$dependent])) {
                        $closure[$dependent] = true;
                        $added = true;
                    }
                }
            }
        } while ($added);

        // 2. Suppression par vagues, dépendantes d'abord : une table T n'est
        //    supprimée que si AUCUNE table encore présente ne la référence —
        //    exactement la condition que PostgreSQL vérifie pour refuser un
        //    DROP en 2BP01.
        $remaining = array_keys($closure);

        while ($remaining !== []) {
            $leaves = [];

            foreach ($remaining as $table) {
                $stillReferenced = false;
                foreach ($edges[$table] ?? [] as $dependent) {
                    if (in_array($dependent, $remaining, true)) {
                        $stillReferenced = true;
                        break;
                    }
                }

                if (! $stillReferenced) {
                    $leaves[] = $table;
                }
            }

            // Aucune feuille = cycle de FK : on retombe sur l'ensemble entier
            // pour ne pas boucler indéfiniment (PostgreSQL refusera, mais le
            // message sera explicite plutôt qu'un timeout).
            if ($leaves === []) {
                $leaves = $remaining;
            }

            foreach ($leaves as $leaf) {
                Schema::dropIfExists($leaf);
            }

            $remaining = array_values(array_diff($remaining, $leaves));
        }
    }

    /**
     * Graphe des FK du schéma tenant : `table référencée => [tables dépendantes]`.
     *
     * Une seule requête pour tout le schéma (cf. coût d'`information_schema`).
     *
     * @return array<string, list<string>>
     */
    private function foreignKeyEdges(): array
    {
        $rows = DB::select(
            'SELECT DISTINCT ccu.table_name AS referenced, tc.table_name AS dependent
               FROM information_schema.table_constraints tc
               JOIN information_schema.key_column_usage kcu
                 ON kcu.constraint_name = tc.constraint_name
                AND kcu.table_schema = tc.table_schema
               JOIN information_schema.constraint_column_usage ccu
                 ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
              WHERE tc.constraint_type = ?
                AND tc.table_schema = ?',
            ['FOREIGN KEY', 'shared_tenants']
        );

        $edges = [];
        foreach ($rows as $row) {
            $edges[(string) $row->referenced][] = (string) $row->dependent;
        }

        return $edges;
    }

    private function callMigrationMethod(Migration $migration, string $method): void
    {
        // Les migrations du repo sont des classes anonymes `return new class extends Migration` :
        // invocation réflexive pour le cycle up/down de test (les méthodes ne sont pas
        // déclarées sur le type de base).
        $reflection = new \ReflectionMethod($migration, $method);
        $reflection->invoke($migration);
    }
}
