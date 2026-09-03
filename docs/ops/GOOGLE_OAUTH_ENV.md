# 🔑 Runbook — Google OAuth : variables d'environnement absentes en prod (issue #5170, P0)

**Version** : 1.0 · **Date** : 2026-08-20 · **Statut** : action humaine requise (dashboard Render)
**Symptôme** : `GET /api/v1/auth/google` → 500 générique (exception Socialite non gérée) car
`GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URL` sont absentes de l'environnement Render.
**Depuis le fix #5170** : l'API répond un **503 propre `GOOGLE_OAUTH_NOT_CONFIGURED`** (message localisé)
au lieu d'un 500 — le code ne bloque plus, mais le flux Google reste KO tant que les variables ne sont pas renseignées.

---

## 1. Renseigner les 3 variables sur Render (dashboard)

Les variables sont déclarées dans `render.yaml` avec **`sync: false`** → elles ne sont **pas** poussées
par le blueprint : il faut les saisir à la main dans le dashboard (elles ne sont jamais écrites dans le dépôt).

1. Ouvrir **Render Dashboard** → service **`gestionemployerbackend`** → onglet **Environment**.
2. Cliquer **Add Environment Variable** (×3) et saisir :

   | Variable | Valeur | Source |
   |---|---|---|
   | `GOOGLE_CLIENT_ID` | `<client_id>` (se termine par `.apps.googleusercontent.com`) | Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client ID |
   | `GOOGLE_CLIENT_SECRET` | `<client_secret>` (secret du même client OAuth) | Idem |
   | `GOOGLE_REDIRECT_URL` | `https://gestionemployer-backend.vercel.app/api/v1/auth/google/callback` | cf. §2 — tant que `leopardo-rh.com` est NXDOMAIN (#3452) |

3. **Ne pas modifier `render.yaml`** pour ces clés : le `sync: false` protège les secrets (jamais de valeurs dans le dépôt, `.secrets.baseline` + secret scanning GitHub).
4. Cliquer **Save Changes** → Render redéploie le service automatiquement (sinon **Manual Deploy → Deploy latest commit**).
5. Vérifier le déploiement : onglet **Events** → `Deploy` → `Live`, puis `GET /api/v1/health` → `{"status":"ok", ...}`.

> ⚠️ Ne jamais committer les valeurs réelles de `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` ni le token
> GitHub dans un commit, un message de commit, un fichier versionné ou un rapport.

## 2. Valeur attendue de `GOOGLE_REDIRECT_URL` (#2277, #3452)

- **#2277 (QA)** : le callback doit atterrir sur la **vitrine** — le proxy Next.js (`front/web`, Vercel)
  route `/api/v1/[...path]` vers l'API et pose le cookie de session sur le domaine vitrine.
  `gestionemployer-backend.vercel.app` est le domaine vitrine `live` du registre canonique
  (`docs/ops/DOMAINS.md`).
- **#3452** : `leopardo-rh.com` est **NXDOMAIN** (aucun enregistrement DNS, constaté au 2026-08-19).
  Tant qu'il n'est pas résolu, l'URL à renseigner est :

  ```
  https://gestionemployer-backend.vercel.app/api/v1/auth/google/callback
  ```

- Dès que #3452 est résolu (DNS + certificat), basculer sur la cible documentée dans `.env.example`
  et `render.yaml` :

  ```
  https://leopardo-rh.com/api/v1/auth/google/callback
  ```

  puis aligner la Google Cloud Console (§3) et re-tester (§4).

> ℹ️ `docs/deployment/KEYS_A_CONFIGURER.md` référence encore l'ancienne URL Render directe
> (`gestionemployerbackend.onrender.com/...`) : elle est **obsolète** depuis #2277 — la valeur
> canonique est l'URL vitrine ci-dessus tant que #3452 est ouvert.

## 3. Aligner la Google Cloud Console (Authorized redirect URIs)

1. Ouvrir **Google Cloud Console** → **APIs & Services** → **Credentials**.
2. Sélectionner le **Client ID OAuth** correspondant à `GOOGLE_CLIENT_ID`.
3. Dans **Authorized redirect URIs**, ajouter **exactement** la valeur de `GOOGLE_REDIRECT_URL`
   (actuellement `https://gestionemployer-backend.vercel.app/api/v1/auth/google/callback`) —
   l'URI doit matcher au caractère près (schéma, hôte, port, chemin).
4. Vérifier **Authorized JavaScript origins** : ajouter l'origine de la vitrine
   (`https://gestionemployer-backend.vercel.app`) si le flux « popup » est utilisé côté web.
5. Enregistrer (sauvegarde immédiate, propagation ~1 min).

## 4. Test de vérification

Après déploiement, vérifier le flux complet :

```bash
# La redirection fonctionne → 302 vers le consentement Google
curl -sI "https://gestionemployer-backend.vercel.app/api/v1/auth/google" | head -5
# attendu : HTTP/2 302, Location: https://accounts.google.com/o/oauth2/...
```

Résultat attendu :

| Réponse | Signification | Action |
|---|---|---|
| `302` → `accounts.google.com` | Configuration OK | — |
| `503` + `error: GOOGLE_OAUTH_NOT_CONFIGURED` | Une variable manque encore (ou est vide) | revérifier §1/§3 |
| `500` | Bug applicatif (ne doit plus arriver pour ce cas) | ouvrir une issue, joindre les logs Sentry |

## Références

- Issues : **#5170** (P0 prod onboarding — ce runbook), **#2277** (callback sur la vitrine), **#3452** (DNS `leopardo-rh.com` NXDOMAIN)
- Code : `api/app/Core/Auth/Interfaces/Api/V1/AuthController.php` (`redirectToGoogle` → 503 `GOOGLE_OAUTH_NOT_CONFIGURED`)
- Config : `api/config/services.php` (`services.google.*`), `render.yaml` (bloc Google OAuth, `sync: false`)
- i18n : `api/lang/{fr,en,ar,tr}/errors.php` (clé `GOOGLE_OAUTH_NOT_CONFIGURED`)
- Registre des domaines : `docs/ops/DOMAINS.md` (source de vérité, garde CI)

---
*Document généré depuis l'issue #5170 (P0 production, onboarding).*
