<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * #9045 — mentions légales de bulletin par pays.
 *
 * Le lot BC-07 avait livré les référentiels documentaires
 * (`docs/payroll/BULLETIN_{FR,TR,CA,CM,CI,SN}_MENTIONS.md`) mais laissé le
 * générateur PDF inchangé : `COUNTRY_LEGAL` ne couvrait pas CM/CI et les
 * identifiants employeur restaient le jeu DZ quel que soit le pays. Ces tests
 * verrouillent la couverture réelle, pays par pays.
 */
class PaySlipCountryLegalMentionsTest extends TestCase
{
    use RefreshTenantDatabase;

    private function makeSlip(Company $company, Employee $employee, string $country): PaySlip
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => $country,
            'status' => 'validated',
        ]);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 500000,
            'total_deductions' => 100000,
            'net_salary' => 400000,
            'status' => 'validated',
        ]);

        return $slip;
    }

    /**
     * Chaque pays du référentiel doit produire une mention légale non vide :
     * un bulletin sans mention pays est un bulletin non conforme.
     */
    public function test_every_referenced_country_renders_a_legal_mention(): void
    {
        // Jeton attendu par juridiction : la mention doit être celle du pays,
        // pas un texte générique. FR/TR/CA n'emploient pas la formule
        // « Code du travail » (droit du travail distinct), d'où un jeton propre.
        $expected = [
            'CM' => 'camerounais',
            'GA' => 'gabonais',
            'CG' => 'congolais',
            'TD' => 'tchadien',
            'CF' => 'centrafricain',
            'GQ' => 'équato-guinéen',
            'CI' => 'ivoirien',
            'BF' => 'burkinabè',
            'ML' => 'malien',
            'TG' => 'togolais',
            'BJ' => 'béninois',
            'NE' => 'nigérien',
            'FR' => 'français',
            'TR' => 'Kanunu',
            'CA' => 'Canada',
        ];

        foreach ($expected as $country => $token) {
            /** @var Company $company */
            $company = Company::factory()->create(['country' => $country]);
            /** @var Employee $employee */
            $employee = Employee::factory()->create(['company_id' => $company->id]);

            $slip = $this->makeSlip($company, $employee, $country);
            $text = $this->pdfText(app(PaySlipPdfGenerator::class)->generate($slip));

            $this->assertStringContainsString(
                $token,
                $text,
                "Le bulletin {$country} doit porter sa mention légale pays (#9045)."
            );
        }
    }

    /**
     * Le Cameroun est le cas explicitement signalé comme manquant par
     * `BULLETIN_CM_MENTIONS.md` : la mention doit citer l'article 68.
     */
    public function test_cameroon_mention_cites_article_68(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CM']);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $text = $this->pdfText(
            app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee, 'CM'))
        );

        $this->assertStringContainsString('camerounais', $text);
        $this->assertStringContainsString('68', $text);
    }

    /**
     * Les identifiants employeur suivent le référentiel du pays : un tenant CM
     * porte RCCM/NIU/CNPS, pas NIF/RC/CNAS (jeu DZ).
     */
    public function test_cameroon_uses_cemac_identifiers_not_dz_ones(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'CM',
            'metadata' => [
                'legal_rccm' => 'RC/DLA/2026/B/1234',
                'legal_niu' => 'M1234567890123',
                'legal_cnps_employer' => 'CNPS-998877',
            ],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $text = $this->pdfText(
            app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee, 'CM'))
        );

        $this->assertStringContainsString('RCCM', $text);
        $this->assertStringContainsString('RC/DLA/2026/B/1234', $text);
        $this->assertStringContainsString('NIU', $text);
        $this->assertStringContainsString('M1234567890123', $text);
        $this->assertStringContainsString('CNPS', $text);

        // Le jeu DZ ne doit pas fuiter sur un bulletin camerounais.
        $this->assertStringNotContainsString('CNAS', $text);
        $this->assertStringNotContainsString('ID.Nat', $text);
    }

    /**
     * Non-régression : le bulletin DZ conserve exactement son jeu historique
     * (F-09, #1539) — la table par pays ne doit pas le modifier.
     */
    public function test_dz_keeps_its_historical_identifier_set(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'metadata' => [
                'legal_nif' => '099916012345678',
                'legal_rc' => '16/00-1234567B23',
                'legal_cnas_employer' => '1234567890',
                'legal_idnat' => '1099-1234567-8',
            ],
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $text = $this->pdfText(
            app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee, 'DZ'))
        );

        $this->assertStringContainsString('NIF', $text);
        $this->assertStringContainsString('099916012345678', $text);
        $this->assertStringContainsString('N° CNAS employeur', $text);
        $this->assertStringContainsString('ID.Nat', $text);
    }

    /**
     * Un identifiant absent n'est jamais rendu vide : il est omis. Un tenant
     * sans métadonnées légales produit un bulletin sans bloc d'identifiants,
     * pas un bloc de libellés orphelins.
     */
    public function test_missing_identifiers_are_omitted_not_rendered_empty(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'CI', 'metadata' => []]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        $text = $this->pdfText(
            app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee, 'CI'))
        );

        $this->assertStringNotContainsString('NCC (DGI)', $text);
        $this->assertStringNotContainsString('N° CNPS employeur', $text);
    }

    private function pdfText(string $binary): string
    {
        // dompdf v3 compresse les flux de contenu (FlateDecode) : on décompresse
        // chaque stream avant d'extraire les chaînes entre parenthèses.
        $text = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) {
                    continue;
                }
                $text .= $this->extractParenthesized($decoded).' ';
            }
        }

        return $text;
    }

    private function extractParenthesized(string $data): string
    {
        // Chaînes entre parenthèses + décodage des échappements dompdf
        // (`\(`, `\)`, `\\`, séquences octales `\nnn`). dompdf v3 encode les
        // flux en UTF-16 (BE ou LE selon la police/le run) : endianness
        // détectée par run (BOM, puis position du premier octet nul).
        $text = '';
        $inParen = false;
        $buf = '';
        foreach (str_split($data) as $ch) {
            if ($inParen) {
                if ($ch === ')') {
                    $text .= $this->decodeRun($buf).' ';
                    $buf = '';
                    $inParen = false;
                } elseif ($ch === '(') {
                    $buf .= '(';
                } else {
                    $buf .= $ch;
                }
            } elseif ($ch === '(') {
                $inParen = true;
                $buf = '';
            }
        }

        // Normalisation des espaces Unicode (U+00A0, U+2000–U+200A, U+202F,
        // U+3000) et des doubles espaces (runs de texte scindés par dompdf).
        $text = (string) preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{3000}]/u', ' ', $text);
        $text = preg_replace('/\s{2,}/', ' ', $text);

        return (string) $text;
    }

    private function decodeRun(string $run): string
    {
        $decoded = preg_replace_callback(
            '/\\\\([0-7]{3})/',
            static fn (array $m): string => chr((int) octdec($m[1])),
            $run
        );
        $decoded = preg_replace('/\\\\([()\\\\])/', '$1', (string) $decoded);

        if (! str_contains((string) $decoded, "\0")) {
            return (string) $decoded;
        }

        // Endianness : BOM explicite, sinon position du premier octet nul.
        if (str_starts_with((string) $decoded, "\xFE\xFF")) {
            return mb_convert_encoding(substr((string) $decoded, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with((string) $decoded, "\xFF\xFE")) {
            return mb_convert_encoding(substr((string) $decoded, 2), 'UTF-8', 'UTF-16LE');
        }

        $littleEndian = isset($decoded[1]) && $decoded[1] === "\0";

        return mb_convert_encoding((string) $decoded, 'UTF-8', $littleEndian ? 'UTF-16LE' : 'UTF-16BE');
    }
}
