# Onboarding Google — parcours invitation-first (issue #5171)

> **Statut** : parcours officiel par défaut (option (a), décision assumée #3724).
> L'arbitrage fondateur (a) invitation-first / (b) self-service / (c) hybride
> reste ouvert — brief : `docs/GESTION_PROJET/BRIEF_DECISION_GOOGLE_SIGNUP.md`
> (PR #5396). Tant que la décision n'est pas actée, **aucun compte n'est
> auto-provisionné** depuis un email Google inconnu en production.

## Énoncé de mission

> « L'utilisateur doit pouvoir se connecter **OU créer son compte** via Google. »

En production, la **création de compte via Google est un parcours
invitation-first** : un administrateur crée la fiche employé en amont, ce qui
déclenche l'email d'invitation ; l'utilisateur accepte puis se connecte via
Google. Le 401 `UNKNOWN_ACCOUNT` (email Google inconnu) est le garde-fou
anti-provisionnement silencieux (#3724) et oriente vers ce parcours.

## Parcours complet (vérifié 2026-08-25)

```
Admin RH/principal
   │  1. Crée la fiche employé (email = email Google du collaborateur)
   ▼
Invitation email envoyée automatiquement
   │  (worker de queue Render `leopardo-queue-worker` — #5172 fermée)
   ▼
Collaborateur ouvre le lien d'invitation
   │  2. Choisit son mot de passe (ou « Continue with Google »)
   ▼
Compte activé — connexion via Google possible
   │  3. Bouton « Continuer avec Google » (login vitrine + checkout)
   ▼
Session créée (token Sanctum, cookie httpOnly vitrine)
```

## Points d'attention (prod)

1. **Config Google** : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`,
   `GOOGLE_REDIRECT` (origine vitrine) — absents → 503 explicite
   (`googleOauthConfigured()`).
2. **Worker de queue** : l'email d'invitation est délivré par le worker
   (`render.yaml` → `leopardo-queue-worker`). Un worker absent = invitations
   jamais envoyées (régression #5172, runbook `docs/ops/`).
3. **Email inconnu** : 401 `UNKNOWN_ACCOUNT` (message localisé ×4) — la
   vitrine affiche « Aucun compte associé à cet email Google. Demandez une
   invitation à votre administrateur. » (callback vitrine → `?error=google_no_account`).
4. **Mode démo** : `DEMO_MODE_ENABLED=true` autorise l'auto-provisionnement
   (uniquement hors production).

## Implémentation (PR issue #5171)

- Backend : message 401 localisé via `errors.UNKNOWN_ACCOUNT` ×4
  (`AuthController::handleGoogleCallback`).
- Vitrine : le callback OAuth distingue le 401 `UNKNOWN_ACCOUNT` →
  `?error=google_no_account` (message dédié) des autres échecs backend →
  `?error=google_auth_failed`.
- Test backend + test vitrine (route callback) couvrant les deux chemins.

## Évolutions possibles (après décision fondateur)

- **(b) Self-service sécurisé** : provisionnement d'un compte trial limité
  (rate-limit, anti-abuse, création tenant) — hors flag DEMO.
- **(c) Hybride** : invitation-first + self-service conditionnel (recommandation
  du brief #5396).
