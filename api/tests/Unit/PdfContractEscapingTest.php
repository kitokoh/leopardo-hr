<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Issue #5588 (durcissement) : le contrat PDF interpole des valeurs saisies
 * par un manager (`job_title`, `salary_frequency`, `currency`) dans des
 * chaînes rendues via `{!! !!}` (nécessaire pour les `<strong>`). Ces
 * valeurs doivent être échappées (`e()`) — un manager malveillant ne doit
 * pas pouvoir injecter du HTML dans un document contractuel.
 */
class PdfContractEscapingTest extends TestCase
{
    public function test_job_title_html_is_escaped_in_contract_pdf(): void
    {
        $html = $this->renderContractWithJobTitle('<script>alert(1)</script>');

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_job_title_with_plain_text_is_rendered_bold(): void
    {
        $html = $this->renderContractWithJobTitle('Développeur');

        $this->assertStringContainsString('<strong>Développeur</strong>', $html);
    }

    /**
     * @return string HTML rendu de la vue pdf.contract
     */
    private function renderContractWithJobTitle(string $jobTitle): string
    {
        $company = new \stdClass();
        $company->name = 'Acme Corp';
        $company->address = '1 rue de la Paix';

        $employee = new \stdClass();
        $employee->first_name = 'Jean';
        $employee->last_name = 'Dupont';

        $contract = new \stdClass();
        $contract->job_title = $jobTitle;
        $contract->contract_type = 'CDI';
        $contract->start_date = '2026-01-01';
        $contract->end_date = null;
        $contract->base_salary = 50000.0;
        $contract->currency = 'DZD';
        $contract->salary_frequency = 'mensuel';
        $contract->work_hours_per_week = '40';
        $contract->clauses = [];

        return view('pdf.contract', [
            'contract' => $contract,
            'employee' => $employee,
            'company' => $company,
        ])->render();
    }
}
