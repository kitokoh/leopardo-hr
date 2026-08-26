<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Issue #5588 (audit sécurité 2026-08-26) — `job_title` (saisi par un
 * manager) était injecté brut dans le PDF de contrat via un placeholder
 * `{!! !!}` : une valeur `&lt;script&gt;` ou `&lt;img onerror&gt;` pouvait
 * altérer le document contractuel. DOMPDF n'exécute pas JS et le remote
 * loading est désactivé (impact limité) mais le document doit être
 * inaltérable : le titre de poste est désormais échappé (`e()`), les tags
 * <strong> de mise en forme restant portés par le template.
 */
class ContractPdfJobTitleEscapingTest extends TestCase
{
    public function test_job_title_is_html_escaped_in_the_contract_pdf(): void
    {
        $contract = (object) [
            'contract_type' => 'CDI',
            'start_date' => '2026-09-01',
            'end_date' => null,
            'job_title' => '<script>alert(1)</script>',
            'base_salary' => 50000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
            'clauses' => [],
        ];

        $company = (object) ['name' => 'Company A', 'address' => 'Alger'];
        $employee = (object) ['first_name' => 'Karim', 'last_name' => 'Ben'];

        $html = view('pdf.contract', [
            'contract' => $contract,
            'company' => $company,
            'employee' => $employee,
        ])->render();

        // Le titre de poste est échappé : les chevrons deviennent des
        // entités, le markup injecté n'apparaît pas tel quel.
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_legitimate_job_title_is_rendered_bold_but_escaped(): void
    {
        $contract = (object) [
            'contract_type' => 'CDI',
            'start_date' => '2026-09-01',
            'end_date' => null,
            'job_title' => 'Développeur <PHP>',
            'base_salary' => 50000,
            'currency' => 'DZD',
            'salary_frequency' => 'monthly',
            'work_hours_per_week' => 40,
            'clauses' => [],
        ];

        $company = (object) ['name' => 'Company A', 'address' => 'Alger'];
        $employee = (object) ['first_name' => 'Karim', 'last_name' => 'Ben'];

        $html = view('pdf.contract', [
            'contract' => $contract,
            'company' => $company,
            'employee' => $employee,
        ])->render();

        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('Développeur &lt;PHP&gt;', $html);
        $this->assertStringNotContainsString('Développeur <PHP>', $html);
    }
}
