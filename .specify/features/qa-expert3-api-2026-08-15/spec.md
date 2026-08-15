# Feature Specification: QA Expert #3 — API (api/) (2026-08-15)

**Feature**: `qa-expert3-api-2026-08-15`
**Created**: 2026-08-15
**Status**: In progress
**Input**: Constitution `.specify/constitution.md` + AGENTS.md + tests black-box live (Render) + revue statique + suite de tests locale.

## Contexte

Troisième vague de test expert de la mission propriétaire (tester « dans tous les sens », consigner chaque manquement selon la méthode Spec Kit, implémenter, merger le maximum). Findings **nouveaux ou confirmés live** ; règle anti-doublon #2400 appliquée (issues + branches vérifiées).

## Findings traités dans cette vague

### #3055 [P2] — GET /employees/{id}/leave-balances sans garde de rôle — **CONFIRMÉ LIVE, CORRIGÉ**
> **Constat live** : employé `karim.aouad@…` lit les soldes du manager `ahmed.benali@…` (`GET /employees/1/leave-balances` → 200).
> **Correctif** : garde `isManager() || self` (403 sinon) — PR #3214, suite `LeaveBalancesRoleGuardTest`.

### #3056 [P2] — essai : réponse verify days=30 mais ends_at=+14j — **CORRIGÉ**
> **Correctif** : `days=30` + `ends_at=+30j`, `trial_days=30` (ProvisionGuidedTrial, PlanSeeder) — PR #3343 (spec #2909 : offre canonique 30 j).

### #3057 [P2] — échec envoi OTP trial avalé → 200 « Code envoyé » sans code — **À IMPLÉMENTER**
> **Preuve** : `RequestTrialSignup` avale l'échec mail (try/catch silencieux) → 200 sans code, pas de resend.

### #3058 [P2] — webhook email-bounce : `services.mail_bounce_webhook.secret` absent de config/.env.example → 503 permanent — **À IMPLÉMENTER**

### #3059 [P3] — per_page non bornés (11+ endpoints) — **CORRIGÉ**
> **Correctif** : `min(100, max(1, per_page))` sur 18 contrôleurs — PR #3420.

### #3060 [P3] — clé signature QR en dur en fallback — **CORRIGÉ**
> **Correctif** : fail-closed (RuntimeException si APP_KEY vide) — PR #3214.

### #3061 [P3] — drift OpenAPI résiduel groupes PA2/platform — **À IMPLÉMENTER**

### #3062 [P3] — route /training/sessions → `indexAllSessions` inexistant (500) — **CORRIGÉ**
> **Correctif** : route → `indexSessionsAll` — PR #3420.

### #3063 [P3] — LeavePolicyController dupliqué Absence vs Planning — **PARTIEL**
> La copie Absence est maintenant gardée (#3055). Déduplication complète hors périmètre.

### #3064 [P3] — drift docs↔code : RBAC_ROUTE_MATRIX vs /onboarding-setup* — **À IMPLÉMENTER**

### #3065 [P3] — POST /employees/link-user : employee_id cross-tenant non validé — **CORRIGÉ**
> **Correctif** : employé doit appartenir à la société de l'acteur (404) — PR #3214, suite `UserEmployeeLinkCrossTenantTest`.

### NOUVEAU — test `TrainingGlobalListTest` cassé sur main (end_date NOT NULL absent du fixture cross-tenant) — **CORRIGÉ**
> Correctif inclus dans PR #3420. Détecté via exécution locale de la suite complète.

## Critères de succès
- Checks requis verts sur main (PHPStan strict level 8, coverage backend ≥ 65 %).
- Aucun endpoint sensible sans garde de rôle (audit RBAC route↔contrôleur).
- Suite complète locale verte (hors environnement).

## Non couverts (décisions)
- #2695 (creds démo dans LoginView) : par conception (AGENTS.md v4.16.250).
- #3053 (ThemeMode.dark) : décision produit PA2-MOB-012.
