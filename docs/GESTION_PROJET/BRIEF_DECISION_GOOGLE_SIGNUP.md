# 📋 Brief de décision — Création de compte via Google en production (issue #5171, P0)

**Date** : 2026-08-24 · **Destinataire** : fondateur (décision produit) · **Statut** : en attente de décision
**Issue** : #5171 · **Décision antérieure** : #3724 (anti-auto-provisionnement silencieux)

> Ce brief ne tranche pas : il rassemble les faits vérifiés (code + live), les options, les
> critères et les questions ouvertes pour que la décision soit prise en connaissance de cause.

---

## 1. Constat (vérifié code + prod, 2026-08-20, QA #F1/#F2 + re-vérifié code le 2026-08-24)

1. **Front** : aucun bouton « Sign up with Google » — login (`front/web/src/app/auth/login/page.tsx:494`)
   et checkout (`.../checkout/page.tsx:314`) utilisent la même fonction `googleAuthHref()` qui pointe
   vers le même flux (« Continue with Google »).
2. **Backend** : tout email Google **inconnu** → `401 UNKNOWN_ACCOUNT`
   (`api/app/Core/Auth/Interfaces/Api/V1/AuthController.php:248`) — le flux d'invitation (#2617)
   crée la ligne employé en amont.
3. **Auto-création** : `Employee::forceCreate` n'est possible que si `config('app.demo_mode_enabled')`
   (`DEMO_MODE_ENABLED=true`, jamais en prod) — `AuthController.php:254`.
4. **Invitation** : le seul chemin légitime (invitation-first) est lui-même **bloqué en prod** par
   l'absence de worker de queue fonctionnel (issue #4948, trial « pending » > 2 h).

**Conséquence** : un nouvel utilisateur Google ne peut **ni** se créer un compte, **ni** être invité
correctement tant que les workers ne tournent pas. Le funnel signup (KPI 1 du plan 60 j,
conversion ≥ 30 %) est à l'arrêt.

## 2. Options

### Option A — Acter l'invitation-first comme parcours officiel (documenter, garder le 401)

| | |
|---|---|
| Effort | Faible : docs onboarding + garde 401 documentée (déjà en place) |
| Avantages | Zéro risque anti-abuse ; cohérent avec #3724 ; rapide |
| Risques | Funnel signup quasi nul tant que l'invitation ne marche pas (dépend de #4948) ; un prospect Google ne peut pas essayer seul le produit |
| KPI impact | KPI 1 (conversion signup) : **ne peut pas être vert** |

### Option B — Self-service Google sécurisé (provisionnement trial limité hors flag DEMO)

| | |
|---|---|
| Effort | Moyen : endpoint/flag produit `ALLOW_GOOGLE_SELF_SIGNUP`, création d'un compte `ordinary` + **company trial provisionnée** (réutiliser `ProvisionGuidedTrial`), rate-limit + quota + anti-abuse |
| Avantages | Seul chemin qui satisfait « se connecter OU créer son compte via Google » ; alimente KPI 1 ; aligné avec le parcours trial existant (#4948 à réparer) |
| Risques | Provisionnement silencieux (contredit #3724) → à conditionner : email Google vérifié, rate-limit IP/email, quota trial (1 par email), reCAPTCHA/anti-bot optionnel, audit trail |
| KPI impact | KPI 1 mesurable ; KPI 2 (trial < 2 min) dépend des workers (#4948) |

### Option C — Hybride (recommandation PM) : invitation-first par défaut + self-service Google pour compte trial/ordinary avec garde-fous

Combinaison de A et B : le parcours invité reste prioritaire (entreprise liée, rôle défini par
l'admin) ; le self-service Google crée uniquement un **compte trial non lié à une entreprise**
(`ordinary`, sans `company_id`), avec les garde-fous de B. C'est le modèle le plus proche de la
promesse produit sans rouvrir l'auto-provisionnement d'entreprises complètes.

## 3. Critères de décision

| Critère | Poids | A | B | C |
|---|---|---|---|---|
| Satisfait la promesse « créer son compte via Google » | Élevé | ❌ | ✅ | ✅ |
| Anti-abuse / pas de provisionnement silencieux d'entreprise | Élevé | ✅ | ⚠️ (garde-fous requis) | ✅ (compte trial seul) |
| Débloque KPI 1 (conversion) et le funnel | Moyen | ❌ | ✅ | ✅ |
| Effort / délai | Moyen | Faible | Moyen | Moyen |
| Dépendances | — | #4948 (workers) | #4948 (workers) | #4948 (workers) |

## 4. Questions au fondateur

1. **Appétit au risque** : le self-service Google (B/C) est-il acceptable en l'état, ou préférez-vous
   verrouiller l'invitation-first (A) jusqu'à ce que l'anti-abuse soit mature ?
2. **Quota trial** : quel périmètre pour le compte auto-créé (trial limité 7/14 j, 1 par email,
   pays DZ d'abord) ?
3. **Lien avec #4948** : faut-il traiter les workers de queue (invitations + trials) en même temps
   que cette décision (sinon l'option choisie reste bloquée en prod) ?
4. **Bouton UI** : ajouter « Sign up with Google » distinct du login (vitrine + checkout) une fois
   la voie choisie ?

## 5. Sources

- Code : `AuthController.php:225-275` (callback Google, 401 UNKNOWN_ACCOUNT, forceCreate demo),
  `googleAuthHref()` (login + checkout), `ProvisionGuidedTrial.php:193` (garde DEMO_MODE).
- QA : `QA_RAPPORT_2026-08-17.md` (F1 création employé 500 — corrigé ; F2 trial pending — #4948).
- Onboarding pilotes : `docs/pilotes/ONBOARDING_PILOTE.md` (« le parcours Google nécessite une
  invitation (#5171) ; ne pas laisser le pilote se heurter au 401 »).
- Plan : `PLAN_60_JOURS.md` (KPI 1 conversion ≥ 30 %, KPI 2 trial < 2 min).
- Décision antérieure : #3724 (anti-provisionnement silencieux).
