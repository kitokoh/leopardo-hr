<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Support\CountryDefaults;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
    public function generate(PayrollRun $run, string $format): string
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
            'sepa_xml' => $this->generateSepaXml($run, $slips, $currency),
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

    private function generateSepaXml(PayrollRun $run, Collection $slips, string $currency = 'EUR'): string
    {
        // Issue #2220/#2223 : l'IBAN/BIC de l'émetteur vient du profil
        // entreprise (Company.metadata['iban'/'bic']) — jamais de
        // placeholder. Export bloqué (exception) si non configuré : un
        // fichier de paiement avec un faux IBAN serait rejeté par la banque.
        [$companyIban, $companyBic] = $this->companyBankIdentity($run);

        // Issue #2223 : les employés sans IBAN sont EXCLUS du fichier (avec
        // avertissement) — jamais de valeur fabriquée ('UNKNOWN').
        $eligibleSlips = $slips->filter(
            fn ($slip): bool => is_string($slip->employee->iban ?? null) && $slip->employee->iban !== ''
        );
        $skipped = $slips->count() - $eligibleSlips->count();

        $msgId = 'LEO-'.now()->format('YmdHis').'-'.$run->id;
        $nbTransactions = $eligibleSlips->count();
        $totalAmount = $eligibleSlips->sum('net_salary');
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
        $xml .= '      <Dbtr><Nm>Leopardo RH</Nm></Dbtr>'."\n";
        $xml .= '      <DbtrAcct><Id><IBAN>'.$this->xmlEscape($companyIban).'</IBAN></Id></DbtrAcct>'."\n";
        $xml .= '      <DbtrAgt><FinInstnId><BIC>'.$this->xmlEscape($companyBic).'</BIC></FinInstnId></DbtrAgt>'."\n";

        foreach ($eligibleSlips as $slip) {
            $employee = $slip->employee;
            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $iban = (string) $employee->iban;

            $xml .= '      <CdtTrfTxInf>'."\n";
            $xml .= '        <PmtId><EndToEndId>SAL-'.$run->id.'-'.$slip->employee_id.'</EndToEndId></PmtId>'."\n";
            $xml .= '        <Amt><InstdAmt Ccy="'.$this->xmlEscape($currency).'">'.number_format($slip->net_salary, 2, '.', '').'</InstdAmt></Amt>'."\n";
            $xml .= '        <CdtrAgt><FinInstnId><BIC>NOTPROVIDED</BIC></FinInstnId></CdtrAgt>'."\n";
            $xml .= '        <Cdtr><Nm>'.$this->xmlEscape($name).'</Nm></Cdtr>'."\n";
            $xml .= '        <CdtrAcct><Id><IBAN>'.$this->xmlEscape($iban).'</IBAN></Id></CdtrAcct>'."\n";
            $xml .= '        <RmtInf><Ustrd>Salaire '.$run->period_start->format('m/Y').'</Ustrd></RmtInf>'."\n";
            $xml .= '      </CdtTrfTxInf>'."\n";
        }

        $xml .= '    </PmtInf>'."\n";
        $xml .= '  </CstmrCdtTrfInitn>'."\n";
        $xml .= '</Document>'."\n";

        if ($skipped > 0) {
            Log::warning('bank_export.sepa_skipped_missing_iban', [
                'run_id' => $run->id,
                'skipped' => $skipped,
            ]);
        }

        return $xml;
    }

    /**
     * Issue #2223 — identité bancaire de l'émetteur (Company.metadata) :
     * jamais de placeholder. Lève une exception si IBAN/BIC non configurés
     * (l'export bloqué vaut mieux qu'un fichier bancaire invalide).
     *
     * @return array{0: string, 1: string}
     */
    private function companyBankIdentity(PayrollRun $run): array
    {
        $company = $run->relationLoaded('company')
            ? $run->getRelation('company')
            : \App\Core\Tenant\Domain\Models\Company::query()->where('id', $run->company_id)->first();

        $metadata = $company instanceof \App\Core\Tenant\Domain\Models\Company ? ($company->metadata ?? []) : [];
        $iban = (string) ($metadata['iban'] ?? '');
        $bic = (string) ($metadata['bic'] ?? '');

        if ($iban === '' || $bic === '') {
            throw new \RuntimeException('Company IBAN/BIC not configured: bank export SEPA impossible (Company.metadata iban/bic).');
        }

        return [$iban, $bic];
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
            // Issue #2223 : jamais de CCP fabriqué (zéro-paddé). Les
            // employés sans compte sont exclus du fichier (avertissement).
            $ccp = $employee->bank_account ?? $employee->iban ?? null;
            if ($ccp === null || trim((string) $ccp) === '') {
                Log::warning('bank_export.ccp_skipped_missing_account', [
                    'run_id' => $run->id,
                    'employee_id' => $slip->employee_id,
                ]);
                continue;
            }
            $amount = str_pad(number_format($slip->net_salary, 2, '', ''), 12, '0', STR_PAD_LEFT);

            $lines[] = 'D'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT).str_pad((string) $ccp, 20).str_pad($name, 30).$amount;
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
            // Issue #2223 : jamais de compte vide/fabriqué dans un fichier
            // de paiement — exclusion avec avertissement.
            $account = $employee->bank_account ?? $employee->iban ?? '';
            if (trim((string) $account) === '') {
                Log::warning('bank_export.bna_skipped_missing_account', [
                    'run_id' => $run->id,
                    'employee_id' => $slip->employee_id,
                ]);
                continue;
            }

            $lines[] = implode('|', [
                'DETAIL',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                (string) $account,
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
