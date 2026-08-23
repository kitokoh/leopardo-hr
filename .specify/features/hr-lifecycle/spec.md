# Feature Specification — Cycle de vie employé complet (issue #5258)

**Issue** : [#5258 [P1][HR][spec-kit] HR 100 % — cycle de vie employé complet (embauche → contrat → évolution → départ)](https://github.com/kitokoh/leopardo-hr/issues/5258)

**Feature Branch** : `mod/hr/5258-employee-lifecycle`

**Created** : 2026-08-23

**Status** : Draft — en attente de validation fondateur (DoD « spec approuvée »)

**Module** : HR (`api/app/Modules/HR/**`). Contraintes : 1 agent = 1 module (PLAN_100PCT §2) ; le **départ touche Payroll** → l'interface seule est spécifiée ici, l'implémentation Payroll appartient à `mod/payroll/*`.

**Sources vérifiées le 2026-08-23** :
- Modèles : `api/app/Modules/HR/Domain/Models/` (16 modèles — l'issue en comptait 14, +`CareerEvent` #5259, +`UserEmployeeLink`)
- Routes : `api/routes/modules/rh.php`, `api/routes/modules/hr_extended.php`
- Actions : `ContractLifecycleAction` (activate/suspend/terminate/renew), `EmployeeService::archive`
- Payroll (interface départ) : `api/app/Modules/Payroll/Interfaces/Api/V1/EndOfContractController.php` (settlement + certificate), `AlgeriaPayrollRules::noticePeriodDays()/severanceMonthsPerYear()`
- Docs : `docs/HR/RBAC_MATRIX.md`, `docs/HR/GUIDE_UTILISATEUR.md`, `docs/payroll/DZ_COMPLIANCE.md`

---

## 1. État des lieux — étapes du cycle sur `main` (audit 2026-08-23)

| Étape du cycle | Statut | Preuve |
|---|---|---|
| **Embauche** (création, import CSV, template) | ✅ Fait | `POST /employees`, `POST /employees/import`, `GET /employees/import-template` (RBAC : principal/rh) |
| **Onboarding** (étapes, invitations, QR) | ✅ Fait | `OnboardingStep/Progress/UserInvitation`, QR onboarding (scan-emp/création) |
| **Dossier employé** (fiche, profil étendu, métadonnées) | ✅ Fait | `employees` + migrations `extended_profile_and_biometrics`, `add_metadata` ; `GET/PUT /employees/{id}` |
| **Contrats** (CRUD, cycle, signature, PDF, pays) | ✅ Fait | `ContractController` + `ContractLifecycleAction` (draft→active/suspended/terminated/expired), `POST /contracts/{id}/sign` idempotent, templates pays #5260, PDF |
| **Évolutions** (promotion, augmentation, transfert) | ✅ Fait (#5259) | `career_events`, workflow `pending→approved→applied/rejected`, `PUT /career-events/{id}/approve|reject|apply`, impact `salary_base` |
| **Évaluations** (cycles de performance) | ✅ Fait | `draft → submitted → acknowledged` + RBAC |
| **Départ** (préavis, solde de tout compte, attestation) | ⚠️ **Partiel — aucun workflow formel HR** | Payroll fournit `GET /employees/{id}/end-of-contract` (settlement) + `certificate-of-employment` ; HR n'a que `archive` (statut) et `terminate` (contrat) — **aucune orchestration, aucun suivi de préavis, aucun enregistrement de départ** |

**Conclusion** : le trou de 100 % est le **départ employé** (workflow formel : décision → préavis → solde de tout compte → attestation → statut/archivage), plus la **cohérence contrat ↔ statut employé** et la **documentation des documents requis par étape**.

## 2. Besoin métier

- Un manager RH/principal enregistre un **départ** (démission, licenciement, fin de CDD, retraite) avec motif, date de dernier jour, préavis (servi ou non).
- Le workflow produit **dans l'ordre** : le préavis (calcul selon pays/ancienneté), le **solde de tout compte** (interface Payroll, endpoint existant), l'**attestation d'emploi** (interface Payroll, endpoint existant), puis le changement d'**état employé** (`active → departed → archived`).
- Chaque étape du cycle produit les **documents requis** (matrice §5) — l'employé et le manager les retrouvent dans le self-service (`/me/career` étendu : historique unifié événements + contrats + évaluations + départ).
- **Anti-régression** : jamais de calcul paie dans HR (constitution §III) — HR orchestre, Payroll calcule.

## 3. Machine à états — cycle de vie employé

```
        ┌────────────┐   ┌───────────┐   ┌────────────┐   ┌──────────────┐
embauche │  pending   │──▶│  active   │──▶│  departed  │──▶│  archived    │
        └────────────┘   └───────────┘   └────────────┘   └──────────────┘
              │                │  ▲             │                 │
              │                │  │ suspendu    │  (rétention légale
              │                ▼  │             ▼                 5 ans)
              │           ┌───────────┐        ┌──────────────┐
              │           │ suspended │        │  (réembauche  │
              │           └───────────┘        │  = nouveau    │
              │                                │  cycle)       │
              └───────────────▶ (échec onboarding → archived)
```

- `pending` : créé via onboarding/QR (statut par défaut à la création).
- `active` : dossier complet + contrat actif (activation manuelle RH ou auto à la première présence).
- `suspended` : contrat suspendu (CDD coupure) — pas de paie.
- `departed` : **NOUVEAU** — introduit par le workflow de départ (voir §6). Bloque toute action paie active.
- `archived` : fin de rétention légale ; données conservées (audit), plus aucun accès applicatif.

Transitions (matrice RBAC existante `docs/HR/RBAC_MATRIX.md`) :
| Transition | Acteur | Garde |
|---|---|---|
| pending → active | principal/rh | contrat actif + email confirmé |
| active → suspended | principal/rh | contrat `suspend` (action existante) |
| active → departed | principal/rh | workflow départ complet (§6) — SdC généré |
| departed → archived | principal (auto après rétention) | délai légal (DZ : 5 ans, cf. archivage comptable) |
| pending/active → archived | principal/rh | abandon / départ sans SdC (archive existante) |

## 4. Contrats — cycle & cohérence avec l'employé

| État contrat | Action existante | Impact employé |
|---|---|---|
| draft | création | — |
| active | `activate` (signature) | → `employees.status = active` (si contrat principal) |
| suspended | `suspend` | → `employees.status = suspended` |
| terminated | `terminate(reason)` | → **déclenche le workflow de départ** (§6) si c'est le dernier contrat actif |
| expired | auto (`renew`/date fin) | → alerte `GET /contracts/expiring` (existant) |

**Gap G4** : la cohérence contrat ↔ statut employé n'est pas automatisée (aujourd'hui deux actions manuelles distinctes). À orchestrer dans l'action HR de cycle de vie (pas dans Payroll).

## 5. Documents requis par étape (matrice)

| Étape | Document | Source | Statut |
|---|---|---|---|
| Embauche | Contrat signé | PDF `contract.blade.php` (généré) | ✅ |
| Embauche | Fiche employé (profil étendu) | `GET /employees/{id}` | ✅ |
| Évolution | Décision de carrière (approbation) | `career_events.approved_at` + audit | ✅ (#5259) |
| Départ | Solde de tout compte | Payroll `EndOfContractController::settlement` | ✅ (interface) |
| Départ | Attestation d'emploi | Payroll `EndOfContractController::certificate` | ✅ (interface) |
| Départ | **Enregistrement de départ (motif, dates)** | **❌ absent — G1** | ❌ |
| Départ | **Récapitulatif préavis (calcul + suivi)** | **❌ absent — G2** | ❌ |
| Toutes | **Checklist documents du dossier employé** | **❌ absent — G3** | ❌ |

## 6. Workflow de départ (l'écart central) — périmètre & interface Payroll

### 6.1 Périmètre HR (à implémenter dans une issue de complétion G1)
1. **Enregistrement** : `POST /employees/{id}/departure` (motif, dernier jour travaillé, préavis servi oui/non, indemnités particulières) → **nouvelle table tenant `employee_departures`** (ou colonnes sur `employees` — arbitrage modèle) + statut `departed`.
2. **Ordonnancement** : le workflow HR appelle **dans l'ordre** les endpoints Payroll existants (settlement → certificate) — HR ne recalcule JAMAIS (constitution §III).
3. **Audit** : `audit_logs` (action `hr.departure`) + historique visible côté employé (`/me/career`).
4. **Blocage paie** : tout run de paie postérieur au `departed_at` doit exclure l'employé (contrat via Payroll, à valider côté Payroll — l'interface est l'événement `employee.departed`).

### 6.2 Interface Payroll (contract, pas d'implémentation HR)
| Besoin HR | Endpoint Payroll existant | Signature |
|---|---|---|
| Solde de tout compte | `GET /employees/{id}/end-of-contract?departure_reason=…&notice_served=…` | `settlement(Request, Employee)` — contexte départ optionnel (#1943), préavis 0 si absent (prudent) |
| Attestation d'emploi | `GET /employees/{id}/certificate-of-employment` | `certificate(Request, Employee)` → PDF |
| Préavis (règle pays) | `AlgeriaPayrollRules::noticePeriodDays()` (22/44 j ouvrés, DZ) | consommé côté Payroll — **HR affiche la valeur, ne la calcule pas** |

**Événement de sortie HR → Payroll** : `employee.departed` (payload : employee_id, departed_at, departure_reason, contract_id) — consommateur Payroll (exclusion des runs) à implémenter **côté Payroll** (`mod/payroll/*`), hors périmètre HR.

## 7. Inventaire des gaps → issues de complétion

| # | Gap | Livrable | Issue proposée |
|---|---|---|---|
| G1 | Workflow de départ formel (enregistrement, statut `departed`, ordonnancement SdC → attestation, audit, self-service) | table `employee_departures` + action + endpoints HR + i18n ×4 + tests | **#5258-A** |
| G2 | Récapitulatif préavis (calcul par pays via Payroll, suivi servi/non servi) | endpoint HR en lecture + doc | **#5258-B** |
| G3 | Checklist documents du dossier employé par étape (upload/liens vers générés) | table `employee_documents` + endpoints | **#5258-C** (à coordonner avec Cabinet #5223 si upload mutualisé) |
| G4 | Cohérence contrat ↔ statut employé (orchestration dans l'action de cycle de vie) | refactor `ContractLifecycleAction` → met à jour employé | **#5258-D** |
| G5 | Historique unifié du cycle (`/me/career` : événements + contrats + évaluations + départ) | extension `SelfServiceController` + tests | **#5258-E** (couplé à G1) |
| G6 | Payroll : exclure les `departed` des runs + SdC intégré au slip | **module Payroll — hors périmètre HR** | à créer par l'agent Payroll (#5246 voisin) |

## 8. DoD (issue #5258)

- [x] Spec `.specify/features/hr-lifecycle/spec.md` écrite (états, transitions, documents requis) — **ce document**
- [x] Inventaire des gaps du module HR (16 modèles audités, §1) — **§7**
- [ ] **Spec approuvée** (fondateur) — les gaps deviennent des issues de complétion (#5258-A → E ; G6 → Payroll)
- [ ] CHANGELOG : une ligne en tête d'`[Unreleased]` pour ce livrable spec
- [ ] PR avec `Closes #5258` (cette spec seule — aucun code applicatif dans cette PR)

## 9. Références

- Issue : https://github.com/kitokoh/leopardo-hr/issues/5258
- Protocole : `docs/plan/PLAN_100PCT.md` §2 (1 agent = 1 module, branche `mod/hr/<ref>`, lock)
- Constitution : `.specify/constitution.md` §I (spec-first), §II (tenant), §III (calcul à la main — Payroll uniquement)
- RBAC : `docs/HR/RBAC_MATRIX.md` (7 rôles × ressources, masquage salarial défensif #5310)
