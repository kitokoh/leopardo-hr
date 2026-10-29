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
        // Issue #6671 — garde anti-500 : un bundle incomplet (clauses ou
        // clé de type absente, ex. déploiement partiel) retombe sur des
        // clauses vides plutôt que de lever une erreur PHP (INTERNAL_ERROR).
        $clauses = $bundle['clauses'] ?? [];
        $clauseSet = $clauses[$contractType] ?? $clauses['cdi'] ?? [];

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
            'clauses' => $clauseSet,
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
            // ── Cameroun — Loi n° 92-007 du 14/08/1992 (code du travail) ─────
            // CEMAC. Le Cameroun est la reference du bloc : c'est le seul pays
            // CEMAC ou le code du travail a ete refondu apres 1992, et les
            // autres membres (Gabon, Congo, Tchad, RCA) s'en inspirent.
            'CM' => [
                'legal_references' => [
                    'code' => 'Code du travail camerounais — loi n° 92-007 du 14 août 1992',
                    'articles' => ['34' => 'Période d’essai', '36' => 'CDD (durée et renouvellement)', '45' => 'Préavis', '60' => 'Congé annuel', '80' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d’essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois, plafonnée à 3 mois (art. 34).',
                'notice_period' => 'Préavis : 15 jours (ancienneté < 6 mois), 1 mois (6 mois - 3 ans), 2 mois (3-10 ans), 3 mois (≥ 10 ans ou cadre) — art. 45.',
                'annual_leave' => 'Congé annuel payé : 1,5 jour ouvrable par mois de travail effectif pour les travailleurs de 18-50 ans, 2,5 jours au-delà (18 à 30 jours/an — art. 60).',
                'overtime' => 'Heures supplémentaires majorées : +20 % (de la 41e à la 48e heure), +30 % (49e-56e), +40 % (au-delà) ; +50 % de nuit, +60 % les jours fériés — art. 80.',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNPS : salarié 4,2 % (pension), employeur 11,2 % (pension + prestations familiales) ; redevance communale additionnelle — règles paye CameroonPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI) à compter de la date d’effet, conformément à la loi n° 92-007 du 14 août 1992.'],
                        ['title' => 'Période d’essai', 'body' => 'Une période d’essai de X mois est prévue (maximum 3 mois, 6 mois pour les cadres — art. 34), au terme de laquelle l’embauche devient définitive.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel est fixé à X FCFA, auquel s’ajoutent les majorations légales (heures supplémentaires, travail de nuit et jours fériés — art. 80).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d’un congé annuel payé de 1,5 jour ouvrable par mois de travail effectif (art. 60).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 15 jours à 3 mois selon l’ancienneté et la catégorie (art. 45).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, pour un motif légalement autorisé par la loi 92-007 (art. 36).'],
                        ['title' => 'Période d’essai', 'body' => 'La période d’essai est fixée à 1 mois par tranche de 6 mois de contrat, dans la limite de 3 mois (art. 34).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X FCFA, assorti des majorations légales applicables (art. 80).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 1,5 jour ouvrable de congé par mois de travail effectif (art. 60).'],
                    ],
                ],
            ],
            // ── Côte d’Ivoire — Loi n° 2015-532 du 20/07/2015 (code du travail) ──
            // CEDEAO. Refonte recente (2015) : c'est la reference du bloc
            // UEMOA avec le Senegal, et la seule a avoir supprime la
            // distinction de duree de preavis par categorie professionnelle.
            'CI' => [
                'legal_references' => [
                    'code' => 'Code du travail ivoirien — loi n° 2015-532 du 20 juillet 2015',
                    'articles' => ['16.6' => 'Période d’essai', '16.2' => 'CDD (durée et renouvellement)', '16.10' => 'Préavis', '25.1' => 'Congé annuel', '22.3' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d’essai : CDI ≤ 1 mois (2 mois pour les agents de maîtrise, 3 mois pour les cadres) ; CDD ≤ 1 mois par année de contrat, renouvelable une fois (art. 16.6).',
                'notice_period' => 'Préavis : 1 mois pour les travailleurs payés à l’heure/jour/mois, 3 mois pour les cadres et agents de maîtrise (art. 16.10).',
                'annual_leave' => 'Congé annuel payé : 2,2 jours ouvrables par mois de travail effectif (26,4 jours/an — art. 25.1), majoré selon l’ancienneté.',
                'overtime' => 'Heures supplémentaires majorées : +15 % (de la 41e à la 46e heure), +50 % (47e-54e), +75 % au-delà ; +50 % de nuit, +100 % les jours fériés et le dimanche — art. 22.3.',
                'minimum_wage' => 'SMIG en vigueur (75 000 FCFA/mois depuis 2023) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNPS : salarié 3,2 % (pension) + 1 % (prestations familiales) ; employeur 7,7 % (pension) + 5,75 % (prestations familiales) + 2 % (accidents du travail) — règles paye IvoryCoastPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI) à compter de la date d’effet, conformément à la loi n° 2015-532 du 20 juillet 2015.'],
                        ['title' => 'Période d’essai', 'body' => 'Une période d’essai de X mois est prévue (1 mois maximum pour les employés, 3 mois pour les cadres — art. 16.6), au terme de laquelle l’embauche devient définitive.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel est fixé à X FCFA, auquel s’ajoutent les majorations légales (heures supplémentaires, travail de nuit, dimanches et jours fériés — art. 22.3).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d’un congé annuel payé de 2,2 jours ouvrables par mois de travail effectif (art. 25.1).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 1 à 3 mois selon la catégorie professionnelle (art. 16.10).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, pour un motif légalement autorisé par la loi 2015-532 (art. 16.2).'],
                        ['title' => 'Période d’essai', 'body' => 'La période d’essai est fixée à 1 mois par année de contrat, renouvelable une fois (art. 16.6).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X FCFA, assorti des majorations légales applicables (art. 22.3).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 2,2 jours ouvrables de congé par mois de travail effectif (art. 25.1).'],
                    ],
                ],
            ],
            // ── France — Code du travail (ord. 2017-1387, loi 2018-217) ──────
            'FR' => [
                'legal_references' => [
                    'code' => 'Code du travail français (partie législative, ordonnance n° 2017-1387 du 22/09/2017)',
                    'articles' => ['L1221-19' => 'Période d’essai', 'L1242-1' => 'CDD (motifs légaux)', 'L1234-1' => 'Préavis', 'L3141-3' => 'Congé annuel', 'L3121-28' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d’essai : 2 mois (employés), 3 mois (techniciens et agents de maîtrise), 4 mois (cadres), renouvelable une fois par accord de branche étendu (art. L1221-19).',
                'notice_period' => 'Préavis (CDI) : 1 mois en principe, 3 mois pour les cadres (usage et conventions collectives — art. L1234-1) ; préavis de licenciement réduit d’un tiers en cas d’ancienneté ≥ 2 ans dans le cadre d’un PSE.',
                'annual_leave' => 'Congé annuel payé : 2,5 jours ouvrables par mois de travail effectif (30 jours ouvrables/an — art. L3141-3), soit 5 semaines.',
                'overtime' => 'Heures supplémentaires majorées : +25 % de la 36e à la 43e heure, +50 % au-delà (accords de branche pouvant prévoir un contingent différent) — art. L3121-28.',
                'minimum_wage' => 'SMIC en vigueur (revalorisé au 1er janvier, indexé sur l’inflation et le salaire moyen ouvrier) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations URSSAF : salarié ≈ 22 % (maladie, vieillesse, chômage, retraite complémentaire, CSG/CRDS) ; employeur ≈ 42 % (allocations familiales, maladie, vieillesse, AT/MP, chômage, formation) — règles paye FrancePayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI) à compter de la date d’effet, régi par le code du travail et la convention collective applicable.'],
                        ['title' => 'Période d’essai', 'body' => 'Une période d’essai de X mois est prévue (2 à 4 mois selon la catégorie — art. L1221-19), renouvelable une fois si un accord de branche étendu le prévoit.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel brut est fixé à X EUR, auquel s’ajoutent les majorations légales des heures supplémentaires (art. L3121-28).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d’un congé annuel payé de 2,5 jours ouvrables par mois de travail effectif, soit 30 jours ouvrables par an (art. L3141-3).'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 1 à 3 mois selon la catégorie professionnelle (art. L1234-1).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, pour l’un des motifs limitativement énumérés à l’article L1242-1 du code du travail.'],
                        ['title' => 'Période d’essai', 'body' => 'La période d’essai est calculée à raison de 1 jour par semaine de contrat, dans la limite de 2 semaines (contrat ≤ 6 mois), 1 mois (contrat > 6 mois) — art. L1242-10.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base brut est fixé à X EUR, assorti des majorations légales applicables (art. L3121-28).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert 2,5 jours ouvrables de congé par mois de travail effectif (art. L3141-3).'],
                    ],
                ],
            ],
            // ── Turquie — İş Kanunu n° 4857 du 22/05/2003 ────────────────────
            // Attention : `annual_leave` n'est PAS proportionnel au mois mais
            // indexé sur l'ANCIENNETE (14/20/26 jours), ce qui interdit de le
            // calculer avec la formule 2,5 j/mois des pays du Maghreb.
            'TR' => [
                'legal_references' => [
                    'code' => 'İş Kanunu (loi sur le travail) n° 4857 du 22 mai 2003',
                    'articles' => ['15' => 'Période d’essai', '11' => 'CDD (durée et renouvellement)', '17' => 'Préavis', '53' => 'Congé annuel (barème par ancienneté)', '41' => 'Heures supplémentaires'],
                ],
                'probation' => 'Période d’essai : 2 mois maximum, renouvelable une fois par accord collectif (4 mois au total) — art. 15. La rupture pendant l’essai dispense de préavis mais ouvre droit aux salaires dus.',
                'notice_period' => 'Préavis : 2 semaines (ancienneté < 6 mois), 4 semaines (6 mois - 1,5 an), 6 semaines (1,5-3 ans), 8 semaines (> 3 ans) — art. 17.',
                'annual_leave' => 'Congé annuel payé INDEXÉ SUR L’ANCIENNETÉ, non proratisable à 2,5 j/mois : 14 jours (1-5 ans), 20 jours (5-15 ans), 26 jours (≥ 15 ans) ; +2 à +6 jours selon l’âge (≥ 50 ans, 18 ans et moins) — art. 53.',
                'overtime' => 'Heures supplémentaires majorées : +25 % (heures normales), +50 % (au-delà de 45 h/sem, jours fériés et repos hebdomadaire) ; plafond annuel 270 heures — art. 41.',
                'minimum_wage' => 'Asgari ücret en vigueur (revalorisé au 1er janvier, ajustement possible en cours d’année) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations SGK : salarié 14 % (maladie + pension) + 1 % (chômage) ; employeur 20,5 % (maladie, pension, AT/MP) + 2 % (chômage) — règles paye TurkeyPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (belirsiz süreli iş sözleşmesi) à compter de la date d’effet, conformément à la loi n° 4857.'],
                        ['title' => 'Période d’essai', 'body' => 'Une période d’essai de X mois est prévue (2 mois maximum, renouvelable une fois par accord collectif — art. 15).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel brut est fixé à X TRY, auquel s’ajoutent les majorations légales des heures supplémentaires (art. 41).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d’un congé annuel payé de 14 jours après 1 an d’ancienneté, 20 jours après 5 ans et 26 jours après 15 ans (art. 53). Ce barème est indexé sur l’ancienneté et ne se proratise pas mensuellement.'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 2 à 8 semaines selon l’ancienneté (art. 17).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (belirli süreli iş sözleşmesi) du X au Y, pour un motif objectif tel qu’un travail déterminé ou une durée déterminée (art. 11).'],
                        ['title' => 'Période d’essai', 'body' => 'La période d’essai est de 2 mois maximum (art. 15) ; un CDD ne peut être renouvelé de manière répétée sans motif objectif, sous peine de requalification en CDI.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base brut est fixé à X TRY, assorti des majorations légales applicables (art. 41).'],
                        ['title' => 'Congés', 'body' => 'Le congé annuel est calculé selon le barème d’ancienneté de l’article 53 (14/20/26 jours).'],
                    ],
                ],
            ],
            // ── Canada — Code canadien du travail (L.R.C. 1985, ch. L-2) ─────
            // Le Canada est un état FEDERAL : les normes du travail varient par
            // province. Ce bundle couvre le regime FEDERAL (entreprises de
            // competence federale) et le socle commun ; le Quebec a ses propres
            // normes (LNT, RLRQ c. N-1.1) et un preavis civil distinct.
            'CA' => [
                'legal_references' => [
                    'code' => 'Code canadien du travail (L.R.C. 1985, ch. L-2), partie III — normes du travail ; Quebec : Loi sur les normes du travail (RLRQ c. N-1.1)',
                    'articles' => ['241' => 'Période d’essai (3 mois)', '230' => 'Préavis de licenciement', '184' => 'Congé annuel', '174' => 'Durée du travail', '251' => 'Congé parental'],
                ],
                'probation' => 'Période d’essai : 3 mois en pratique (le Code fédéral ne fixe pas de durée légale ; l’admissibilité à la plainte pour licenciement injustifié exige 12 mois de service continu — art. 240). Au Québec, la LNT prévoit 3 mois.',
                'notice_period' => 'Préavis de licenciement (fédéral) : 2 semaines (3 mois-1 an de service), 2 semaines + 1 semaine par année complète au-delà de la 1re année (maximum 8 semaines) — art. 230. Au Québec : 1 semaine (3 mois-1 an), 2 semaines (1-5 ans), 4 semaines (5-10 ans), 8 semaines (≥ 10 ans).',
                'annual_leave' => 'Congé annuel : 2 semaines après 1 an de service continu, 3 semaines après 6 ans (fédéral — art. 184). Au Québec : 2 semaines (1-3 ans), 3 semaines (≥ 3 ans). Indemnité de vacances : 4 %, puis 6 % (fédéral) / 4 %, puis 6 % (Québec).',
                'overtime' => 'Heures supplémentaires : heures normales 8 h/jour et 40 h/semaine (fédéral) ; majoration d’au moins 1,5 × le salaire horaire normal — art. 174. Au Québec : 40 h/semaine, majoration de 50 %.',
                'minimum_wage' => 'Salaire minimum FÉDÉRAL en vigueur (revisé annuellement au 1er avril) ; les provinces fixent leur propre taux (Québec, Ontario, etc.) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations : RPC/RRQ (retraite, salarié + employeur), AE (assurance-emploi), RQAP au Québec ; impôts fédéral et provincial retenus à la source — règles paye CanadaPayrollRules.',
                'clauses' => [
                    'cdi' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée à compter de la date d’effet, régi par le Code canadien du travail (partie III) ou, au Québec, par la Loi sur les normes du travail.'],
                        ['title' => 'Période d’essai', 'body' => 'Une période d’essai de 3 mois est prévue, au terme de laquelle l’embauche devient définitive. L’admissibilité à une plainte pour licenciement injustifié exige 12 mois de service continu (art. 240).'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base annuel est fixé à X CAD, versé selon la périodicité convenue, auquel s’ajoutent les majorations légales des heures supplémentaires (art. 174).'],
                        ['title' => 'Congés', 'body' => 'Le salarié bénéficie d’un congé annuel payé de 2 semaines après 1 an de service continu, porté à 3 semaines après 6 ans (art. 184), avec indemnité de vacances de 4 % puis 6 %.'],
                        ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise à un préavis de 2 semaines majoré d’une semaine par année de service au-delà de la première, dans la limite de 8 semaines (art. 230).'],
                    ],
                    'cdd' => [
                        ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée du X au Y, pour un travail dont la fin est prévisible (remplacement, projet déterminé, saison).'],
                        ['title' => 'Période d’essai', 'body' => 'La période d’essai est de 3 mois, sauf durée moindre prévue au contrat.'],
                        ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X CAD, assorti des majorations légales applicables (art. 174).'],
                        ['title' => 'Congés', 'body' => 'Le salarié acquiert une indemnité de vacances de 4 % du salaire brut (art. 184).'],
                    ],
                ],
            ],
            // ══════════════════════════════════════════════════════════════
            // Issue #9053 — extension CEMAC / CEDEAO.
            //
            // Les 9 pays ci-dessous étaient supportés par le moteur de paie
            // (CemacPayrollRules / CedeaoPayrollRules) et par les règles de
            // congés (lot BC-06), mais retombaient sur le bundle GÉNÉRIQUE
            // « Règles locales à préciser » : un contrat généré pour un
            // salarié gabonais ou béninois ne citait donc aucun texte.
            //
            // Les clauses sont produites par `ohadaBundle()` : la famille
            // OHADA partage la même contexture de contrat, seules les
            // références et les paramètres légaux changent. Les valeurs
            // proviennent des règles de congés déjà livrées (BC-06) et des
            // règles de paie du pays — pas d'invention.
            // ══════════════════════════════════════════════════════════════

            // ── Gabon — loi n° 022/2021 du 19/11/2021 (CEMAC) ─────────────
            'GA' => $this->ohadaBundle([
                'code' => 'Code du travail gabonais — loi n° 022/2021 du 19 novembre 2021',
                'articles' => ['185' => 'Congé annuel', '170' => 'Période d\'essai', '180' => 'Préavis', '190' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 170).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 170',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 180.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 180',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de service effectif (24 jours ouvrables/an ; 2,5 j/mois pour les moins de 18 ans — art. 185).',
                'leave_short' => '2 jours ouvrables',
                'leave_article' => 'art. 185',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +30 % (49e-56e), +40 % au-delà ; +50 % de nuit, +60 % les jours fériés — art. 190.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 190',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNSS gabonaise (pension, prestations familiales, risques professionnels) — règles paye CemacPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Congo — loi n° 45-75 du 15/03/1975 (CEMAC) ────────────────
            'CG' => $this->ohadaBundle([
                'code' => 'Code du travail congolais — loi n° 45-75 du 15 mars 1975',
                'articles' => ['16' => 'Période d\'essai', '26' => 'Préavis', '141' => 'Congé annuel', '89' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 16).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 16',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 26.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 26',
                'annual_leave' => 'Congé annuel payé : 2,17 jours ouvrables par mois de travail effectif (26 jours ouvrables/an — art. 141).',
                'leave_short' => '2,17 jours ouvrables',
                'leave_article' => 'art. 141',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +30 % (49e-56e), +40 % au-delà ; +50 % de nuit, +60 % les jours fériés — art. 89.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 89',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNSS congolaise (pension, prestations familiales, risques professionnels) — règles paye CemacPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Tchad — loi n° 038/PR/96 du 11/12/1996 (CEMAC) ────────────
            'TD' => $this->ohadaBundle([
                'code' => 'Code du travail tchadien — loi n° 038/PR/96 du 11 décembre 1996',
                'articles' => ['25' => 'Période d\'essai', '35' => 'Préavis', '212' => 'Congé annuel', '160' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 25).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 25',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 35.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 35',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de travail effectif (24 jours ouvrables/an — art. 212).',
                'leave_short' => '2 jours ouvrables',
                'leave_article' => 'art. 212',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +30 % (49e-56e), +40 % au-delà ; +50 % de nuit, +60 % les jours fériés — art. 160.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 160',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNPS tchadienne (pension, prestations familiales, risques professionnels) — règles paye CemacPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── République centrafricaine — loi n° 09.004 du 29/01/2009 ────
            'CF' => $this->ohadaBundle([
                'code' => 'Code du travail centrafricain — loi n° 09.004 du 29 janvier 2009',
                'articles' => ['20' => 'Période d\'essai', '30' => 'Préavis', '280' => 'Congé annuel', '150' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 20).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 20',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 30.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 30',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de travail effectif (24 jours ouvrables/an, plafond 30 jours — art. 280 s.).',
                'leave_short' => '2 jours ouvrables',
                'leave_article' => 'art. 280 s.',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +30 % (49e-56e), +40 % au-delà ; +50 % de nuit, +60 % les jours fériés — art. 150.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 150',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations CNSS centrafricaine (pension, prestations familiales, risques professionnels) — règles paye CemacPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Guinée équatoriale — loi n° 2/1990 (CEMAC) ────────────────
            'GQ' => $this->ohadaBundle([
                'code' => 'Code du travail équato-guinéen — loi n° 2/1990 du 4 janvier 1990',
                'articles' => ['14' => 'Période d\'essai', '24' => 'Préavis', '60' => 'Congé annuel', '45' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 14).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 14',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 24.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 24',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de travail effectif (24 jours ouvrables/an — art. 60).',
                'leave_short' => '2 jours ouvrables',
                'leave_article' => 'art. 60',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +30 % (49e-56e), +40 % au-delà ; +50 % de nuit, +60 % les jours fériés — art. 45.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 45',
                'minimum_wage' => 'SMIG en vigueur (décret annuel) — base de référence pour le salaire de base.',
                'social_security' => 'Cotisations INSESO (pension, prestations familiales, risques professionnels) — règles paye CemacPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Bénin — loi n° 98-004 du 27/01/1998 (CEDEAO) ──────────────
            'BJ' => $this->ohadaBundle([
                'code' => 'Code du travail béninois — loi n° 98-004 du 27 janvier 1998',
                'articles' => ['12' => 'Période d\'essai', '22' => 'Préavis', '158' => 'Congé annuel', '100' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 12).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 12',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 22.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 22',
                'annual_leave' => 'Congé annuel payé : 2 jours ouvrables par mois de travail effectif (24 jours ouvrables/an — art. 158).',
                'leave_short' => '2 jours ouvrables',
                'leave_article' => 'art. 158',
                'overtime' => 'Heures supplémentaires majorées : +12 % (41e-48e heure), +35 % (49e-56e), +50 % au-delà ; +50 % de nuit, +100 % les jours fériés — art. 100.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 100',
                'minimum_wage' => 'SMIG en vigueur (52 000 XOF/mois — règles paye CedeaoPayrollRules).',
                'social_security' => 'Cotisations CNSS béninoise (pension, prestations familiales, risques professionnels) — règles paye CedeaoPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Mali — loi n° 92-020 du 23/09/1992 (CEDEAO) ───────────────
            'ML' => $this->ohadaBundle([
                'code' => 'Code du travail malien — loi n° 92-020 du 23 septembre 1992',
                'articles' => ['12' => 'Période d\'essai', '22' => 'Préavis', '148' => 'Congé annuel', '100' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 12).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 12',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 22.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 22',
                'annual_leave' => 'Congé annuel payé : 2,5 jours par mois de travail effectif, jours non ouvrables compris (30 jours/an — art. L.148 s.).',
                'leave_short' => '2,5 jours',
                'leave_article' => 'art. L.148 s.',
                'overtime' => 'Heures supplémentaires majorées : +10 % (41e-48e heure), +35 % (49e-56e), +50 % au-delà ; +50 % de nuit, +100 % les jours fériés — art. 100.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 100',
                'minimum_wage' => 'SMIG en vigueur (40 000 XOF/mois — règles paye CedeaoPayrollRules).',
                'social_security' => 'Cotisations INPS malienne (pension, prestations familiales, risques professionnels) — règles paye CedeaoPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Togo — loi n° 2021-012 du 18/06/2021 (CEDEAO) ─────────────
            'TG' => $this->ohadaBundle([
                'code' => 'Code du travail togolais — loi n° 2021-012 du 18 juin 2021',
                'articles' => ['12' => 'Période d\'essai', '22' => 'Préavis', '160' => 'Congé annuel', '100' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 12).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 12',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 22.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 22',
                'annual_leave' => 'Congé annuel payé : 2,5 jours ouvrables par mois de travail effectif (30 jours ouvrables/an — art. 160).',
                'leave_short' => '2,5 jours ouvrables',
                'leave_article' => 'art. 160',
                'overtime' => 'Heures supplémentaires majorées : +20 % (41e-48e heure), +35 % (49e-56e), +50 % au-delà ; +50 % de nuit, +100 % les jours fériés — art. 100.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 100',
                'minimum_wage' => 'SMIG en vigueur (52 500 XOF/mois depuis le 01/01/2023 — règles paye CedeaoPayrollRules).',
                'social_security' => 'Cotisations CNSS togolaise (pension, prestations familiales, risques professionnels) — règles paye CedeaoPayrollRules.',
                'currency' => 'FCFA',
            ]),

            // ── Niger — loi n° 2012-45 du 25/09/2012 (CEDEAO) ─────────────
            'NE' => $this->ohadaBundle([
                'code' => 'Code du travail nigérien — loi n° 2012-45 du 25 septembre 2012',
                'articles' => ['12' => 'Période d\'essai', '22' => 'Préavis', '116' => 'Congé annuel', '100' => 'Heures supplémentaires'],
                'probation' => 'Période d\'essai : CDI ≤ 3 mois (6 mois pour les cadres) ; CDD ≤ 1 mois par tranche de 6 mois (art. 12).',
                'probation_short' => 'maximum 3 mois, 6 mois pour les cadres — art. 12',
                'notice_period' => 'Préavis : 8 jours (ancienneté < 1 an), 1 mois (1-5 ans), 2 mois (≥ 5 ans) — art. 22.',
                'notice_short' => '8 jours à 2 mois selon l\'ancienneté — art. 22',
                'annual_leave' => 'Congé annuel payé : 2,5 jours calendaires par mois de travail effectif (30 jours calendaires/an — art. 116 s.).',
                'leave_short' => '2,5 jours calendaires',
                'leave_article' => 'art. 116 s.',
                'overtime' => 'Heures supplémentaires majorées : +10 % (41e-48e heure), +35 % (49e-56e), +50 % au-delà ; +50 % de nuit, +100 % les jours fériés — art. 100.',
                'overtime_short' => 'heures supplémentaires, travail de nuit et jours fériés — art. 100',
                'minimum_wage' => 'SMIG en vigueur (30 047 XOF/mois — règles paye CedeaoPayrollRules).',
                'social_security' => 'Cotisations CNSS nigérienne (pension, prestations familiales, risques professionnels) — règles paye CedeaoPayrollRules.',
                'currency' => 'FCFA',
            ]),
        ];
    }

    /**
     * Issue #9053 — gabarit de bundle pour la famille OHADA (CEMAC + CEDEAO).
     *
     * Les contrats de cette famille partagent la même contexture : seules les
     * références légales et les paramètres (essai, préavis, congés, heures
     * supplémentaires, devise) changent. Factoriser évite de recopier 9 fois
     * les mêmes clauses — et donc de les voir diverger à la première
     * correction.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    private function ohadaBundle(array $meta): array
    {
        $currency = $meta['currency'];
        $law = $meta['code'];

        return [
            'legal_references' => [
                'code' => $law,
                'articles' => $meta['articles'],
            ],
            'probation' => $meta['probation'],
            'notice_period' => $meta['notice_period'],
            'annual_leave' => $meta['annual_leave'],
            'overtime' => $meta['overtime'],
            'minimum_wage' => $meta['minimum_wage'],
            'social_security' => $meta['social_security'],
            'clauses' => [
                'cdi' => [
                    ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée indéterminée (CDI) à compter de la date d\'effet, conformément au '.$law.'.'],
                    ['title' => 'Période d\'essai', 'body' => 'Une période d\'essai de X mois est prévue ('.$meta['probation_short'].'), au terme de laquelle l\'embauche devient définitive.'],
                    ['title' => 'Rémunération', 'body' => 'Le salaire de base mensuel est fixé à X '.$currency.', auquel s\'ajoutent les majorations légales ('.$meta['overtime_short'].').'],
                    ['title' => 'Congés', 'body' => 'Le salarié bénéficie d\'un congé annuel payé de '.$meta['leave_short'].' par mois de travail effectif ('.$meta['leave_article'].').'],
                    ['title' => 'Préavis', 'body' => 'La rupture du contrat est soumise au préavis légal de '.$meta['notice_short'].'.'],
                ],
                'cdd' => [
                    ['title' => 'Nature du contrat', 'body' => 'Le présent contrat est conclu pour une durée déterminée (CDD) du X au Y, pour un motif légalement autorisé par le '.$law.'.'],
                    ['title' => 'Période d\'essai', 'body' => 'La période d\'essai est fixée à 1 mois par tranche de 6 mois de contrat, dans la limite de 3 mois ('.$meta['probation_short'].').'],
                    ['title' => 'Rémunération', 'body' => 'Le salaire de base est fixé à X '.$currency.', assorti des majorations légales applicables ('.$meta['overtime_short'].').'],
                    ['title' => 'Congés', 'body' => 'Le salarié acquiert '.$meta['leave_short'].' de congé par mois de travail effectif ('.$meta['leave_article'].').'],
                ],
            ],
        ];
    }

    /**
     * Bundle générique (pays hors DZ/MA/TN/SN/CM/CI/FR/TR/CA) : clauses
     * neutres sans référence légale — le contrat reste rédigé par l'entreprise.
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
