<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PaySlip;
use DOMDocument;
use DOMElement;

/**
 * #7935 (V2-2) — Feuillets annuels canadiens : T4 (ARC) et RL-1 (Revenu
 * Quebec).
 *
 * Les deux feuillets partagent la meme assiette annuelle mais **ne se
 * confondent pas** : le T4 declare au federal (ARC, case 14 = revenu
 * d'emploi, case 22 = impot federal retenu, case 24 = RRQ/RPC), le RL-1
 * declare au Quebec (case A = revenu d'emploi quebecois, case E = impot du
 * Quebec, case B = RRQ, case H = RQAP).
 *
 * **Decision structurante — un feuillet par (salarie, annee), pas par run.**
 * Le T4 est un document ANNUEL : une annee peut compter douze runs, et
 * additionner les `net_salary` produirait un revenu *net* la ou le fisc
 * attend un revenu *brut*. Le service prend donc une collection de
 * bulletins et agrege, en ecartant les bulletins d'une autre annee plutot
 * qu'en les melangeant silencieusement.
 *
 * **Decision structurante — les cases ne sont pas recalculees ici.**
 * Les cotisations RRQ/RPC, AE et RQAP dependent de maximums annuels (MGA)
 * revalorises chaque annee et du cumul annuel : les recalculer dans un
 * export ferait diverger le feuillet du bulletin. Le service lit les
 * retenues **reellement prelevees** sur les lignes de bulletin et echoue
 * si l'impot federal est absent — un T4 a 0,00 en case 22 declarerait a
 * l'ARC un impot non retenu, ce qui expose l'employeur a une cotisation
 * retrospective.
 *
 * **Decision structurante — la province n'est PAS devinee.**
 * Ni `employees` ni `companies` ne portent de province : seul `country`
 * existe. Deviner « QC » pour un salarie canadien emettrait un RL-1 a une
 * administration ou l'employe n'est pas enregistre. La province est donc
 * fournie **explicitement** par l'appelant ; un salarie sans province
 * connue ne produit **aucun** RL-1 (mais reste declare au federal).
 *
 * Ce que ce service ne fait PAS : produire le fichier de transmission
 * (T619 + sommaires ARC, XML RL-1). Il produit les **feuillets
 * individuels** ; le format de transmission a une version annuelle propre
 * et releve d'un lot dedie.
 */
final class CanadaT4ExportService
{
    /** Code de province quebecoise. */
    public const PROVINCE_QUEBEC = 'QC';

    /**
     * Construit les feuillets T4 de l'annee pour les bulletins fournis.
     *
     * @param  iterable<PaySlip>  $slips  Bulletins de l'annee, tous employes confondus.
     * @param  int  $year  Annee fiscale.
     * @param  array<int|string, string>  $provinceByEmployee  Province de residence par employee_id.
     * @return string XML des feuillets T4.
     *
     * @throws DsnConformityException si un salarie n'est pas declarable.
     */
    public function buildT4(iterable $slips, int $year, array $provinceByEmployee = []): string
    {
        $byEmployee = $this->groupByEmployee($slips, $year);

        if ($byEmployee === []) {
            throw new DsnConformityException(['Aucun bulletin pour l\'annee fiscale '.$year.'.']);
        }

        $anomalies = [];
        foreach ($byEmployee as $employeeId => $employeeSlips) {
            foreach ($this->validate($employeeId, $employeeSlips) as $anomalie) {
                $anomalies[] = $anomalie;
            }
        }

        if ($anomalies !== []) {
            throw new DsnConformityException($anomalies);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('T4Declarations');
        $root->setAttribute('annee', (string) $year);
        $dom->appendChild($root);

        foreach ($byEmployee as $employeeId => $employeeSlips) {
            $province = $this->provinceFor($employeeId, $provinceByEmployee);
            $root->appendChild($this->t4Slip($dom, $employeeSlips, $year, $province));
        }

        return $dom->saveXML() ?: '';
    }

    /**
     * Construit les feuillets RL-1 des salaries **quebecois** uniquement.
     *
     * @param  iterable<PaySlip>  $slips
     * @param  array<int|string, string>  $provinceByEmployee
     * @return string XML des feuillets RL-1, ou chaine vide si aucun salarie au Quebec.
     *
     * @throws DsnConformityException si un salarie quebecois n'est pas declarable.
     */
    public function buildRl1(iterable $slips, int $year, array $provinceByEmployee = []): string
    {
        $byEmployee = $this->groupByEmployee($slips, $year);

        $quebec = [];
        foreach ($byEmployee as $employeeId => $employeeSlips) {
            if ($this->provinceFor($employeeId, $provinceByEmployee) === self::PROVINCE_QUEBEC) {
                $quebec[$employeeId] = $employeeSlips;
            }
        }

        if ($quebec === []) {
            return '';
        }

        $anomalies = [];
        foreach ($quebec as $employeeId => $employeeSlips) {
            foreach ($this->validate($employeeId, $employeeSlips) as $anomalie) {
                $anomalies[] = $anomalie;
            }
        }

        if ($anomalies !== []) {
            throw new DsnConformityException($anomalies);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('RL1Declarations');
        $root->setAttribute('annee', (string) $year);
        $dom->appendChild($root);

        foreach ($quebec as $employeeSlips) {
            $root->appendChild($this->rl1Slip($dom, $employeeSlips, $year));
        }

        return $dom->saveXML() ?: '';
    }

    /**
     * Controles bloquants communs aux deux feuillets.
     *
     * @param  list<PaySlip>  $employeeSlips
     * @return list<string>
     */
    public function validate(int|string $employeeId, array $employeeSlips): array
    {
        $anomalies = [];
        $employee = $this->employeeOf($employeeSlips);
        $who = $employee instanceof Employee && $employee->matricule !== null && $employee->matricule !== ''
            ? 'Salarie '.$employee->matricule
            : 'Salarie #'.$employeeId;

        // Le NAS est obligatoire sur le T4 : sans lui l'ARC rejette le
        // feuillet, et un NAS invente est pire qu'un NAS absent — il
        // rattache le revenu a une autre personne.
        $sin = $this->digitsOnly($employee?->getAttribute('national_id'));
        if ($sin === '') {
            $anomalies[] = $who.' : NAS absent — obligatoire sur le feuillet T4.';
        } elseif (! $this->isValidSin($sin)) {
            $anomalies[] = sprintf('%s : NAS invalide (%s) — 9 chiffres et cle de Luhn attendus.', $who, $sin);
        }

        if ($this->annualGross($employeeSlips) <= 0.0) {
            $anomalies[] = sprintf('%s : revenu d\'emploi annuel nul ou negatif.', $who);
        }

        if ($this->annualLineTotal($employeeSlips, ['impot_federal', 'federal_tax']) <= 0.0) {
            $anomalies[] = $who.' : impot federal retenu absent ou nul (case 22) — un feuillet sans retenue expose l\'employeur a une cotisation retrospective.';
        }

        return $anomalies;
    }

    /**
     * @param  iterable<PaySlip>  $slips
     * @return array<int|string, list<PaySlip>>
     */
    private function groupByEmployee(iterable $slips, int $year): array
    {
        $byEmployee = [];

        foreach ($slips as $slip) {
            // Un bulletin d'une autre annee dans un T4 produirait un feuillet
            // faux sans aucune erreur : on l'ecarte ici, en plus du filtre de
            // l'appelant, pour qu'un `where` oublie ne corrompe pas un
            // exercice clos.
            if ((int) $slip->period_start->format('Y') !== $year) {
                continue;
            }

            $byEmployee[$slip->employee_id] ??= [];
            $byEmployee[$slip->employee_id][] = $slip;
        }

        return $byEmployee;
    }

    /**
     * @param  array<int|string, string>  $provinceByEmployee
     */
    private function provinceFor(int|string $employeeId, array $provinceByEmployee): string
    {
        $raw = $provinceByEmployee[$employeeId] ?? '';

        return strtoupper(trim($raw));
    }

    /**
     * @param  list<PaySlip>  $employeeSlips
     */
    private function t4Slip(DOMDocument $dom, array $employeeSlips, int $year, string $province): DOMElement
    {
        $employee = $this->employeeOf($employeeSlips);
        $slip = $dom->createElement('T4');

        $slip->appendChild($this->leaf($dom, 'Annee', (string) $year));
        $slip->appendChild($this->leaf($dom, 'NAS', $this->digitsOnly($employee?->getAttribute('national_id'))));
        $slip->appendChild($this->leaf($dom, 'Nom', $employee instanceof Employee ? (string) $employee->last_name : ''));
        $slip->appendChild($this->leaf($dom, 'Prenom', $employee instanceof Employee ? (string) $employee->first_name : ''));

        // Case 14 — revenu d'emploi : le BRUT annuel, jamais le net.
        $slip->appendChild($this->leaf($dom, 'Case14_RevenuEmploi', $this->money($this->annualGross($employeeSlips))));

        // Case 22 — impot federal retenu.
        $slip->appendChild($this->leaf($dom, 'Case22_ImpotFederalRetenu', $this->money(
            $this->annualLineTotal($employeeSlips, ['impot_federal', 'federal_tax'])
        )));

        // Case 24 — cotisations RRQ/RPC retenues (part salariale).
        $slip->appendChild($this->leaf($dom, 'Case24_RRQ_RPC', $this->money(
            $this->annualLineTotal($employeeSlips, ['rrq', 'rpc', 'qpp'])
        )));

        // Case 16 — pensions (RPA). Souvent 0 en PME, mais la case doit
        // exister pour que le feuillet soit complet.
        $slip->appendChild($this->leaf($dom, 'Case16_Pensions', $this->money(
            $this->annualLineTotal($employeeSlips, ['rpa', 'pension'])
        )));

        // Case 18 — allocations imposables (avantages).
        $slip->appendChild($this->leaf($dom, 'Case18_AllocationsImposables', $this->money(
            $this->annualLineTotal($employeeSlips, ['avantage', 'taxable_benefit'])
        )));

        // Province de residence, telle que fournie : jamais devinee.
        $slip->appendChild($this->leaf($dom, 'Province', $province));

        return $slip;
    }

    /**
     * @param  list<PaySlip>  $employeeSlips
     */
    private function rl1Slip(DOMDocument $dom, array $employeeSlips, int $year): DOMElement
    {
        $employee = $this->employeeOf($employeeSlips);
        $slip = $dom->createElement('RL1');

        $slip->appendChild($this->leaf($dom, 'Annee', (string) $year));
        $slip->appendChild($this->leaf($dom, 'NAS', $this->digitsOnly($employee?->getAttribute('national_id'))));
        $slip->appendChild($this->leaf($dom, 'Nom', $employee instanceof Employee ? (string) $employee->last_name : ''));
        $slip->appendChild($this->leaf($dom, 'Prenom', $employee instanceof Employee ? (string) $employee->first_name : ''));

        // Case A — revenu d'emploi quebecois (meme assiette que T4 case 14).
        $slip->appendChild($this->leaf($dom, 'CaseA_RevenuEmploi', $this->money($this->annualGross($employeeSlips))));

        // Case E — impot du Quebec retenu.
        $slip->appendChild($this->leaf($dom, 'CaseE_ImpotQuebecRetenu', $this->money(
            $this->annualLineTotal($employeeSlips, ['impot_quebec', 'quebec_tax'])
        )));

        // Case B — cotisation RRQ (part salariale).
        $slip->appendChild($this->leaf($dom, 'CaseB_RRQ', $this->money(
            $this->annualLineTotal($employeeSlips, ['rrq', 'qpp'])
        )));

        // Case H — cotisation RQAP (part salariale).
        $slip->appendChild($this->leaf($dom, 'CaseH_RQAP', $this->money(
            $this->annualLineTotal($employeeSlips, ['rqap', 'qpip'])
        )));

        return $slip;
    }

    /**
     * @param  list<PaySlip>  $employeeSlips
     */
    private function employeeOf(array $employeeSlips): ?Employee
    {
        $first = $employeeSlips[0] ?? null;
        $employee = $first?->employee;

        return $employee instanceof Employee ? $employee : null;
    }

    /**
     * Revenu d'emploi annuel : somme des bruts. Le net ne convient pas — le
     * fisc attend le revenu avant retenues.
     *
     * @param  list<PaySlip>  $employeeSlips
     */
    private function annualGross(array $employeeSlips): float
    {
        $total = 0.0;
        foreach ($employeeSlips as $slip) {
            $total += (float) $slip->gross_salary;
        }

        return round($total, 2);
    }

    /**
     * Somme annuelle des lignes dont le code de composant (ou, a defaut, le
     * libelle) contient l'un des fragments donnes.
     *
     * Le code fait foi : les libelles de lignes sont affiches a l'utilisateur
     * et peuvent etre traduits ou reecrits, alors qu'un code de
     * `salary_components` est stable. Le repli sur le libelle existe parce
     * que les lignes historiques peuvent avoir perdu leur composant.
     *
     * @param  list<PaySlip>  $employeeSlips
     * @param  list<string>  $codeFragments
     */
    private function annualLineTotal(array $employeeSlips, array $codeFragments): float
    {
        $total = 0.0;

        foreach ($employeeSlips as $slip) {
            foreach ($slip->lines as $line) {
                $component = $line->salaryComponent;
                $code = $component !== null ? strtolower((string) $component->code) : '';
                $label = strtolower((string) $line->name);
                $haystack = $code !== '' ? $code : $label;

                $matched = false;
                foreach ($codeFragments as $fragment) {
                    if (str_contains($haystack, $fragment)) {
                        $matched = true;
                        break;
                    }
                }

                if (! $matched) {
                    continue;
                }

                // Les retenues sont stockees en negatif selon la convention du
                // moteur de paie : le feuillet declare un montant positif.
                $total += abs((float) ($line->amount ?? 0.0));
            }
        }

        return round($total, 2);
    }

    /**
     * Validation du NAS : 9 chiffres et cle de Luhn. Un NAS au hasard est
     * rejete par l'ARC ; mieux vaut le dire avant la transmission.
     */
    private function isValidSin(string $sin): bool
    {
        if (strlen($sin) !== 9) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $sin[$i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum % 10 === 0;
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function leaf(DOMDocument $dom, string $name, string $value): DOMElement
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
