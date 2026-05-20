<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PayrollRun;
use Illuminate\Support\Collection;

class BankExportGenerator
{
    public function generate(PayrollRun $run, string $format): string
    {
        $slips = $run->paySlips()
            ->with('employee:id,first_name,last_name,iban,bank_account')
            ->where('status', 'validated')
            ->get();

        return match ($format) {
            'sepa_xml' => $this->generateSepaXml($run, $slips),
            'ccp_dz' => $this->generateCcpAlgerie($run, $slips),
            'cpa_dz' => $this->generateCpaBna($run, $slips, 'CPA'),
            'bna_dz' => $this->generateCpaBna($run, $slips, 'BNA'),
            'virement_ma' => $this->generateCsvGeneric($run, $slips),
            'csv_generic' => $this->generateCsvGeneric($run, $slips),
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

    private function generateSepaXml(PayrollRun $run, Collection $slips): string
    {
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
        $xml .= '      <Dbtr><Nm>Leopardo RH</Nm></Dbtr>'."\n";
        $xml .= '      <DbtrAcct><Id><IBAN>PLACEHOLDER_COMPANY_IBAN</IBAN></Id></DbtrAcct>'."\n";
        $xml .= '      <DbtrAgt><FinInstnId><BIC>PLACEHOLDER_BIC</BIC></FinInstnId></DbtrAgt>'."\n";

        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $iban = $employee->iban ?? 'UNKNOWN';

            $xml .= '      <CdtTrfTxInf>'."\n";
            $xml .= '        <PmtId><EndToEndId>SAL-'.$run->id.'-'.$slip->employee_id.'</EndToEndId></PmtId>'."\n";
            $xml .= '        <Amt><InstdAmt Ccy="EUR">'.number_format($slip->net_salary, 2, '.', '').'</InstdAmt></Amt>'."\n";
            $xml .= '        <CdtrAgt><FinInstnId><BIC>NOTPROVIDED</BIC></FinInstnId></CdtrAgt>'."\n";
            $xml .= '        <Cdtr><Nm>'.$this->xmlEscape($name).'</Nm></Cdtr>'."\n";
            $xml .= '        <CdtrAcct><Id><IBAN>'.$this->xmlEscape($iban).'</IBAN></Id></CdtrAcct>'."\n";
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
            $ccp = $employee->bank_account ?? $employee->iban ?? str_pad((string) $employee->id, 20, '0', STR_PAD_LEFT);
            $amount = str_pad(number_format($slip->net_salary, 2, '', ''), 12, '0', STR_PAD_LEFT);

            $lines[] = 'D'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT).str_pad($ccp, 20).str_pad($name, 30).$amount;
            $seq++;
        }

        $lines[] = 'T'.str_pad((string) $slips->count(), 6, '0', STR_PAD_LEFT).str_pad(number_format($slips->sum('net_salary'), 2, '', ''), 15, '0', STR_PAD_LEFT);

        return implode("\r\n", $lines)."\r\n";
    }

    private function generateCsvGeneric(PayrollRun $run, Collection $slips): string
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
                $run->country_code ?? 'EUR',
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
            $account = $employee->bank_account ?? $employee->iban ?? '';

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
