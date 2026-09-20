<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Onboarding\Domain\Services\SetupInterviewPlanner;
use PHPUnit\Framework\TestCase;

/**
 * #7493 — moteur de mapping réponses d'entretien → plan d'activation.
 * Déterministe et sans effet de bord : testable en unité pure.
 */
class SetupInterviewPlannerTest extends TestCase
{
    private SetupInterviewPlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new SetupInterviewPlanner;
    }

    public function test_restaurateur_avec_employes_obtient_restaurant_et_outils_equipe(): void
    {
        $plan = $this->planner->plan([
            'company_type' => 'team',
            'team_size' => '11-50',
            'sector' => 'restaurant',
            'premises' => 'single',
            'scheduled_hours' => 'yes',
        ]);

        $this->assertSame(['restaurant'], $plan['solutions']);
        $this->assertContains('employees', $plan['tools']);
        $this->assertContains('attendance', $plan['tools']);
    }

    public function test_solo_ne_recoit_jamais_d_outil_d_equipe_hors_plancher(): void
    {
        $plan = $this->planner->plan([
            'company_type' => 'solo',
            'sector' => 'services',
            'priorities' => ['showcase', 'attendance', 'payroll'],
        ]);

        $this->assertSame([], $plan['solutions']);
        $this->assertNotContains('employees', $plan['tools']);
        $this->assertContains('showcase', $plan['tools']);
        // Plancher solo (#7423) : attendance/payroll restent accessibles.
        $this->assertContains('attendance', $plan['tools']);
        $this->assertContains('payroll', $plan['tools']);
    }

    public function test_les_priorites_activent_les_outils_horizontaux_correspondants(): void
    {
        $plan = $this->planner->plan([
            'company_type' => 'team',
            'sector' => 'commerce',
            'priorities' => ['accounting', 'crm', 'cameras'],
        ]);

        $this->assertSame([], $plan['solutions']);
        foreach (['accounting', 'crm', 'cameras', 'employees', 'attendance'] as $tool) {
            $this->assertContains($tool, $plan['tools']);
        }
    }

    public function test_reponse_inconnue_ne_produit_jamais_d_activation(): void
    {
        $plan = $this->planner->plan([
            'sector' => 'inconnu',
            'priorities' => ['dynamite'],
            'hack' => 'restaurant',
        ]);

        $this->assertSame(['solutions' => [], 'tools' => []], $plan);
    }

    public function test_sanitize_rejette_les_cles_et_valeurs_hors_allowlist(): void
    {
        $result = $this->planner->sanitize([
            'sector' => 'restaurant',
            'company_type' => 'pirate',
            'inconnue' => 'x',
            'priorities' => ['payroll', 'dynamite'],
            'premises' => null,
        ]);

        $this->assertSame('restaurant', $result['answers']['sector']);
        $this->assertSame(['payroll'], $result['answers']['priorities']);
        $this->assertNull($result['answers']['premises']);
        $this->assertArrayNotHasKey('company_type', $result['answers']);
        $this->assertContains('company_type', $result['rejected']);
        $this->assertContains('inconnue', $result['rejected']);
    }

    /**
     * #7853 — `company_name` est une question en TEXTE LIBRE (2..120, trim),
     * zappable (`null`), et ne produit jamais d'activation.
     */
    public function test_sanitize_accepte_company_name_en_texte_libre_borne(): void
    {
        $result = $this->planner->sanitize([
            'company_name' => '  Boulangerie El Amel  ',
            'sector' => 'commerce',
        ]);

        $this->assertSame('Boulangerie El Amel', $result['answers']['company_name']);
        $this->assertSame([], $result['rejected']);

        // Question sautée : `null` accepté.
        $skipped = $this->planner->sanitize(['company_name' => null]);
        $this->assertNull($skipped['answers']['company_name']);
    }

    public function test_sanitize_rejette_company_name_hors_bornes_ou_non_chaine(): void
    {
        foreach ([['x'], 'A', str_repeat('a', 121), 42, '   '] as $invalid) {
            $result = $this->planner->sanitize(['company_name' => $invalid]);
            $this->assertContains('company_name', $result['rejected']);
            $this->assertArrayNotHasKey('company_name', $result['answers']);
        }
    }

    public function test_company_name_ne_produit_jamais_d_activation(): void
    {
        $plan = $this->planner->plan(['company_name' => 'restaurant']);

        $this->assertSame(['solutions' => [], 'tools' => []], $plan);
    }
}
