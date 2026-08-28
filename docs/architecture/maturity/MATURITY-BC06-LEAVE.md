# Rapport de maturité — BC-06 LEAVE

> **DEP-BC06 (issue #5882)** — Deep maturity, BC-06 Leave & Absence.
> Audité le 2026-08-28 (main `8b3609f`). Agent propriétaire : wave maturité.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-06).

## Périmètre

LEAVE = congés, absences, soldes, justificatifs et validations. Implémentation
répartie : module `api/app/Modules/Absence` (contrôleurs/requests/providers —
squelette DDD) + modèles dans `api/app/Modules/Planning/Domain/Models`
(`Absence`, `AbsenceType`, `LeaveBalance`, `LeaveBalanceLog`, `LeavePolicy`,
`LeaveAccrual`) + `AbsenceService`/`ApproveLeave` (Infrastructure/Application).
Routes `/api/v1/absences/*`, events `AbsenceApproved`/`AbsenceRejected`/
`AbsenceRequested`.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟡 PARTIEL | Vocabulaire cohérent (Absence/AbsenceType/LeaveBalance/LeavePolicy) mais **domaine hébergé dans le module Planning** (BC-05) : couplage de propriété à répartir (recommandation 1). Pas de glossaire BC-06. |
| D2 | Données | 🟢 PRÉSENT | Tables tenant migrées (`absences`, `leave_balances`, `leave_balance_logs`, `absence_types`, `leave_policies`), `company_id` partout, snapshot `leave_balances` = source de vérité du solde (issue #2666). |
| D3 | Tenant | 🟢 PRÉSENT | `Absence`/`LeaveBalance`/`LeaveBalanceLog` utilisent `BelongsToCompany` + checks explicites `company_id !== actor->company_id → 404` sur show/approve/reject/proof/destroy. Routes sous middleware `tenant`. |
| D4 | API | 🟢 PRÉSENT | Routes complètes `/absences` (index, store, show, proof, approve, reject, update, destroy) avec Requests dédiées (`StoreAbsenceRequest`, `RejectAbsenceRequest`, `AbsenceIndexRequest`), documentées OpenAPI (couverture verrouillée). |
| D5 | Autorisation | 🟢 PRÉSENT | Fail-closed : non-manager ne voit que ses absences (403 sur les absences d'autrui), cross-tenant → 404. Approbation par manager uniquement. Pas de Policy formelle (checks inline) — acceptable, à consolider en Policy (recommandation 2). |
| D6 | Transactions | 🟢 PRÉSENT | `AbsenceService::approve` transactionnel : déduction du solde sur snapshot verrouillé (`lockForUpdate`, formule balance − used − pending), rejet si solde insuffisant, transition `pending` seule (double approbation → 422). |
| D7 | Asynchronisme | 🟡 PARTIEL | Events `AbsenceApproved`/`AbsenceRejected`/`AbsenceRequested` dispatchés (synchrones). Pas de job/outbox spécifique (MAT-008 en cours). |
| D8 | Sécurité | 🟢 PRÉSENT | Justificatifs (proof) servis via endpoint dédié avec contrôle cross-tenant (404) — pas de fichier exposé hors autorisation ; `rejected_reason` borné. |
| D9 | Frontend | 🟢 PRÉSENT | Web client (formulaire demande + liste, #5693) + mobile employee (soldes, demandes) + manager (validations). Non audité en profondeur. |
| D10 | Performance | 🟢 PRÉSENT | Index tenant-first (convention #1613), requêtes bornées, verrou de ligne ciblé sur la déduction. Budgets p95/p99 non versionnés (MAT-014 en cours). |
| D11 | Exploitation | 🟢 PRÉSENT | Runbooks RH existants, observabilité structurée globale. |
| D12 | Produit | 🟡 PARTIEL | Parcours golden « solde → demande → justificatif → validation → déduction → log » bien testé unitairement (8 fichiers de tests) mais pas de golden journey end-to-end versionné (MAT-013 en cours). |

## Correctif livré (PR de ce DEP)

**Verrouillage test de l'isolation cross-tenant et des invariants du workflow
congés** (D3/D5/D6/D8) :

- `api/tests/Feature/Absences/LeaveCrossTenantIsolationTest.php` (4 scénarios,
  deux tenants A/B) :
  - `GET /absences` (manager A) → uniquement les absences du tenant A ;
  - `GET /absences/{idB}` → **404** (jamais de fuite de détail) ;
  - `POST /absences/{idB}/approve` → **404** (jamais de décision cross-tenant) ;
  - approbation d'une absence `deducts_leave` du tenant A → solde déduit
    **exactement une fois** (`days_count` retiré du snapshot), statut `approved`,
    une seule entrée `leave_balance_logs`, seconde approbation → **422**.

## Recommandations (non bloquantes, PR futures)

1. **Répartir le domaine congés** (D1) : déplacer les modèles `Absence*`/
   `LeaveBalance*`/`LeavePolicy` vers un module `Absence` complet, ou acter le
   partage dans le registre BC-05/BC-06 (contrat explicite).
2. **Consolider l'autorisation en Policies formelles** (D5) :
   `AbsencePolicy` (viewAny/view/approve/reject/update/destroy) avec les checks
   cross-tenant — centralise les `abort(404)` inline du contrôleur.
3. **Idempotence de la déduction** (D6) : le verrou `lockForUpdate` protège la
   course, mais l'approbation rejouée après crash intermédiaire (commit parti)
   doit être prouvée idempotente (test de rejeu).
4. **Golden journey congés** (D12) : parcours end-to-end versionné
   solde → demande → validation → déduction → consultation, avec justificatif.

## Non-régression

Aucun code de production modifié — correctif purement contractuel (tests +
rapport). Comportement existant (fail-closed 404/403, déduction transactionnelle)
verrouillé tel quel.
