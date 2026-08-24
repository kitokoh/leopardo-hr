<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Infrastructure\Services;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Support\CountryDefaults;
use Illuminate\Support\Collection;
use Throwable;

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
     * @param  array{iban: string|null, bic: string|null}|array{name: string, iban: string|null, bic: string|null}|null  $companyBank  coordonnées débiteur pré-résolues (job), sinon résolues ici (contexte tenant)
     */
    public function generate(PayrollRun $run, string $format, ?array $companyBank = null): string
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
            'sepa_xml' => $this->generateSepaExport($run, $slips, $currency, $companyBank),
            'ccp_dz' => $this->generateCcpAlgerie($run, $slips),
            'cpa_dz' => $this->generateCpaBna($run, $slips, 'CPA'),
            'bna_dz' => $this->generateCpaBna($run, $slips, 'BNA'),
            'cnep_dz' => $this->generateCnep($run, $slips),
            'edx_dz' => $this->generateEdx($run, $slips),
            'virement_ma' => $this->generateCsvGeneric($run, $slips, $currency),
            'csv_generic' => $this->generateCsvGeneric($run, $slips, $currency),
            default => throw new \InvalidArgumentException("Unsupported bank export format: {$format}"),
        };
    }

    /**
     * Point d'entrée SEPA : résout les coordonnées bancaires DE L'ENTREPRISE
     * (débiteur) depuis la configuration tenant et refuse explicitement
     * l'export si elles sont absentes — plus aucun placeholder dans le fichier.
     *
     * @return array{iban: string|null, bic: string|null}
     */
    private function companyBankDetails(PayrollRun $run): array
    {
        $company = null;

        try {
            if (app()->bound('current_company')) {
                $company = currentCompany();
            }
        } catch (Throwable) {
            // Pas de contexte tenant actif — on tente la resolution directe.
        }

        if (! $company instanceof Company) {
            /** @var Company|null $company */
            $company = Company::query()
                ->withoutGlobalScopes()
                ->whereKey($run->company_id)
                ->first();
        }

        $metadata = $company->metadata ?? [];

        return [
            'iban' => is_string($metadata['bank']['iban'] ?? null) && $metadata['bank']['iban'] !== ''
                ? $metadata['bank']['iban']
                : null,
            'bic' => is_string($metadata['bank']['bic'] ?? null) && $metadata['bank']['bic'] !== ''
                ? $metadata['bank']['bic']
                : null,
        ];
    }

    /** @param Collection<int, PaySlip> $slips */
    /**
     * @param  Collection<int, PaySlip>  $slips
     * @param  array{iban: string|null, bic: string|null}|array{name: string, iban: string|null, bic: string|null}|null  $companyBank
     */
    private function generateSepaExport(PayrollRun $run, Collection $slips, string $currency, ?array $companyBank = null): string
    {
        $bank = $companyBank ?? $this->companyBankDetails($run);

        if (! is_string($bank['iban']) || ! is_string($bank['bic'])) {
            throw new \RuntimeException(
                'Configuration bancaire entreprise manquante (metadata.bank.iban / metadata.bank.bic) — export SEPA impossible.'
            );
        }

        return $this->generateSepaXml($run, $slips, $currency, $bank['iban'], $bank['bic']);
    }

    public function fileExtension(string $format): string
    {
        return match ($format) {
            'sepa_xml' => 'xml',
            'ccp_dz' => 'txt',
            'cpa_dz' => 'txt',
            'bna_dz' => 'txt',
            'cnep_dz' => 'txt',
            'edx_dz' => 'txt',
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
            'cnep_dz' => 'text/plain',
            'edx_dz' => 'text/plain',
            'virement_ma' => 'text/csv',
            'csv_generic' => 'text/csv',
            default => 'application/octet-stream',
        };
    }

    private function generateSepaXml(
        PayrollRun $run,
        Collection $slips,
        string $currency = 'EUR',
        ?string $companyIban = null,
        ?string $companyBic = null,
    ): string {
        if (! is_string($companyIban) || ! is_string($companyBic) || $companyIban === '' || $companyBic === '') {
            throw new \RuntimeException(
                'Configuration bancaire entreprise manquante (metadata.bank.iban / metadata.bank.bic) — export SEPA impossible.'
            );
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
        $xml .= '      <Dbtr><Nm>'.$this->xmlEscape($run->company->name ?? 'Leopardo RH').'</Nm></Dbtr>'."\n";
        $xml .= '      <DbtrAcct><Id><IBAN>'.$this->xmlEscape($companyIban).'</IBAN></Id></DbtrAcct>'."\n";
        $xml .= '      <DbtrAgt><FinInstnId><BIC>'.$this->xmlEscape($companyBic).'</BIC></FinInstnId></DbtrAgt>'."\n";

        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''));
            $iban = $employee->iban ?? 'UNKNOWN';

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

    /**
     * #5243 — Ordre de virement CNEP Banque (Algérie) : convention interne
     * pipe-delimited HEADER/DETAIL/FOOTER, en DZD (format 100 % local, comme
     * ccp_dz/cpa_dz/bna_dz). Chaque DÉTAIL porte le RIB (bank_account),
     * le nom, le montant net et la référence de paie.
     *
     * ⚠️ Format à valider avec CNEP Banque avant usage réel (même niveau de
     * confiance `pilot` que les formats ccp_dz/cpa_dz/bna_dz existants).
     *
     * @param  Collection<int, PaySlip>  $slips
     */
    private function generateCnep(PayrollRun $run, Collection $slips): string
    {
        $lines = [];
        $batchDate = now()->format('dmY');
        $batchRef = 'CNEP-'.now()->format('YmdHis').'-'.$run->id;
        $totalAmount = $slips->sum('net_salary');

        $lines[] = implode('|', [
            'HEADER',
            'CNEP',
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
            $rib = $employee->bank_account ?? $employee->iban ?? '';

            $lines[] = implode('|', [
                'DETAIL',
                str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                $rib,
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

    /**
     * #5243 — Ordre de virement EDX (échange de données interbancaire
     * algérien) : enregistrements à largeur fixe H (entête) / D (détail) /
     * F (fin), montants nets en DZD (format 100 % local). Le RIB source
     * est celui de l'employé (bank_account, fallback IBAN), le RIB cible
     * est omis volontairement (virement masse géré par la banque).
     *
     * ⚠️ Convention interne documentée — le gabarit exact des colonnes est
     * à confirmer avec la banque émettrice avant usage en production.
     *
     * @param  Collection<int, PaySlip>  $slips
     */
    private function generateEdx(PayrollRun $run, Collection $slips): string
    {
        $lines = [];
        $totalAmount = $slips->sum('net_salary');
        $batchRef = 'EDX-'.now()->format('YmdHis').'-'.$run->id;

        // Entête : H + référence lot + date (dmY) + nombre + total (15,2).
        $lines[] = 'H'.str_pad(substr($batchRef, 0, 20), 20)
            .str_pad(now()->format('dmY'), 8)
            .str_pad((string) $slips->count(), 6, '0', STR_PAD_LEFT)
            .str_pad(number_format($totalAmount, 2, '.', ''), 15, '0', STR_PAD_LEFT)
            .str_pad('DZD', 3);

        $seq = 1;
        foreach ($slips as $slip) {
            $employee = $slip->employee;
            $name = mb_strtoupper(trim(($employee->last_name ?? '').' '.($employee->first_name ?? '')));
            $rib = $employee->bank_account ?? $employee->iban ?? '';

            // Détail : D + séquence (6) + RIB (20) + nom (30) + net (12,2) + référence (20).
            $lines[] = 'D'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT)
                .str_pad(substr($rib, 0, 20), 20)
                .str_pad(mb_substr($name, 0, 30), 30)
                .str_pad(number_format($slip->net_salary, 2, '.', ''), 12, '0', STR_PAD_LEFT)
                .str_pad(substr('SAL-'.$run->id.'-'.$slip->employee_id, 0, 20), 20);
            $seq++;
        }

        // Fin : F + nombre (6) + total (15,2).
        $lines[] = 'F'.str_pad((string) $slips->count(), 6, '0', STR_PAD_LEFT)
            .str_pad(number_format($totalAmount, 2, '.', ''), 15, '0', STR_PAD_LEFT);

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
