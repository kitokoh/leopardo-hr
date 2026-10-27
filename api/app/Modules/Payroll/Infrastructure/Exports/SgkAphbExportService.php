<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use DOMDocument;
use DOMElement;

/**
 * #7934 (V2-1) — Declaration mensuelle SGK : APHB (Aylik Prim ve Hizmet
 * Belgesi), format e-bildirge.
 *
 * En Turquie, l'employeur declare chaque mois a la SGK, **par isyeri
 * (etablissement)**, les jours de prime (prim ödeme gün sayisi) et les
 * assiettes de chaque sigortalı. Le document porte un `belgeTuru` et un
 * `kanunNo` (code de loi) qui determinent les subventions appliquees : se
 * tromper de code ne rend pas le fichier invalide, cela fait perdre
 * l'exoneration — c'est un ecart financier silencieux.
 *
 * Structure e-bildirge (APHB) : un enregistrement par salarie, avec
 *   - TCKN (kimlik no) : 11 chiffres, cle de controle mod 10 ;
 *   - prim ödeme gün sayisi : jours **effectivement** declares a la SGK
 *     (plafonne a 30 par mois, et jamais plus que les jours calendaires) ;
 *   - kazanç (assiette) : brut, plafonne au plafond SGK (7,5 x asgari ucret) ;
 *   - belgeTuru / kanunNo : codes de document et de loi.
 *
 * Ce que ce service ne fait PAS : calculer les cotisations. Les taux et
 * plafonds SGK evoluent chaque annee (asgari ucret revalorise en janvier et
 * en juillet) et relevent du moteur de paie, pas d'un export.
 */
final class SgkAphbExportService
{
    /** Plafond mensuel SGK = 7,5 x salaire minimum brut (2026). */
    public const PLAFOND_SGK_MENSUEL = 7.5 * 22_104.67;

    /** Jours calendaires maximaux declarables par mois. */
    private const JOURS_MAX_MENSUEL = 30;

    /**
     * Construit le fichier APHB du run.
     *
     * @return string Contenu XML e-bildirge.
     *
     * @throws DsnConformityException si un salarie n'est pas declarable.
     */
    public function build(PayrollRun $run): string
    {
        $slips = $run->paySlips ?? $run->paySlips()->get();

        $anomalies = [];
        foreach ($slips as $slip) {
            foreach ($this->validate($slip) as $anomalie) {
                $anomalies[] = $anomalie;
            }
        }

        if ($anomalies !== []) {
            throw new DsnConformityException($anomalies);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElement('AylikPrimVeHizmetBelgesi');
        $root->setAttribute('versiyon', '1.0');
        $dom->appendChild($root);

        // En-tete : isyeri (etablissement) + periode de declaration.
        $root->appendChild($this->leaf($dom, 'IsyeriSicilNo', (string) ($run->company_id ?? '')));
        $root->appendChild($this->leaf($dom, 'DonemYil', $run->period_start->format('Y')));
        $root->appendChild($this->leaf($dom, 'DonemAy', $run->period_start->format('n')));
        // belgeTuru 1 = "Asil" (declaration principale, pas un correctif).
        $root->appendChild($this->leaf($dom, 'BelgeTuru', '1'));

        $sigortalilar = $dom->createElement('Sigortalilar');

        foreach ($slips as $slip) {
            $sigortalilar->appendChild($this->sigortali($dom, $slip, $run));
        }

        $root->appendChild($sigortalilar);

        return $dom->saveXML() ?: '';
    }

    /**
     * @return list<string>
     */
    public function validate(PaySlip $slip): array
    {
        $anomalies = [];
        $employee = $slip->employee;
        $who = $employee?->matricule !== null && $employee->matricule !== ''
            ? 'Salarie '.$employee->matricule
            : 'Bulletin #'.$slip->id;

        $tckn = $this->digitsOnly($employee?->getAttribute('national_id'));

        if ($tckn === '') {
            $anomalies[] = $who.' : TCKN absent — obligatoire pour la declaration SGK.';
        } elseif (! $this->isValidTckn($tckn)) {
            $anomalies[] = sprintf('%s : TCKN invalide (%s) — 11 chiffres et cle de controle attendus.', $who, $tckn);
        }

        $jours = $this->jours($slip);

        if ($jours <= 0) {
            $anomalies[] = sprintf('%s : aucun jour de prime declare (prim ödeme gün sayisi = 0).', $who);
        } elseif ($jours > self::JOURS_MAX_MENSUEL) {
            $anomalies[] = sprintf(
                '%s : %d jours declares, au-dela du maximum SGK mensuel (%d).',
                $who,
                $jours,
                self::JOURS_MAX_MENSUEL,
            );
        }

        if ($slip->gross_salary <= 0.0) {
            $anomalies[] = sprintf('%s : assiette nulle ou negative (%.2f).', $who, $slip->gross_salary);
        }

        return $anomalies;
    }

    private function sigortali(DOMDocument $dom, PaySlip $slip, PayrollRun $run): DOMElement
    {
        $employee = $slip->employee;
        $node = $dom->createElement('Sigortali');

        $node->appendChild($this->leaf($dom, 'TCKimlikNo', $this->digitsOnly($employee?->getAttribute('national_id'))));
        $prenom = $employee instanceof Employee ? $employee->first_name : '';
        $nom = $employee instanceof Employee ? $employee->last_name : '';

        $node->appendChild($this->leaf($dom, 'Ad', (string) $prenom));
        $node->appendChild($this->leaf($dom, 'Soyad', (string) $nom));
        $node->appendChild($this->leaf($dom, 'GorevKodu', '01'));
        $node->appendChild($this->leaf($dom, 'PrimOdemeGunSayisi', (string) $this->jours($slip)));

        // Assiette plafonnee : declarer au-dela du plafond SGK gonfle la
        // contribution sans ouvrir de droit supplementaire.
        $assiette = min((float) $slip->gross_salary, self::PLAFOND_SGK_MENSUEL);
        $node->appendChild($this->leaf($dom, 'Kazanc', $this->money($assiette)));

        // KanunNo 05510 : loi 5510 (regime general). Un code errone ne rend
        // pas le fichier invalide mais change les subventions appliquees.
        $node->appendChild($this->leaf($dom, 'KanunNo', '05510'));
        $node->appendChild($this->leaf($dom, 'BelgeTuru', '1'));
        $node->appendChild($this->leaf($dom, 'DonemYil', $run->period_start->format('Y')));
        $node->appendChild($this->leaf($dom, 'DonemAy', $run->period_start->format('n')));

        return $node;
    }

    /**
     * Jours de prime declares : jours effectivement travailles, plus conges
     * payes et jours feries (qui sont declares), jamais les conges sans solde.
     */
    private function jours(PaySlip $slip): int
    {
        $travailles = (float) $slip->actual_days_worked;
        $congesPayes = (float) $slip->paid_leave_days;
        $feries = (float) $slip->public_holiday_days;

        $total = $travailles + $congesPayes + $feries;

        // Sans donnee de presence, on retombe sur les jours ouvrés du
        // bulletin — declarer 0 ferait rejeter la ligne par la SGK.
        if ($total <= 0.0) {
            $total = (float) $slip->working_days;
        }

        return max(0, min(self::JOURS_MAX_MENSUEL, (int) round($total)));
    }

    /**
     * Validation TCKN : 11 chiffres, premier != 0, et deux cles de controle
     * (10e chiffre par somme ponderee, 11e par somme des 10 premiers).
     */
    private function isValidTckn(string $tckn): bool
    {
        if (strlen($tckn) !== 11 || $tckn[0] === '0') {
            return false;
        }

        $digits = array_map(static fn (string $d): int => (int) $d, str_split($tckn));

        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];

        $tenth = (($oddSum * 7) - $evenSum) % 10;
        if ($tenth < 0) {
            $tenth += 10;
        }

        if ($tenth !== $digits[9]) {
            return false;
        }

        $eleventh = array_sum(array_slice($digits, 0, 10)) % 10;

        return $eleventh === $digits[10];
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
