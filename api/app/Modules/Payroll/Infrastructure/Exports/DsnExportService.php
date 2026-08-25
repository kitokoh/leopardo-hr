<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use DOMDocument;
use Illuminate\Support\Carbon;

/**
 * Export DSN (Déclaration Sociale Nominative) — structure minimale S21.G00.
 *
 * #5438 — Pack FR lot 1 (gap E1 FR_COMPLIANCE.md). Produit un fichier XML
 * bien formé conforme à la norme DSN (namespace `urn:fr:dsi:dsn:v1`) pour un
 * run de paie validé, avec les blocs :
 *   - S21.G00.01 : Individu (nom, prénom, identifiant — NIR si disponible)
 *   - S21.G00.02 : Contrat (dates de période)
 *   - S21.G00.06 : Rémunération (brut, assiette, net)
 *   - S21.G00.11 : Cotisation individuelle (salariale + patronale agrégées)
 *
 * ⚠️ Périmètre pilot : structure nominale et montants testés ; la validation
 * URSSAF complète (contrôle technique du fichier, S21.G00.19 etc.) reste
 * hors périmètre (voir docs/payroll/FR_COMPLIANCE.md, gap E1).
 */
class DsnExportService
{
    /**
     * @return string Contenu XML de la DSN pour le run donné.
     */
    public function build(PayrollRun $run): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $declaration = $dom->createElementNS('urn:fr:dsi:dsn:v1', 'Declaration');
        $declaration->setAttribute('VersionFichier', '01');
        $declaration->setAttribute('TypeFichier', '01');
        $dom->appendChild($declaration);

        // En-tête — émetteur = entreprise (schema_name du tenant comme
        // identifiant d'établissement, pilot).
        $entete = $dom->createElement('EnTete');
        $entete->appendChild($this->leaf($dom, 'S21.G00.00', '001'));
        $entete->appendChild($this->leaf($dom, 'S21.G00.00.001', $run->company_id !== null ? (string) $run->company_id : ''));
        $entete->appendChild($this->leaf($dom, 'S21.G00.00.002', $run->country_code));
        $entete->appendChild($this->leaf($dom, 'S21.G00.00.004', $run->period_start->format('Y-m-d')));
        $declaration->appendChild($entete);

        $slips = $run->paySlips ?? $run->paySlips()->get();

        foreach ($slips as $slip) {
            $this->appendIndividu($dom, $declaration, $slip);
        }

        return $dom->saveXML() ?: '';
    }

    private function appendIndividu(DOMDocument $dom, \DOMElement $declaration, PaySlip $slip): void
    {
        $employee = $slip->employee;
        $employeeId = (string) ($employee->matricule ?? $slip->employee_id ?? '');

        // S21.G00.01 — Individu.
        $individu = $dom->createElement('Bloc');
        $individu->setAttribute('Id', 'S21.G00.01');
        $individu->appendChild($this->leaf($dom, 'S21.G00.01.001', $employeeId));
        $individu->appendChild($this->leaf($dom, 'S21.G00.01.002', (string) ($employee->first_name ?? '')));
        $individu->appendChild($this->leaf($dom, 'S21.G00.01.003', (string) ($employee->last_name ?? '')));
        $individu->appendChild($this->leaf($dom, 'S21.G00.01.006', (string) ($employee?->getAttribute('social_security_number') ?? '')));
        $declaration->appendChild($individu);

        // S21.G00.02 — Contrat (période d'emploi = période du bulletin).
        $contrat = $dom->createElement('Bloc');
        $contrat->setAttribute('Id', 'S21.G00.02');
        $contrat->appendChild($this->leaf($dom, 'S21.G00.02.001', $slip->period_start->format('Y-m-d')));
        $contrat->appendChild($this->leaf($dom, 'S21.G00.02.002', $slip->period_end->format('Y-m-d')));
        $declaration->appendChild($contrat);

        // S21.G00.06 — Rémunération.
        $remun = $dom->createElement('Bloc');
        $remun->setAttribute('Id', 'S21.G00.06');
        $remun->appendChild($this->leaf($dom, 'S21.G00.06.001', $this->money($slip->gross_salary)));
        $remun->appendChild($this->leaf($dom, 'S21.G00.06.002', $this->money($slip->total_deductions)));
        $remun->appendChild($this->leaf($dom, 'S21.G00.06.003', $this->money($slip->net_salary)));
        $declaration->appendChild($remun);

        // S21.G00.11 — Cotisation individuelle (agrégée salariale + patronale).
        $cotisation = $dom->createElement('Bloc');
        $cotisation->setAttribute('Id', 'S21.G00.11');
        $cotisation->appendChild($this->leaf($dom, 'S21.G00.11.001', 'salariales'));
        $cotisation->appendChild($this->leaf($dom, 'S21.G00.11.002', $this->money($slip->total_deductions)));
        $declaration->appendChild($cotisation);

        $cotisationPat = $dom->createElement('Bloc');
        $cotisationPat->setAttribute('Id', 'S21.G00.11');
        $cotisationPat->appendChild($this->leaf($dom, 'S21.G00.11.001', 'patronales'));
        $cotisationPat->appendChild($this->leaf($dom, 'S21.G00.11.002', $this->money($slip->employer_contributions)));
        $declaration->appendChild($cotisationPat);
    }

    private function leaf(DOMDocument $dom, string $name, string $value): \DOMElement
    {
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));

        return $el;
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
