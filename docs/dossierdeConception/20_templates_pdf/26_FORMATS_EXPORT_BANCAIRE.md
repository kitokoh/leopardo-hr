# FORMATS EXPORT BANCAIRE — LEOPARDO RH
# Version 1.0 | Mars 2026
# Service : app/Services/BankExportService.php

---

## FORMATS SUPPORTÉS PAR PAYS

| Code | Pays | Format | Phase |
|------|------|--------|-------|
| `DZ_GENERIC` | Algérie | CSV délimité virgule | Phase 1 (MVP) |
| `MA_CIH` | Maroc — CIH Bank | CSV spécifique CIH | Phase 2 |
| `FR_SEPA` | France | XML ISO 20022 pain.001 | Phase 2 |
| `TN_GENERIC` | Tunisie | CSV générique | Phase 2 |
| `TR_GENERIC` | Turquie | CSV générique | Phase 2 |

---

## FORMAT DZ_GENERIC (MVP — Algérie)

```
Fichier : virements_YYYY-MM_YYYYMMDD_HHmmss.csv
Encodage : UTF-8 avec BOM (requis par Excel algérien)
Séparateur : virgule (,)
Guillemets : oui, sur les champs texte
Décimaux : point (.)

Structure (une ligne par employé) :
"Matricule","Nom","Prénom","IBAN","Banque","Montant","Devise","Motif","Période"
"EMP-001","Benali","Ahmed","DZ590123456789012345678901","BNA","85000.00","DZD","Salaire","2026-04"
```

```php
// BankExportService::generateDZGeneric(array $payrolls, string $period): string

public function generateDZGeneric(array $payrolls, string $period): string
{
    $bom  = "\xEF\xBB\xBF"; // BOM UTF-8
    $rows = ['"Matricule","Nom","Prénom","IBAN","Banque","Montant","Devise","Motif","Période"'];

    foreach ($payrolls as $payroll) {
        $employee = $payroll->employee;
        $iban     = $employee->iban ? decrypt($employee->iban) : '';
        $rows[]   = implode(',', [
            '"' . $employee->matricule . '"',
            '"' . $employee->last_name . '"',
            '"' . $employee->first_name . '"',
            '"' . $iban . '"',
            '"' . ($employee->bank_name ?? '') . '"',
            number_format($payroll->net_payable, 2, '.', ''),
            '"DZD"',
            '"Salaire ' . $period . '"',
            '"' . $period . '"',
        ]);
    }

    return $bom . implode("\n", $rows);
}
```

---

## FORMAT FR_SEPA (Phase 2 — France)

Structure XML ISO 20022 `pain.001.001.03` (Credit Transfer Initiation).
Un fichier XML par lot de virements, validé contre le schéma XSD officiel.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">
  <CstmrCdtTrfInitn>
    <GrpHdr>
      <MsgId>LEOPARDO-2026-04-001</MsgId>
      <CreDtTm>2026-04-30T10:00:00</CreDtTm>
      <NbOfTxs>45</NbOfTxs>
      <CtrlSum>3825000.00</CtrlSum>
      <InitgPty><Nm>TECHCORP SPA</Nm></InitgPty>
    </GrpHdr>
    <PmtInf>
      <PmtInfId>SALAIRES-2026-04</PmtInfId>
      <PmtMtd>TRF</PmtMtd>
      <ReqdExctnDt>2026-04-30</ReqdExctnDt>
      <Dbtr><Nm>TECHCORP SPA</Nm></Dbtr>
      <DbtrAcct><Id><IBAN>FR7630006000011234567890189</IBAN></Id></DbtrAcct>
      <!-- Une <CdtTrfTxInf> par employé -->
      <CdtTrfTxInf>
        <PmtId><EndToEndId>EMP-001-2026-04</EndToEndId></PmtId>
        <Amt><InstdAmt Ccy="EUR">3500.00</InstdAmt></Amt>
        <Cdtr><Nm>BENALI Ahmed</Nm></Cdtr>
        <CdtrAcct><Id><IBAN>FR7630004000031234567890143</IBAN></Id></CdtrAcct>
        <RmtInf><Ustrd>Salaire 04/2026 - EMP-001</Ustrd></RmtInf>
      </CdtTrfTxInf>
    </PmtInf>
  </CstmrCdtTrfInitn>
</Document>
```

---

## ENDPOINT API

```
GET /api/v1/payroll/export-bank?period=2026-04&format=DZ_GENERIC

Response 200 :
  Content-Type: text/csv (ou application/xml pour SEPA)
  Content-Disposition: attachment; filename="virements_2026-04_20260430_100000.csv"
  Body: [contenu du fichier]

Erreurs :
  422 : PAYROLL_NOT_VALIDATED — paie non encore validée pour cette période
  404 : NO_PAYROLL_FOUND — aucun bulletin pour cette période
  403 : PLAN_NO_BANK_EXPORT — plan Starter sans export bancaire
```

---

## RÈGLES MÉTIER

- Seules les paies `status = 'validated'` peuvent être exportées
- Un export crée une ligne dans `payroll_export_batches` (traçabilité)
- Le même lot peut être réexporté (idempotent)
- Les IBAN sont déchiffrés à la volée, jamais stockés en clair dans le fichier temporaire
- Le fichier est streamé directement depuis Laravel — pas sauvegardé sur disque
