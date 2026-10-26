# Checklist exécutable — secrets & variables Render prod (#8397)

> **Statut : PRÉPARÉ, non exécuté.** Les étapes 1 à 3 exigent un accès propriétaire
> (dashboard Render + paramètres GitHub). Aucune ligne de code ne peut les
> satisfaire : c'est pour cela que #8397 est labellisée `blocked`.
>
> Ce document transforme l'issue en **procédure déroulable** : chaque étape est
> une commande ou un clic, avec son critère de réussite. Décision PM 2026-10-26.

## 1. Pourquoi la prod est en retard

Trois gardes échouent parce que **deux secrets de dépôt n'existent pas** :

| Secret | Usage | Conséquence de son absence |
|---|---|---|
| `RENDER_PROD_API_KEY` | `.github/workflows/deploy-main-catchup.yml`, `db-standby-failover.yml` | Le rail de rattrapage ne peut ni lire ni déclencher le deploy prod |
| `RENDER_PROD_SERVICE_ID` | idem | Idem (identifie le service cible `srv-…`) |

Constat vérifié le 2026-10-26 : ces deux noms sont **absents** des 29 secrets de
dépôt existants. Les gardes dégradent proprement (elles émettent un `::notice`
au lieu d'échouer sèchement, cf. audit Phase 1 du 2026-10-21) — mais tant qu'ils
manquent, **aucun rattrapage automatique de la prod n'est possible** et la prod
reste derrière `main`.

## 2. Étape 1 — Créer la clé API Render

1. Dashboard Render → **Account Settings → API Keys → Create API Key**.
2. Nommer la clé `github-actions-prod-catchup` (identifiable, révocable).
3. Copier la valeur (`rnd_…`) — elle n'est affichée qu'une fois.
4. Relever l'ID du service prod : `srv-…` (dashboard → service `leopardo-prod` →
   URL de la page, ou `GET /v1/services` avec la clé).

```bash
# Contrôle immédiat de la clé avant de la poser comme secret
export RENDER_API_KEY='rnd_...'
curl -fsS -H "Authorization: Bearer ${RENDER_API_KEY}" \
  https://api.render.com/v1/services?limit=100 | jq -r '.[].service | "\(.id)\t\(.name)\t\(.type)"'
```

**Réussite :** la sortie contient `leopardo-prod` (type `web`). Relever son `id`.

## 3. Étape 2 — Poser les deux secrets de dépôt

```bash
gh secret set RENDER_PROD_API_KEY --repo kitokoh/leopardo-hr
gh secret set RENDER_PROD_SERVICE_ID --repo kitokoh/leopardo-hr --body 'srv-xxxxxxxxxxxx'
```

**Réussite :** `gh secret list --repo kitokoh/leopardo-hr | grep RENDER_PROD`
affiche les deux lignes.

## 4. Étape 3 — Vérifier les 21 variables `sync: false` du service web

Le blueprint ne peut pas créer ces valeurs : elles sont marquées `sync: false`,
donc **à saisir dans le dashboard Render**. Une seule manquante suffit à casser
un parcours utilisateur. Liste exhaustive extraite de `render.prod.yaml`
(service `leopardo-prod`, 49 `envVars` dont 21 `sync: false`) :

| # | Variable | Source de la valeur | Sans elle |
|---|---|---|---|
| 1 | `DB_URL` | Neon — projet `LEOPARDO`, branche `production` | L'API ne démarre pas |
| 2 | `APP_KEY` | **ÉPINGLÉE** le 2026-09-20 — ne pas régénérer | Sessions + données chiffrées invalidées |
| 3 | `SENTRY_LARAVEL_DSN` | Projet Sentry `leopardo-hr` | Erreurs prod invisibles |
| 4 | `STRIPE_SECRET_KEY` | Dashboard Stripe (live) | Aucun paiement par carte |
| 5 | `STRIPE_WEBHOOK_SECRET` | Dashboard Stripe → Webhooks | Webhooks rejetés (fail-closed) |
| 6 | `STRIPE_PRICE_STARTER` | Dashboard Stripe → Produits | Souscription Starter impossible |
| 7 | `STRIPE_PRICE_BUSINESS` | idem | Souscription Business impossible |
| 8 | `STRIPE_PRICE_ENTERPRISE` | idem | Souscription Enterprise impossible |
| 9 | `CHARGILY_API_KEY` | Dashboard Chargily | Paiement local (CIB/Edahabia) KO |
| 10 | `CHARGILY_WEBHOOK_SECRET` | Dashboard Chargily | Webhooks Chargily rejetés |
| 11 | `GOOGLE_CLIENT_ID` | Google Cloud Console → OAuth client Web | Login Google KO |
| 12 | `GOOGLE_CLIENT_SECRET` | idem | idem |
| 13 | `GOOGLE_REDIRECT_URL` | `https://<hôte api prod>/api/v1/auth/google/callback` | Erreur `redirect_uri_mismatch` |
| 14 | `FIREBASE_PROJECT_ID` | Firebase Console | Push mobiles KO |
| 15 | `FIREBASE_SERVICE_ACCOUNT_JSON` | Firebase → Comptes de service (JSON sur **une ligne**) | Push KO |
| 16 | `MAIL_HOST` | Fournisseur SMTP | Envoi e-mail KO |
| 17 | `MAIL_USERNAME` | idem | idem |
| 18 | `MAIL_PASSWORD` | idem | idem |
| 19 | `RESEND_KEY` | Dashboard Resend | **Aucun e-mail ne part** (transport retenu : Resend, #5139) |
| 20 | `SUPER_ADMIN_PASSWORD` | Secret interne | Compte superadmin non initialisable |
| 21 | `ADMIN_DASHBOARD_URL` | URL du cockpit admin | Liens des e-mails de jobs morts |

> ⚠️ `FORCE_SUPER_ADMIN_PASSWORD_RESET=true` est présent dans le blueprint.
> `docker-entrypoint.sh` le **vide** quand `DEPLOY_TIER != dev` — comportement
> attendu, ne pas « corriger ».

Contrôle de parité automatisable (aucune valeur secrète n'est lue) :

```bash
RENDER_API_KEY=... dev-hub/tools/render-verify-services.sh
```

## 5. Étape 4 — Ordre de redéploiement

L'ordre n'est pas arbitraire : les migrations tournent **uniquement** sur le
service web (`RUN_MIGRATIONS=true`) et jamais au boot des workers (#6558).

| Ordre | Action | Raison |
|---|---|---|
| 1 | Poser les 21 variables `sync: false` sur `leopardo-prod` | Le web doit pouvoir démarrer |
| 2 | Poser les 2 secrets de dépôt (§3) | Débloque le rail de rattrapage |
| 3 | Déclencher `deploy-main-catchup` (`workflow_dispatch`) | C'est le rail qui aligne la prod sur `main` |
| 4 | Vérifier `/api/v1/health` : `version` = HEAD de `main` | Critère d'alignement |
| 5 | **Worker/scheduler** : voir #8147 — non provisionnable sans moyen de paiement | Blocage distinct |

```bash
gh workflow run deploy-main-catchup.yml --repo kitokoh/leopardo-hr
gh run watch --repo kitokoh/leopardo-hr
```

> Ne **pas** réactiver `WEB_QUEUE_DRAIN`/`WEB_SCHEDULER_LOOP` après provisionnement
> d'un worker : cela créerait une double consommation de la queue
> (cf. en-tête de `render.prod.yaml`, plan de sortie de l'intérim).

## 6. Étape 5 — Critères de réussite

Les trois gardes de #8397 doivent repasser au vert :

- [ ] **Rattraper la prod Render si en retard sur main** — plus de
      `secrets.RENDER_PROD_API_KEY manquant`
- [ ] **Déploiement aligné (prod)** — `prod↔main` écart = 0 commit
- [ ] **Déploiement aligné (dev)** — même famille

Puis clôturer #8397 en citant le SHA de prod servi.

## 7. Rollback

Retirer les deux secrets de dépôt (`gh secret delete`) : le rail de rattrapage
retombe en `::notice`, exactement dans l'état actuel. **Aucune donnée n'est
impactée** — cette procédure ne modifie que de la configuration.

## 8. Décision PM (2026-10-26)

- **Ne pas** provisionner le worker dans cette issue : il est bloqué par une
  décision de budget distincte (#8147 / #7649, refus 402). Mélanger les deux
  rendrait les deux impossible à clôturer.
- **Ne pas** poser `APP_KEY` en `generateValue` : cela réinitialiserait les
  sessions et rendrait indéchiffrables les payloads existants (#7967). La valeur
  est épinglée, la procédure la préserve.
- **Documenter plutôt que coder** : le code de dégradation gracieuse est déjà en
  place (audit 2026-10-21). Ce qui manque est strictement humain.

## Références

- Issue #8397 · `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` · `docs/ops/ACTIONS_PROPRIETAIRES.md`
- `render.prod.yaml` (état provisionné + bloc worker commenté, #7845)
- `docs/CI_CD_SECRETS.md` (inventaire des secrets CI)
