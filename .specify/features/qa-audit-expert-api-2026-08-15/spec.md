# Feature Specification: Audit expert API — 2026-08-15

**Feature Branch**: `qa-audit-expert-api-2026-08-15`

**Created**: 2026-08-15

**Status**: Draft

**Input**: Mission de test expert de la plateforme (session 2026-08-15) — audit du backend Laravel (`api/`) : routes vs contrôleurs vs OpenAPI, logiques métier, sécurité, i18n, cohérence config. Base : `main` (`8a57dbf8`).

Méthode : constitution `.specify/constitution.md` (spec-first, isolation multi-tenant, PHPStan strict level 8, PR = issue avec `Closes #`), workflow Spec Kit `specify → plan → tasks → implement`, conversion des tâches en issues GitHub (`qa-audit-2026-08-15`).

## User Stories & Testing

### User Story A1 — Le cycle approbation de congé fonctionne avec des soldes crédités (Priority: P1)

Aujourd'hui `AbsenceService::approve()` vérifie le solde sur la **chaîne de logs** (`LeaveBalanceLog`, dernier `balance_after`) alors que tous les chemins de **crédit** (`LeavePolicyController::credit()` → `LeaveBalance::increment('balance')`, accruals, carry-forward) n'écrivent **jamais** de log. Résultat : la **première** approbation d'un congé après n'importe quel crédit lève `INSUFFICIENT_LEAVE_BALANCE` (0.0 < days) — workflow RH de base cassé.

**Pourquoi P1** : aucune approbation de congé payé ne peut aboutir après un crédit de solde manuel/automatique.

**Test indépendant** : test Feature « crédit de solde puis approbation » → 200 et solde décrémenté ; la vérification utilise la même source que `currentAvailableBalance` (snapshot `LeaveBalance`) avec `lockForUpdate` dans la transaction ; les logs restent une piste d'audit.

**Acceptance Scenarios**:
1. **Given** un employé avec un solde crédité (via `POST /leave-balances/credit` ou job d'accrual), **When** un manager approuve une absence `deducts_leave`, **Then** l'approbation réussit (200), le solde est décrémenté, et un log d'audit `absence_approved` est écrit.
2. **Given** un solde insuffisant, **When** on approuve, **Then** `422 INSUFFICIENT_LEAVE_BALANCE` est retourné (comportement conservé).
3. **Given** deux approbations simultanées, **When** elles se chevauchent, **Then** aucune ne dépasse le solde (verrouillage `lockForUpdate`).
4. **Given** une absence d'un type qui ne déduit pas, **When** on approuve, **Then** aucun contrôle de solde n'est appliqué (inchangé).

### User Story A2 — Le webhook email-bounce est authentifié et fail-closed (Priority: P1)

`POST /api/v1/webhooks/email-bounce` compare le secret à `config('services.mail_bounce_webhook.secret')` — clé **absente** de `config/services.php` et de `.env.example` → `$configuredSecret === ''` → le contrôle est **toujours contourné** : n'importe qui peut marquer un employé `email_bounced_at` (lookup email non scopé tenant) et injecter des `CommunicationEvents` factices.

**Pourquoi P1** : sécurité — endpoint public non authentifié avec impact données RH.

**Test indépendant** : envoi sans header secret → 403 ; avec mauvais secret → 403 ; avec bon secret → 200 ; `config/services.php` contient la clé et `.env.example` la variable.

**Acceptance Scenarios**:
1. **Given** la clé `MAIL_BOUNCE_WEBHOOK_SECRET` absente de l'environnement, **When** un POST arrive sur `/webhooks/email-bounce`, **Then** réponse `403` (fail-closed) + log warning explicite.
2. **Given** la clé configurée, **When** un POST sans/mauvais secret, **Then** `403` ; avec le bon secret, **Then** traitement normal (200).
3. **Given** une requête valide, **When** l'email appartient à un autre tenant, **Then** aucune modification cross-tenant.

### User Story A3 — Les webhooks Stripe ne perdent plus d'événements en silence (Priority: P2)

`StripeWebhookController` renvoie `200` sur toute erreur de **traitement** (« prevent Stripe from retrying ») : une erreur transitoire (DB, fournisseur) fait disparaître l'événement sans retry ni dead-letter.

**Test indépendant** : test avec erreur simulée pendant le traitement → réponse 500 (Stripe retente) ; seule une signature invalide → 400/401.

**Acceptance Scenarios**:
1. **Given** un événement valide, **When** le traitement échoue (exception), **Then** réponse `500` pour déclencher le retry Stripe (ou enqueue vers une file de retry).
2. **Given** une signature invalide, **When** le webhook reçoit le POST, **Then** `400/401` (inchangé).

### User Story A4 — Le pointage est protégé contre les courses (race conditions) (Priority: P2)

`AttendanceService::checkIn/checkOut` est en check-then-act sans transaction/verrou/contrainte unique : deux check-in parallèles créent deux sessions ouvertes ; deux check-out parallèles ferment la même session (last-write-wins, heures recalculées avec des `now()` différents).

**Test indépendant** : test parallèle (deux requêtes simultanées) → une seule session ouverte ; index unique partiel `(employee_id, date, session_number)` ou `lockForUpdate` sur la session ouverte.

**Acceptance Scenarios**:
1. **Given** un employé sans session ouverte, **When** deux check-in arrivent en parallèle, **Then** une seule session est créée, l'autre reçoit un conflit (409/422).
2. **Given** une session ouverte, **When** deux check-out arrivent en parallèle, **Then** une seule fermeture aboutit, l'autre reçoit un état clair.

### User Story A5 — Le solde de congés est scopé à l'employé demandé (Priority: P2)

`GET /employees/{employeeId}/leave-balances` **ignore le paramètre de route** `{employeeId}` : le contrôleur lit `$request->get('employee_id')` — un manager qui demande le solde d'un employé reçoit **tous** les soldes de l'entreprise (exposition cross-team, contrat cassé).

**Test indépendant** : `GET /employees/42/leave-balances` → uniquement le solde de l'employé 42 ; l'ancien comportement (paramètre `employee_id` en query) est documenté/rétro-compatible.

**Acceptance Scenarios**:
1. **Given** un manager, **When** il appelle `/employees/{employeeId}/leave-balances`, **Then** seuls les soldes de cet employé sont retournés.
2. **Given** un employé d'une autre équipe, **When** on demande son solde sans permission, **Then** `404/403` (pas d'exposition).

### User Story A6 — Les jours de congé sont comptés selon la convention métier (Priority: P2)

`days_count = diffInDays(start, end) + 1` compte les **jours calendaires** (week-ends, fériés) à la fois pour la déduction du solde et l'indemnité — un congé vendredi→lundi consomme 4 jours alors que l'employé ne travaille que 2.

**Test indépendant** : test avec congé vendredi→lundi → `days_count` = jours ouvrés (calendrier entreprise) ; la convention est documentée par type d'absence.

### User Story A7 — La paie ne compte pas deux fois un congé chevauchant deux périodes (Priority: P2)

`PayrollCalculator::sumApprovedLeaveDays` additionne `days_count` **entier** de chaque absence approuvée chevauchant la période, sans clipping à `[period_start, period_end]` — une absence 25 janv. → 5 févr. est comptée en **totalité** dans les runs de janvier ET février (double déduction dans le prorata).

**Test indépendant** : golden test — absence chevauchante → jours comptés uniquement sur l'intersection.

### User Story A8 — Le contrat OpenAPI reflète les routes réelles (Priority: P2)

`openapi.yaml` est ~26 % derrière le routeur : 134 groupes de chemins non documentés (webhooks publics, `/public/careers/*`, `/trial/*`, `/user/*`, `/onboarding/invitation/{token}`, departments, payrolls, reports, conversations, announcements, marketing/posts) et des **noms de paramètres divergents** (`{webhook}` vs `{webhookEndpoint}`, `{loan}` vs `{loanId}`, `{session}` vs `{sessionId}`, `{employee}` vs `{employeeId}`, `{jobPosting}` vs `{id}`) qui cassent les clients générés.

**Test indépendant** : `check-openapi-coverage.sh` / parseur route↔OpenAPI → écart documenté en-deçà d'un seuil ; les noms de paramètres alignés sur les routes.

### User Story A9 — Les transitions de statut et les verrous protègent les données (Priority: P2)

- `AbsenceService::create()` : garde de solde en check-then-insert sans verrou → deux requêtes simultanées dépassent le solde.
- `ExpenseClaimController::approve()/reject()` : aucun contrôle de transition — un brouillon peut être approuvé, une demande approuvée peut être rejetée.
- `RequestTrialSignup` : `catch(Throwable)` avale les échecs d'envoi d'OTP et de création de `CompanyRequest` → l'utilisateur reçoit un OTP sans demande en attente (verify échoue ensuite) ou l'entreprise est créée deux fois.

**Test indépendant** : tests de concurrence (2 requêtes simultanées), tests de transitions (chaque état → états autorisés), test d'échec simulé d'envoi OTP → échec du signup (pas de demi-état).

### User Story A10 — Indemnité 1/10e paie : les bulletins « calculés » comptent (Priority: P2)

`referenceGross12Months` ne somme que les bulletins `status='validated'` alors que `calculateRun` crée des bulletins `'calculated'` — une entreprise qui ne valide jamais retombe en silence sur base×12 pour l'indemnité de congés.

**Test indépendant** : run avec bulletins `calculated` → indemnité 1/10e calculée sur la somme réelle.

### User Story A11 — Hygiène API : pas de fuite, pas de labels codés, des listes bornées (Priority: P3)

- `VerifyTrialSignup` renvoie le `temp_password` du manager dans le JSON (fuite potentielle via logs/proxy) → lien/token de reset à la place.
- Labels `work_state_label` codés en français (« Hors ligne », « En conge », « Absent ») dans `EmployeeController` → fichiers `lang/*.php`.
- `per_page` non borné sur leave-balances/accruals/payroll/expense → clamp min/max.
- `lang/ar/payroll.php` et `lang/tr/payroll.php` : 2 clés manquantes (`public_holidays_admin_only`, `public_holidays_company_only`).
- Routes `approve/reject/submit` enregistrées en PUT **et** POST → garder un seul verbe.
- `computeOvertimePay` arrondit le taux horaire à 2 décimales AVANT les multiplicateurs 1.25/1.5 (sous-paiement systématique) → précision complète jusqu'à l'arrondi final.
- `NON_WORK_TYPES` ne contient que `'break'` → une session fermée en `leave`/`holiday` compte dans `hours_worked`/heures sup.
- `executeCalculateRun` : ~5 requêtes par employé dans une transaction sans chunking → chunker/batch.
- `MarketingLeadController` : secret partagé fail-open si non configuré → loguer bruyamment au minimum.
- `KioskController::resolveAuthorizedKiosk` : `SET search_path TO shared_tenants,public` sans reset (`try/finally` manquant) → fuite d'état de connexion.
- Routes `/ai/*` : middleware `AITenantInjector` (attribute) remplace le middleware tenant → lier `current_company`.
- URLs de prod codées en dur (`wss://proxy.leopardo-rh.com` dans `config/cameras.php`, `admin@leopardo-rh.com` dans `config/demo.php`) → env uniquement.
- `POST /webhooks/{webhookEndpoint}/test` enregistré **deux fois** (ombre) → supprimer le doublon.
- Routes de notifications dupliquées (`PUT /notifications/read-all` vs `POST /notifications/mark-all-read`, `PUT .../read` vs `PATCH .../read`) → canonicaliser (une paire).

## Edge Cases

- Solde crédité par plusieurs chemins (credit manuel, accrual mensuel, carry-forward annuel) sans logs → la source de vérité du solde courant est `LeaveBalance.balance`.
- Webhook email-bounce : secret absent en local → 403 fail-closed + warning ; ne jamais casser la CI par manque de clé (tester avec clé factice).
- `leave-balances` : rétro-compat du paramètre `employee_id` en query si des clients l'utilisent.
- Webhook Stripe : les erreurs de signature doivent rester 400/401 (Stripe n'attend pas de retry) — seules les erreurs de traitement passent en 500.
- Paramètres OpenAPI : ne renommer que côté spec (aligner sur les routes), vérifier la génération SDK (`generate-openapi-sdk.mjs`).
