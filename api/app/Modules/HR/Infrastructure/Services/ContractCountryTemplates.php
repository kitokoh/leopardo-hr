<?php

declare(strict_types=1);

namespace App\Modules\HR\Infrastructure\Services;

/**
 * Modèles légaux de contrat par pays (issue #5260).
 *
 * Clauses rédactionnelles TYPES par pays (DZ/MA/TN/SN d'abord), sourcées sur
 * les codes du travail applicables. Ce sont des RÉSUMÉS STRUCTURÉS destinés à
 * la rédaction de contrats — pas une consultation juridique : chaque bloc
 * cite son texte de référence et la revue d'un expert légal reste requise
 * avant utilisation en production (DoD #5260 « un contrat généré est
 * conforme (revue légale) » — gate documentée dans la spec).
 *
 * Les pays supportés par le moteur de paie multi-pays (CountryDefaults)
 * hors liste retombent sur un bundle générique (références vides).
 */
class ContractCountryTemplates
{
    /**
     * @return array<int, string>
     */
    public function supportedCountries(): array
    {
        return array_keys($this->bundles());
    }

    public function supports(string $country): bool
    {
        return isset($this->bundles()[strtoupper($country)]);
    }

    /**
     * @return array<string, mixed> bundle du pays (ou générique si inconnu)
     */
    public function forCountry(string $country, string $contractType = 'cdi'): array
    {
        $country = strtoupper($country);
        $bundle = $this->bundles()[$country] ?? $this->genericBundle();

        return [
            'country' => $country,
            'contract_type' => $contractType,
            'legal_references' => $bundle['legal_references'],
            'probation' => $bundle['probation'],
            'notice_period' => $bundle['notice_period'],
            'annual_leave' => $bundle['annual_leave'],
            'overtime' => $bundle['overtime'],
            'minimum_wage' => $bundle['minimum_wage'],
            'social_security' => $bundle['social_security'],
            'clauses' => $bundle['clauses'][$contractType] ?? $bundle['clauses']['cdi'],
        ];
    }

    /**
     * Bundle complet par pays : métadonnées légales + clauses CDI/CDD.
     *
     * @return array<string, array<string, mixed>>
     */
    private function bundles(): array
    {
        return [
            // ── Algérie — Loi 90-11 du 21/04/1990 (relations de travail) ──────
            'DZ' => [
                'legal_references' => [
                    'code' => 'Loi n° 90-11 du 21 avril 1990 relative aux relations de travail',
                    'articles' => ['15' => 'Période d\'essai', '12' => 'Heures supplémentaires', '53' => 'Congé annuel', '92' => 'Préavis de licenciement'],
                ],
                'probation' => 'Période d\'essai : CDI ≤ 6 mois ; CDD ≤ 1 mois par année de contrat, plafonnée à 6 mois (loi 90-11, art. 15).',
                'notice_period' => 'Préavis : 1 mois si ancienneté < 10 ans, 2 mois si ≥ 10 ans (loi 90-11, art. 92).',
                'annual_leave' => 'Congé annuel payé : 2,5 jours ouvrables par mois de travail effectif (30 jours/an — loi 90-11, art. 53).',
                'overtime' => 'Heures supplémentaires majorées : 25 % (jour), 50 % (nuit / jour de repos), 100 % (jours fériés) au-delà de 40 h/sem (loi 90-11, art. 12).',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNAS : salarié 9 %, employeur 26 % (non plafonnées — règles paye AlgeriaPayrollRules).',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI) à compter de la date d\'effet, conformément à la loi 90-11 relative aux relations de travail.'],
                        ['title' => 'Période d\'essai', 'body' => 'Une période d\'essai de X mois est prévue, renouvelable dans les limites de l\'article 15 de la loi 90-11, au terme de laquelle l\'embauche devient définitive.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel est fixé à X, versé selon la périodicité convenue, auquel s\'ajoutent les majorations légales (heures supplémentaires, travail des jours fériés — art. 12).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d\'un congé annuel payé de 2,5 jours ouvrables par mois de travail effectif (art. 53).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 1 à 2 mois selon l\'ancienneté (art. 92).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, pour le motif légal prévu par la loi 90-11.'],
                        ['title' => 'Période d\'essai', 'body' => 'La période d\'essai est fixée à 1 mois par année de contrat, dans la limite de 6 mois (art. 15).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X, assorti des majorations légales applicables (art. 12).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 2,5 jours ouvrables de congé par mois de travail effectif (art. 53).'],
                    ],
                ],
            ],
            // ── Maroc — Dahir n° 1-03-194 (loi 65-99, code du travail) ─────────
            'MA' => [
                'legal_references' => [
                    'code' => 'Code du travail marocain — loi n° 65-99 (dahir n° 1-03-194 du 11/09/2003)',
                    'articles' => ['16' => 'CDD (durée et renouvellement)', '23' => 'Période d\'essai', '54' => 'Préavis', '232' => 'Congé annuel', '201' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois ; CDD ≤ 1 mois par année de contrat, plafonnée à 2 mois (loi 65-99, art. 23).',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (5-10 ans), 3 mois (≥ 10 ans) — art. 54.',
                'annual_leave' => 'Congé annuel payé : 1,5 jour ouvrable par mois de travail effectif (18 jours/an — art. 232).',
                'overtime' => 'Heures supplémentaires majorées : 25 % (jour), 50 % (nuit / repos hebdomadaire) au-delà de 44 h/sem (art. 201).',
                'minimum_wage' => 'SMIG en vigueur (3 422,72 MAD/mois 2026 — règles paye MoroccoPayrollRules, audit #5248).',
                'social_security' => 'Cotisations CNSS (salarié 4,48 % plafonnée à 6 000 MAD/mois) + AMO (salarié 2,26 %) — règles paye MoroccoPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI), conformément au code du travail (loi 65-99).'],
                        ['title' => 'Période d\'essai', 'body' => 'Une période d\'essai de X mois est prévue (maximum légal : 3 mois — art. 23).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X MAD, auquel s\'ajoutent les majorations légales des heures supplémentaires (art. 201).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d\'un congé annuel de 1,5 jour ouvrable par mois de travail effectif (art. 232).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 8 jours à 3 mois selon l\'ancienneté (art. 54).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, renouvelable dans les limites de l\'article 16 du code du travail.'],
                        ['title' => 'Période d\'essai', 'body' => 'La période d\'essai est fixée à 1 mois par année de contrat, dans la limite de 2 mois (art. 23).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X MAD, majorations légales incluses le cas échéant (art. 201).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 1,5 jour ouvrable de congé par mois de travail effectif (art. 232).'],
                    ],
                ],
            ],
            // ── Tunisie — Loi n° 96-62 du 15/07/1996 (code du travail) ─────────
            'TN' => [
                'legal_references' => [
                    'code' => 'Code du travail tunisien — loi n° 96-62 du 15 juillet 1996',
                    'articles' => ['6' => 'CDD (durée maximale 4 ans)', '10' => 'Période d\'essai', '17' => 'Préavis', '49' => 'Congé annuel', '88' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d\'essai : CDI ≤ 6 mois ; CDD ≤ 1 mois (travailleurs non qualifiés) ou 3 mois (cadres) — art. 10.',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-3 ans), 2 mois (3-6 ans), 3 mois (≥ 6 ans) — art. 17.',
                'annual_leave' => 'Congé annuel payé : 1,5 jour ouvrable par mois de travail effectif (18 jours/an — art. 49).',
                'overtime' => 'Heures supplémentaires majorées : 25 % (jour), 50 % (nuit), 100 % (jours fériés/repos) au-delà de 40 h/sem (art. 88).',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNSS (salarié ~9,18 % selon assiettes) + régime ASSP — règles paye TunisiaPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI), conformément au code du travail (loi 96-62).'],
                        ['title' => 'Période d\'essai', 'body' => 'Une période d\'essai de X mois est prévue, dans la limite de 6 mois (art. 10).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X, majorations légales des heures supplémentaires incluses (art. 88).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d\'un congé annuel de 1,5 jour ouvrable par mois de travail effectif (art. 49).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 8 jours à 3 mois selon l\'ancienneté (art. 17).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, dans la limite légale de 4 ans renouvellements compris (art. 6).'],
                        ['title' => 'Période d\'essai', 'body' => 'La période d\'essai est fixée conformément à l\'article 10 du code du travail.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X, majorations légales incluses le cas échéant (art. 88).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 1,5 jour ouvrable de congé par mois de travail effectif (art. 49).'],
                    ],
                ],
            ],
            // ── Sénégal — Loi n° 97-17 du 01/12/1997 (code du travail) ────────
            'SN' => [
                'legal_references' => [
                    'code' => 'Code du travail sénégalais — loi n° 97-17 du 1er décembre 1997',
                    'articles' => ['L.51' => 'CDD (durée et renouvellement)', 'L.53' => 'Période d\'essai', 'L.56' => 'Préavis', 'L.139' => 'Congé annuel', 'L.104' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d\'essai : CDI ≤ 6 mois ; CDD ≤ 1 mois par année de contrat, plafonnée à 6 mois (art. L.53).',
                'notice_period' => 'Préavis : 1 mois (ancienneté < 1 an), 2 mois (1-5 ans), 3 mois (≥ 5 ans) — art. L.56.',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de travail effectif (24 jours/an — art. L.139).',
                'overtime' => 'Heures supplémentaires majorées : +10 % (2 premières heures), +20 % (au-delà) en semaine ; +20 %/+30 % le dimanche ; taux majorés jours fériés — art. L.104.',
                'minimum_wage' => 'SMIG en vigueur (64 305,43 FCFA/mois référence 2026 — règles paye SenegalPayrollRules).',
                'social_security' => 'Cotisations IPRES (retraite) + CSS (prestations familiales 7 %, accidents du travail) + ITS — règles paye SenegalPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI), conformément au code du travail (loi 97-17).'],
                        ['title' => 'Période d\'essai', 'body' => 'Une période d\'essai de X mois est prévue, dans la limite de 6 mois (art. L.53).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X FCFA, majorations légales des heures supplémentaires incluses (art. L.104).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d\'un congé annuel de 2 jours ouvrables par mois de travail effectif (art. L.139).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 1 à 3 mois selon l\'ancienneté (art. L.56).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, renouvelable dans les limites de l\'article L.51 du code du travail.'],
                        ['title' => 'Période d\'essai', 'body' => 'La période d\'essai est fixée conformément à l\'article L.53 du code du travail.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire mensuel de base est fixé à X FCFA, majorations légales incluses le cas échéant (art. L.104).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 2 jours ouvrables de congé par mois de travail effectif (art. L.139).'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Bundle générique (pays hors DZ/MA/TN/SN) : clauses neutres sans
     * référence légale — le contrat reste rédigé par l'entreprise.
     *
     * @return array<string, mixed>
     */
    private function genericBundle(): array
    {
        return [
            'legal_references' => ['code' => 'Règles locales à préciser', 'articles' => []],
            'probation' => 'Période d\'essai à définir selon les règles locales.',
            'notice_period' => 'Préavis à définir selon les règles locales.',
            'annual_leave' => 'Congé annuel selon les règles locales.',
            'overtime' => 'Heures supplémentaires selon les règles locales.',
            'minimum_wage' => 'Salaire minimum selon les règles locales.',
            'social_security' => 'Cotisations sociales selon les règles locales.',
            'clauses' => [
                'cdi' => [
                    ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI).'],
                    ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel est fixé à X.'],
                ],
                'cdd' => [
                    ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y.'],
                    ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X.'],
                ],
            ],
        ];
    }
}
