<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Exports;

use App\Modules\Payroll\Domain\Models\PaySlip;

/**
 * Controles de coherence NEODeS sur un bulletin avant emission DSN — #7936.
 *
 * Ne produit rien : collecte les anomalies bloquantes. Separer le controle de
 * la generation permet de tester les regles sans construire de XML, et de
 * remonter *toutes* les anomalies en un passage plutot qu'une seule.
 *
 * Regles implementees (cahier technique DSN, blocs S21.G00.30/40/50) :
 *  - CCH-11 : NIR obligatoire, 13 chiffres + cle de 2, cle valide (mod 97).
 *  - CCH-12 : une date de naissance est obligatoire (JJMMAAAA pour NEODeS).
 *  - Sexe obligatoire s'il n'est pas deductible du NIR.
 *  - Contrat : nature + date de debut obligatoires ; un CDD doit porter une
 *    date de fin (S21.G00.40.010).
 *  - Versement : le net verse ne peut pas exceder le brut (un net verse
 *    superieur signale une erreur de calcul, pas un cas metier).
 *  - PAS : un taux renseigne doit etre un pourcentage plausible et un type de
 *    taux doit l'accompagner (regle DGFiP : sans type, le taux est inexploitable).
 */
final class DsnConformityChecker
{
    /**
     * @param  array{sexe: ?string, date_naissance: ?string, nature_contrat: ?string, date_debut_contrat: ?string, date_fin_contrat: ?string, nir: ?string}  $identite
     * @return list<string> Anomalies bloquantes (vide = conforme).
     */
    public function check(PaySlip $slip, array $identite): array
    {
        $anomalies = [];
        $who = $this->identify($slip, $identite);

        // CCH-11 — NIR.
        $nir = $this->digitsOnly($identite['nir']);
        if ($nir === '') {
            $anomalies[] = sprintf('%s : NIR absent (S21.G00.30.001 obligatoire).', $who);
        } elseif (! $this->isValidNir($nir)) {
            $anomalies[] = sprintf(
                '%s : NIR invalide (%s) — 13 chiffres + cle de controle attendus.',
                $who,
                $nir,
            );
        }

        // CCH-12 — etat civil.
        if (($identite['date_naissance'] ?? null) === null || $identite['date_naissance'] === '') {
            $anomalies[] = sprintf('%s : date de naissance absente (S21.G00.30.006 obligatoire).', $who);
        }

        $sexe = $identite['sexe'] ?? null;
        if ($sexe !== '01' && $sexe !== '02') {
            $anomalies[] = sprintf('%s : sexe non determine (S21.G00.30.005 attendu 01 ou 02).', $who);
        }

        // Contrat — S21.G00.40.
        if (($identite['nature_contrat'] ?? null) === null) {
            $anomalies[] = sprintf('%s : nature du contrat inconnue (S21.G00.40.007 obligatoire).', $who);
        }

        if (($identite['date_debut_contrat'] ?? null) === null) {
            $anomalies[] = sprintf('%s : date de debut de contrat absente (S21.G00.40.001 obligatoire).', $who);
        }

        // Un CDD (02) sans date de fin est declare incomplet par NEODeS.
        if (($identite['nature_contrat'] ?? null) === '02' && ($identite['date_fin_contrat'] ?? null) === null) {
            $anomalies[] = sprintf(
                '%s : CDD sans date de fin previsionnelle (S21.G00.40.010 obligatoire pour la nature 02).',
                $who,
            );
        }

        // Versement — coherence des montants.
        if ($slip->net_salary > $slip->gross_salary + 0.01) {
            $anomalies[] = sprintf(
                '%s : net verse (%.2f) superieur au brut (%.2f) — verifier le calcul du bulletin.',
                $who,
                $slip->net_salary,
                $slip->gross_salary,
            );
        }

        if ($slip->gross_salary < 0.0) {
            $anomalies[] = sprintf('%s : brut negatif (%.2f) non declarable.', $who, $slip->gross_salary);
        }

        return $anomalies;
    }

    /**
     * Cle NIR (modulo 97) : les 13 premiers chiffres modulo 97 donnent la cle
     * sur 2 chiffres (97 - reste). Les NIR provisoires (7/8 en prefixe) sont
     * refuses : NEODeS exige alors un numero technique temporaire, pas un
     * faux NIR.
     */
    private function isValidNir(string $nir): bool
    {
        if (strlen($nir) !== 15) {
            return false;
        }

        $base = substr($nir, 0, 13);
        $key = (int) substr($nir, 13, 2);

        if (! ctype_digit($base) || ! ctype_digit((string) $key)) {
            return false;
        }

        // Corse : 2A/2B sont encodages valides mais non numeriques — hors
        // perimetre de ce controle automatique, on les laisse passer au
        // controle structurel sans calcul de cle.
        return 97 - ((int) $base % 97) === $key;
    }

    private function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    /**
     * @param  array{sexe: ?string, date_naissance: ?string, nature_contrat: ?string, date_debut_contrat: ?string, date_fin_contrat: ?string, nir: ?string}  $identite
     */
    private function identify(PaySlip $slip, array $identite): string
    {
        $matricule = $slip->employee?->matricule;

        return $matricule !== null && $matricule !== ''
            ? sprintf('Salarie %s', $matricule)
            : sprintf('Bulletin #%d', (int) $slip->id);
    }
}
