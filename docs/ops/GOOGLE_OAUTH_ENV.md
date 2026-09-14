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

## 5. État au 2026-09-14 — écran de consentement **non publiable** (domaine), mode « Testing » assumé

**Ce qui fonctionne désormais** : les 3 variables sont renseignées sur **dev ET prod** (client OAuth du projet
Google Cloud `leopardo-508608`, URI de redirection = callback **vitrine** de chaque environnement). Vérifié en ligne
sur les deux : `GET /api/v1/auth/google?intent=signup` → **302** vers `accounts.google.com`, `redirect_uri` vitrine,
cookie de session posé, `state` anti-CSRF présent. Le parcours navigateur atteint la page Google
(« Sign in with Google — to continue to gestionemployer-backend.vercel.app »).

**Blocage restant — la publication de l'écran de consentement** : elle est **impossible aujourd'hui**, faute de
**domaine vérifiable**. Google exige, pour publier l'application (et pour renseigner les liens Accueil /
Confidentialité / CGU), des **« domaines autorisés » dont la propriété est prouvée via Search Console**. Or la vitrine
vit sous `*.vercel.app` : ce domaine appartient à Vercel, **nous ne pouvons pas le vérifier**. Tant que **#3452**
(`leopardo-rh.com` **NXDOMAIN**) n'est pas résolu, il n'existe aucun domaine à nous à déclarer.

**Décision du propriétaire (2026-09-14)** : rester en mode **« Testing »** et s'appuyer sur la **liste d'utilisateurs
test** de l'écran de consentement (limite Google : 100 comptes) **jusqu'à l'achat et la configuration d'un nom de
domaine**. Aucune conséquence sur le code ; c'est un état opérationnel assumé, pas une régression.

**Symptôme observé en mode Testing** (documenté pour ne pas le rediagnostiquer) : après le choix du compte Google, le
navigateur revient sur **`https://gestionemployer-backend.vercel.app/auth/login`**. C'est le comportement **voulu** du
callback vitrine lorsque Google **ne renvoie aucun `code`** (`error=access_denied`) : il redirige vers la connexion au
lieu d'afficher du JSON. Le message affiché identifie la cause :

| URL d'arrivée | Message affiché | Signification |
|---|---|---|
| `/auth/login?error=google` | « La connexion avec Google a échoué. Veuillez réessayer. » | **Google n'a rien renvoyé** → compte hors liste d'utilisateurs test (**mode Testing**), ou autorisation refusée/annulée |
| `?error=google_auth_failed` | « Google a refusé la connexion. » | L'**API** a rejeté l'échange (code ou `state` invalide) |
| `?error=google_no_account` | « Aucun compte Leopardo RH n'est associé… » | Parcours connexion (`intent=login`), e-mail inconnu (#3724) |
| `?error=google_network` | « Impossible de contacter Google… » | API injoignable |

> Pour tester malgré tout, **avant** publication : Google Cloud Console → *Google Auth Platform* → **Audience** →
> **Utilisateurs test** → ajouter les comptes Google autorisés (les 100 places sont gratuites et suffisent au pilote).

**À faire le jour où un domaine est acheté et configuré** :
1. créer le domaine, le faire résoudre (DNS + certificat) et **vérifier sa propriété dans Search Console** ;
2. le déclarer comme **domaine autorisé** de l'écran de consentement ;
3. **publier l'application** (statut « En production » — les scopes `openid`/`email`/`profile` étant **non sensibles**,
   la publication ne déclenche **aucune revue Google**) ;
4. retirer la dépendance à la liste d'utilisateurs test ;
5. basculer `GOOGLE_REDIRECT_URL` **et** les URI de la console sur le domaine (cf. §2), puis rejouer §4.

## Références

- Issues : **#5170** (P0 prod onboarding — ce runbook), **#2277** (callback sur la vitrine), **#3452** (DNS `leopardo-rh.com` NXDOMAIN)
- Code : `api/app/Core/Auth/Interfaces/Api/V1/AuthController.php` (`redirectToGoogle` → 503 `GOOGLE_OAUTH_NOT_CONFIGURED`)
- Config : `api/config/services.php` (`services.google.*`), `render.yaml` (bloc Google OAuth, `sync: false`)
- i18n : `api/lang/{fr,en,ar,tr}/errors.php` (clé `GOOGLE_OAUTH_NOT_CONFIGURED`)
- Registre des domaines : `docs/ops/DOMAINS.md` (source de vérité, garde CI)

---
*Document généré depuis l'issue #5170 (P0 production, onboarding).*
