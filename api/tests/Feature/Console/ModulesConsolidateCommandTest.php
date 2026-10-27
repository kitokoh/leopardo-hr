<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * BOS-012 (#8199) — `modules:consolidate`, palier 1 (diagnostic).
 *
 * Le critère d'acceptation 1 de l'issue est « `--dry-run` : rapport de diff
 * produit et archivé dans la PR ». Ces tests prouvent les deux moitiés :
 * le rapport **voit** réellement les désynchronisations historiques, et il
 * **n'écrit rien** (le diff est un diagnostic, pas une migration implicite).
 */
class ModulesConsolidateCommandTest extends TestCase
{
    /**
     * @param  array<string, bool>  $features
     * @param  array<string, bool>  $modules
     */
    private function companyWith(array $features, array $modules): Company
    {
        $company = Company::factory()->create();
        $metadata = $company->metadata ?? [];
        $metadata['modules'] = $modules;

        $company->forceFill([
            'features' => $features,
            'metadata' => $metadata,
        ])->save();

        return $company->fresh() ?? $company;
    }

    public function test_reports_a_divergence_between_client_selection_and_platform_flag(): void
    {
        // Désync historique : le client a activé `crm` (metadata.modules) mais
        // le flag plateforme a été coupé côté console. C'est exactement le
        // motif des incidents #7235 / #7432.
        $company = $this->companyWith(
            features: ['crm' => false],
            modules: ['crm' => true],
        );

        $exit = Artisan::call('modules:consolidate', ['--dry-run' => true, '--company' => $company->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exit, 'Un écart doit sortir en code 1 (utilisable en CI).');
        $this->assertStringContainsString($company->id, $output);
        $this->assertStringContainsString('divergence', $output);
        $this->assertStringContainsString('crm', $output);
    }

    public function test_default_mode_is_read_only_without_the_flag(): void
    {
        // `--dry-run` est le comportement par défaut, pas un mode à activer :
        // la commande ne contient aucun chemin d'écriture. L'absence du flag ne
        // doit donc jamais transformer le diagnostic en migration implicite.
        $company = $this->companyWith(
            features: ['crm' => false],
            modules: ['crm' => true],
        );

        $before = $this->snapshot($company);

        $exit = Artisan::call('modules:consolidate', ['--company' => $company->id]);

        $this->assertSame(1, $exit);
        $this->assertSame($before, $this->snapshot($company));
    }

    public function test_clean_company_reports_no_drift_and_exits_zero(): void
    {
        $company = $this->companyWith(
            features: ['crm' => true],
            modules: ['crm' => true],
        );

        $exit = Artisan::call('modules:consolidate', ['--company' => $company->id]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('aucun écart', $output);
    }

    public function test_a_missing_platform_flag_is_not_reported_as_a_drift(): void
    {
        // `employees` n'a AUCUN flag plateforme (pas de miroir) : il ne vit que
        // dans metadata.modules. Le compter comme écart inventerait des
        // divergences qui n'existent pas et ferait écrire de fausses valeurs.
        $company = $this->companyWith(
            features: [],
            modules: ['employees' => true],
        );

        $exit = Artisan::call('modules:consolidate', ['--company' => $company->id]);

        $this->assertSame(0, $exit, 'Un outil sans flag plateforme ne peut pas dériver.');
    }

    public function test_missing_client_key_is_reported_when_the_flag_is_declared(): void
    {
        // Le flag plateforme existe mais le client n'a aucune clé (sélection
        // jamais faite) : l'UI serait muette alors que le module est actif.
        $company = $this->companyWith(
            features: ['crm' => true],
            modules: [],
        );

        $exit = Artisan::call('modules:consolidate', ['--company' => $company->id]);
        $output = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('missing_client_key', $output);
    }

    public function test_dry_run_never_writes_to_the_database(): void
    {
        $company = $this->companyWith(
            features: ['crm' => false],
            modules: ['crm' => true],
        );

        $before = $this->snapshot($company);

        Artisan::call('modules:consolidate', ['--dry-run' => true, '--company' => $company->id]);

        $this->assertSame(
            $before,
            $this->snapshot($company),
            'Le diagnostic ne doit jamais modifier metadata.modules ni features.'
        );
    }

    public function test_json_report_is_archiveable(): void
    {
        $company = $this->companyWith(
            features: ['crm' => false],
            modules: ['crm' => true],
        );

        Artisan::call('modules:consolidate', ['--json' => true, '--company' => $company->id]);
        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report);
        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['drift_count']);
        $this->assertSame($company->id, $report['drifts'][0]['company_id']);
        $this->assertSame('crm', $report['drifts'][0]['client_key']);
        $this->assertFalse($report['drifts'][0]['target_value'], 'Le flag plateforme gagne.');
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Company $company): array
    {
        $fresh = Company::query()->whereKey($company->id)->first();

        return [
            'features' => $fresh?->features,
            'metadata' => $fresh?->metadata,
        ];
    }
}
