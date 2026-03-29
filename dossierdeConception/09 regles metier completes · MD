# RÈGLES MÉTIER COMPLÈTES — LEOPARDO RH
# Version 1.0 | Mars 2026
# Remplace : business_rules_document.md (supprimé — trop incomplet)

---

## 1. CALCUL DES HEURES TRAVAILLÉES

### 1.1 Formule de base
```
heures_brutes     = check_out - check_in                    (en heures décimales)
heures_nettes     = heures_brutes - (break_minutes / 60)    (soustraction pause)
heures_travail    = MAX(0, heures_nettes)                   (jamais négatif)
heures_sup        = MAX(0, heures_travail - overtime_threshold_daily)
```

### 1.2 Précision de calcul
- Toujours en **minutes entières** côté serveur, converti en décimal pour le stockage
- Exemple : 8h04min = 8.0667h → stocker 8.07 (arrondi 2 décimales)
- Jamais arrondir à l'heure entière — perte de précision inacceptable

### 1.3 Cas limites check-out
| Situation | Comportement attendu |
|-----------|---------------------|
| Employé oublie de pointer le départ | Laisser `check_out = NULL`, statut = `incomplete`. Alerte gestionnaire à H+1 après l'heure de fin configurée. Le gestionnaire saisit manuellement le départ. |
| Check-out saisi après minuit (nuit) | Autoriser — la date reste celle du check_in. Heures sup calculées normalement. |
| Check-out avant check_in (erreur de saisie manuelle) | Refuser avec erreur `CHECKOUT_BEFORE_CHECKIN`. Le gestionnaire doit corriger les deux valeurs. |
| Employé pointe arrivée deux fois le même jour | Le deuxième appel retourne `ALREADY_CHECKED_IN` (HTTP 409) avec l'heure du premier check-in. Suggérer un check-out. |
| Employé pointe départ sans avoir pointé arrivée | Retourner `MISSING_CHECK_IN` (HTTP 422). Pas d'enregistrement créé. |

---

## 2. CALCUL DU STATUT DE POINTAGE

### 2.1 Règles de statut
```
check_in ≤ heure_début + late_tolerance_minutes  →  status = 'ontime'
check_in >  heure_début + late_tolerance_minutes  →  status = 'late'
Aucun check_in dans la journée à H+1 de l'heure de début →  status = 'absent' (auto)
Journée dans une absence approuvée                →  status = 'leave'
Journée est un jour férié configuré               →  status = 'holiday'
check_in enregistré mais check_out NULL           →  status = 'incomplete'
```

### 2.2 Priorité des statuts
```
holiday > leave > absent > late > ontime > incomplete
```
Un employé en congé approuvé qui pointe quand même → statut reste `leave`, pointage enregistré, alerte gestionnaire.

---

## 3. CALCUL DES RETARDS ET PÉNALITÉS

### 3.1 Détection du retard
```
minutes_de_retard = MAX(0, check_in_minutes - (heure_début_minutes + late_tolerance_minutes))
```

### 3.2 Pénalités de retard sur la paie
La formule de pénalité est **configurable par l'entreprise** via `company_settings`.
Deux modes disponibles :

**Mode A — Proportionnel (par défaut)**
```
taux_journalier      = salaire_base / jours_ouvrables_du_mois
penalite_retard      = (minutes_de_retard / 60) × taux_horaire
taux_horaire         = salaire_base / (jours_ouvrables_du_mois × heures_journalières_attendues)
```

**Mode B — Forfait par tranche (configurable)**
```
Exemple configuré : 1-15 min retard = 0 pénalité (tolérance)
                    16-30 min retard = 0.5h de salaire déduit
                    31-60 min retard = 1h de salaire déduit
                    > 60 min retard  = demi-journée déduite
Les tranches sont définies dans company_settings['payroll.penalty_brackets'] (JSON)
```

### 3.3 Cas limites pénalités
- Les pénalités de retard ne peuvent jamais dépasser le salaire d'une journée
- Un retard sur un jour férié = 0 pénalité (le jour n'est pas travaillé)
- Un retard sur un jour de congé approuvé = 0 pénalité

---

## 4. CALCUL DES HEURES SUPPLÉMENTAIRES

### 4.1 Seuils configurables
```
overtime_threshold_daily   (défaut : 8.0h/jour)
overtime_threshold_weekly  (défaut : 40.0h/semaine)
```

### 4.2 Calcul journalier vs hebdomadaire
```
HS journalières  = MAX(0, heures_nettes_jour - overtime_threshold_daily)
HS hebdomadaires = MAX(0, Σ(heures_nettes_semaine) - overtime_threshold_weekly - Σ(HS journalières)
```
Les HS hebdomadaires sont calculées en fin de semaine (dimanche soir) par le scheduler.

### 4.3 Taux de majoration configurables
```
payroll.overtime_rate_1 = 1.25  (25% de majoration — ex: 2 premières heures sup)
payroll.overtime_rate_2 = 1.50  (50% de majoration — heures sup au-delà)
payroll.overtime_threshold_rate_change = 2  (nombre d'heures au taux 1 avant passage taux 2)
```

### 4.4 Montant des heures supplémentaires
```
taux_horaire = salaire_base / (jours_ouvrables_mois × heures_attendues_jour)
montant_HS   = (HS_taux_1 × taux_horaire × overtime_rate_1)
             + (HS_taux_2 × taux_horaire × overtime_rate_2)
```

---

## 5. CALCUL DE LA PAIE MENSUELLE

### 5.1 Formule complète
```
BRUT TOTAL
= salaire_base
+ overtime_amount         (heures supplémentaires du mois)
+ Σ(bonuses)              (primes ponctuelles + récurrentes)

COTISATIONS SALARIALES
= Σ(cotisation.rate × cotisation.base × cotisation.multiplier)
  plafonnée à cotisation.ceiling si défini

BRUT IMPOSABLE
= BRUT TOTAL - cotisations_déductibles (certaines cotisations non déductibles IR)

IR (IMPÔT SUR LE REVENU)
= Calcul par tranches sur BRUT IMPOSABLE - abattements configurés

NET AVANT RETENUES
= BRUT TOTAL - COTISATIONS_SALARIALES_TOTALES - IR

RETENUES DIVERSES
= advance_deduction       (mensualités d'avance dues ce mois)
+ absence_deduction       (absences non payées × taux journalier)
+ penalty_deduction       (pénalités retard si configurées)
+ Σ(deductions)           (mutuelles, prêts autres, etc.)

NET À PAYER
= NET AVANT RETENUES - RETENUES DIVERSES

Contrainte : NET À PAYER ≥ 0 (jamais négatif — les déductions sont plafonnées au net disponible)
```

### 5.2 Calcul des absences déduites
```
jours_absents_non_payés  = COUNT(absences) WHERE is_paid = false AND mois = période_paie
taux_journalier          = salaire_base / jours_ouvrables_du_mois
absence_deduction        = jours_absents_non_payés × taux_journalier
```

### 5.3 Cas limites paie
| Situation | Comportement |
|-----------|-------------|
| Employé sans aucun pointage du mois | Anomalie signalée en simulation — gestionnaire doit confirmer avant validation |
| Net à payer < 0 après déductions | Plafonner les déductions au net disponible. Solde reporté au mois suivant (advance_deduction restante dans repayment_plan) |
| Paie déjà validée et erreur découverte | Pas d'annulation possible. Créer une paie rectificative le mois suivant (ligne de correction explicite dans bonuses/deductions) |
| Employé embauché en cours de mois | Calcul prorata : salaire_base × (jours_ouvrables_travaillés / jours_ouvrables_totaux) |
| Employé partant en cours de mois | Même prorata avec date de départ |

---

## 6. RÈGLES DES CONGÉS

### 6.1 Acquisition mensuelle
```
// Exécutée le 1er de chaque mois par LeaveAccrueMonthly
jours_acquis = accrual_rate_monthly
// Condition : l'employé doit avoir au moins 1 jour de présence dans le mois précédent
// Un mois d'absence complète = pas d'acquisition
new_balance  = leave_balance + jours_acquis
if new_balance > max_balance : new_balance = max_balance   (plafonné)
```

### 6.2 Calcul des jours de congé demandés
```
jours_demandés = COUNT(jours ouvrables entre start_date et end_date INCLUS)
               - COUNT(jours fériés dans la période)
               - COUNT(week-ends selon work_days du planning de l'employé)
```

### 6.3 Validation du solde
```
// Au moment de la demande (serveur)
if jours_demandés > leave_balance AND type.deducts_leave : erreur INSUFFICIENT_LEAVE_BALANCE
```

### 6.4 Report annuel
```
// Exécuté le 31 décembre ou au 1er janvier par commande cron
if carry_over = false : leave_balance = 0  (remise à zéro)
if carry_over = true  : leave_balance = MIN(leave_balance, carry_over_max_days)
// Les jours au-delà du plafond de report sont perdus (ou indemnisés selon règle entreprise)
```

---

## 7. RÈGLES DES AVANCES SUR SALAIRE

### 7.1 Éligibilité
```
// Toutes ces conditions doivent être vraies
advance.enabled = true (company_settings)
employee.salary_base > 0
COUNT(advances WHERE status = 'approved' AND amount_remaining > 0) < max_simultaneous
DATE(now()) - DATE(dernière avance approuvée) >= min_delay_days
montant_demandé <= salary_base × (max_percentage / 100)
```

### 7.2 Clôture automatique (PayrollService)
```
// Exécutée à chaque calcul de paie pour chaque employé
pour chaque advance WHERE status = 'approved' AND amount_remaining > 0 :
  trouver la mensualité du mois courant dans repayment_plan
  si mensualité trouvée ET paid = false :
    déduire du net_salary
    marquer paid = true dans le JSON repayment_plan
    amount_remaining -= mensualité
    si amount_remaining <= 0 : status = 'repaid'
    TOUT dans DB::transaction()
```

---

## 8. GESTION DES JOURS FÉRIÉS ISLAMIQUES

### 8.1 Problème
Les fêtes islamiques sont mobiles (basées sur le calendrier lunaire).
La date change chaque année d'environ 11 jours en moins.

### 8.2 Solution implémentée
```
Dans hr_model_templates.holiday_calendar :
- is_recurring = true  : fête à date fixe (ex: 01-01 Nouvel An)
- is_recurring = false : fête avec date exacte sur l'année (ex: 2026-03-20 Aïd Al-Fitr)

Les fêtes is_recurring = false doivent être mises à jour chaque année
par le Super Admin via son panneau d'administration.
Un cron alert le Super Admin en novembre de chaque année pour
mettre à jour les dates de l'année suivante.
```

---

## 9. CAS LIMITES GÉNÉRAUX

### 9.1 Suppression d'un employé
```
Jamais de DELETE. Toujours : employees.status = 'archived'
Les données (pointages, paies, tâches) restent accessibles en lecture.
L'employé archivé ne peut plus se connecter.
Son email reste réservé (UQ) — ne peut pas être réutilisé pour un nouvel employé.
```

### 9.2 Changement de planning en cours de mois
```
Si le planning d'un employé change le 15 du mois :
  - Jours 1-14 : ancien planning pour le calcul des retards et HS
  - Jours 15-31 : nouveau planning
  - La paie agrège les deux périodes séparément
  - L'attendance_log garde le schedule_id du planning actif au moment du pointage
```

### 9.3 Création d'entreprise — ordre des opérations
```
1. Créer l'enregistrement dans public.companies
2. SELECT create_tenant_schema('company_{uuid}') via PostgreSQL
3. Appliquer le modèle RH du pays (INSERT dans company_settings)
4. Insérer les types d'absence standards (congé payé, maladie, maternité)
5. Insérer le planning de travail par défaut (8h-17h, Lun-Ven)
6. Créer le premier employé/gestionnaire (role=manager, manager_role=principal)
7. Envoyer l'email de bienvenue avec identifiants
TOUT dans une transaction — rollback complet si une étape échoue
```