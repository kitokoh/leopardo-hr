# 🐆 Rapport QA — Leopardo RH
**Session du 2026-08-17 · Mission « chef de projet » : test complet de l'app + tickets pour les devs**

---

## 1. Contexte & méthode

| Élément | Détail |
|---|---|
| Cible | `kitokoh/leopardo-hr` — main @ `bcf0ffd` (2026-08-17) |
| Surfaces live testées | Vitrine web `gestionemployer-backend.vercel.app` · API `gestionemployerbackend.onrender.com` (v4.24.0) · Admin `leo-admin.pages.dev` |
| Env local (replay du code main) | PHP 8.4.24 · PostgreSQL 14 · Redis 6 · Laravel 12 (API complète + base seedée avec les 3 sociétés démo) · Next.js 16 (vitrine/dashboard) |
| Méthode | Tests manuels UI (navigateur + captures) · tests API (edge cases, RBAC, tenant isolation) · exécution des suites de tests du repo · revue de code ciblée + vérification des audits précédents (08-15/08-16) |
| Périmètre non couvert | Apps Flutter (5) : non exécutables dans le sandbox (pas d'émulateur) ; kiosque ZKTeco : prototype local non déployé ; paiement Stripe réel : non déclenché (pas de transaction) |

**Note de confiance** : tous les findings « live » ont été reproduits à la main sur les déploiements de production ; les findings « code » ont été reproduits via l'API locale ET via les tests du repo (preuves citées).

---

## 2. Synthèse exécutive

> ⚠️ **Le funnel d'acquisition (trial + paiement) est intégralement cassé en production** : aucun prospect ne peut actuellement entrer dans le produit par le parcours self-service.

- 🔴 **1 régression P0 sur `main`** : la création d'employé (`POST /api/v1/employees`) renvoie **500** systématiquement — flux HR cœur bloqué, et **20 tests de la suite HR sont rouges** sur main (preuve CI locale).
- 🔴 **3 problèmes P1 en production** : trial guidé bloqué en « pending » (worker de queue mort), trial OTP → 500, checkout en mode sandbox affiché en prod.
- 🟡 2 incohérences P2 (pricing 14 vs 30 jours ; tunnel de paiement mort).
- 🟢 **Le reste est solide** : vitrine (design, i18n, formulaires), auth, RBAC, isolation tenant, attendance, absences — tous les findings des audits 08-15/08-16 sont **corrigés sur main** (équipe réactive).

**11 issues créées** sur GitHub : #4947 → #4957 (détail en §5).

---

## 3. Findings — détaillés

### 🔴 P0 — Bloquant

#### F1. `POST /api/v1/employees` → 500 INTERNAL_ERROR (création d'employé cassée sur main) — **#4947**
- **Repro** (API locale sur main, confirmé par les tests du repo) :
  `POST /api/v1/employees` avec `{first_name, last_name, email, contract_type, salary_type, salary_base, role, password}` → **500** ; idem avec `send_invitation: true` sans password.
- **Cause racine** : `EmployeeService::create()` retire `password_hash` du payload (`Arr::pull`, L65) **avant** `Employee::query()->create(...)` (L67), alors que la colonne est `NOT NULL` sans défaut → `SQLSTATE[23502]` → 500. Le `forceFill(['password_hash'=>...])->save()` (L71) arrive après l'INSERT raté. Régression de la famille de durcissement #4307/#4308/#4550.
- **Preuve CI** : `php artisan test tests/Feature/HR/` → **20 failed / 22 passed** (15× SQLSTATE[23502]) : EmployeeImportRaceTest, EmployeeMailResilienceTest, EmployeeNumericCastTest, EmployeePasswordProvisioningTest, EmployeeSensitiveFillableTest, EmployeeServiceCreateFillableTest, ExportHistoryTest.
- **Impact** : onboarding employé + import CSV + invitation → tout le flux HR d'entrée est bloqué.
- **Action** : réintroduire `password_hash` en un seul INSERT (pattern #3677/#4151), rétablir la suite HR verte, ajouter tests de non-régression.

### 🔴 P1 — Haute priorité

#### F2. Trial guidé bloqué en « pending » en prod (worker queue) — **#4948**
- **Preuve live** : `POST /trial/signup` (`guided_trial`) → `provisioning_sandbox` ; `GET /trial/status?token=…` reste **`pending` > 2h** (ni `ready` ni `failed` ⇒ le `ProvisionDemoTenantJob` n'est jamais exécuté). Aucun email de magic link reçu (inbox de test vide). Ligne de test créée en prod : email `qaleopardo20260817@emalupe.com`, token `qDdX7IWVCsGhfL6c46oT2DZHpZ6hvTIJwFykgbc8TfLZrqqctewBgueiymF74rz8` (consultable par l'équipe).
- **Cause probable** : worker `leopardo-queue-worker` (render.yaml) ne consomme pas la file `default`. Lié à l'épic #3765/#3766.
- **Impact** : promesse « sandbox < 30 s » non tenue ; le parcours self-service (le plus visible de la vitrine) ne délivre rien.

#### F3. Trial OTP (`self_service`) → 500 en live malgré #4874 — **#4949**
- **Preuve live** : `POST /trial/signup` avec `requestedWorkflow: "self_service"` → `500 INTERNAL_ERROR`. Soit #4874 (mergé) n'est pas déployé sur Render, soit le fix ne couvre pas le chemin explicite.
- **Rappel code** : `RequestTrialSignup::execute()` retourne `false` si l'envoi d'email échoue (fix #3057) mais le contrôleur ne gère pas ce retour proprement sur ce chemin (constat 11 de l'audit 08-15).

#### F4. Checkout en mode sandbox visible en production — **#4950**
- **Preuve live** : `/checkout?plan=pilot&billing=monthly` affiche l'encart ambre, la carte **4242 4242 4242 4242** pré-remplie et le badge « TEST CARD ». Le code ne l'affiche que si `NEXT_PUBLIC_CHECKOUT_SANDBOX=true` → **cette variable est activée sur l'env Vercel de prod** (contredit #2628 « never shown in production »).
- **Impact** : formulaire de paiement factice en prod ; + le bouton « Démarrer l'essai gratuit » échoue avec `CHECKOUT_UNAVAILABLE` (voir F6).

### 🟡 P2 — Moyenne

#### F5. Pricing : « 14-day free trial » vs « 30-day trial » sur la même page — **#4951**
- **Preuve live** : hero = « 14-day free trial — no credit card » ; carte Pilot = « 30-day trial. Up to 5 employees. ». Backend : `trial_days = 14`. Résiduel des épics #3012/#3516.

#### F6. Tunnel de paiement mort : `CHECKOUT_UNAVAILABLE` — **#4952**
- **Preuve live** : `POST /api/billing/checkout` → « Le paiement en ligne est temporairement indisponible ». Fail-closed voulu (#2628/#2665) sans clés Stripe, mais **résultat produit : tunnel d'achat mort** avec CTA en production. Décision produit requise (brancher Stripe ou masquer les CTA).

### 🟢 P3 — Améliorations

| # | Finding | Preuve | Issue |
|---|---|---|---|
| F7 | Admin : label « PLATFORM ADMINISTRATION • V4.16 » obsolète (API v4.24.0) | live, leo-admin.pages.dev | #4953 |
| F8 | Admin : bouton « Acces Demo » affiché en prod → échec garanti (demo mode off, `/demo-users` → 404) | live | #4954 |
| F9 | API : réponses 429 non localisées (« Too Many Attempts. ») | live, sous throttle | #4955 |
| F10 | /about : « 6 pays » vs **19 codes** gérés côté backend (DZ, MA, TN, FR, TR, SN, CA + CEMAC×6 + CEDEAO×6) | live + code | #4956 |
| F11 | OpenAPI spec non servie en prod (404) | live | déjà suivi #4842 |
| F12 | Vitrine live en retard sur main | live (fixes 15/08 pourtant déployés) | déjà suivi #4867 |

---

## 4. ✅ Ce qui fonctionne bien (vérifié)

- **Vitrine** : 20+ routes 200 ; design cohérent (landing, pricing, demo, contact, faq, docs, blog, careers) ; i18n FR/EN/TR/AR fonctionnel (`?lang=` + html lang correct) ; menu mobile propre ; formulaires (signup, demo, contact) avec états de succès/erreur ; login avec message d'erreur propre ; anti-bot canonicals/hreflang OK.
- **API (live)** : health OK (DB/Redis/storage/queue) ; headers de sécurité (HSTS, nosniff, X-Frame-Options, referrer-policy) ; validations 422 localisées ; anti-énumération (forgot-password, signup uniforme) ; register fermé sans invitation ; gate `demo-users` actif ; throttles présents (auth-sensitive, trial 5/15, web-login).
- **API (locale, code main)** : login/logout/me/profile ; dashboard summary/KPI ; employés (list, show, update, archive, search) ; **attendance** (check-in 201, check-out 200, today, anomalies, monthly-report) ; **absences** (create 201, approve/reject par RH, chevauchement 422, filtrage par employé) ; contrats, leave-balances, notifications ; onboarding checklist + launch-readiness (score 100) ; **RBAC** (employé bloqué sur /employees, /dashboard, /payroll ; accès limité à ses propres absences) ; **isolation tenant** (lectures cross-tenant → 403/404) ; pagination bornée (max 100) sur les contrôleurs vérifiés.
- **Tests** : Attendance 52/52 verts.
- **Audits précédents** : tous les findings 08-15/08-16 vérifiés → **corrigés sur main** (IDOR leave-balances, approvals sans policy, QR fail-open, bulk-pay fail-open, OAuth tenantless, exceptions brutes, throttles SSO, ATS dedup, canonicals, métriques inventées, études de cas sans disclaimer…). Le SSRF caméras a bien un blocklist IP (#3147/#3179).

---

## 5. Issues créées (#4947 → #4957)

| Issue | Sév. | Sujet |
|---|---|---|
| [#4947](https://github.com/kitokoh/leopardo-hr/issues/4947) | P0 | POST /employees → 500 (NOT NULL password_hash) + 20 tests HR rouges |
| [#4948](https://github.com/kitokoh/leopardo-hr/issues/4948) | P1 | Trial guidé bloqué « pending » en prod (worker queue) |
| [#4949](https://github.com/kitokoh/leopardo-hr/issues/4949) | P1 | Trial OTP self_service → 500 en live (post-#4874) |
| [#4950](https://github.com/kitokoh/leopardo-hr/issues/4950) | P1 | Checkout sandbox actif en production |
| [#4951](https://github.com/kitokoh/leopardo-hr/issues/4951) | P2 | Pricing 14 vs 30 jours |
| [#4952](https://github.com/kitokoh/leopardo-hr/issues/4952) | P2 | Tunnel de paiement KO (CHECKOUT_UNAVAILABLE) |
| [#4953](https://github.com/kitokoh/leopardo-hr/issues/4953) | P3 | Label version V4.16 admin |
| [#4954](https://github.com/kitokoh/leopardo-hr/issues/4954) | P3 | Bouton « Acces Demo » en prod |
| [#4955](https://github.com/kitokoh/leopardo-hr/issues/4955) | P3 | 429 non localisés |
| [#4956](https://github.com/kitokoh/leopardo-hr/issues/4956) | P3 | « 6 pays » vs 19 codes |
| [#4957](https://github.com/kitokoh/leopardo-hr/issues/4957) | — | Task : plan de correction + ordre de traitement |

**Tâches recommandées pour les devs (détail dans #4957) :**
1. **Dev API (P0)** : corriger `EmployeeService::create()` (password_hash en un seul INSERT), rétablir la suite HR verte, tests de non-régression (création avec/sans password, invitation, import).
2. **Ops (P1)** : vérifier le worker queue sur Render + drain des `trial_provisionings` pending (#4948) ; confirmer le déploiement de #4874 (#4949) ; retirer `NEXT_PUBLIC_CHECKOUT_SANDBOX` de l'env Vercel prod (#4950).
3. **Web (P2/P3)** : unifier la durée d'essai (#4951) ; trancher le tunnel Stripe (#4952) ; libellés admin (#4953/#4954) ; localiser le 429 (#4955) ; corriger le compteur de pays (#4956).

---

## 6. Limites de la session

- **Apps mobiles Flutter (5)** : non testées (pas d'émulateur Android dans le sandbox) — à couvrir via un run de tests Flutter en CI ou sur device.
- **Kiosque ZKTeco** : prototype local non déployé — non testable en prod.
- **Paiement Stripe réel** : non déclenché (pas de transaction) — le constat s'arrête à `CHECKOUT_UNAVAILABLE`.
- **Tests Payroll** : 14 échecs sur l'env local (PG 14 vs PG 16 probable pour les tests de triggers) — **à confirmer en CI** ; plusieurs échecs semblent en cascade du P0 création d'employé (les fixtures créent des employés).
- **Impact prod du P0** : la version déployée peut précéder la régression (dépend de l'état du dernier déploiement Render) — le code de `main` est cassé quoi qu'il en soit et le sera au prochain déploiement.

---
*Rapport généré le 2026-08-17 — session QA complète (vitrine live, API prod, replay local du code main, suites de tests, revue d'audits).*
