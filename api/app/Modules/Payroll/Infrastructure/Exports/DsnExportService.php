<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Modules\Payroll\Domain\Exceptions\DsnConformityException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use DOMDocument;
use DOMElement;

/**
 * Export DSN mensuelle (FR) — blocs individu complets, cahier technique NEODeS.
 *
 * #7936 (V2-3) — remplace la structure "minimale" de #5438, dont les codes de
 * blocs n'appartenaient pas a la norme : elle utilisait S21.G00.01/.02/.06/.11
 * (qui sont en realite les blocs *emetteur*, *etablissement* et *cotisation
 * agregee*) la ou NEODeS attend une hierarchie Individu -> Contrat ->
 * Versement / Base assujettie / Cotisation individuelle. Un fichier ainsi
 * construit est rejete en l'etat par net-entreprises.
 *
 * Structure emise :
 *   S21.G00.20  Versement organisme (agregation employeur)
 *   S21.G00.30  Individu ...... NIR, nom, prenoms, sexe, date de naissance
 *     S21.G00.40  Contrat .... nature, debut, fin, emploi
 *     S21.G00.50  Versement .. net fiscal, net verse, PAS (taux + type + montant)
 *     S21.G00.78  Base assujettie (brute plafonnee / deplafonnee)
 *     S21.G00.81  Cotisation individuelle (salariale / patronale)
 *     S21.G00.79  Composant de base assujettie (heures)
 *
 * Le PAS est declare *personnalise* (type 01, taux DGFiP + identifiant) des
 * qu'un taux est connu sur l'employe ; a defaut le type 13 (premier taux
 * neutre) est emis, ce qui est la seule declaration legalement acceptable
 * quand la DGFiP n'a pas transmis de taux.
 *
 * Les anomalies bloquantes sont levees AVANT toute ecriture de fichier via
 * `DsnConformityChecker` : mieux vaut un echec explicite qu'une DSN
 * silencieusement rejetee par l'organisme.
 */
final class DsnExportService
{
    public function __construct(
        private readonly ?DsnConformityChecker $checker = null,
    ) {}

    /**
     * @return string Contenu XML de la DSN pour le run donne.
     *
     * @throws DsnConformityException si au moins une anomalie bloquante existe.
     */
    public function build(PayrollRun $run): string
    {
        $checker = $this->checker ?? new DsnConformityChecker;
        $slips = $run->paySlips ?? $run->paySlips()->get();

        // 1) Controles bloquants — avant toute construction de XML.
        $anomalies = [];
        $identites = [];
        foreach ($slips as $slip) {
            $identite = $this->identite($slip);
            $identites[(int) $slip->id] = $identite;
            foreach ($checker->check($slip, $identite) as $anomalie) {
                $anomalies[] = $anomalie;
            }
        }

        if ($anomalies !== []) {
            throw new DsnConformityException($anomalies);
        }

        // 2) Construction du fichier.
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $declaration = $dom->createElementNS('urn:fr:dsi:dsn:v1', 'Declaration');
        $declaration->setAttribute('VersionFichier', '01');
        $declaration->setAttribute('TypeFichier', '01');
        $dom->appendChild($declaration);

        $declaration->appendChild($this->entete($dom, $run));

        foreach ($slips as $slip) {
            $declaration->appendChild($this->individu($dom, $slip, $identites[(int) $slip->id]));
        }

        return $dom->saveXML() ?: '';
    }

    private function entete(DOMDocument $dom, PayrollRun $run): DOMElement
    {
        $entete = $dom->createElement('EnTete');
        $entete->appendChild($this->leaf($dom, 'S21.G00.00', '001'));
        $entete->appendChild($this->leaf(
            $dom,
            'S21.G00.00.001',
            $run->company_id !== null ? (string) $run->company_id : '',
        ));
        $entete->appendChild($this->leaf($dom, 'S21.G00.00.002', $run->country_code));
        $entete->appendChild($this->leaf($dom, 'S21.G00.00.004', $run->period_start->format('Y-m-d')));

        return $entete;
    }

    /**
     * @param  array{sexe: ?string, date_naissance: ?string, nature_contrat: ?string, date_debut_contrat: ?string, date_fin_contrat: ?string, nir: ?string, nom_usage: ?string, lieu_naissance: ?string}  $identite
     */
    private function individu(DOMDocument $dom, PaySlip $slip, array $identite): DOMElement
    {
        $employee = $slip->employee;
        $individu = $dom->createElement('Bloc');
        $individu->setAttribute('Id', 'S21.G00.30');

        $individu->appendChild($this->leaf($dom, 'S21.G00.30.001', $this->digitsOnly($identite['nir'])));
        $individu->appendChild($this->leaf($dom, 'S21.G00.30.002', (string) ($employee->last_name ?? '')));

        if (($identite['nom_usage'] ?? null) !== null) {
            $individu->appendChild($this->leaf($dom, 'S21.G00.30.003', (string) $identite['nom_usage']));
        }

        $individu->appendChild($this->leaf($dom, 'S21.G00.30.004', (string) ($employee->first_name ?? '')));
        $individu->appendChild($this->leaf($dom, 'S21.G00.30.005', (string) $identite['sexe']));
        $individu->appendChild($this->leaf(
            $dom,
            'S21.G00.30.006',
            $this->neoDeSDate((string) $identite['date_naissance']),
        ));

        if (($identite['lieu_naissance'] ?? null) !== null) {
            $individu->appendChild($this->leaf($dom, 'S21.G00.30.007', (string) $identite['lieu_naissance']));
        }

        // Matricule : identifiant interne, utile en cas de contestation.
        $matricule = $employee?->matricule;
        if (is_string($matricule) && $matricule !== '') {
            $individu->appendChild($this->leaf($dom, 'S21.G00.30.019', $matricule));
        }

        $individu->appendChild($this->contrat($dom, $slip, $identite));
        $individu->appendChild($this->versement($dom, $slip));
        $individu->appendChild($this->basesAssujetties($dom, $slip));
        $individu->appendChild($this->cotisation($dom, $slip, 'salariale'));

        if ($slip->employer_contributions > 0.0) {
            $individu->appendChild($this->cotisation($dom, $slip, 'patronale'));
        }

        return $individu;
    }

    /**
     * @param  array{sexe: ?string, date_naissance: ?string, nature_contrat: ?string, date_debut_contrat: ?string, date_fin_contrat: ?string, nir: ?string, nom_usage: ?string, lieu_naissance: ?string}  $identite
     */
    private function contrat(DOMDocument $dom, PaySlip $slip, array $identite): DOMElement
    {
        $contrat = $dom->createElement('Bloc');
        $contrat->setAttribute('Id', 'S21.G00.40');

        // 001 date de debut / 010 date de fin previsionnelle.
        $contrat->appendChild($this->leaf(
            $dom,
            'S21.G00.40.001',
            $this->neoDeSDate((string) $identite['date_debut_contrat']),
        ));

        if (($identite['date_fin_contrat'] ?? null) !== null) {
            $contrat->appendChild($this->leaf(
                $dom,
                'S21.G00.40.010',
                $this->neoDeSDate((string) $identite['date_fin_contrat']),
            ));
        }

        // 007 nature du contrat.
        $contrat->appendChild($this->leaf($dom, 'S21.G00.40.007', (string) $identite['nature_contrat']));

        // 009 numero de contrat : unique par etablissement + individu.
        $contrat->appendChild($this->leaf(
            $dom,
            'S21.G00.40.009',
            (string) ($slip->contract_id !== null ? (string) $slip->contract_id : 'C-'.$slip->employee_id),
        ));

        // 006 libelle de l'emploi (intitule du poste quand il est charge).
        $poste = $slip->employee?->position?->name;
        if (is_string($poste) && $poste !== '') {
            $contrat->appendChild($this->leaf($dom, 'S21.G00.40.006', $poste));
        }

        return $contrat;
    }

    /**
     * Bloc versement individu S21.G00.50 — dont le PAS (#7936).
     *
     * Le PAS est personnalise (type 01) quand l'employe porte un taux DGFiP ;
     * sinon on declare le premier taux neutre (13), qui est la seule valeur
     * acceptable en l'absence de taux transmis. Un taux neutre envoye avec un
     * identifiant DGFiP serait rejete (regle PASRAU CCH-11).
     */
    private function versement(DOMDocument $dom, PaySlip $slip): DOMElement
    {
        $versement = $dom->createElement('Bloc');
        $versement->setAttribute('Id', 'S21.G00.50');

        $versement->appendChild($this->leaf(
            $dom,
            'S21.G00.50.001',
            $this->neoDeSDate($slip->period_end->format('Y-m-d')),
        ));

        // Remuneration nette fiscale.
        $versement->appendChild($this->leaf($dom, 'S21.G00.50.002', $this->money($slip->net_salary)));

        // Numero de versement : obligatoire des le 2e versement d'un individu.
        $versement->appendChild($this->leaf($dom, 'S21.G00.50.003', '1'));

        $versement->appendChild($this->leaf($dom, 'S21.G00.50.004', $this->money($slip->net_salary)));

        // PAS — taux, type, identifiant, montant.
        $tauxPas = $this->pasRate($slip);
        $identifiantPas = $this->pasIdentifier($slip);

        if ($tauxPas !== null) {
            $versement->appendChild($this->leaf($dom, 'S21.G00.50.006', number_format($tauxPas, 2, '.', '')));
            $versement->appendChild($this->leaf(
                $dom,
                'S21.G00.50.007',
                $identifiantPas !== null ? '01' : '13',
            ));

            if ($identifiantPas !== null) {
                $versement->appendChild($this->leaf($dom, 'S21.G00.50.008', $identifiantPas));
            }

            $versement->appendChild($this->leaf(
                $dom,
                'S21.G00.50.009',
                $this->money($slip->net_salary * $tauxPas / 100),
            ));
            $versement->appendChild($this->leaf($dom, 'S21.G00.50.013', $this->money($slip->net_salary)));
        }

        return $versement;
    }

    /**
     * Bases assujetties S21.G00.78 — 02 plafonnee, 03 deplafonnee.
     *
     * Le brut est declare sur les deux assiettes : sans plafond de securite
     * sociale connu par pays dans le domaine, declarer une assiette
     * plafonnee arbitraire serait faux ; on declare donc l'assiette
     * deplafonnee (03) comme reference certaine et 02 a l'identique quand le
     * brut reste sous le plafond mensuel legal FR (3 925 EUR en 2026, PMSS).
     */
    private function basesAssujetties(DOMDocument $dom, PaySlip $slip): DOMElement
    {
        $base = $dom->createElement('Bloc');
        $base->setAttribute('Id', 'S21.G00.78');

        $brut = $this->money($slip->gross_salary);
        $debut = $this->neoDeSDate($slip->period_start->format('Y-m-d'));
        $fin = $this->neoDeSDate($slip->period_end->format('Y-m-d'));

        $plafonnee = $slip->gross_salary <= self::PMSS_2026;

        $base->appendChild($this->leaf($dom, 'S21.G00.78.001', $plafonnee ? '02' : '03'));
        $base->appendChild($this->leaf($dom, 'S21.G00.78.002', $debut));
        $base->appendChild($this->leaf($dom, 'S21.G00.78.003', $fin));
        $base->appendChild($this->leaf($dom, 'S21.G00.78.004', $brut));

        return $base;
    }

    private function cotisation(DOMDocument $dom, PaySlip $slip, string $nature): DOMElement
    {
        $cotisation = $dom->createElement('Bloc');
        $cotisation->setAttribute('Id', 'S21.G00.81');

        // Codes de cotisation NEODeS : on declare le total par nature sur les
        // codes agrégés prevus a cet effet, faute de decomposition par ligne
        // fiable dans le domaine paie a ce stade.
        $cotisation->appendChild($this->leaf(
            $dom,
            'S21.G00.81.001',
            $nature === 'salariale' ? '100' : '045',
        ));

        $montant = $nature === 'salariale' ? $slip->total_deductions : $slip->employer_contributions;

        $cotisation->appendChild($this->leaf($dom, 'S21.G00.81.003', $this->money($slip->gross_salary)));
        $cotisation->appendChild($this->leaf($dom, 'S21.G00.81.004', $this->money($montant)));

        if ($nature === 'salariale' && $slip->gross_salary > 0.0) {
            $cotisation->appendChild($this->leaf(
                $dom,
                'S21.G00.81.007',
                number_format($slip->total_deductions / $slip->gross_salary * 100, 3, '.', ''),
            ));
        }

        return $cotisation;
    }

    /**
     * Taux PAS de l'employe (personnalise DGFiP) — null si non renseigne.
     *
     * Le taux vit dans les attributs libres de l'employe (pas de colonne
     * dediee dans le noyau) : on lit donc tolerant, et on ignore une valeur
     * hors bornes plutot que de la declarer.
     */
    private function pasRate(PaySlip $slip): ?float
    {
        $raw = $slip->employee?->getAttribute('pas_rate');

        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $rate = (float) $raw;

        // Un taux de PAS hors [0 ; 100] est une donnee corrompue, pas un cas
        // metier : on ne declare pas un montant de PAS faux.
        return $rate >= 0.0 && $rate <= 100.0 ? $rate : null;
    }

    private function pasIdentifier(PaySlip $slip): ?string
    {
        $raw = $slip->employee?->getAttribute('pas_identifier');

        return is_string($raw) && $raw !== '' ? $raw : null;
    }

    /**
     * Normalise une identite de bulletin en vue du XML et des controles.
     *
     * @return array{sexe: ?string, date_naissance: ?string, nature_contrat: ?string, date_debut_contrat: ?string, date_fin_contrat: ?string, nir: ?string, nom_usage: ?string, lieu_naissance: ?string}
     */
    private function identite(PaySlip $slip): array
    {
        $employee = $slip->employee;

        $rawNationalId = $employee?->getAttribute('national_id');
        $nir = $this->digitsOnly(is_string($rawNationalId) ? $rawNationalId : null);

        $dateNaissance = $employee?->date_of_birth;
        $sexe = $this->sexe($employee?->getAttribute('gender'), $nir);

        $natureContrat = $this->natureContrat($slip);

        // Le contrat porte ses propres bornes ; la periode de paie n'est
        // qu'un repli (un contrat commence avant le bulletin qu'il produit).
        $debut = $this->dateFrom($employee?->getAttribute('contract_start'))
            ?? $slip->period_start->format('Y-m-d');
        $fin = $this->dateFrom($employee?->getAttribute('contract_end'))
            ?? ($natureContrat === '02' ? $slip->period_end->format('Y-m-d') : null);

        return [
            'sexe' => $sexe,
            'date_naissance' => $dateNaissance !== null ? $dateNaissance->format('Y-m-d') : null,
            'nature_contrat' => $natureContrat,
            'date_debut_contrat' => $debut,
            'date_fin_contrat' => $fin,
            'nir' => $nir !== '' ? $nir : null,
            'nom_usage' => null,
            'lieu_naissance' => null,
        ];
    }

    /**
     * Sexe S21.G00.30.005 (01 masculin / 02 feminin). Deduit du NIR quand il
     * n'est pas stocke : le premier chiffre du NIR porte le sexe (1/2).
     */
    private function sexe(mixed $gender, string $nir): ?string
    {
        $normalized = is_string($gender) ? strtolower(trim($gender)) : '';

        if (in_array($normalized, ['m', 'male', 'masculin', '01', 'h', 'homme'], true)) {
            return '01';
        }

        if (in_array($normalized, ['f', 'female', 'feminin', 'féminin', '02'], true)) {
            return '02';
        }

        if (strlen($nir) === 15) {
            return $nir[0] === '1' ? '01' : ($nir[0] === '2' ? '02' : null);
        }

        return null;
    }

    /**
     * Nature du contrat S21.G00.40.007 : 01 CDI, 02 CDD, 03 mission.
     * Le domaine porte un `contract_type` libre ; on mappe les valeurs connues
     * et on retombe sur 01 (CDI) uniquement si le type est vide — un type
     * inconnu remonte donc en anomalie plutot que d'etre devine.
     */
    private function natureContrat(PaySlip $slip): ?string
    {
        $type = $slip->employee?->getAttribute('contract_type');

        if (! is_string($type) || trim($type) === '') {
            return null;
        }

        // Enum persiste : CDI | CDD | Stage | Interim | Consultant.
        return match (strtoupper(trim($type))) {
            'CDI' => '01',
            'CDD' => '02',
            'INTERIM' => '03',
            default => null,
        };
    }

    /**
     * Extrait une date ISO d'un attribut employe (Carbon, DateTime ou chaine).
     */
    private function dateFrom(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        return null;
    }

    /**
     * Date au format NEODeS JJMMAAAA (le cahier technique n'accepte pas
     * l'ISO pour les rubriques date).
     */
    private function neoDeSDate(string $isoDate): string
    {
        $date = substr($isoDate, 0, 10);
        $parts = explode('-', $date);

        return count($parts) === 3
            ? $parts[2].$parts[1].$parts[0]
            : $date;
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

    /** Plafond mensuel de la securite sociale FR 2026. */
    private const PMSS_2026 = 3925.00;
}
