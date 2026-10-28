<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Core\Feature\Domain\ModuleConsolidationDiff;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * BOS-012 (#8199) — `modules:consolidate --apply`, palier 2 : l'écriture
 * unifiée.
 *
 * Critère d'acceptation 2 de l'issue : « Après exécution : 0 diff fonctionnel
 * (`hasFeature` avant/après identique sur tous les tenants staging) ». Ces
 * tests ne se contentent pas de relire la cible : ils **rejouent le
 * diagnostic** après l'écriture. Déclarer « convergé » parce que la valeur
 * écrite ressemble à la valeur attendue serait une pétition de principe ;
 * converger, c'est que le prochain rapport n'ait plus rien à dire.
 *
 * Critère 3 : « Rollback démontré : dual-read conservé 1 release ». L'écriture
 * ne touche QUE la vue (`metadata.modules`) — `features` n'est jamais réécrit.
 * C'est ce qui rend le retour arrière trivial : rien à défaire côté source.
 */
class ModulesConsolidateApplyTest extends TestCase
{
    /**
     * @param  array<string, bool>  $features
     * @param  array<string, mixed>  $modules
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

    /**
     * Les verdicts `hasFeature` de TOUS les flags du registre — c'est la
     * mesure du critère d'acceptation 2, pas une clé choisie.
     *
     * @return array<string, bool>
     */
    private function featureVerdicts(Company $company): array
    {
        $fresh = Company::query()->whereKey($company->id)->first();

        $this->assertNotNull($fresh);

        $verdicts = [];

        foreach (Company::KNOWN_MODULES as $module) {
            $verdicts[$module] = $fresh->hasFeature($module);
        }

        return $verdicts;
    }

    public function test_apply_converges_a_divergent_company(): void
    {
        // Le motif historique : le client a activé `crm`, la console plateforme
        // l'a coupé. La vue doit suivre la source.
        $company = $this->companyWith(features: ['crm' => false], modules: ['crm' => true]);

        $exit = Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $this->assertSame(0, $exit, 'Un --apply qui converge doit sortir en 0.');

        $fresh = Company::query()->whereKey($company->id)->first();
        $this->assertNotNull($fresh);
        $this->assertFalse(
            $fresh->metadata['modules']['crm'] ?? null,
            'La vue dérivée doit suivre le flag plateforme.'
        );

        // Convergence VÉRIFIÉE : le diagnostic rejoué ne trouve plus rien.
        $this->assertSame(
            0,
            Artisan::call('modules:consolidate', ['--company' => $company->id]),
            'Après --apply, le diagnostic doit être vide — sinon la commande ne converge pas.'
        );
    }

    public function test_apply_never_rewrites_features(): void
    {
        // Garantie structurelle du critère d'acceptation 2 : `hasFeature()` ne
        // lit que `features`. Si l'écriture ne touche pas `features`, la parité
        // n'est pas constatée, elle est impossible à violer.
        $company = $this->companyWith(
            features: ['crm' => false, 'rh' => true],
            modules: ['crm' => true, 'training' => true],
        );

        $beforeVerdicts = $this->featureVerdicts($company);
        $beforeFeatures = $this->snapshot($company)['features'];

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $this->assertSame(
            $beforeFeatures,
            $this->snapshot($company)['features'],
            '`features` est la SOURCE : --apply ne doit jamais la réécrire.'
        );

        $this->assertSame(
            $beforeVerdicts,
            $this->featureVerdicts($company),
            'Critère d\'acceptation 2 : hasFeature identique avant/après.'
        );
    }

    public function test_apply_is_idempotent(): void
    {
        // Une écriture qui ne converge pas est indistinguable d'un échec. Le
        // second passage ne doit plus rien modifier du tout.
        $company = $this->companyWith(features: ['crm' => false], modules: ['crm' => true]);

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);
        $afterFirst = $this->snapshot($company);

        $exit = Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $this->assertSame(0, $exit);
        $this->assertSame($afterFirst, $this->snapshot($company), 'Le second --apply ne doit rien réécrire.');
    }

    public function test_a_clean_company_is_a_no_op(): void
    {
        $company = $this->companyWith(features: ['crm' => true], modules: ['crm' => true]);

        $before = $this->snapshot($company);

        $exit = Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $this->assertSame(0, $exit);
        $this->assertSame($before, $this->snapshot($company));
    }

    public function test_without_apply_nothing_is_written(): void
    {
        // Le diagnostic reste le comportement par défaut : l'écriture est
        // opt-in, jamais implicite.
        $company = $this->companyWith(features: ['crm' => false], modules: ['crm' => true]);

        $before = $this->snapshot($company);

        $exit = Artisan::call('modules:consolidate', ['--dry-run' => true, '--company' => $company->id]);

        $this->assertSame(1, $exit, 'Sans --apply, un écart reste un écart : code 1.');
        $this->assertSame($before, $this->snapshot($company), 'Sans --apply, AUCUNE écriture.');
    }

    public function test_apply_preserves_non_mirror_client_keys(): void
    {
        // `employees` / `attendance` ne vivent QUE dans metadata.modules. Une
        // consolidation qui les écraserait éteindrait des outils réels.
        $company = $this->companyWith(
            features: ['crm' => false],
            modules: ['employees' => true, 'attendance' => false, 'payroll' => true, 'crm' => true],
        );

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $fresh = Company::query()->whereKey($company->id)->first();
        $this->assertNotNull($fresh);

        $modules = $fresh->metadata['modules'];
        $this->assertTrue($modules['employees']);
        $this->assertFalse($modules['attendance']);
        $this->assertTrue($modules['payroll']);
        $this->assertFalse($modules['crm']);
    }

    public function test_apply_preserves_other_metadata_keys(): void
    {
        // `metadata` est un fourre-tout : la consolidation ne touche QUE
        // `metadata.modules`. Écraser `company_type` ou `provisioned_by`
        // casserait le comportement du client (profil solo, sandbox…).
        $company = Company::factory()->create();
        $company->forceFill([
            'features' => ['crm' => false],
            'metadata' => [
                'company_type' => 'solo',
                'provisioned_by' => 'guided_trial',
                'is_sandbox' => true,
                'modules' => ['crm' => true],
            ],
        ])->save();

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $fresh = Company::query()->whereKey($company->id)->first();
        $this->assertNotNull($fresh);

        $this->assertSame('solo', $fresh->metadata['company_type']);
        $this->assertSame('guided_trial', $fresh->metadata['provisioned_by']);
        $this->assertTrue($fresh->metadata['is_sandbox']);
    }

    public function test_apply_reports_json_with_applied_count(): void
    {
        // Le rapport JSON est archivé dans la PR (critère d'acceptation 1) : il
        // doit distinguer un run de diagnostic d'un run d'écriture.
        $company = $this->companyWith(features: ['crm' => false], modules: ['crm' => true]);

        Artisan::call('modules:consolidate', ['--apply' => true, '--json' => true, '--company' => $company->id]);
        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report);
        $this->assertFalse($report['dry_run'], 'Un --apply n\'est pas un dry-run.');
        $this->assertSame(1, $report['companies_applied']);
        $this->assertSame([], $report['companies_unconverged']);
        $this->assertSame('crm', $report['drifts'][0]['client_key']);
    }

    public function test_a_key_declared_nowhere_is_never_materialised(): void
    {
        // ⚠️ Garde-fou produit. `Company::moduleSelection()` documente qu'une
        // absence de sélection = « aucun verrouillage rétroactif ». Poser
        // `crm => false` sur un tenant qui n'a jamais fait de choix
        // transformerait une absence en interdiction, et le client lit
        // `metadata.modules` AVANT `features` : le module disparaîtrait de
        // l'interface pour tous les tenants historiques.
        //
        // Ici `crm` n'est déclaré NI côté client NI côté plateforme : il doit
        // rester absent de la vue.
        $company = $this->companyWith(features: ['rh' => true], modules: ['employees' => true]);

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $fresh = Company::query()->whereKey($company->id)->first();
        $this->assertNotNull($fresh);

        $this->assertArrayNotHasKey(
            'crm',
            $fresh->metadata['modules'],
            'Une clé que personne n\'a déclarée ne doit jamais être matérialisée.'
        );
        $this->assertArrayNotHasKey('accounting', $fresh->metadata['modules']);
        $this->assertArrayNotHasKey('training', $fresh->metadata['modules']);

        // Et le module reste résolu par sa voie normale (capacité / plan),
        // exactement comme avant la consolidation.
        $this->assertSame(0, Artisan::call('modules:consolidate', ['--company' => $company->id]));
    }

    public function test_a_platform_flag_declared_alone_is_projected_into_the_view(): void
    {
        // Symétrique du test précédent : dès que la SOURCE déclare la clé, la
        // vue doit la porter — sinon l'UI serait muette sur un module actif
        // (c'est le cas `missing_client_key` du diagnostic).
        $company = $this->companyWith(features: ['crm' => true], modules: ['employees' => true]);

        Artisan::call('modules:consolidate', ['--apply' => true, '--company' => $company->id]);

        $fresh = Company::query()->whereKey($company->id)->first();
        $this->assertNotNull($fresh);

        $this->assertTrue($fresh->metadata['modules']['crm'] ?? null);
        $this->assertTrue($fresh->metadata['modules']['employees'] ?? null);
    }

    public function test_the_diff_kinds_are_reported_before_writing(): void
    {
        // Le rapport doit rester exploitable même en --apply : c'est lui qui
        // documente ce qui a été corrigé, et il est archivé dans la PR.
        $company = $this->companyWith(features: ['crm' => false], modules: ['crm' => true]);

        Artisan::call('modules:consolidate', ['--apply' => true, '--json' => true, '--company' => $company->id]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(ModuleConsolidationDiff::KIND_DIVERGENCE, $report['drifts'][0]['kind']);
        $this->assertTrue($report['drifts'][0]['client_value']);
        $this->assertFalse($report['drifts'][0]['flag_value']);
        $this->assertFalse($report['drifts'][0]['target_value']);
    }
}
