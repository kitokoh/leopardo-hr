<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll\Golden;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\CountryRules\CanadaPayrollRules;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Golden tests Canada PROVINCIAL (QC + ON) — issue #7933, lot BC-07
 * « conformité légale multi-marchés » (épic #7924), constitution §III.
 *
 * Méthodologie : chaque valeur est CALCULÉE À LA MAIN
 * (docs/payroll/CA_COMPLIANCE.md §1bis/§2bis/§6), pas reprise du code — une
 * divergence = régression de conformité.
 *
 * Règles (pilot, 2026) :
 *  - QUÉBEC : RRQ/QPP 6,30 % (base 5,3 % + supplémentaire 1 %) sur
 *    (min(brut, YMPE mensuel $6 216,67) − exemption $291,67), RRQ2 4 % sur
 *    [$6 216,67, $7 083,33], AE taux RÉDUIT QC 1,30 % (patronal 1,82 %) sur
 *    le brut plafonné à $5 741,67, RQAP 0,430 % (patronal 0,602 %) plafonné
 *    à $8 583,33 ($103 000/an). Impôt = fédéral (barème 2026 − crédit BPA
 *    $16 452 × 14 %) × 0,835 (abattement du Québec 16,5 %) + barème QC 2026
 *    (14 % ≤ 54 345, 19 % ≤ 108 680, 24 % ≤ 132 245, 25,75 % au-delà) −
 *    crédit personnel QC $18 952 × 14 % ($2 653,28), le tout / 12.
 *  - ONTARIO : cotisations FÉDÉRALES inchangées (CPP/CPP2/EI). Impôt =
 *    fédéral (sans abattement) + barème ON 2026 (5,05 % ≤ 53 891,
 *    9,15 % ≤ 107 785, 11,16 % ≤ 150 000, 12,16 % ≤ 220 000, 13,16 %
 *    au-delà) − crédit personnel ON $12 989 × 5,05 % ($655,94) + SURTAXE
 *    (20 % de l'impôt ON de base > $5 818, +36 % > $7 446), le tout / 12.
 */
class GoldenCaProvincialPayrollTest extends TestCase
{
    use RefreshTenantDatabase;

    private function rules(string $province): CanadaPayrollRules
    {
        return (new CanadaPayrollRules)->forProvince($province);
    }

    public function test_golden_qc_bas_salaire_2000(): void
    {
        // Brut $2 000 (QC) :
        //   RRQ = 6,30 % × (2 000 − 291,67) = 6,30 % × 1 708,33 = 107,63
        //   AE QC = 1,30 % × 2 000 = 26,00 · RQAP = 0,430 % × 2 000 = 8,60
        //   → salarié 107,63 + 26,00 + 8,60 = 142,23
        //   → patron 107,63 + 1,82 % × 2 000 (36,40) + 0,602 % × 2 000 (12,04) = 156,07
        //   IR : assiette 1 857,77 → annuel 22 293,24
        //     fédéral 14 % = 3 121,05 − BPA 2 303,28 = 817,77 ; × 0,835 = 682,84
        //     QC 14 % = 3 121,05 − crédit 2 653,28 = 467,77
        //     total (682,84 + 467,77) / 12 = 95,88
        //   Net = 1 857,77 − 95,88 = 1 761,89
        $charges = $this->rules('QC')->calculateSocialCharges(2000.0);
        $this->assertSame(142.23, $charges['employee']);
        $this->assertSame(156.07, $charges['employer']);

        $tax = $this->rules('QC')->calculateIncomeTax(2000.0 - $charges['employee']);
        $this->assertSame(95.88, $tax);
        $this->assertSame(1761.89, round(2000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_qc_salaire_moyen_6000(): void
    {
        // Brut $6 000 (QC — AE plafonnée à la MIE mensuelle) :
        //   RRQ = 6,30 % × (6 000 − 291,67) = 6,30 % × 5 708,33 = 359,63
        //   AE QC = 1,30 % × 5 741,67 = 74,64 · RQAP = 0,430 % × 6 000 = 25,80
        //   → salarié 460,07 · patron = 359,63 + 1,82 % × 5 741,67 (104,50)
        //     + 0,602 % × 6 000 (36,12) = 500,24
        //   IR : assiette 5 539,93 → annuel 66 479,16
        //     fédéral 8 193,22 + 7 956,16 × 20,5 % (1 631,01) = 9 824,23
        //       − 2 303,28 = 7 520,95 ; × 0,835 = 6 280,00
        //     QC 7 608,30 + 12 134,16 × 19 % (2 305,49) = 9 913,79
        //       − 2 653,28 = 7 260,51
        //     total (6 280,00 + 7 260,51) / 12 = 1 128,38
        //   Net = 5 539,93 − 1 128,38 = 4 411,55
        $charges = $this->rules('QC')->calculateSocialCharges(6000.0);
        $this->assertSame(460.07, $charges['employee']);
        $this->assertSame(500.24, $charges['employer']);

        $tax = $this->rules('QC')->calculateIncomeTax(6000.0 - $charges['employee']);
        $this->assertSame(1128.38, $tax);
        $this->assertSame(4411.55, round(6000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_qc_rrq2_et_plafond_rqap_9000(): void
    {
        // Brut $9 000 (QC — RRQ/RRQ2 plafonnés, RQAP plafonné à $8 583,33) :
        //   RRQ max = 6,30 % × (6 216,67 − 291,67) = 6,30 % × 5 925,00 = 373,28
        //   RRQ2 = 4 % × (7 083,33 − 6 216,67) = 4 % × 866,66 = 34,67
        //   AE QC = 1,30 % × 5 741,67 = 74,64 · RQAP = 0,430 % × 8 583,33 = 36,91
        //   → salarié 519,49 · patron = 373,28 + 34,67 + 104,50
        //     + 0,602 % × 8 583,33 (51,67) = 564,11
        //   IR : assiette 8 480,51 → annuel 101 766,12
        //     fédéral 8 193,22 + 43 243,12 × 20,5 % (8 864,84) = 17 058,06
        //       − 2 303,28 = 14 754,78 ; × 0,835 = 12 320,24
        //     QC 7 608,30 + 47 421,12 × 19 % (9 010,01) = 16 618,31
        //       − 2 653,28 = 13 965,03
        //     total (12 320,24 + 13 965,03) / 12 = 2 190,44
        //   Net = 8 480,51 − 2 190,44 = 6 290,07
        $charges = $this->rules('QC')->calculateSocialCharges(9000.0);
        $this->assertSame(519.49, $charges['employee']);
        $this->assertSame(564.11, $charges['employer']);

        $tax = $this->rules('QC')->calculateIncomeTax(9000.0 - $charges['employee']);
        $this->assertSame(2190.44, $tax);
        $this->assertSame(6290.07, round(9000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_qc_haut_salaire_15000(): void
    {
        // Brut $15 000 (QC — toutes cotisations plafonnées) :
        //   salarié = 373,28 + 34,67 + 74,64 + 36,91 = 519,49 · patron 564,11
        //   IR : assiette 14 480,51 → annuel 173 766,12
        //     fédéral 8 193,22 + 11 997,01 + 56 721,12 × 26 % (14 747,49)
        //       = 34 937,72 − 2 303,28 = 32 634,44 ; × 0,835 = 27 249,76
        //     QC 7 608,30 + 10 323,65 + 5 655,60 + 41 521,12 × 25,75 %
        //       (10 691,69) = 34 279,24 − 2 653,28 = 31 625,96
        //     total (27 249,76 + 31 625,96) / 12 = 4 906,31
        //   Net = 14 480,51 − 4 906,31 = 9 574,20
        $charges = $this->rules('QC')->calculateSocialCharges(15000.0);
        $this->assertSame(519.49, $charges['employee']);
        $this->assertSame(564.11, $charges['employer']);

        $tax = $this->rules('QC')->calculateIncomeTax(15000.0 - $charges['employee']);
        $this->assertSame(4906.31, $tax);
        $this->assertSame(9574.20, round(15000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_on_bas_salaire_2000(): void
    {
        // Brut $2 000 (ON — cotisations FÉDÉRALES : CPP/EI) :
        //   CPP = 5,95 % × 1 708,33 = 101,65 · EI = 1,63 % × 2 000 = 32,60
        //   → salarié 134,25 · patron = 101,65 + 2,282 % × 2 000 (45,64) = 147,29
        //   IR : assiette 1 865,75 → annuel 22 389,00
        //     fédéral 14 % = 3 134,46 − 2 303,28 = 831,18
        //     ON 5,05 % = 1 130,64 − crédit 655,94 = 474,70 (< 5 818 : pas de surtaxe)
        //     total (831,18 + 474,70) / 12 = 108,82
        //   Net = 1 865,75 − 108,82 = 1 756,93
        $charges = $this->rules('ON')->calculateSocialCharges(2000.0);
        $this->assertSame(134.25, $charges['employee']);
        $this->assertSame(147.29, $charges['employer']);

        $tax = $this->rules('ON')->calculateIncomeTax(2000.0 - $charges['employee']);
        $this->assertSame(108.82, $tax);
        $this->assertSame(1756.93, round(2000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_on_salaire_moyen_6000(): void
    {
        // Brut $6 000 (ON — cotisations identiques au golden fédéral 6000) :
        //   salarié 433,24 · patron 470,67
        //   IR : assiette 5 566,76 → annuel 66 801,12
        //     fédéral 8 193,22 + 8 278,12 × 20,5 % (1 697,01) = 9 890,23
        //       − 2 303,28 = 7 586,95
        //     ON 2 721,50 + 12 910,12 × 9,15 % (1 181,28) = 3 902,78
        //       − 655,94 = 3 246,83 (< 5 818 : pas de surtaxe)
        //     total (7 586,95 + 3 246,83) / 12 = 902,82
        //   Net = 5 566,76 − 902,82 = 4 663,94
        $charges = $this->rules('ON')->calculateSocialCharges(6000.0);
        $this->assertSame(433.24, $charges['employee']);
        $this->assertSame(470.67, $charges['employer']);

        $tax = $this->rules('ON')->calculateIncomeTax(6000.0 - $charges['employee']);
        $this->assertSame(902.82, $tax);
        $this->assertSame(4663.94, round(6000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_on_surtaxe_premier_palier_9000(): void
    {
        // Brut $9 000 (ON — surtaxe palier 1 SEUL) :
        //   cotisations plafonnées : salarié 480,79 (CPP 352,54 + CPP2 34,67
        //   + EI 93,59) · patron 518,23
        //   IR : assiette 8 519,21 → annuel 102 230,52
        //     fédéral 8 193,22 + 43 707,52 × 20,5 % (8 960,04) = 17 153,26
        //       − 2 303,28 = 14 849,98
        //     ON base 2 721,50 + 48 339,52 × 9,15 % (4 423,07) = 7 144,57
        //       − 655,94 = 6 488,62
        //     surtaxe = 20 % × (6 488,62 − 5 818) = 134,12 (6 488,62 < 7 446 :
        //       pas de palier 2) → ON total 6 622,74
        //     total (14 849,98 + 6 622,74) / 12 = 1 789,39
        //   Net = 8 519,21 − 1 789,39 = 6 729,82
        $charges = $this->rules('ON')->calculateSocialCharges(9000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules('ON')->calculateIncomeTax(9000.0 - $charges['employee']);
        $this->assertSame(1789.39, $tax);
        $this->assertSame(6729.82, round(9000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_golden_on_haut_salaire_15000_surtaxe_deux_paliers(): void
    {
        // Brut $15 000 (ON — surtaxe 2 paliers) :
        //   salarié 480,79 · patron 518,23 (plafonnés)
        //   IR : assiette 14 519,21 → annuel 174 230,52
        //     fédéral 8 193,22 + 11 997,01 + 57 185,52 × 26 % (14 868,24)
        //       = 35 058,47 − 2 303,28 = 32 755,19
        //     ON base 2 721,50 + 4 931,30 + 4 711,19 + 24 230,52 × 12,16 %
        //       (2 946,43) = 15 310,42 − 655,94 = 14 654,48
        //     surtaxe = 20 % × (14 654,48 − 5 818) = 1 767,29
        //       + 36 % × (14 654,48 − 7 446) = 2 595,05 → ON total 19 016,82
        //     total (32 755,19 + 19 016,82) / 12 = 4 314,33
        //   Net = 14 519,21 − 4 314,33 = 10 204,88
        $charges = $this->rules('ON')->calculateSocialCharges(15000.0);
        $this->assertSame(480.79, $charges['employee']);
        $this->assertSame(518.23, $charges['employer']);

        $tax = $this->rules('ON')->calculateIncomeTax(15000.0 - $charges['employee']);
        $this->assertSame(4314.33, $tax);
        $this->assertSame(10204.88, round(15000.0 - $charges['employee'] - $tax, 2));
    }

    public function test_unknown_province_throws_no_silent_fallback(): void
    {
        // #7933 — AUCUN fallback silencieux de province : code inconnu = exception.
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown Canadian province/territory code [XX]');

        (new CanadaPayrollRules)->forProvince('XX');
    }

    public function test_company_metadata_province_override_scopes_quebec_rules(): void
    {
        // #7933 — override entreprise : company.metadata.payroll_ca_province
        // = 'QC' → forCompany() applique les régimes québécois (mêmes
        // montants que test_golden_qc_salaire_moyen_6000).
        $company = Company::factory()->create([
            'country' => 'CA',
            'metadata' => [CanadaPayrollRules::COMPANY_PROVINCE_METADATA_KEY => 'QC'],
        ]);

        $rules = (new CanadaPayrollRules)->forCompany($company->id);
        $charges = $rules->calculateSocialCharges(6000.0);
        $this->assertSame(460.07, $charges['employee']);
        $this->assertSame(500.24, $charges['employer']);

        // Sans metadata : comportement fédéral-seul documenté.
        $federalCompany = Company::factory()->create(['country' => 'CA']);
        $federal = (new CanadaPayrollRules)->forCompany($federalCompany->id);
        $this->assertSame(433.24, $federal->calculateSocialCharges(6000.0)['employee']);
    }

    public function test_provincial_metadata_and_contributions(): void
    {
        $qc = $this->rules('QC');
        $on = $this->rules('ON');
        $federal = new CanadaPayrollRules;

        // La province participe à l'empreinte de version des règles.
        $this->assertStringStartsWith('v1-', $qc->rulesVersion());
        $this->assertStringEndsWith('-qc', $qc->rulesVersion());
        $this->assertStringEndsWith('-on', $on->rulesVersion());
        $this->assertStringEndsWith('-federal', $federal->rulesVersion());
        $this->assertNotSame($qc->rulesVersion(), $on->rulesVersion());

        // QC : RRQ/RRQ2/AE réduit/RQAP à la place de CPP/CPP2/AE standard.
        $qcCodes = array_column($qc->socialContributions(), 'code');
        $this->assertSame(
            ['QPP_CA_EMP', 'QPP_CA_PAT', 'QPP2_CA_EMP', 'QPP2_CA_PAT', 'EI_QC_EMP', 'EI_QC_PAT', 'QPIP_QC_EMP', 'QPIP_QC_PAT'],
            $qcCodes
        );
        $onCodes = array_column($on->socialContributions(), 'code');
        $this->assertContains('CPP_CA_EMP', $onCodes);
        $this->assertNotContains('QPP_CA_EMP', $onCodes);

        // Confidence reste pilot ; le référentiel est inchangé ; les
        // provinces non modélisées restent documentées fédéral-seul.
        $this->assertSame('pilot', $qc->confidenceLevel());
        $this->assertSame('docs/payroll/CA_COMPLIANCE.md', $qc->complianceSource());
        $this->assertStringContainsString('QC and ON ONLY', $qc->complianceWarning());
        $bc = $this->rules('BC');
        $this->assertSame(433.24, $bc->calculateSocialCharges(6000.0)['employee']);
        $this->assertSame(0.0, $bc->calculateIncomeTax(0.0));
    }
}
