<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Support\CountryDefaults;
use Illuminate\Support\Collection;

/**
 * PA2-I18N-003 — Bank export currency must follow the payroll run's own
 * country (`PayrollRun::$country_code`, resolved through the canonical
 * `App\Support\CountryDefaults` catalogue), not a hardcoded 3-letter code.
 * `DZD` only ever appears here as the technical last-resort fallback that
 * `CountryDefaults::for()` itself falls back to for an unknown/empty code —
 * it is never a hardcoded per-format assumption.
 */
class BankExportGenerator
{
    /**
     * @param  array{name?: string, iban?: ?string, bic?: ?string}  $companyBank
     *        Débiteur SEPA lu depuis `companies.metadata` (issue #2198) :
     *        `name` = raison sociale, `iban` = IBAN débiteur (obligatoire SEPA),
     *        `bic` = BIC débiteur (optionnel, sinon l'élément est omis).
     */
    public function generate(PayrollRun $run, string $format, array $companyBank = []): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,iban,bank_account')
            ->where('status', 'validated')
            ->get();

        // sepa_xml/csv_generic/virement_ma are multi-country formats, so
        // their currency must follow the payroll run's own country. ccp_dz/
        // cpa_dz/bna_dz are Algeria-only local bank formats (Poste/CPA/BNA)
        // that are always denominated in DZD regardless of country_code,
        // so they intentionally keep their literal 'DZD'.
        $currency = CountryDefaults::for($run->country_code)['currency'];

        return match ($format) {
            'sepa_xml' => $this->generateSepaXml($run, $slips, $currency, $companyBank),
            'ccp_dz' => $this->generateCcpAlgerie($run, $slips),
            'cpa_dz' => $this->generateCpaBna($run, $slips, 'CPA'),
            'bna_dz' => $this->generateCpaBna($run, $slips, 'BNA'),
            'virement_ma' => $this->generateCsvGeneric($run, $slips, $currency),
            'csv_generic' => $this->generateCsvGeneric($run, $slips, $currency),
            default => throw new \InvalidArgumentException("Unsupported bank export format: {$format}"),
        };
    }

    public function fileExtension(string $format): string
    {
        return match ($format) {
            'sepa_xml' => 'xml',
            'ccp_dz' => 'txt',
            'cpa_dz' => 'txt',
            'bna_dz' => 'txt',
            'virement_ma' => 'csv',
            'csv_generic' => 'csv',
            default => 'dat',
        };
    }

    public function mimeType(string $format): string
    {
        return match ($format) {
            'sepa_xml' => 'application/xml',
            'ccp_dz' => 'text/plain',
            'cpa_dz' => 'text/plain',
            'bna_dz' => 'text/plain',
            'virement_ma' => 'text/csv',
            'csv_generic' => 'text/csv',
            default => 'application/octet-stream',
        };
    }

    /**
     * @param  array{name?: string, iban?: ?string, bic?: ?string}  $companyBank
     */
    private function generateSepaXml(PayrollRun $run, Collection $slips, string $currency = 'EUR', array $companyBank = []): string
    {
        // Issue #2198 — no placeholder ever: the SEPA file must be usable by
        // a bank as-is. The debtor IBAN comes from companies.metadata and is
        // mandatory; the debtor BIC is optional (element omitted when absent).
        $companyName = trim((string) ($companyBank['name'] ?? 'Leopardo RH'));
        $companyIban = isset($companyBank['iban']) ? trim((string) $companyBank['iban']) : '';
        $companyBic = isset($companyBank['bic']) ? trim((string) $companyBank['bic']) : '';

        if ($companyIban === '') {
            throw new \RuntimeException('MISSING_COMPANY_IBAN: company IBAN (metadata.company_iban) is required for SEPA export.');
        }

        $msgId = 'LEO-'.now()->format('YmdHis').'-'.$run->id;
        $nbTransactions = $slips->count();
        $totalAmount = $slips->sum('net_salary');
        $creationDate = now()->toIso8601String();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">'."\n";
        $xml .= '  <CstmrCdtTrfInitn>'."\n";
        $xml .= '    <GrpHdr>'."\n";
        $xml .= '      <MsgId>'.$this->xmlEscape($msgId).'</MsgId>'."\n";
        $xml .= '      <CreDtTm>'.$creationDate.'</CreDtTm>'."\n";
        $xml .= '      <NbOfTxs>'.$nbTransactions.'</NbOfTxs>'."\n";
        $xml .= '      <CtrlSum>'.number_format($totalAmount, 2, '.', '').'</CtrlSum>'."\n";
        $xml .= '      <InitgPty><Nm>Leopardo RH</Nm></InitgPty>'."\n";
        $xml .= '    </GrpHdr>'."\n";
        $xml .= '    <PmtInf>'."\n";
        $xml .= '      <PmtInfId>PMT-'.$run->id.'</PmtInfId>'."\n";
        $xml .= '      <PmtMtd>TRF</PmtMtd>'."\n";
        $xml .= '      <NbOfTxs>'.$nbTransactions.'</NbOfTxs>'."\n";
        $xml .= '      <CtrlSum>'.number_format($totalAmount, 2, '.', '').'</CtrlSum>'."\n";
        $xml .= '      <ReqdExctnDt>'.now()->addWeekdays(2)->format('Y-m-d').'</ReqdExctnDt>'."\n";
        $xml .= '      <Dbtr><Nm>'.$this->xmlEscape($companyName).'</Nm></Dbtr>'."\n";
        $xml .= '      <DbtrAcct><Id><IBAN>'.$this->xmlEscape($companyIban).'</IBAN></Id></DbtrAcct>'."\n";
        if ($companyBic !== '') {
            $xml .= '      <DbtrAgt><FinInstnId><BIC>'.$this->xmlEscape($companyBic).'</BIC></FinInstnId></DbtrAgt>'."\n";
        }

        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $iban = trim((string) ($employee->iban ?? ''));

            if ($iban === '') {
                throw new \RuntimeException('MISSING_EMPLOYEE_IBAN: employee #'.$slip->employee_id.' has no IBAN for SEPA export.');
            }

            $xml .= '      <CdtTrfTxInf>'."\n";
            $xml .= '        <PmtId><EndToEndId>SAL-'.$run->id.'-'.$slip->employee_id.'</EndToEndId></PmtId>'."\n";
            $xml .= '        <Amt><InstdAmt Ccy="'.$this->xmlEscape($currency).'">'.number_format($slip->net_salary, 2, '.', '').'</InstdAmt></Amt>'."\n";
            // BIC créancier optionnel : BIC entreprise en fallback, sinon
            // l'élément CdtrAgt est omis (valide selon pain.001.001.03).
            if ($companyBic !== '') {
                $xml .= '        <CdtrAgt><FinInstnId><BIC>'.$this->xmlEscape($companyBic).'</BIC></FinInstnId></CdtrAgt>'."\n";
            }
            $xml .= '        <Cdtr><Nm>'.$this->xmlEscape($name).'</Nm></Cdtr>'."\n";
            $xml .= '        <CdtrAcct><Id><IBAN>'.$this->xmlEscape((string) $iban).'</IBAN></Id></CdtrAcct>'."\n";
            $xml .= '        <RmtInf><Ustrd>Salaire '.$run->period_start->format('m/Y').'</Ustrd></RmtInf>'."\n";
            $xml .= '      </CdtTrfTxInf>'."\n";
        }

        $xml .= '    </PmtInf>'."\n";
        $xml .= '  </CstmrCdtTrfInitn>'."\n";
        $xml .= '</Document>'."\n";

        return $xml;
    }
    private function generateCcpAlgerie(PayrollRun $run, Collection $slips): string
    {
        $lines = [];
        $lines[] = str_pad('ENTETE', 120);
        $lines[] = 'H'.str_pad('LEOPARDO', 30).now()->format('dmY').str_pad((string) $slips->count(), 6, '0', STR_PAD_LEFT).str_pad(number_format($slips->sum('net_salary'), 2, '', ''), 15, '0', STR_PAD_LEFT);

        $seq = 1;
        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = mb_strtoupper(trim(($employee->last_name ?? '').' '.($employee->first_name ?? '')));
            // Issue #2223 : plus jamais de CCP fabriqué (str_pad de l'id) —
            // fail-closed si le compte est absent.
            $ccp = $employee->bank_account ?? $employee->iban;

            if (! is_string($ccp) || $ccp === '') {
                throw new \RuntimeException(sprintf(
                    'CCP export requires a bank account (CCP or IBAN) for employee #%d (%s).',
                    $slip->employee_id,
                    $name
                ));
            }

            $amount = str_pad(number_format($slip->net_salary, 2, '', ''), 12, '0', STR_PAD_LEFT);

            $lines[] = 'D'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT).str_pad($ccp, 20).str_pad($name, 30).$amount;
            $seq++;
        }

        $lines[] = 'T'.str_pad((string) $slips->count(), 6, '0', STR_PAD_LEFT).str_pad(number_format($slips->sum('net_salary'), 2, '', ''), 15, '0', STR_PAD_LEFT);

        return implode("\r\n", $lines)."\r\n";
    }

    private function generateCsvGeneric(PayrollRun $run, Collection $slips, string $currency = 'EUR'): string
    {
        $csv = "employee_id,first_name,last_name,iban,bank_account,net_salary,currency,period\n";

        $period = $run->period_start->format('Y-m');

        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%.2f,%s,%s\n",
                $slip->employee_id,
                $this->csvEscape($employee->first_name ?? ''),
                $this->csvEscape($employee->last_name ?? ''),
                $this->csvEscape($employee->iban ?? ''),
                $this->csvEscape($employee->bank_account ?? ''),
                $slip->net_salary,
                $currency,
                $period
            );
        }

        return $csv;
    }

    private function generateCpaBna(PayrollRun $run, Collection $slips, string $bank): string
    {
        $lines = [];
        $batchDate = now()->format('dmY');
        $batchRef = strtoupper($bank).'-'.now()->format('YmdHis').'-'.$run->id;
        $totalAmount = $slips->sum('net_salary');

        $lines[] = implode('|', [
            'HEADER',
            $bank,
            $batchRef,
            $batchDate,
            (string) $slips->count(),
            number_format($totalAmount, 2, '.', ''),
            'DZD',
            'LEOPARDO RH',
        ]);

        $seq = 1;
        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = mb_strtoupper(trim(($employee->last_name ?? '').' '.($employee->first_name ?? '')));
            // Issue #2223 : fail-closed si le compte bancaire est absent.
            $account = $employee->bank_account ?? $employee->iban;

            if (! is_string($account) || $account === '') {
                throw new \RuntimeException(sprintf(
                    '%s export requires a bank account (CCP or IBAN) for employee #%d (%s).',
                    $bank,
                    $slip->employee_id,
                    $name
                ));
            }

            $lines[] = implode('|', [
                'DETAIL',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                $account,
                $name,
                number_format($slip->net_salary, 2, '.', ''),
                'DZD',
                'SAL-'.$run->id.'-'.$slip->employee_id,
                'Salaire '.$run->period_start->format('m/Y'),
            ]);
            $seq++;
        }

        $lines[] = implode('|', [
            'FOOTER',
            (string) $slips->count(),
            number_format($totalAmount, 2, '.', ''),
        ]);

        return implode("\r\n", $lines)."\r\n";
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function csvEscape(string $value): string
    {
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
