# Travel Web — déploiement Vercel (`front/travel-web`)

> Issue #7740 (épic #7736, décision #6918). Runbook propriétaire du raccordement
> CI de `front/travel-web` aux deux projets Vercel. État courant des URLs et
> domaines : `docs/ops/DEPLOYMENT_URLS.md` et `docs/ops/DOMAINS.md` (sections
> Travel, PR #7830/#7835).

## Topologie (P07 — dev/prod séparés)

| Volet | Projet Vercel | Compte | Domaine | Backend ciblé |
|---|---|---|---|---|
| dev | `leopardo-travel` | africanovatech | https://leopardo-travel.vercel.app | `https://gestionemployerbackend.onrender.com/api/v1` |
| prod | `leopardo-travel-prod` | ibrahimkoubaye | https://leopardo-travel-prod.vercel.app | `https://leopardo-prod.onrender.com/api/v1` |

Les deux projets ont été créés et configurés via l'API Vercel (PR #7830/#7835) :
`framework=nextjs`, `rootDirectory=front/travel-web`. Le fichier
`front/travel-web/vercel.json` (région `cdg1`, headers de sécurité,
`ignoreCommand` scopé `:/front/travel-web`) est appliqué par les deux projets.

## Chemins de déploiement

1. **GitHub Actions — `.github/workflows/travel-web-deploy.yml`** (ce lot, #7740) :
   - PR touchant `front/travel-web/**` → déploiement **preview** sur le projet dev
     (URL unique par déploiement, résumé dans le run).
   - Push `main` touchant `front/travel-web/**` → déploiement **production**
     (`vercel deploy --prebuilt --prod`) sur `leopardo-travel-prod` + healthcheck.
   - `workflow_dispatch` avec input `environment` (`preview`/`production`) pour
     un (re)déploiement manuel.
   - **Prêt-à-secrets** : tant que les secrets ci-dessous ne sont pas posés, les
     jobs se terminent en skip explicite (`::warning::` + step summary) — rien
     n'échoue, rien n'est déployé.
2. **Intégration Git Vercel** (projet prod uniquement, lié à `kitokoh/leopardo-hr`,
   production branch `main`) : repli automatique. Le projet dev n'a PAS de lien
   git (Login Connection GitHub absente du compte africanovatech — action
   propriétaire, voir `docs/ops/DEPLOYMENT_URLS.md`).
3. **Vercel CLI manuelle** : repli documenté dans `docs/ops/DEPLOYMENT_URLS.md`.

L'intégration au pipeline de promotion par tag (`deploy-prod.yml`) reste hors
périmètre immédiat — suivi tracé dans #7740/#7736.

## Secrets GitHub Actions à poser (action PROPRIÉTAIRE)

Repo → Settings → Secrets and variables → Actions → *Secrets*. Deux jeux
distincts (deux comptes Vercel — jamais de token partagé entre dev et prod).
Inventaire canonique : `docs/CI_CD_SECRETS.md` (garde de parité #7271).

| Secret | Volet | Contenu |
|---|---|---|
| `VERCEL_TRAVEL_DEV_TOKEN` | dev | Token CLI Vercel du compte **africanovatech** (Account Settings → Tokens, scope le plus restreint possible) |
| `VERCEL_TRAVEL_DEV_ORG_ID` | dev | `orgId` du compte africanovatech (visible dans `.vercel/project.json` après `vercel link`, ou Dashboard → Settings → General) |
| `VERCEL_TRAVEL_DEV_PROJECT_ID` | dev | `projectId` du projet `leopardo-travel` |
| `VERCEL_TRAVEL_PROD_TOKEN` | prod | Token CLI Vercel du compte **ibrahimkoubaye** |
| `VERCEL_TRAVEL_PROD_ORG_ID` | prod | `orgId` du compte ibrahimkoubaye |
| `VERCEL_TRAVEL_PROD_PROJECT_ID` | prod | `projectId` du projet `leopardo-travel-prod` |

> Les `orgId`/`projectId` ne sont pas des secrets au sens strict côté Vercel,
> mais le repo a décidé de ne pas les committer (hygiène PR #7835 — retrait de
> `front/travel-web/.vercel/project.json`, `.vercel/` ignoré) : ils vivent donc
> en secrets, comme `VERCEL_PROD_ORG_ID`/`VERCEL_PROD_PROJECT_ID` (front/web).

Pour récupérer les IDs sans rien committer :

```bash
cd front/travel-web
vercel link --yes --token <TOKEN_DU_COMPTE>   # choisir le projet
cat .vercel/project.json                      # {"orgId": "...", "projectId": "..."}
rm -rf .vercel                                # ne jamais committer
```

## Variables d'environnement runtime (côté Vercel, PAS dans le repo)

Posées via Dashboard → Project → Settings → Environment Variables (déjà en
place depuis PR #7830 — vérifier après toute recréation de projet) :

| Variable | dev (`leopardo-travel`) | prod (`leopardo-travel-prod`) |
|---|---|---|
| `BACKEND_API_URL` | `https://gestionemployerbackend.onrender.com/api/v1` | `https://leopardo-prod.onrender.com/api/v1` |
| `NEXT_PUBLIC_SITE_URL` | `https://leopardo-travel.vercel.app` | `https://leopardo-travel-prod.vercel.app` |

Notes :
- La chaîne de résolution du proxy same-origin est
  `API_PROXY_TARGET` > `BACKEND_API_URL` > `NEXT_PUBLIC_API_URL` > défaut Render
  dev (voir `front/travel-web/README.md`) — `API_PROXY_TARGET` n'est utile que
  pour pointer temporairement ailleurs sans toucher `BACKEND_API_URL`.
- Aucun secret d'API n'est nécessaire : le proxy est restreint fail-closed à
  `public/travel/*` (headers `Authorization`/`Cookie` supprimés sauf allowlist
  compte client — PR #7781/#7795).
- Les endpoints `public/travel/marketplace/*` répondent 404 tant que le backend
  Render du volet n'inclut pas #7737/#7781.

## Domaines

- **dev** : `leopardo-travel.vercel.app` (domaine par défaut du projet). Pas de
  domaine custom prévu pour le tier dev.
- **prod** : `leopardo-travel-prod.vercel.app` aujourd'hui. Pour un domaine
  custom (ex. `travel.leopardo.example`) : Dashboard → `leopardo-travel-prod` →
  Settings → Domains → Add, poser le CNAME chez le registrar, puis mettre à
  jour `NEXT_PUBLIC_SITE_URL` (prod), `docs/ops/DOMAINS.md`,
  `docs/ops/DEPLOYMENT_URLS.md` et l'URL du healthcheck prod dans
  `.github/workflows/travel-web-deploy.yml`.
- Registre canonique des domaines : `docs/ops/DOMAINS.md`.

## Marketplace « Leopardo Marché » (`front/marketplace`, #7994)

Même mécanique que travel-web, avec deux différences : il n'existe **pas de
projet marketplace dev distinct** (la preview de PR est un déploiement preview
du projet prod, URL unique `*.vercel.app`) et le skip « secrets absents » est
**bloquant sur push main** (`::error` + échec du job — #7994 §4 : un push qui
touche le front sans déploiement possible doit être visible, fin du run vert
trompeur constaté par l'audit).

| Secret | Contenu |
|---|---|
| `VERCEL_MARCHE_PROD_TOKEN` | Token CLI Vercel du compte **ibrahimkoubaye** — token DÉDIÉ, **jamais un token voué à révocation** (#7994 §1) |
| `VERCEL_MARCHE_PROD_ORG_ID` | `orgId` du compte ibrahimkoubaye |
| `VERCEL_MARCHE_PROD_PROJECT_ID` | `projectId` du projet `leopardo-marche` (rootDirectory `front/marketplace`, domaine `https://leopardo-marche.vercel.app`) |

- Workflow : `.github/workflows/marketplace-deploy.yml` (PR → preview ; push
  main / dispatch → production + healthcheck sur le domaine stable).
- `NEXT_PUBLIC_MARKET_API_BASE` est exigée au build (#7963) et déjà posée sur
  le projet — `vercel pull` la récupère comme pour travel-web.
- Après un déploiement production réussi, le workflow enregistre un **GitHub
  Deployment** (`Production – leopardo-marche`, même convention pour
  travel-web) : la garde `deploy-drift-guard.yml` (job `fronts-vercel`,
  script `dev-hub/tools/check-vercel-front-drift.sh`) compare alors le sha
  servi à `main` **sans aucun secret Vercel** et échoue visiblement en cas
  de retard (#7994 §5).
- Les déploiements de l'intégration Git Vercel du projet étaient tous
  CANCELED depuis le 2026-09-19 (constat #7994) — vérifier l'intégration
  côté Dashboard si le repli git doit revivre ; le chemin de référence
  reste le workflow CI ci-dessus.

## Checklist propriétaire (activation du pipeline)

- [ ] Créer un token CLI sur chaque compte Vercel (africanovatech, ibrahimkoubaye).
- [ ] Récupérer `orgId`/`projectId` des deux projets (`vercel link`, voir plus haut).
- [ ] Poser les 6 secrets `VERCEL_TRAVEL_*` dans GitHub (tableau ci-dessus).
- [ ] Poser les 3 secrets `VERCEL_MARCHE_PROD_*` (section Marketplace, #7994).
- [ ] Vérifier les env vars runtime des deux projets (tableau ci-dessus).
- [ ] Lancer `travel-web-deploy.yml` en `workflow_dispatch` (`preview`) et
      vérifier le run vert + l'URL de preview dans le step summary.
- [ ] Lancer en `workflow_dispatch` (`production`) et vérifier
      https://leopardo-travel-prod.vercel.app (healthcheck du run + contrôle visuel).
- [ ] (Optionnel, débloque l'intégration Git dev) Ajouter la Login Connection
      GitHub au compte africanovatech puis lier le projet `leopardo-travel` au
      repo — voir `docs/ops/DEPLOYMENT_URLS.md`.
- [ ] (Optionnel) Désactiver la Deployment Protection des previews du projet dev
      si les previews doivent être partageables sans authentification Vercel.

## Dépannage

- **Job prod en échec « secrets absents » sur push main** : comportement voulu
  depuis #7994 §4 (skip bloquant) — poser les secrets du volet concerné, ou
  déployer manuellement via la CLI en attendant (la garde drift des fronts
  restera rouge tant que la prod n'aura pas été redéployée via un workflow
  enregistreur).
- **Job preview en skip avec warning (PR)** : secrets absents — non bloquant,
  voir checklist.
- **`vercel pull` échoue (403/projet introuvable)** : token du mauvais compte,
  ou `ORG_ID`/`PROJECT_ID` ne correspondent pas au projet — re-vérifier avec
  `vercel link`.
- **Build en échec `ENOENT front/travel-web/front/travel-web/...`** : la CLI a
  été lancée avec `--cwd front/travel-web` alors que le projet porte déjà
  `rootDirectory` — lancer depuis la racine du repo (fix #6810).
- **Healthcheck prod rouge** : ouvrir l'URL du déploiement dans le step summary,
  puis les logs du build côté Vercel (env manquante, quota
  `api-deployments-free-per-day` — leçon #4868).
