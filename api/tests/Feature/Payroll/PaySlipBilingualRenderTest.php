<?php

declare(strict_types=1);

namespace Tests\Feature\Payroll;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Pdf\ArabicPdfText;
use App\Modules\Payroll\Infrastructure\Services\PaySlipPdfGenerator;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issue #5242 — DoD « rendu fr + ar sans cassure (golden-ish test) ».
 *
 * Le bulletin de paie DZ doit se rendre en français (LTR) et en arabe (RTL)
 * sans glyphes manquants, avec les mentions légales présentes dans les deux
 * langues. Le test rend le PDF réel (dompdf) dans chaque locale et vérifie :
 *   1. les mentions/titres attendus sont présents dans le flux texte extrait ;
 *   2. aucun caractère de substitution (U+FFFD) — signature d'un glyphe manquant ;
 *   3. en arabe, la police embarquée est bien une police arabe (pas DejaVu
 *      Sans dont la couverture arabe est partielle — rendu « cassé »).
 */
class PaySlipBilingualRenderTest extends TestCase
{
    use RefreshTenantDatabase;

    private const LEGAL_METADATA = [
        'legal_nif' => '099916012345678',
        'legal_rc' => '16/00-1234567B23',
        'legal_cnas_employer' => '1234567890',
        'legal_idnat' => '1099-1234567-8',
    ];

    private function makeSlip(Company $company, Employee $employee): PaySlip
    {
        /** @var PayrollRun $run */
        $run = PayrollRun::create([
            'company_id' => $company->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'country_code' => 'DZ',
            'status' => 'validated',
        ]);

        /** @var PaySlip $slip */
        $slip = PaySlip::create([
            'payroll_run_id' => $run->id,
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'gross_salary' => 60000,
            'total_deductions' => 12442,
            'net_salary' => 47558,
            'status' => 'validated',
        ]);

        return $slip;
    }

    public function test_fr_render_contains_official_mentions_without_missing_glyphs(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'language' => 'fr',
            'metadata' => self::LEGAL_METADATA,
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'fr',
        ]);

        $binary = app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee));
        $text = $this->pdfText($binary);

        // Titre (transformé en MAJUSCULES par la CSS) + mentions légales DZ
        // (référentiel BULLETIN_DZ_MENTIONS.md).
        $this->assertStringContainsString('BULLETIN DE PAIE', $text);
        $this->assertStringContainsString('NIF', $text);
        $this->assertStringContainsString('099916012345678', $text);
        $this->assertStringContainsString('RC', $text);
        $this->assertStringContainsString('16/00-1234567B23', $text);
        $this->assertStringContainsString('ID.Nat', $text);

        // Numérotation du bulletin.
        $this->assertStringContainsString('Bulletin N°', $text);

        // Aucun glyphe manquant (U+FFFD = replacement character).
        $this->assertStringNotContainsString("\u{FFFD}", $text);
    }

    public function test_ar_render_is_unbroken_and_uses_an_arabic_font(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'DZ',
            'currency' => 'DZD',
            'language' => 'ar',
            'metadata' => self::LEGAL_METADATA,
        ]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'preferred_language' => 'ar',
        ]);

        $binary = app(PaySlipPdfGenerator::class)->generate($this->makeSlip($company, $employee));

        // 1. Le titre arabe rendu est le résultat du shaping (#5242) : plus
        //    aucune lettre de base, formes contextuelles + inversion RTL.
        //    L'extracteur ajoute un espace entre les runs (dompdf scinde les
        //    caractères arabes en plusieurs TJ) → comparaison sans espaces.
        $text = $this->pdfText($binary);
        $stripWs = static fn (string $s): string => (string) preg_replace('/\s+/u', '', $s);
        $this->assertStringContainsString(
            $stripWs(ArabicPdfText::shape('كشف الراتب')),
            $stripWs($text)
        );

        // 2. Aucun glyphe manquant.
        $this->assertStringNotContainsString("\u{FFFD}", $text);

        // 3. La police arabe Almarai est embarquée (dompdf ne retombe pas sur
        //    DejaVu Sans dont la couverture arabe est partielle → cassure).
        $this->assertStringContainsString('Almarai', $binary);
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
            // Pas d'octet nul : run Latin-1 pur OU UTF-16BE de caractères tous
            // ≥ U+0100 (ex. formes de présentation arabes — pas de NUL dans
            // les octets hauts). On décode en UTF-16BE et on garde le résultat
            // s'il contient de l'arabe (shaping #5242), sinon Latin-1.
            if (strlen((string) $decoded) % 2 === 0) {
                $utf16 = (string) @mb_convert_encoding((string) $decoded, 'UTF-8', 'UTF-16BE');
                if (preg_match('/[\x{0600}-\x{06FF}\x{FB50}-\x{FEFF}]/u', $utf16)) {
                    return $utf16;
                }
            }

            return (string) $decoded;
        }

        $bytes = (string) $decoded;
        $utf16be = str_starts_with($bytes, "\xFE\xFF");
        $utf16le = str_starts_with($bytes, "\xFF\xFE");
        if ($utf16be) {
            $bytes = substr($bytes, 2);
        } elseif ($utf16le) {
            $bytes = substr($bytes, 2);
        } else {
            // Pas de BOM : détection par le premier octet nul.
            $firstNul = strpos($bytes, "\0");
            $utf16be = $firstNul !== false && $firstNul % 2 === 0;
            $utf16le = $firstNul !== false && $firstNul % 2 === 1;
        }

        if ($utf16be || $utf16le) {
            $out = '';
            for ($i = 0, $len = strlen($bytes); $i + 1 < $len; $i += 2) {
                $hi = $utf16be ? $bytes[$i] : $bytes[$i + 1];
                $lo = $utf16be ? $bytes[$i + 1] : $bytes[$i];
                $code = (ord($hi) << 8) | ord($lo);
                if ($code >= 0xD800 && $code <= 0xDBFF && $i + 3 < $len) {
                    $hi2 = $utf16be ? $bytes[$i + 2] : $bytes[$i + 3];
                    $lo2 = $utf16be ? $bytes[$i + 3] : $bytes[$i + 2];
                    $code = 0x10000 + (($code - 0xD800) << 10) + (((ord($hi2) << 8) | ord($lo2)) - 0xDC00);
                    $i += 2;
                }
                $out .= mb_chr($code, 'UTF-8');
            }

            return $out;
        }

        return $bytes;
    }
}
