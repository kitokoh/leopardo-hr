# RUNBOOK — Google OAuth en production (issues #5170 / #5171 / #5173)

**Version** : 1.0 · **Date** : 2026-08-20 · **Auteur** : Agent PM (batch #5170)
**Symptôme traité** : « Continue with Google » → 500 `INTERNAL_ERROR` (page JSON brute) ou 401 `UNKNOWN_ACCOUNT`.

> **État au 2026-09-14** : les 3 variables sont renseignées (dev **et** prod) et l'initiation renvoie bien un **302**
> vers `accounts.google.com` (redirect_uri = callback **vitrine**). Deux défauts intermédiaires ont été corrigés :
> **#7370** (un backfill de démarrage non gardé pouvait faire échouer un déploiement Render) et **#7372**
> (les routes OAuth n'avaient **aucun middleware de session** → `Session store not set on request.` → 503, même clés
> valides). **Reste un blocage non technique** : l'écran de consentement **ne peut pas être publié** faute de domaine
> vérifiable (`*.vercel.app` n'est pas à nous, #3452) → **mode « Testing » + liste d'utilisateurs test** assumé jusqu'à
> l'achat d'un domaine. Détail, symptôme d'arrivée sur `/auth/login` et procédure de sortie : `docs/ops/GOOGLE_OAUTH_ENV.md` §5.

---

## 1. Symptômes et cause racine

| Symptôme observé en prod (2026-08-20) | Cause racine |
|---|---|
| `GET /api/v1/auth/google` → 500 `INTERNAL_ERROR` | `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URL` **absents** de l'env Render (20 variables présentes, zéro `GOOGLE_*`) → Socialite ne peut pas construire l'URL → exception non gérée |
| `GET /api/v1/auth/google/callback` → 401 `UNKNOWN_ACCOUNT` | Email Google inconnu en base → **décision produit** #3724 (anti-provisionnement silencieux) : seul le flux invitation-first est légitime (issue #5171) |

**Depuis le fix #5170 (merge sur main)** : la redirection échoue **rapidement et proprement** avec `503 GOOGLE_OAUTH_NOT_CONFIGURED` + un message localisé (fr/en/tr/ar) et un log `auth.google.not_configured` indiquant quelles variables manquent — plus jamais de 500 générique.

## 2. Vérification rapide (diagnostic)

```bash
# 1. L'endpoint de redirection (doit répondre, pas de 500)
curl -s -o /dev/null -w "%{http_code}\n" https://gestionemployerbackend.onrender.com/api/v1/auth/google
#   → 503 GOOGLE_OAUTH_NOT_CONFIGURED = env pas configurée (aller en §3)
#   → 302 vers accounts.google.com = env OK

# 2. État de la config sur Render (dashboard ou API)
#    API : https://api.render.com/v1/services?name=gestionemployerbackend (Bearer $RENDER_API_KEY)
```

## 3. Renseigner les variables sur Render (action ops — accès requis)

Dans le dashboard Render (service `gestionemployerbackend` → **Environment**) :

| Variable | Valeur attendue |
|---|---|
| `GOOGLE_CLIENT_ID` | Client ID du projet Google Cloud (OAuth) |
| `GOOGLE_CLIENT_SECRET` | Secret correspondant |
| `GOOGLE_REDIRECT_URL` | `https://gestionemployer-backend.vercel.app/api/v1/auth/google/callback` (tant que #3452 — `leopardo-rh.com` NXDOMAIN — n'est pas résolu ; sinon `https://leopardo-rh.com/api/v1/auth/google/callback`) |

⚠️ **Garder `sync: false`** : ces variables ne doivent pas être écrasées par un push blueprint (elles sont déjà déclarées `sync: false` dans `render.yaml`).

## 4. Aligner la console Google Cloud

- Projet Google Cloud → APIs & Services → Credentials → OAuth 2.0 Client IDs
- **Authorized redirect URIs** : ajouter la même valeur que `GOOGLE_REDIRECT_URL` ci-dessus (exactement, sans slash final).
- **Authorized JavaScript origins** : `https://gestionemployer-backend.vercel.app` + `https://gestionemployerbackend.onrender.com` + domaine vitrine.

### 4bis. Publier l'écran de consentement — bloqué par l'absence de domaine (2026-09-14)

La publication (statut « En production ») exige des **« domaines autorisés » vérifiés dans Search Console** : la vitrine
étant sous `*.vercel.app` (domaine de Vercel, non vérifiable par nous) et `leopardo-rh.com` étant **NXDOMAIN** (#3452),
**aucun domaine ne peut être déclaré** → la publication est impossible aujourd'hui.

**Contournement retenu** : mode **« Testing »** + **liste d'utilisateurs test** (Google Cloud Console → *Google Auth
Platform* → **Audience** → *Utilisateurs test*), limite Google de 100 comptes — suffisant pour le pilote. Les scopes
`openid`/`email`/`profile` étant non sensibles, la publication ne déclenchera **aucune revue** le jour venu.

## 5. Re-tester (DoD)

```bash
# Redirection vers Google (suivre les redirects)
curl -sI https://gestionemployerbackend.onrender.com/api/v1/auth/google | head -5
#   → HTTP 302 Location: https://accounts.google.com/o/oauth2/...
curl -sI https://gestionemployer-backend.vercel.app/api/v1/auth/google | head -5
#   → 302 (proxy vitrine)

# Callback avec un email inconnu → 401 UNKNOWN_ACCOUNT (normal, voir §6)
# Callback avec un email invité → 200 + token
```

## 6. Le 401 UNKNOWN_ACCOUNT est-il un bug ?

**Non** — c'est le comportement voulu (décision #3724, réaffirmée dans #5171 en attente d'arbitrage produit) :

- Parcours officiel = **invitation-first** : un admin crée la ligne employé (email d'invitation envoyé), puis l'utilisateur se connecte via Google.
- L'auto-création de compte n'existe que sur les environnements de démo (`DEMO_MODE_ENABLED=true`).
- Si un nouvel utilisateur doit pouvoir s'auto-inscrire via Google → **arbitrage produit requis** (issue #5171, options a/b décrites).

## 7. Erreurs et codes associés

| Code | HTTP | Sens |
|---|---|---|
| `GOOGLE_OAUTH_NOT_CONFIGURED` | 503 | Env non configurée (nouveau, #5170) |
| `INVALID_OAUTH_STATE` | 400 | State anti-CSRF manquant/invalide (#2619) |
| `GOOGLE_AUTH_FAILED` | 422 | Échec de l'échange OAuth chez Google |
| `UNKNOWN_ACCOUNT` | 401 | Email inconnu — invitation-first (#3724/#5171) |
| `GOOGLE_TOKEN_INVALID` | 422 | Jeton mobile invalide |

## 8. Gardes & protection anti-régression

- Test d'intégration : `AuthGoogleSignInTest::test_google_redirect_returns_503_when_oauth_not_configured` (et le test miroir avec credentials présents).
- E2E : issue #5174 (couverture Playwright du flux bouton + callback) — branche dédiée par l'agent QA.
- UX vitrine : issue #5173 (paramètre `?error=` ignoré sur la page login).

---

*Runbook généré depuis le constat QA du 2026-08-20 (issues #5170/#5171/#5173, rapport `tasks/leopardo-qa-onboarding-google-2026-08-20/RAPPORT_PM_2026-08-20.md`).*
