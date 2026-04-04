# TEMPLATE BULLETIN DE PAIE — LEOPARDO RH
# Version 1.0 | Mars 2026
# Fichier cible : api/resources/views/pdf/payslip.blade.php

---

## MENTIONS LÉGALES OBLIGATOIRES PAR PAYS

| Champ | FR | DZ | MA | TN | TR |
|-------|:--:|:--:|:--:|:--:|:--:|
| SIRET / RC entreprise | ✅ | ✅ (NIF) | ✅ (RC) | ✅ (MF) | ✅ (Vergi No) |
| Numéro de bulletin | ✅ | ✅ | ✅ | ✅ | ✅ |
| Période de paie | ✅ | ✅ | ✅ | ✅ | ✅ |
| Matricule employé | ✅ | ✅ | ✅ | ✅ | ✅ |
| Numéro SS / CNAS | ✅ | ✅ | ✅ | ✅ | ✅ |
| Convention collective | ✅ | — | — | — | — |
| Coefficient / Échelon | ✅ | ✅ | — | — | — |
| Mention congés restants | ✅ | ✅ | ✅ | ✅ | ✅ |
| Cumul annuel brut | ✅ | — | — | — | — |

---

## TEMPLATE BLADE (DomPDF)

```html
{{-- resources/views/pdf/payslip.blade.php --}}
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
  <meta charset="UTF-8">
  <style>
    * { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
    /* DejaVu Sans = seule police DomPDF supportant l'arabe */

    body { margin: 0; padding: 20px; color: #1a1a2e; }

    /* ─── EN-TÊTE ─── */
    .header { display: flex; justify-content: space-between; border-bottom: 2px solid #e8b400; padding-bottom: 12px; margin-bottom: 20px; }
    .company-logo { width: 80px; height: 40px; object-fit: contain; }
    .company-info { font-size: 9px; color: #666; }
    .payslip-title { font-size: 14px; font-weight: bold; color: #1a1a2e; text-align: right; }

    /* ─── BLOC EMPLOYÉ ─── */
    .employee-block { background: #f8f9fa; padding: 10px; border-radius: 4px; margin-bottom: 16px; }
    .employee-block table { width: 100%; }
    .employee-block td { padding: 2px 8px; }
    .label { color: #666; width: 40%; }

    /* ─── TABLEAU DE PAIE ─── */
    .payroll-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
    .payroll-table th { background: #1a1a2e; color: white; padding: 6px 8px; text-align: left; }
    .payroll-table td { padding: 5px 8px; border-bottom: 1px solid #eee; }
    .payroll-table tr:nth-child(even) { background: #f8f9fa; }
    .amount { text-align: right; font-weight: bold; }
    .section-header { background: #e8f4fd; font-weight: bold; color: #1a1a2e; }
    .total-row { background: #1a1a2e; color: white; font-weight: bold; font-size: 11px; }
    .net-row { background: #e8b400; color: #1a1a2e; font-weight: bold; font-size: 12px; }

    /* ─── PIED DE PAGE ─── */
    .footer { border-top: 1px solid #eee; padding-top: 8px; margin-top: 20px; font-size: 8px; color: #999; }
    .congé-box { border: 1px solid #e8b400; padding: 6px 12px; display: inline-block; border-radius: 4px; }
  </style>
</head>
<body>

{{-- ─── EN-TÊTE ─── --}}
<div class="header">
  <div>
    @if($company->logo_path)
      <img class="company-logo" src="{{ storage_path('app/public/' . $company->logo_path) }}">
    @endif
    <div class="company-info">
      <strong>{{ $company->name }}</strong><br>
      {{ $company->address }}<br>
      {{ __('pdf.registration_number', [], $locale) }} : {{ $company->registration_number ?? '—' }}
    </div>
  </div>
  <div>
    <div class="payslip-title">{{ __('pdf.payslip_title', [], $locale) }}</div>
    <div style="text-align:right; color:#666; font-size:9px;">
      {{ __('pdf.period', [], $locale) }} : {{ $periodLabel }}<br>
      {{ __('pdf.payslip_number', [], $locale) }} : {{ str_pad($payroll->id, 6, '0', STR_PAD_LEFT) }}
    </div>
  </div>
</div>

{{-- ─── BLOC EMPLOYÉ ─── --}}
<div class="employee-block">
  <table>
    <tr>
      <td class="label">{{ __('pdf.employee_name', [], $locale) }}</td>
      <td><strong>{{ $employee->last_name }} {{ $employee->first_name }}</strong></td>
      <td class="label">{{ __('pdf.matricule', [], $locale) }}</td>
      <td>{{ $employee->matricule }}</td>
    </tr>
    <tr>
      <td class="label">{{ __('pdf.position', [], $locale) }}</td>
      <td>{{ $employee->position?->name ?? '—' }}</td>
      <td class="label">{{ __('pdf.department', [], $locale) }}</td>
      <td>{{ $employee->department?->name ?? '—' }}</td>
    </tr>
    <tr>
      <td class="label">{{ __('pdf.contract_type', [], $locale) }}</td>
      <td>{{ strtoupper($employee->contract_type) }}</td>
      <td class="label">{{ __('pdf.hire_date', [], $locale) }}</td>
      <td>{{ \Carbon\Carbon::parse($employee->contract_start)->locale($locale)->isoFormat('L') }}</td>
    </tr>
    @if($employee->social_security_number)
    <tr>
      <td class="label">{{ __('pdf.ss_number', [], $locale) }}</td>
      <td colspan="3">{{ $employee->social_security_number }}</td>
    </tr>
    @endif
  </table>
</div>

{{-- ─── TABLEAU DE PAIE ─── --}}
<table class="payroll-table">
  <thead>
    <tr>
      <th style="width:50%">{{ __('pdf.designation', [], $locale) }}</th>
      <th style="width:15%; text-align:right">{{ __('pdf.base', [], $locale) }}</th>
      <th style="width:15%; text-align:right">{{ __('pdf.rate', [], $locale) }}</th>
      <th style="width:20%; text-align:right">{{ __('pdf.amount', [], $locale) }}</th>
    </tr>
  </thead>
  <tbody>

    {{-- BRUT --}}
    <tr class="section-header"><td colspan="4">{{ __('pdf.section_gross', [], $locale) }}</td></tr>
    <tr>
      <td>{{ __('pdf.base_salary', [], $locale) }}</td>
      <td class="amount">{{ number_format($payroll->salary_base, 2) }}</td>
      <td></td>
      <td class="amount">{{ number_format($payroll->salary_base, 2) }}</td>
    </tr>
    @if($payroll->overtime_hours > 0)
    <tr>
      <td>{{ __('pdf.overtime', [], $locale) }} ({{ $payroll->overtime_hours }}h)</td>
      <td></td>
      <td></td>
      <td class="amount">{{ number_format($payroll->overtime_amount, 2) }}</td>
    </tr>
    @endif
    @foreach(json_decode($payroll->bonuses, true) ?? [] as $bonus)
    <tr>
      <td>{{ $bonus['label'] }}</td>
      <td></td>
      <td></td>
      <td class="amount">{{ number_format($bonus['amount'], 2) }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="3">{{ __('pdf.gross_total', [], $locale) }}</td>
      <td class="amount">{{ number_format($payroll->gross_total, 2) }}</td>
    </tr>

    {{-- COTISATIONS --}}
    <tr class="section-header"><td colspan="4">{{ __('pdf.section_deductions', [], $locale) }}</td></tr>
    @foreach(json_decode($payroll->cotisations_salariales, true) ?? [] as $cotisation)
    <tr>
      <td>{{ $cotisation['name_i18n'][$locale] ?? $cotisation['name_i18n']['fr'] }}</td>
      <td class="amount">{{ number_format($payroll->gross_total, 2) }}</td>
      <td class="amount">{{ $cotisation['rate'] }}%</td>
      <td class="amount">- {{ number_format($cotisation['amount'], 2) }}</td>
    </tr>
    @endforeach
    @if($payroll->ir_amount > 0)
    <tr>
      <td>{{ __('pdf.income_tax', [], $locale) }}</td>
      <td class="amount">{{ number_format($payroll->gross_taxable, 2) }}</td>
      <td></td>
      <td class="amount">- {{ number_format($payroll->ir_amount, 2) }}</td>
    </tr>
    @endif
    @if($payroll->advance_deduction > 0)
    <tr>
      <td>{{ __('pdf.advance_deduction', [], $locale) }}</td>
      <td></td>
      <td></td>
      <td class="amount">- {{ number_format($payroll->advance_deduction, 2) }}</td>
    </tr>
    @endif
    @if($payroll->absence_deduction > 0)
    <tr>
      <td>{{ __('pdf.absence_deduction', [], $locale) }}</td>
      <td></td>
      <td></td>
      <td class="amount">- {{ number_format($payroll->absence_deduction, 2) }}</td>
    </tr>
    @endif

    {{-- NET --}}
    <tr class="net-row">
      <td colspan="3">{{ __('pdf.net_payable', [], $locale) }}</td>
      <td class="amount">{{ number_format($payroll->net_payable, 2) }} {{ $currency }}</td>
    </tr>

  </tbody>
</table>

{{-- ─── SOLDE CONGÉS ─── --}}
<div class="congé-box">
  {{ __('pdf.leave_balance', [], $locale) }} : <strong>{{ $employee->leave_balance }} {{ __('pdf.days', [], $locale) }}</strong>
</div>

{{-- ─── PIED DE PAGE ─── --}}
<div class="footer">
  {{ __('pdf.footer_legal', [], $locale) }}<br>
  {{ __('pdf.generated_by', [], $locale) }} Leopardo RH — {{ now()->locale($locale)->isoFormat('LLL') }}
</div>

</body>
</html>
```

---

## CLÉS DE TRADUCTION REQUISES — `lang/{locale}/pdf.php`

```php
// À créer pour : fr, ar, en, tr
return [
    'payslip_title'      => 'BULLETIN DE PAIE',
    'period'             => 'Période',
    'payslip_number'     => 'N° de bulletin',
    'employee_name'      => 'Nom et Prénom',
    'matricule'          => 'Matricule',
    'position'           => 'Poste',
    'department'         => 'Département',
    'contract_type'      => 'Type contrat',
    'hire_date'          => 'Date d\'embauche',
    'ss_number'          => 'N° Sécurité Sociale',
    'registration_number'=> 'N° d\'identification',
    'designation'        => 'Désignation',
    'base'               => 'Base',
    'rate'               => 'Taux',
    'amount'             => 'Montant',
    'section_gross'      => 'ÉLÉMENTS DE RÉMUNÉRATION',
    'base_salary'        => 'Salaire de base',
    'overtime'           => 'Heures supplémentaires',
    'gross_total'        => 'SALAIRE BRUT TOTAL',
    'section_deductions' => 'RETENUES ET COTISATIONS',
    'income_tax'         => 'Impôt sur le Revenu (IR)',
    'advance_deduction'  => 'Remboursement avance sur salaire',
    'absence_deduction'  => 'Retenue absences non justifiées',
    'net_payable'        => 'NET À PAYER',
    'leave_balance'      => 'Solde de congés restants',
    'days'               => 'jour(s)',
    'footer_legal'       => 'Ce bulletin de paie doit être conservé sans limitation de durée.',
    'generated_by'       => 'Généré par',
];
```

---

## TEMPLATE COMPLEMENTAIRE - RECU DE PERIODE (INFORMATIF)

Document distinct du bulletin officiel.

### Usage
- Generation depuis `QuickEstimateScreen` (manager).
- Objectif: preuve simple en cas de separation ou contestation.
- Statut legal: informatif uniquement.

### Contenu minimal obligatoire
- Identite entreprise + employe
- Periode choisie (`from` -> `to`)
- Jours travailles, heures totales, heures supplementaires
- Montant estime brut, deductions estimees, net estime
- Mention fixe:
  - "Document informatif - bulletin officiel emis en fin de mois."
- Zone de signature manager / employe (impression papier)

### Fichier cible conseille
- `api/resources/views/pdf/period-receipt.blade.php`
