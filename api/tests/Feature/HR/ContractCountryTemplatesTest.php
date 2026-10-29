<?php

declare(strict_types=1);

namespace Tests\Feature\HR;

use App\Modules\HR\Infrastructure\Services\ContractCountryTemplates;
use Tests\TestCase;

/**
 * #7940 (V2-6) — modeles de contrat CEMAC / CEDEAO / France / Turquie / Canada.
 *
 * Ce qui est verifie ici n'est pas la redaction (elle releve de la revue
 * legale) mais les PIEGES de chaque droit national, ceux qu'une reprise
 * mecanique du modele algerien ferait tomber :
 *
 *   - TR : le conge annuel est indexe sur l'ANCIENNETE (14 / 20 / 26 j), il ne
 *     se proratise pas a 2,5 j/mois. Le calculer comme au Maghreb sous-estime
 *     le droit du salarie de pres d'un tiers apres 5 ans.
 *   - CA : le Canada est un etat FEDERAL, le regime couvert est le federal ;
 *     le preavis n'est pas un mois fixe mais 2 semaines + 1 par annee.
 *   - FR : la periode d'essai est fonction de la CATEGORIE (2/3/4 mois), pas
 *     d'une duree unique.
 *   - CI / CM : le preavis depend de l'anciennete (CI) ou de la categorie (CM),
 *     pas d'un bareme unique.
 */
class ContractCountryTemplatesTest extends TestCase
{
    public function test_the_five_new_countries_are_supported(): void
    {
        $templates = new ContractCountryTemplates;

        foreach (['CM', 'CI', 'FR', 'TR', 'CA'] as $country) {
            $this->assertTrue(
                $templates->supports($country),
                sprintf('%s doit etre supporte (avant #7940, il retombait sur le bundle generique).', $country),
            );
        }
    }

    public function test_previous_countries_still_supported(): void
    {
        // Non-regression : les 4 pays d'origine ne doivent pas avoir ete
        // perdus en ajoutant les 5 nouveaux.
        $templates = new ContractCountryTemplates;

        foreach (['DZ', 'MA', 'TN', 'SN'] as $country) {
            $this->assertTrue($templates->supports($country), $country.' doit rester supporte');
        }
    }

    /**
     * Issue #9053 — les 9 pays CEMAC/CEDEAO du moteur de paie ne doivent plus
     * retomber sur le bundle generique « Regles locales a preciser ».
     */
    public function test_cemac_and_cedeao_countries_are_supported(): void
    {
        $templates = new ContractCountryTemplates;

        foreach (['GA', 'CG', 'TD', 'CF', 'GQ', 'BJ', 'ML', 'TG', 'NE'] as $country) {
            $this->assertTrue(
                $templates->supports($country),
                sprintf('%s doit etre supporte (avant #9053, il retombait sur le bundle generique).', $country),
            );
        }
    }

    /**
     * Issue #9053 — chaque bundle CEMAC/CEDEAO cite son propre texte de loi et
     * porte ses parametres reels : ni le texte algerien, ni le placeholder
     * generique ne doivent subsister.
     */
    public function test_cemac_and_cedeao_bundles_cite_their_own_law(): void
    {
        $templates = new ContractCountryTemplates;

        $expected = [
            'GA' => 'loi n° 022/2021',
            'CG' => 'loi n° 45-75',
            'TD' => 'loi n° 038/PR/96',
            'CF' => 'loi n° 09.004',
            'GQ' => 'loi n° 2/1990',
            'BJ' => 'loi n° 98-004',
            'ML' => 'loi n° 92-020',
            'TG' => 'loi n° 2021-012',
            'NE' => 'loi n° 2012-45',
        ];

        foreach ($expected as $country => $law) {
            $bundle = $templates->forCountry($country);

            $this->assertStringContainsString(
                $law,
                $bundle['legal_references']['code'],
                $country.' doit citer son propre texte de loi',
            );
            $this->assertStringNotContainsString(
                'Règles locales à préciser',
                $bundle['legal_references']['code'],
                $country.' ne doit plus retomber sur le bundle generique',
            );
            $this->assertStringNotContainsString(
                'loi 90-11',
                $bundle['legal_references']['code'],
                $country.' ne doit pas heriter du texte algerien',
            );
            // `forCountry()` retourne le jeu de clauses DEJA resolu pour le
            // type de contrat demande (cdi par defaut), pas la carte cdi/cdd.
            $this->assertNotEmpty($bundle['clauses'], $country.' doit avoir des clauses CDI');
            $this->assertNotEmpty(
                $templates->forCountry($country, 'cdd')['clauses'],
                $country.' doit avoir des clauses CDD',
            );
        }
    }

    /**
     * Issue #9053 — les conges annuels des bundles CEMAC/CEDEAO reprennent les
     * valeurs deja livrees par le lot BC-06 (regles de conges), pas une valeur
     * generique : 24 j pour GA/TD/CF/GQ/BJ, 26 j pour CG, 30 j pour ML/TG/NE.
     */
    public function test_cemac_and_cedeao_leave_matches_the_leave_rules(): void
    {
        $templates = new ContractCountryTemplates;

        $expectedDays = [
            'GA' => '24 jours',
            'CG' => '26 jours',
            'TD' => '24 jours',
            'CF' => '24 jours',
            'GQ' => '24 jours',
            'BJ' => '24 jours',
            'ML' => '30 jours',
            'TG' => '30 jours',
            'NE' => '30 jours',
        ];

        foreach ($expectedDays as $country => $days) {
            $bundle = $templates->forCountry($country);

            $this->assertStringContainsString(
                $days,
                $bundle['annual_leave'],
                $country.' doit reprendre le volume de conges du lot BC-06',
            );
        }
    }

    public function test_turkey_leave_is_seniority_based_not_monthly_prorated(): void
    {
        // Le piege decisif : en Turquie le conge est un bareme d'anciennete.
        // Si le bundle se contentait d'un « 2,5 jours par mois », un salarie
        // de 5 ans aurait 30 jours au lieu de 20 — et le generateur de
        // contrat afficherait un droit qui n'existe pas.
        $bundle = (new ContractCountryTemplates)->forCountry('TR');

        $this->assertStringContainsString('14 jours', $bundle['annual_leave']);
        $this->assertStringContainsString('20 jours', $bundle['annual_leave']);
        $this->assertStringContainsString('26 jours', $bundle['annual_leave']);
        $this->assertStringContainsString(
            'ANCIENNETÉ',
            $bundle['annual_leave'],
            'Le bareme doit se declarer indexe sur l anciennete, pas sur les mois travailles.',
        );
        $this->assertStringNotContainsString(
            '2,5 jour',
            $bundle['annual_leave'],
            'Un ratissage a 2,5 j/mois contredirait le bareme turc.',
        );
    }

    public function test_canada_bundle_covers_federal_regime_and_flags_provincial_difference(): void
    {
        $bundle = (new ContractCountryTemplates)->forCountry('CA');

        // Le Canada n'a pas un droit du travail unique : annoncer un regime
        // federal sans nommer la difference quebecoise ferait signer un
        // contrat ontarien avec des normes qui ne s'y appliquent pas.
        $this->assertStringContainsString('L.R.C. 1985', $bundle['legal_references']['code']);
        $this->assertStringContainsString('Quebec', $bundle['legal_references']['code']);

        // Preavis federal : 2 semaines + 1 par annee, plafonne a 8 semaines.
        $this->assertStringContainsString('8 semaines', $bundle['notice_period']);
        // Le preavis se compte en SEMAINES. Attention : « 3 mois-1 an de
        // service » apparait legitimement comme SEUIL d'admissibilite — c'est
        // une duree de preavis exprimee en mois qui serait le reflexe francais
        // a ecarter, pas toute occurrence de « 3 mois ».
        $this->assertStringContainsString('semaines', $bundle['notice_period']);
        $this->assertStringNotContainsString(
            '1 à 3 mois',
            $bundle['notice_period'],
            'Un preavis « 1 a 3 mois » est le bareme francais, il n a pas cours ici.',
        );
    }

    public function test_france_probation_depends_on_category(): void
    {
        $bundle = (new ContractCountryTemplates)->forCountry('FR');

        // 2 / 3 / 4 mois selon employe / technicien / cadre.
        $this->assertStringContainsString('2 mois', $bundle['probation']);
        $this->assertStringContainsString('3 mois', $bundle['probation']);
        $this->assertStringContainsString('4 mois', $bundle['probation']);
        $this->assertStringContainsString('L1221-19', implode(' ', array_keys($bundle['legal_references']['articles'])));
    }

    public function test_ivory_coast_notice_is_category_based(): void
    {
        $bundle = (new ContractCountryTemplates)->forCountry('CI');

        $this->assertStringContainsString('loi n° 2015-532', $bundle['legal_references']['code']);
        $this->assertStringContainsString('3 mois', $bundle['notice_period']);
        $this->assertStringContainsString('agents de maîtrise', $bundle['notice_period']);
    }

    public function test_cameroon_is_referenced_as_cemac_basis(): void
    {
        $bundle = (new ContractCountryTemplates)->forCountry('CM');

        $this->assertStringContainsString('92-007', $bundle['legal_references']['code']);
        $this->assertStringContainsString('CNPS', $bundle['social_security']);
        // `forCountry()` renvoie deja le jeu de clauses SELECTIONNE (ici CDI),
        // pas la carte <type> => clauses.
        $this->assertStringContainsString('FCFA', $bundle['clauses'][2]['body']);
    }

    public function test_every_new_country_has_both_contract_types(): void
    {
        $templates = new ContractCountryTemplates;

        foreach (['CM', 'CI', 'FR', 'TR', 'CA'] as $country) {
            foreach (['cdi', 'cdd'] as $type) {
                $bundle = $templates->forCountry($country, $type);
                $this->assertNotEmpty(
                    $bundle['clauses'],
                    sprintf('%s/%s doit porter des clauses.', $country, $type),
                );
                $this->assertSame($type, $bundle['contract_type']);
            }
        }
    }

    public function test_every_new_country_cites_a_real_legal_source(): void
    {
        $templates = new ContractCountryTemplates;

        foreach (['CM', 'CI', 'FR', 'TR', 'CA'] as $country) {
            $bundle = $templates->forCountry($country);
            $this->assertNotSame(
                'Règles locales à préciser',
                $bundle['legal_references']['code'],
                sprintf('%s doit citer son texte de reference, pas le bundle generique.', $country),
            );
            $this->assertNotEmpty(
                $bundle['legal_references']['articles'],
                sprintf('%s doit citer au moins un article.', $country),
            );
        }
    }

    public function test_unknown_country_still_falls_back_to_generic_bundle(): void
    {
        // Non-regression #6671 : un pays hors liste ne doit pas lever, il
        // retombe sur des clauses neutres.
        $bundle = (new ContractCountryTemplates)->forCountry('ZZ');

        $this->assertSame('ZZ', $bundle['country']);
        $this->assertSame('Règles locales à préciser', $bundle['legal_references']['code']);
        $this->assertNotEmpty($bundle['clauses']);
    }
}
