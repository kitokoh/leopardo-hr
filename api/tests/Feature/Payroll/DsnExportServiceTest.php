<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Exports\DsnExportService;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * DSN mensuelle FR — structure NEODeS reelle (#7936).
 *
 * Remplace les assertions de #5438, qui validaient les codes de blocs
 * *faux* (S21.G00.01/.02/.06/.11 = emetteur / etablissement / cotisation
 * agregee). Une DSN correcte est une hierarchie :
 *   .30 Individu -> .40 Contrat -> .50 Versement / .78 Base / .81 Cotisation.
 *
 * Les NIR de test sont mathematiquement valides (cle mod 97) : un NIR
 * quelconque serait rejete par le controle bloquant, ce qui est le but.
 */
class DsnExportServiceTest extends TestCase
{
    use CreatesMvpSchema;

    /** NIR valide : base 1800512345001 -> cle 82. */
    private const VALID_NIR = '180051234500182';

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_build_produces_neodes_hierarchy(): void
    {
        $run = $this->makeRunWithSlip();

        $xml = (new DsnExportService)->build($run);

        $dom = new \DOMDocument;
        $this->assertTrue($dom->loadXML($xml), 'Le XML DSN doit etre bien forme.');
        $root = $dom->documentElement;
        if ($root === null) {
            $this->fail('DSN XML : element racine manquant.');
        }
        $this->assertSame('urn:fr:dsi:dsn:v1', $root->namespaceURI);

        $blocs = [];
        foreach ($dom->getElementsByTagNameNS('urn:fr:dsi:dsn:v1', 'Bloc') as $bloc) {
            $blocs[] = $bloc->getAttribute('Id');
        }

        // Hierarchie NEODeS : l'individu porte contrat, versement, base, cotisation.
        $this->assertContains('S21.G00.30', $blocs, 'Bloc Individu present');
        $this->assertContains('S21.G00.40', $blocs, 'Bloc Contrat present');
        $this->assertContains('S21.G00.50', $blocs, 'Bloc Versement individu present');
        $this->assertContains('S21.G00.78', $blocs, 'Bloc Base assujettie present');
        $this->assertContains('S21.G00.81', $blocs, 'Bloc Cotisation individuelle present');

        // Les anciens codes errones ne doivent plus apparaitre.
        $this->assertNotContains('S21.G00.01', $blocs);
        $this->assertNotContains('S21.G00.02', $blocs);
        $this->assertNotContains('S21.G00.06', $blocs);

        // L'individu est enfant du bloc .30, pas de la racine (hierarchie).
        $individu = null;
        foreach ($dom->getElementsByTagNameNS('urn:fr:dsi:dsn:v1', 'Bloc') as $bloc) {
            if ($bloc->getAttribute('Id') === 'S21.G00.30') {
                $individu = $bloc;
                break;
            }
        }
        $this->assertNotNull($individu, 'Bloc S21.G00.30 introuvable.');
        $children = [];
        foreach ($individu->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->nodeName === 'Bloc') {
                $children[] = $child->getAttribute('Id');
            }
        }
        $this->assertContains('S21.G00.40', $children, 'Contrat rattache a l individu');
        $this->assertContains('S21.G00.50', $children, 'Versement rattache a l individu');
    }

    public function test_build_declares_valued_rubriques(): void
    {
        $run = $this->makeRunWithSlip();

        $xml = (new DsnExportService)->build($run);

        // Identite en clair (NIR sans cle de controle separee, etat civil).
        $this->assertStringContainsString(self::VALID_NIR, $xml);
        $this->assertStringContainsString('Jeanne', $xml);
        $this->assertStringContainsString('Martin', $xml);

        // Date NEODeS JJMMAAAA (pas ISO) pour la date de naissance.
        $this->assertStringContainsString('15031985', $xml);

        // Montants du bulletin.
        $this->assertStringContainsString('3000.00', $xml);
        $this->assertStringContainsString('2202.81', $xml);
        $this->assertStringContainsString('1026.60', $xml);

        // PAS personnalise : taux 12.5 % + type 01 (DGFiP) + identifiant.
        $this->assertStringContainsString('S21.G00.50.006', $xml);
        $this->assertStringContainsString('12.50', $xml);
        $this->assertStringContainsString('S21.G00.50.007', $xml);
        $this->assertStringContainsString('GIP-750000000', $xml);
    }

    public function test_build_rejects_slip_without_nir(): void
    {
        $run = $this->makeRunWithSlip(nir: null);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/NIR absent/');

        (new DsnExportService)->build($run);
    }

    public function test_build_rejects_invalid_nir_key(): void
    {
        // Cle de controle volontairement fausse (00 au lieu de 82).
        $run = $this->makeRunWithSlip(nir: '180051234500100');

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/NIR invalide/');

        (new DsnExportService)->build($run);
    }

    public function test_build_rejects_net_above_gross(): void
    {
        $run = $this->makeRunWithSlip(netSalary: 3500.00);

        $this->expectException(DsnConformityException::class);
        $this->expectExceptionMessageMatches('/net verse .* superieur au brut/');

        (new DsnExportService)->build($run);
    }

    private function makeRunWithSlip(
        ?string $nir = self::VALID_NIR,
        float $netSalary = 2202.81,
    ): PayrollRun {
        /** @var Company $company */
        $company = Company::factory()->create(['timezone' => 'Europe/Paris']);

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'first_name' => 'Jeanne',
            'last_name' => 'Martin',
            'matricule' => 'EMP-FR-001',
            'status' => 'active',
            'date_of_birth' => '1985-03-15',
            'gender' => 'F',
            'contract_type' => 'CDI',
            'contract_start' => '2020-01-06',
            'national_id' => $nir,
            'pas_rate' => 12.5,
            'pas_identifier' => 'GIP-750000000',
        ]);

        /** @var PayrollRun $run */
        $run = PayrollRun::query()->create([
            'company_id' => $company->id,
            'country_code' => 'FR',
            'status' => 'validated',
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'total_gross' => 3000.00,
            'total_net' => 2202.81,
            'employee_count' => 1,
        ]);

        PaySlip::query()->create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'gross_salary' => 3000.00,
            'total_deductions' => 644.41,
            'net_salary' => $netSalary,
            'employer_contributions' => 1026.60,
            'status' => 'validated',
        ]);

        return $run->fresh() ?? $run;
    }

    /**
     * La contrainte de bornes du taux PAS doit exister EN BASE, et pas
     * seulement dans le fichier de migration.
     *
     * Regression reelle corrigee ici : la migration posait d'abord les
     * colonnes puis la contrainte, mais sa garde d'idempotence
     * (`if (Schema::hasColumn('employees', 'pas_rate')) return;`) sortait
     * AVANT l'ALTER. Une migration interrompue entre les deux etapes — ou
     * rejouee apres un `up()` partiel — laissait donc la table sans CHECK,
     * en silence : un taux a 250 % redevenait declarable a l'administration.
     * Le test interroge `pg_constraint` (source de verite serveur) et non le
     * code PHP, puis verifie que la borne est reellement appliquee.
     */
    public function test_pas_rate_range_constraint_exists_and_is_enforced(): void
    {
        $constraint = \Illuminate\Support\Facades\DB::selectOne(
            'SELECT pg_get_constraintdef(c.oid) AS def FROM pg_constraint c '
            .'JOIN pg_class t ON t.oid = c.conrelid '
            ."WHERE c.conname = 'employees_pas_rate_range' AND t.relname = 'employees'"
        );

        $this->assertNotNull(
            $constraint,
            'La contrainte employees_pas_rate_range doit exister en base (un taux hors [0;100] serait declarable).'
        );
        $this->assertStringContainsString('pas_rate', (string) $constraint->def);

        // La borne est appliquee par le serveur, pas par une validation applicative.
        // L'ecriture est isolee dans un savepoint : sous PostgreSQL une violation
        // de contrainte avorte la transaction courante (25P02), ce qui ferait
        // echouer le `SET search_path` du setUp suivant et empoisonnerait tous
        // les tests restants du worker.
        $rejected = false;
        try {
            \Illuminate\Support\Facades\DB::transaction(function (): void {
                \Illuminate\Support\Facades\DB::table('employees')->insert([
                    'company_id' => 1,
                    'first_name' => 'Hors',
                    'last_name' => 'Bornes',
                    'pas_rate' => 250,
                ]);
            });
        } catch (\Illuminate\Database\QueryException) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Un taux PAS a 250 % doit etre rejete par la base.');
    }
}
