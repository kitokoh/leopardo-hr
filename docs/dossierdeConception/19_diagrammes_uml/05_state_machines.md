# Machines à États — Workflows Critiques du Métier

> **Projet :** Leopardo RH v3.3.3
> **Date :** 2025
> **Statut :** Dossier de Conception — Diagrammes UML

Ce document décrit les machines à états (state machines) des cinq entités principales du système, modélisées en syntaxe Mermaid `stateDiagram-v2`. Chaque diagramme représente les états possibles, les transitions valides et les événements déclencheurs.

---

## 5.1 Avances sur Salaire (`salary_advances.status`)

```mermaid
stateDiagram-v2
    %% ============================================================
    %% Avances sur Salaire — Cycle de vie complet
    %% Table : salary_advances | Colonne : status
    %% ============================================================

    direction LR

    [*] --> pending : Création de la demande

    state pending {
        %% État initial : demande soumise par l'employé
        %% En attente de validation par le manager
    }

    state approved {
        %% Manager a validé : montant de la mensualité calculé
        %% Plan de remboursement généré (repayment_plan JSONB)
    }

    state active {
        %% Remboursement en cours
        %% La première paie a effectué une déduction
    }

    state repaid {
        %% amount_remaining = 0 après la dernière déduction
        %% État terminal — archive
    }

    state rejected {
        %% Manager a rejeté la demande
        %% État terminal — aucune réactivation possible
    }

    pending --> approved : Manager approuve\nPUT /advances/{id}/approve
    pending --> rejected : Manager rejette\nPUT /advances/{id}/reject

    approved --> active : Première déduction\nexécutée par la paie (auto)

    active --> repaid : Dernière déduction\namount_remaining = 0 (auto)

    approved --> rejected : Annulation\n(avant toute déduction)

    %% Styles pour les états terminaux
    repaid --> [*]
    rejected --> [*]
```

### Légende des transitions

| Transition | Déclencheur | Automatique / Manuel |
|---|---|---|
| `pending → approved` | Manager valide l'avance | Manuel (API) |
| `pending → rejected` | Manager rejette l'avance | Manuel (API) |
| `approved → active` | 1ʳᵉ déduction sur bulletin de paie | Auto (PayrollService) |
| `active → repaid` | Solde restant atteint 0 | Auto (PayrollService) |
| `approved → rejected` | Annulation avant toute déduction | Manuel (API) |

### Règles métier

- Un montant de mensualité est calculé automatiquement lors de l'approbation selon la formule : `amount / remaining_payroll_periods`
- Le champ `amount_remaining` est décrémenté à chaque paie par `PayrollService::deductAdvances()`
- L'état `rejected` est irréversible — aucune réactivation possible
- L'état `repaid` est irréversible — l'avance est clôturée

---

## 5.2 Absences (`absences.status`)

```mermaid
stateDiagram-v2
    %% ============================================================
    %% Absences — Cycle de vie
    %% Table : absences | Colonne : status
    %% ============================================================

    direction TB

    [*] --> pending : Soumission par l'employé

    state pending {
        %% Employé a soumis la demande
        %% En attente de décision du manager
    }

    state approved {
        %% Manager a approuvé
        %% leave_balance décrémenté si absence rémunérée
    }

    state rejected {
        %% Manager a rejeté
        %% Aucune modification du solde de congés
        %% État terminal
    }

    state cancelled {
        %% Employé a annulé (uniquement depuis pending)
        %% Solde re-crédité si l'absence était payée
        %% État terminal
    }

    pending --> approved : Manager approuve\nSolde décrémenté si payé
    pending --> rejected : Manager rejette\nAucun impact solde
    pending --> cancelled : Employé annule\n(depuis pending uniquement)

    %% États terminaux
    approved --> [*] : Date de fin passée
    rejected --> [*]
    cancelled --> [*]
```

### Légende des transitions

| Transition | Déclencheur | Impact congés |
|---|---|---|
| `pending → approved` | Manager valide | `leave_balance -= days_count` (si `is_paid`) |
| `pending → rejected` | Manager rejette | Aucun impact |
| `pending → cancelled` | Employé annule | Re-crédit si absence payée |
| `approved → [*]` | Date de fin atteinte | Clôture automatique |

### Règles métier

- L'annulation (`cancelled`) n'est possible que depuis l'état `pending`
- Les champs `decided_by` et `decision_comment` sont renseignés lors de la transition
- Le service `AbsenceService::approve()` décrémente le solde via `LeaveBalanceLog`
- Les justificatifs (`requires_justification`) sont obligatoires pour certains types

---

## 5.3 Employés (`employees.status`)

```mermaid
stateDiagram-v2
    %% ============================================================
    %% Employés — Cycle de vie
    %% Table : employees | Colonne : status
    %% ============================================================

    direction TB

    [*] --> active : Création du compte employé

    state active {
        %% État normal de travail
        %% L'employé peut pointer, poser des congés, etc.
    }

    state suspended {
        %% Suspension par l'admin (disciplinaire ou autre)
        %% Accès restreint — pointage désactivé
    }

    state archived {
        %% Suppression logique (soft delete)
        %% Données conservées pour l'historique
        %% Réactivation possible vers active
    }

    active --> suspended : Suspension\nPUT /employees/{id} (admin)
    suspended --> active : Réactivation\nPUT /employees/{id} (admin)

    active --> archived : Suppression logique\nDELETE /employees/{id} (admin)
    archived --> active : Réactivation\nPUT /employees/{id} (admin)

    %% Note : pas de transition directe suspended <-> archived
    %% Le passage par active est obligatoire
```

### Légende des transitions

| Transition | Déclencheur | Effet |
|---|---|---|
| `active → suspended` | Admin suspend l'employé | Désactivation accès + pointage |
| `suspended → active` | Admin réactive | Restauration complète des accès |
| `active → archived` | Admin supprime (soft delete) | `deleted_at` renseigné |
| `archived → active` | Admin réactive | `deleted_at = null` |

### Règles métier

- Il n'existe **pas** de transition directe entre `suspended` et `archived` — le passage par `active` est obligatoire
- La suspension désactive le pointage automatique (ZKTeco) et l'accès mobile
- L'archivage conserve toutes les données historiques (paies, absences, pointages)
- L'audit trail est conservé dans `audit_logs` quelle que soit la transition

---

## 5.4 Bulletins de Paie (`payrolls.status`)

```mermaid
stateDiagram-v2
    %% ============================================================
    %% Bulletins de Paie — Cycle de vie
    %% Table : payrolls | Colonne : status
    %% ============================================================

    direction LR

    [*] --> draft : Calcul par PayrollService

    state draft {
        %% Bulletin calculé mais non encore validé
        %% Montants calculés : brut, cotisations, IR, net
        %% Modifications possibles par l'admin
    }

    state validated {
        %% PDF généré et stocké
        %% Notification envoyée à l'employé
        %% Bulletin non modifiable
    }

    draft --> validated : Validation définitive\nPOST /payroll/validate\n(GeneratePayslipPdfJob complet)

    validated --> [*] : Archivage
```

### Légende des transitions

| Transition | Déclencheur | Processus |
|---|---|---|
| `draft → validated` | Admin valide la paie | `GeneratePayslipPdfJob` → PDF stocké → Notification |

### Règles métier

- Le calcul initial (`draft`) est effectué par `PayrollService::calculateForEmployee()`
- La validation déclenche le job `GeneratePayslipPdfJob` (queue Redis + Supervisor)
- Le PDF est stocké dans `payrolls.pdf_path` (stockage S3/local)
- Une notification de type `payslip_ready` est envoyée à l'employé
- Une fois validé, le bulletin est **verrouillé** — toute correction nécessite une régénération

---

## 5.5 Journal de Pointage (`attendance_logs.status`)

```mermaid
stateDiagram-v2
    %% ============================================================
    %% Journal de Pointage — Cycle de vie
    %% Table : attendance_logs | Colonne : status
    %% ============================================================

    direction TB

    state incomplete {
        %% check_in effectué, check_out en attente
        %% Statut temporaire, mis à jour à chaque check_out
    }

    state ontime {
        %% check_out effectué dans la plage horaire + tolérance
        %% Calcul : heures travaillées >= heures requises
    }

    state late {
        %% check_out effectué hors tolérance
        %% Ou check_in tardif > late_tolerance_minutes
    }

    state absent {
        %% Aucun check_in pour la journée
        %% Détection automatique en fin de journée
    }

    state leave {
        %% Absence approuvée ou congé posé
        %% Remplissage automatique par le système
    }

    state holiday {
        %% Jour férié public ou congé de l'entreprise
        %% Remplissage automatique par le système
    }

    %% Pointage en temps réel
    [*] --> incomplete : check_in effectué

    %% Transitions par check_out
    incomplete --> ontime : check_out dans\nplage horaire + tolérance
    incomplete --> late : check_out hors\ntolérance (auto)

    %% Transitions automatiques par le système (sans pointage)
    [*] --> absent : Fin de journée\nsans check_in (auto)
    [*] --> leave : Absence approuvée\nou congé posé (auto)
    [*] --> holiday : Jour férié public\nou congé entreprise (auto)

    %% Tous les états sont terminaux pour la journée
    ontime --> [*]
    late --> [*]
    absent --> [*]
    leave --> [*]
    holiday --> [*]
```

### Légende des transitions

| Transition | Déclencheur | Automatique |
|---|---|---|
| `→ incomplete` | Employé badge (check_in) | Non (action employé) |
| `incomplete → ontime` | Employé badge (check_out) dans les temps | Non → Auto classification |
| `incomplete → late` | Employé badge (check_out) hors tolérance | Non → Auto classification |
| `→ absent` | Fin de journée sans check_in | Oui (cron job) |
| `→ leave` | Absence approuvée pour la date | Oui (AbsenceService) |
| `→ holiday` | Jour férié dans le calendrier | Oui (HolidayService) |

### Règles métier

- La classification `ontime` vs `late` est basée sur le champ `schedules.late_tolerance_minutes`
- Les heures supplémentaires sont calculées selon `overtime_threshold_daily` et `overtime_threshold_weekly`
- Un enregistrement peut passer en `manual_edit` si un administrateur corrige manuellement (`is_manual_edit = true`)
- Le champ `method` enregistre le canal de pointage : `zkteco`, `gps`, `manual`
- Le cron `attendance:close-day` s'exécute chaque nuit pour détecter les absences

---

## Vue consolidée — Résumé des états

| Entité | États | Terminal(s) | Transitions auto |
|---|---|---|---|
| `salary_advances` | pending, approved, active, repaid, rejected | repaid, rejected | approved→active, active→repaid |
| `absences` | pending, approved, rejected, cancelled | approved, rejected, cancelled | approved→[*] (date fin) |
| `employees` | active, suspended, archived | — (cyclique) | — |
| `payrolls` | draft, validated | validated | — |
| `attendance_logs` | incomplete, ontime, late, absent, leave, holiday | Tous (journalier) | →absent, →leave, →holiday |
