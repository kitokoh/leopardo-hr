# Décisions OPS/CI — 2026-10-26

> Arbitrages rendus en posture fondateur / DevOps / PM sur les issues
> d'infrastructure et de gouvernance restées ouvertes. Chaque décision est
> motivée, et distingue explicitement **ce qui a été livré par du code** de
> **ce qui exige une action propriétaire** (aucun commit ne peut le satisfaire).

---

## #8062 — Domaine canonique `www.leopardo-rh.com` en NXDOMAIN

**Statut : BLOQUÉE côté DNS (action propriétaire), DÉBLOQUÉE côté signal.**

### Le problème réel

`www.leopardo-rh.com` est l'URL canonique **déclarée par le code**
(`front/web/src/lib/site.ts` → `DEFAULT_SITE_URL`) mais ne résout pas. Le seul
accès fonctionnel est `gestionemployer-backend.vercel.app` — un nom de projet
Vercel qui n'inspire aucune confiance en barre d'adresse. Metadata OG, sitemap,
JSON-LD et liens partagés pointent donc vers un domaine mort.

### Décision D-OPS-1 — Le code ne peut pas créer un enregistrement DNS

La correction est une configuration de zone (`leopardo-rh.com` → projet Vercel
`web`, A/ALIAS + CNAME `www`). Aucun commit ne peut la réaliser. **L'issue reste
ouverte**, priorité P0 confirmée.

### Décision D-OPS-2 — Rendre la panne VISIBLE plutôt que silencieuse

Ce qui était codable, c'est le **signal**. Livré :

- `dev-hub/tools/check-canonical-domain-live.sh` — vérifie que le domaine
  canonique résout, répond 200, et **correspond au domaine déclaré par le
  front** (sinon le garde surveillerait un domaine que le site n'annonce pas) ;
- `dev-hub/tools/tests/check-canonical-domain-live.test.sh` — 5 scénarios, dont
  NXDOMAIN, HTTP 500, divergence de domaine et **source de vérité absente
  (fail-closed : pas de vert par défaut)** ;
- `.github/workflows/canonical-domain-guard.yml` — planifié toutes les 6 h.

### Décision D-OPS-3 — Garde PLANIFIÉE, jamais requise au merge

Un check **requis** resterait rouge jusqu'à l'action propriétaire et bloquerait
**tous** les merges du dépôt. Le remède serait pire que le mal. La garde est
donc planifiée (visible dans l'onglet Actions) et non requise — même arbitrage
que `deploy-drift-guard.yml`.

### Procédure de bascule (à exécuter par le propriétaire)

1. Zone DNS `leopardo-rh.com` → projet Vercel `web` : `A`/`ALIAS` sur l'apex,
   `CNAME www` → cible Vercel.
2. Ajouter le domaine dans le projet Vercel (+ redirection apex ↔ www, HTTPS).
3. Poser `NEXT_PUBLIC_SITE_URL=https://www.leopardo-rh.com` au déploiement.
4. Vérifier : `bash dev-hub/tools/check-canonical-domain-live.sh` → **exit 0**.
5. Renommer/aliaser le projet Vercel pour ne plus exposer
   `gestionemployer-backend` (vestige trompeur : c'est le front public).

**Critère de clôture :** la garde passe au vert, et `leopardo-rh.com` comme
`www.leopardo-rh.com` répondent 200.

---

## #8397 — Secrets Render absents (prod 619 commits derrière `main`)

**Statut : PRÉPARÉE, non exécutée — action propriétaire.**

Livré par la PR #8900 (mergée) : `docs/ops/RENDER_SECRETS_CHECKLIST.md`
transforme l'issue en **procédure déroulable** — chaque étape est une commande
ou un clic, avec son critère de réussite. Deux secrets de dépôt manquent
(`RENDER_PROD_API_KEY`, `RENDER_PROD_SERVICE_ID`) : sans eux, aucun rattrapage
automatique de la prod n'est possible.

**Décision :** l'issue reste ouverte jusqu'à l'exécution de la checklist. Le
livrable codable (la procédure) est mergé ; le reste est un accès dashboard.

---

## #8147 — Services worker/scheduler dédiés en production

**Statut : BLOQUÉE côté provisionnement, DÉBLOQUÉE côté signal.**

Livré par la PR #8900 (mergée) : `docs/ops/RENDER_WORKER_DECISION.md`
(arbitrage documenté) + l'alerte « prod derrière `main` > 1 h »
(`dev-hub/tools/check-prod-drift-age.sh`, workflow `deploy-drift-guard.yml`).

**Le blocage est budgétaire, pas technique :** le provisionnement a été tenté
via l'API Render le 2026-09-19 (`POST /v1/services`, `type: background_worker`,
`plan: starter`) → **refus HTTP 402 « Payment information is required »**. Les
*Background Workers* ne sont pas éligibles au tier gratuit.

**Décision D-OPS-4 — Ne pas dé-commenter le bloc worker de `render.prod.yaml`.**
Le blueprint documente explicitement « l'**état provisionné** » ; le
dé-commenter sans moyen de paiement produirait un blueprint qui ment (sync en
échec 402). L'état *cible* reste commenté jusqu'à la décision de budget.

---

## #7967 — Queue + scheduler dans un web service `plan: free` avec spin-down

**Statut : SUPERSEDED par #8147 — recommandation de clôture.**

Le rapprochement programme Business OS est déjà consigné dans l'issue : #8147
reprend le périmètre complet (services dédiés + alerte drift + estimation coût).
Le correctif `APP_KEY` est livré (`ab11e87c1`, `bc0464b35`).

**Décision D-OPS-5 — Un seul ticket fait foi.** Maintenir deux tickets pour le
même sujet garantit qu'un des deux sera oublié. #8147 fait foi ; #7967 est
recommandée à la clôture comme *superseded*. La clôture effective reste une
décision propriétaire (elle change le suivi du programme).

---

## #7657 — 2 gardes planifiées rouges en attente d'actions propriétaire

**Statut : BLOQUÉE — action propriétaire (secrets).**

Les corrections de code sont livrées (`ae0159a8e`, `0d18e154e` : OpenAPI
dédoublonné, 12 routes documentées, garde RBAC). Les 2 gardes restantes
exigent des secrets que seul le propriétaire peut créer :

| Garde | Secret manquant | Action |
|---|---|---|
| `branch-protection-guard.yml` | `BRANCH_PROTECTION_TOKEN` | Fine-grained PAT, permission **Administration : Read-only** |
| `database-backup.yml` | `BACKUP_S3_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` | Bucket S3 + identifiants |

**Décision D-OPS-6 — Ne PAS utiliser un PAT classique large.** La garde n'a
besoin que de la **lecture d'administration** du dépôt. Un PAT `admin:org`/`repo`
serait un privilège sans rapport avec le besoin — et un secret de plus à faire
fuiter. Le principe du moindre privilège s'applique aussi aux gardes.

---

## #8285 — Panne Cloudflare « Workers Builds: gestionemploye »

**Statut : BLOQUÉE — action propriétaire (Cloudflare).**

Le check échoue de façon identique sur `main` et sur les branches de PR
(confirmé : `8eb4130` → failure instantané). La correction exige un accès au
compte Cloudflare (configuration du build worker), hors de portée d'un commit.

**Décision D-OPS-7 — Ne pas masquer le check.** Le rendre non bloquant
supprimerait le signal sans corriger la cause, et la panne reviendrait
silencieusement. L'issue reste ouverte et le check reste visible.

---

## #7994 — Fronts Vercel prod jamais redéployés

**Statut : PARTIELLEMENT IMPLÉMENTÉE — reste une action propriétaire.**

Audit du 2026-09-26 : les 3 items actionnables in-repo sont **déjà livrés** sur
`main` par la PR #8015 (`ed54db30a`) — `marketplace-deploy.yml`, skip secrets
bloquant (`::error` + `exit 1`), garde de drift des fronts Vercel. Le
redéploiement **effectif** des fronts prod dépend de secrets Vercel
(`VERCEL_*_PROD_*`) que seul le propriétaire peut poser.

**Décision D-OPS-8 — Ne pas re-coder ce qui est livré.** L'issue est conservée
ouverte pour tracer l'action propriétaire restante, pas pour dupliquer un
travail déjà mergé.

---

## Synthèse

| Issue | Décision | Livré par du code | Reste à faire |
|---|---|---|---|
| #8062 | D-OPS-1/2/3 | Garde + tests + workflow | Zone DNS (propriétaire) |
| #8397 | — | Checklist exécutable (PR #8900) | Poser 2 secrets (propriétaire) |
| #8147 | D-OPS-4 | Décision + alerte drift (PR #8900) | Moyen de paiement (propriétaire) |
| #7967 | D-OPS-5 | — | Clôture comme *superseded* |
| #7657 | D-OPS-6 | Correctifs OpenAPI/RBAC (déjà livrés) | 4 secrets (propriétaire) |
| #8285 | D-OPS-7 | — | Accès Cloudflare (propriétaire) |
| #7994 | D-OPS-8 | 3 items livrés par #8015 | Secrets Vercel (propriétaire) |
