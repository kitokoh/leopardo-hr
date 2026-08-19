# 📋 Plan d'action semaine — Leopardo RH (issues QA #4947 → #4957)
**Pilotage : session QA du 2026-08-17 · Semaine cible : 2026-08-18 → 2026-08-22**

---

## 1. Priorisation

| Rang | Issue | Sév. | Sujet | Effort | Dépendance | Qui |
|---|---|---|---|---|---|---|
| 1 | [#4947](https://github.com/kitokoh/leopardo-hr/issues/4947) | P0 | Création d'employé → 500 (+20 tests HR rouges) | M | — (débloque CI + onboarding) | Dev API |
| 2 | [#4950](https://github.com/kitokoh/leopardo-hr/issues/4950) | P1 | Checkout sandbox actif en prod | **S** (config) | Accès Vercel | Ops |
| 3 | [#4948](https://github.com/kitokoh/leopardo-hr/issues/4948) | P1 | Trial guidé bloqué « pending » (worker queue) | S-M (diag) | Accès Render | Ops |
| 4 | [#4949](https://github.com/kitokoh/leopardo-hr/issues/4949) | P1 | Trial OTP → 500 en live | S-M | Vérif déploiement #4874 | Dev API |
| 5 | [#4952](https://github.com/kitokoh/leopardo-hr/issues/4952) | P2 | Tunnel de paiement KO | **Décision produit** puis M (Stripe) ou S (masquer CTA) | Décision + accès Stripe | PM/Produit + Ops |
| 6 | [#4951](https://github.com/kitokoh/leopardo-hr/issues/4951) | P2 | Pricing 14 vs 30 jours | S | — | Dev Web |
| 7 | [#4955](https://github.com/kitokoh/leopardo-hr/issues/4955) | P3 | 429 non localisés | S-M | — | Dev API |
| 8 | [#4953](https://github.com/kitokoh/leopardo-hr/issues/4953) | P3 | Label V4.16 admin obsolète | S | — | Dev Web/Admin |
| 9 | [#4954](https://github.com/kitokoh/leopardo-hr/issues/4954) | P3 | Bouton « Acces Demo » en prod | S | — | Dev Web/Admin |
| 10 | [#4956](https://github.com/kitokoh/leopardo-hr/issues/4956) | P3 | « 6 pays » vs 19 codes | S | — | Dev Web |
| — | [#4957](https://github.com/kitokoh/leopardo-hr/issues/4957) | — | Plan de correction (suivi) | — | — | PM |

**Logique de priorisation**
1. **#4947 d'abord** : il casse CI (20 tests rouges sur main) — tant qu'il n'est pas mergé, aucune autre PR ne peut passer sereinement, et le prochain déploiement met la prod en panne sur l'onboarding RH.
2. **Bloc 1 (prod, funnel acquisition)** : #4950, #4948, #4949 — trois actions courtes qui rétablissent le parcours prospect (trial + checkout). Regroupées pour débloquer la conversion.
3. **#4952** : dépend d'une décision produit → à trancher tôt dans la semaine, exécution selon l'arbitrage.
4. **Bloc 2 (cohérence)** : #4951, #4955 — quick wins web/API.
5. **Bloc 3 (finition admin/contenu)** : #4953, #4954, #4956.
6. **#4957** : vivant — mis à jour au fil des merges, clôturé en fin de semaine.

---

## 2. Plan séquence — Jour par jour

### 📅 Jour 1 — Lundi : lever les blocages
- **Kickoff** : assignations selon protocole #2400 (branche `fix/<N>-<slug>` = lock, self-assign), rappel conventions (PR `Closes #N`, entrée CHANGELOG obligatoire, gardes CI).
- **Prérequis CI** : vérifier si #4868 (check Vercel « api-deployments-free-per-day » qui bloque toutes les PR) est toujours actif → config GitHub/Vercel admin pour le rendre non-bloquant. *Sans ça, les merges de la semaine seront bloqués.*
- **#4947 (P0, dev API)** : fix `EmployeeService::create()` — réinjecter `password_hash` en un seul INSERT (pattern #3677/#4151), rétablir la suite HR verte (`tests/Feature/HR/`), ajouter tests de non-régression (création avec password / sans password / `send_invitation` / import CSV). PR dans la journée.
- **#4950 (ops)** : retirer `NEXT_PUBLIC_CHECKOUT_SANDBOX` de l'env Vercel **prod** → redéploiement → vérif visuelle `/checkout`.
- **#4948 (ops, diagnostic)** : logs du worker `leopardo-queue-worker` sur Render, statut Redis Upstash, consommation de la file `default`.

**Sortie J1** : PR #4947 prête + CI verte ; checkout sandbox retiré de prod ; diagnostic worker posé.

### 📅 Jour 2 — Mardi : rétablir le funnel trial
- **Merge #4947** (si CI verte) + vérif live après déploiement API.
- **#4949** : confirmer si le fix #4874 est déployé (le health affiche toujours v4.24.0) ; si non → redéployer ; si toujours 500 → corriger le chemin `self_service` (gestion du retour `false` de `RequestTrialSignup::execute()`) + PR.
- **#4948 (suite)** : remise en route du worker + **drain des `trial_provisionings` pending** (dont la ligne QA `qaleopardo20260817@emalupe.com` créée pendant la session) → nettoyage ou re-traitement.
- **Test E2E funnel complet (staging puis prod)** :
  - Parcours guidé : signup → `pending` → `ready` (+ login_url) → magic link → login dashboard.
  - Parcours OTP : signup → email reçu → code → verify → accès.
- **Télémétrie** : ajouter une alerte si un job trial reste > 15 min en `pending` (prévention récidive #4948).

**Sortie J2** : un prospect peut entrer dans le produit via les deux parcours de trial.

### 📅 Jour 3 — Mercredi : tunnel paiement + cohérence
- **#4952 (décision, 10h max)** : arbitrage produit —
  - *Option A (recommandée)* : brancher Stripe live (clés, prix pilot/operations/enterprise alignés #4630, webhooks, page success).
  - *Option B (palier)* : masquer les CTA paiement du pricing/checkout tant que Stripe n'est pas prêt (garder uniquement « Essai gratuit »).
  - Exécution selon l'arbitrage + test du parcours.
- **#4951 (dev web)** : unifier « 14-day » partout (`pricing.ts` + autres occurrences) + test interdisant deux durées sur la même page.
- **#4955 (dev API)** : 429 structuré/localisé (pattern `ApiError` + catalogue i18n pré-login #4501).

**Sortie J3** : tunnel paiement cohérent (payant ou masqué) ; pricing homogène ; 429 propre.

### 📅 Jour 4 — Jeudi : finition + régression globale
- **#4953 + #4954 (batch admin)** : version alimentée depuis `/api/v1/health` (fallback statique) ; bloc « Accès Démo » visible uniquement si démo active (flag exposé par l'API).
- **#4956 (dev web)** : compteur « 19 pays » (ou alimenté depuis le registre backend).
- **Test de non-régression complet (staging)** :
  - Parcours prospect : vitrine → signup → trial → login → onboarding wizard → dashboard.
  - Parcours RH : création d'employé (password + invitation) → import CSV → invitation → activation employé.
  - RBAC + isolation tenant (scripts de la session QA réutilisables).

**Sortie J4** : toutes les issues assignées sont en PR ou mergées ; parcours principaux validés en staging.

### 📅 Jour 5 — Vendredi : release & vérification
- **Suites CI complètes** : backend (test + qualité), mobile, build, lint, type-check, CodeQL, governance ; coverage-gate ≥ 65 % (ratchet).
- **Confirmer en CI les 14 échecs Payroll** vus en local (PG 14 vs PG 16 / cascade du P0) : si réels → créer une issue dédiée ; sinon clôturer le doute.
- **Release gate** : `release-readiness.ps1 -Strict` (23/23) avant déploiement.
- **Déploiement** + vérification live de chaque fix avec les repros de la session QA (#4947 : POST /employees 201 ; #4949 : signup 200 ; #4950 : plus d'UI sandbox ; #4948 : status `ready` ; #4951 : 14-day partout…).
- **Clôture** : mise à jour de #4957 (statuts, blocages), fermeture des issues vérifiées, rétro 15 min.

**Sortie J5** : prod saine sur les parcours prospect + RH ; 10 issues résolues/fermées (hors report) ; #4957 clôturé.

---

## 3. Definition of Done (par issue)

| Issue | DoD |
|---|---|
| #4947 | `POST /employees` → 201 (avec/sans password, invitation) ; `tests/Feature/HR/` 100 % verts ; CI backend vert ; entrée CHANGELOG |
| #4948 | Un trial guidé passe `pending → ready` < 5 min en prod ; aucune ligne pending > 24 h ; alerte posée |
| #4949 | `POST /trial/signup` (`self_service`) → 200 « code envoyé » (ou erreur honnête localisée) ; OTP reçu et vérifiable |
| #4950 | `/checkout` prod sans encart sandbox ni carte 4242 ; `NEXT_PUBLIC_CHECKOUT_SANDBOX` absent de l'env prod |
| #4951 | Une seule durée d'essai affichée sur `/pricing` ; test anti-régression ajouté |
| #4952 | Parcours paiement opérationnel **ou** CTA masqués proprement (décision actée dans l'issue) |
| #4953 | Version admin alimentée par l'API (v4.24.x affichée) |
| #4954 | Bouton « Acces Demo » invisible en prod (visible uniquement en démo/staging) |
| #4955 | 429 au format `error/message/localized_message`, localisé FR par défaut |
| #4956 | « 19 pays » (ou chiffre dynamique) sur /about + FAQ cohérente |
| #4957 | Plan mis à jour au fil de l'eau, clôturé en fin de semaine avec stats (10/11 résolues) |

---

## 4. Risques & dépendances

| Risque | Impact | Mitigation |
|---|---|---|
| **Accès prod requis** (Render, Vercel, Stripe) pour #4948/#4950/#4952/#4949 | Blocage des P1 tant qu'un humain n'agit pas | Préparer les tickets ops avec instructions exactes ; escalader dès J1 ; le user (ou l'admin plateforme) doit donner les accès |
| **#4868** (check Vercel bloque toutes les PR) | Aucun merge possible cette semaine | Lever en premier (config GitHub/Vercel admin) — J1 |
| **Décision #4952 non tranchée** | Tunnel paiement incohérent toute la semaine | Arbitrage produit bloqué à J3 10h ; palier B (masquer CTA) exécutable sans Stripe |
| **P0 non déployé = prod en panne** (onboarding RH) au prochain déploiement | Incident prod | Fix #4947 mergé dès J1, déployé au plus tard J2 |
| Tests Payroll : doute PG14 vs PG16 | Faux positif ou vrais bugs | Vérification CI J5 (avant release) |
| Volatilité des services gratuits (épic #3765/#3766) | Récurrence de #4948 | Alerte « trial pending > 15 min » + monitoring queue |

---

## 5. Critères de sortie de semaine

1. **Funnel prospect 100 % fonctionnel** : signup → trial (2 parcours) → login → dashboard (vérifié live).
2. **Flux RH débloqué** : création/import/invitation d'employés OK (201) + suite HR verte.
3. **Pricing & paiement cohérents** (une durée d'essai, un tunnel paiement assumé).
4. **10 issues résolues** (#4947 → #4956), #4957 clôturé avec rétro.
5. **Aucune régression connue** sur les parcours testés (re-run des scripts QA de la session).

---
*Plan généré le 2026-08-17 — à mettre à jour au fil de l'eau dans #4957.*
