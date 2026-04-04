# MVP-04 — Daily Summary + Quick Estimate + PDF Reçu
# Agent : Claude Code
# Durée : 4-6 heures
# Prérequis : MVP-03 vert (Pointage fonctionnel)

---

## CE QUE TU FAIS

Implémenter les 3 features qui font la VALEUR du MVP pour le persona Murat :
1. "Combien je dois aujourd'hui à cet employé ?"
2. "Combien je dois sur une période libre ?"
3. "Génère-moi un reçu PDF de cette estimation"

---

## AVANT DE COMMENCER

1. Lis `PILOTAGE.md` (5 min)
2. Vérifie : `php artisan test` → 0 failure
3. Lis `docs/dossierdeConception/05_regles_metier/05_REGLES_METIER.md` — section Calcul journalier

---

## ENDPOINTS À IMPLÉMENTER

| Méthode | Route | Description | RBAC |
|---------|-------|-------------|------|
| GET | `/api/v1/employees/{id}/daily-summary` | Résumé du jour | Manager: tous, Employee: self |
| GET | `/api/v1/employees/{id}/quick-estimate` | Estimation période | Manager: tous, Employee: self |
| GET | `/api/v1/employees/{id}/receipt` | PDF reçu de période | Manager: tous |

---

## LOGIQUE MÉTIER (dans EstimationService)

### Daily Summary
```
Entrée : employee_id, date (défaut = aujourd'hui)

Calcul :
1. Charger attendance_log du jour
2. Si pas de pointage → { status: "absent", estimated_wage: 0 }
3. Si check-in seulement → { status: "incomplete", hours: en_cours, estimated_wage: en_cours }
4. Si check-in + check-out → calcul complet :

   salary_type = 'fixed' :
     daily_wage = salary_base / jours_ouvrés_du_mois (22 par défaut)
     overtime_pay = overtime_hours * (daily_wage / 8) * overtime_rate_1

   salary_type = 'hourly' :
     daily_wage = hours_worked * hourly_rate
     overtime_pay = overtime_hours * hourly_rate * overtime_rate_1

   salary_type = 'daily' :
     daily_wage = salary_base (c'est déjà le taux journalier)
     overtime_pay = overtime_hours * (salary_base / 8) * overtime_rate_1

   estimated_total = daily_wage + overtime_pay

Sortie :
{
  employee_id, date,
  hours_worked, overtime_hours,
  daily_wage, overtime_pay, estimated_total,
  currency: company.currency,
  note: "Estimation indicative — montant final calculé en fin de mois"
}
```

### Quick Estimate
```
Entrée : employee_id, from (date début), to (date fin)

Calcul :
1. Charger tous les attendance_logs entre from et to
2. Pour chaque jour :
   - Si pointage complet → calculer daily_wage + overtime (même formule)
   - Si absent → 0
   - Si congé (Phase 2) → selon type
3. Sommer :
   - total_days_worked
   - total_hours
   - total_overtime_hours
   - estimated_gross
   - estimated_deductions (CNAS salariale = 9% du gross pour DZ)
   - estimated_net = gross - deductions

Sortie :
{
  employee: { id, name },
  period: { from, to, working_days, days_present },
  totals: { hours, overtime_hours, gross, deductions, net },
  currency,
  breakdown: [ { date, hours, wage, overtime }, ... ],
  disclaimer: "Estimation non officielle — le bulletin de paie fait foi"
}
```

### Receipt PDF
```
Entrée : employee_id, from, to (mêmes paramètres que quick-estimate)

Actions :
1. Calculer le quick-estimate
2. Générer un PDF avec DomPDF contenant :
   - En-tête : nom entreprise + logo si disponible
   - Titre : "Reçu de période (estimation)"
   - Employé : nom, matricule, poste
   - Période : du ... au ...
   - Tableau : chaque jour avec heures + montant
   - Total estimé
   - Mention : "CE DOCUMENT N'EST PAS UN BULLETIN DE PAIE OFFICIEL"
   - Date de génération + nom du manager qui l'a demandé
3. Retourner le PDF en téléchargement direct
```

---

## FICHIERS À CRÉER

```
app/Http/Controllers/EstimationController.php
app/Services/EstimationService.php
app/Http/Resources/DailySummaryResource.php
app/Http/Resources/QuickEstimateResource.php
resources/views/pdf/receipt.blade.php
```

---

## TESTS À ÉCRIRE

```php
// tests/Feature/Estimation/DailySummaryTest.php
it('returns daily wage for fixed salary employee');
it('returns daily wage for hourly employee');
it('returns 0 for absent day');
it('includes overtime pay');
it('employee can see own summary');
it('manager can see any employee summary');
it('employee cannot see other employee summary');

// tests/Feature/Estimation/QuickEstimateTest.php
it('returns correct total for a week');
it('returns correct CNAS deduction for DZ');
it('handles period with no attendance');
it('handles period with partial attendance');

// tests/Feature/Estimation/ReceiptPdfTest.php
it('generates a PDF file');
it('PDF contains disclaimer text');
it('manager can generate receipt for any employee');
it('employee cannot generate receipt');
```

---

## RÈGLES CRITIQUES

- Tous les montants affichés avec la devise de l'entreprise (`company.currency`)
- Arrondir à 2 décimales
- Le reçu PDF DOIT contenir la mention "NON OFFICIEL" en gras
- Les heures sup utilisent `overtime_rate_1` = 1.25 par défaut (configurable via company_settings)
- Les jours ouvrés par mois = 22 par défaut (configurable)
- Pour le MVP : pas de pénalités retard, pas de cotisations complexes
- Cotisation MVP = CNAS salariale 9% du brut (Algérie uniquement)

---

## PORTE VERTE → MVP-05

```
[ ] php artisan test → 0 failure (incluant MVP-01 à MVP-03)
[ ] GET /employees/{id}/daily-summary → montant estimé correct
[ ] GET /employees/{id}/quick-estimate → totaux période corrects
[ ] GET /employees/{id}/receipt → PDF téléchargeable
[ ] Calcul overtime correct (heures * taux * 1.25)
[ ] RBAC : employee voit self, manager voit tous
[ ] PDF contient mention "NON OFFICIEL"
```

---

## COMMIT

```
feat(estimation): daily summary + quick estimate with CNAS deduction
feat(pdf): receipt PDF with DomPDF for period estimation
test(estimation): daily wage, overtime, quick estimate, tenant isolation
```
