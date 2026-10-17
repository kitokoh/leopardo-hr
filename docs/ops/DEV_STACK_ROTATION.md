# DEV_STACK_ROTATION.md — Rotation multi-stacks du tier dev (A/B/C)

> Épic #8659. Sous-issues : #8660 (registre+doc), #8661 (keepalive),
> #8662 (bascule manuelle), #8663 (bascule auto), #8664 (worker + blueprint B).
>
> **Périmètre : tier DEV uniquement.** La prod (render.prod.yaml,
> deploy-prod.yml, promotion par tag validé) est hors de portée de tout ce
> qui suit — elle reste déployée uniquement quand un palier de stabilisation
> est atteint et qu'un tag `vX.Y.Z` est créé.

---

## 1. Incident fondateur (constat d'audit 2026-10-17)

| Fait | Preuve |
|---|---|
| API dev **suspendue** depuis le 2026-09-20 | Render API : `suspended=suspended`, `suspenders=["billing"]` sur `srv-d7dro8u7r5hc73a395pg` ; dernier deploy `live` = 2026-09-20 |
| Cause | Quota gratuit Render du workspace (750 h/mois) épuisé : keepalive 24/7 (~750 h) + chevauchements d'instances pendant les builds |
| Fronts dev sains | Vercel `gestionemployer-backend` 200, Pages `leo-admin` 200 (mais inutilisables sans API) |
| Prod saine | `leopardo-prod` `not_suspended` — non concernée |

Leçon : **le keepalive naïf détruit le quota qu'il protège**, et un tier dev
mono-fournisseur est un SPOF dès qu'un quota gratuit est atteint. D'où ce
système : plusieurs stacks complètes sur des comptes distincts, une bascule
manuelle en un geste, une bascule automatique gardée, un keepalive budgété.

## 2. Concepts

**Stack** = ensemble autonome sur UN jeu de comptes distinct :

| Composant | Rôle | Plan gratuit de référence |
|---|---|---|
| API | Render Web Service (docker, frankfurt) | 750 h/mois **par compte**, spin-down après 15 min d'inactivité |
| DB | Neon Postgres | compute suspendu après inactivité, quotas transfert/compute **par compte** |
| KV (optionnel) | Render Key Value ou Upstash | 1 instance free par workspace Render |
| Web | Vercel (front web) | build minutes **par compte** |
| Admin | Cloudflare Pages (optionnel) | builds/mois **par compte** |

**Données** : fictives, produites par les seeders → chaque stack possède SA
base Neon ; une bascule ne migre aucune donnée, elle re-seed si demandé
(`--seed`, one-shot `RESET_TEST_DB_ONCE` fail-closed `DEPLOY_TIER=dev`).

**Registre** : `deploy/dev-stacks.json` — source documentaire des stacks
(statuts : `active | ready | degraded | suspended | planned`).
**Pointeurs runtime** : variables du dépôt `DEV_ACTIVE_STACK`,
`DEV_API_BASE_URL`, `DEV_WEB_URL`, `DEV_ADMIN_URL` — mises à jour par le
script de bascule (le fichier registre, lui, s'aligne en PR — protection de
branche ; le watchdog lit l'état courant depuis les variables).

État au 2026-10-17 : stack **a** = existante, `suspended` (quota, reset le
1er du mois) ; stacks **b**/**c** = `planned` (comptes à créer, § 7).

## 3. Quotas gratuits — la carte des pièges

| Fournisseur | Limite critique | Symptôme quand atteinte | Parade |
|---|---|---|---|
| Render web free | 750 h/mois/compte | **Suspension billing** (l'incident du 2026-09-20) | keepalive fenêtré (§ 4), 1 service éveillé par compte, rotation de comptes |
| Render builds | minutes de build limitées | builds refusés en fin de mois | `autoDeploy:false`, gate `api_changed` (déjà en place, #6700) |
| Neon free | compute hours + transfert/mois/compte | compute suspendu → API 503 | keepalive fenêtré (chaque ping réveille le compute), rotation |
| Vercel hobby | build minutes + bande passante | déploiements en échec | fronts par compte, pas de rebuild à la bascule (front B déjà déployé) |
| Upstash / KV free | requêtes/jour | erreurs cache | `CACHE_STORE=file` + probe redis au boot (déjà en place) |
| GitHub Actions (dépôt PUBLIC) | minutes illimitées, mais file + ordonnanceur crons parfois instables | crons qui ne tirent plus (constat 2026-09-21) | relais Worker Cloudflare (§ 5), battement sur push limité |
| Cloudflare Workers | 100 000 req/jour | — | le keepalive coûte ~4 500 req/mois |

Règle d'or : **un compte = une stack = un seul service tenu éveillé**. Deux
services éveillés 24/7 sur le même compte = suspension garantie avant la fin
du mois.

## 4. Keepalive — « comment garder Render éveillé »

### La réponse courte

On ne le garde PAS éveillé en permanence. On le garde éveillé **pendant les
heures de travail**, et on accepte un cold start de 30–60 s hors fenêtre.

### Le budget (à vérifier avant de toucher aux crons)

- spin-down Render après **15 min** sans requête → cadence de ping : **10 min** ;
- fenêtre retenue : **06:00–22:59 UTC, 7 j/7** (~17 h/jour) ;
- service éveillé ≈ 17 h × 31 j ≈ **527 h/mois** ;
- + chevauchement de builds (deux instances pendant chaque déploiement,
  ~90 h/mois en développement intense) ≈ **620 h/mois** ;
- 620 h < 750 h ⇒ **marge ~17 %**. Rétrécir la fenêtre (ex. 07–21 h, lun–ven)
  est le premier levier si la consommation réelle dérive.

### Mise en œuvre (deux ordonnanceurs se couvrent)

1. `.github/workflows/dev-keepalive.yml` — cron GitHub fenêtré + battement
   sur push (≤ 1/h, fenêtre uniquement) + **watchdog** (§ 6).
2. `infra/cloudflare/dev-keepalive-worker/` — Cron Trigger Cloudflare, même
   fenêtre (déploiement : README du dossier). Relais indispensable : les
   crons GitHub ont déjà cessé de tirer sur ce dépôt le 2026-09-21.

Un double ping ne coûte rien (service déjà éveillé) ; un ping manqué = un
cold start. Les deux sont volontairement redondants.

⚠️ **Ne pinger que la stack ACTIVE** (`KEEPALIVE_URL` / `DEV_API_BASE_URL`).
Pinger une stack standby la maintiendrait éveillée et grillerait SON quota —
c'est précisément le piège de l'incident fondateur.

## 5. Fichiers — « pour les fichiers, comment faire »

Constat : `FILESYSTEM_DISK=local` sur le tier dev (`api/.env.example`) ; le
disque d'un service Render free est **éphémère** (perdu à chaque déploiement
et spin-down). Des secrets Backblaze B2 (`B2_KEY_ID`, `B2_APP_KEY`,
`B2_BUCKET_NAME`) existent déjà pour les sauvegardes DB chiffrées.

Décision dev (données fictives) :

| Besoin | Choix | Pourquoi |
|---|---|---|
| Fichiers de test courants (uploads, PDF) | **disque local éphémère** (statu quo) | gratuit, zéro config ; la perte au spin-down est sans importance — les seeders régénèrent le contexte |
| Fichiers à conserver entre spin-downs (jeux de démo, fixtures lourdes) | **B2 dans le bucket existant, préfixe `dev-files/`** | 10 Go offerts, déjà provisionné, S3-compatible (`AWS_ENDPOINT` + `AWS_USE_PATH_STYLE_ENDPOINT=true`) — pas de nouveau compte |
| Fichiers « prod-like » | hors sujet dev | la prod garde sa propre stratégie (render.prod.yaml) |

Ne jamais utiliser le KV/Redis comme stockage de fichiers (quota requêtes).

## 6. Bascule — manuelle et automatique

### Manuelle (un dev, un geste)

Depuis GitHub : workflow **« Dev Stack Switch »** → Run workflow → stack
cible, option `seed`. En local :

```bash
scripts/dev-stack-health.sh --pretty        # qui est sain ?
scripts/dev-stack-switch.sh b --seed        # bascule + reseed de la cible
```

Le script : vérifie la cible (statut `ready` + healthcheck 200 — **jamais de
bascule vers une stack malade**), met à jour `DEV_ACTIVE_STACK` /
`DEV_API_BASE_URL` (+ URLs web/admin si définies), re-seed si demandé,
re-vérifie, affiche le runbook de fin. Rollback : `dev-stack-switch.sh a`.

### Automatique (le programme le fait)

Le watchdog de `dev-keepalive.yml` : si la stack active échoue **3 sondes
espacées** dans un run, il cherche une stack `ready` dans le registre et
appelle le workflow de bascule (`seed=true`). Garde-fous :

- **cooldown 24 h** (anti ping-pong ; état = dernière issue `[dev-switch]`) ;
- jamais vers `planned | suspended | degraded` ;
- rapport systématique en issue `[dev-switch]` (succès ET échec) ;
- si aucune cible : **alerte sans bascule** (issue d'alerte).

Effet de bord vertueux : `deploy-main.yml` déploie désormais sur **toutes
les stacks dont le hook est configuré** (fan-out) — la stack standby est
toujours sur le dernier `main`, la bascule n'a aucun rattrapage de code à
faire. Seul l'échec du hook de la stack ACTIVE fait échouer le pipeline.

### Après une bascule (check-list du rapport `[dev-switch]`)

1. PR d'alignement : `deploy/dev-stacks.json` (`active_stack`, statuts) et
   `infra/cloudflare/dev-keepalive-worker/wrangler.toml` (`KEEPALIVE_URL`) ;
2. vérifier le front de la nouvelle stack (§ 7, étape fronts) ;
3. annoncer l'URL à l'équipe.

## 7. Onboarding d'une stack B/C (< 30 min, comptes à créer)

1. **Comptes** : créer Render B, Neon B, Vercel B (emails dédiés, ex.
   `leopardo.dev.b@…`). UN jeu de comptes par stack (§ 3).
2. **API** : dashboard Render B → *New + → Blueprint* → repo
   `kitokoh/leopardo-hr`, branche `main`, fichier **`render.dev-b.yaml`** →
   *Apply*. Saisir les `sync:false` : `DB_URL` (Neon B), `APP_KEY`
   (régénérée), `SUPER_ADMIN_PASSWORD`, `FRONTEND_URL`.
3. **Hook** : service `leopardo-dev-b` → Settings → Deploy Hook → ajouter le
   secret GitHub `RENDER_DEPLOY_HOOK_URL_STACK_B`. Dès le prochain merge sur
   `main`, la stack B se déploie toute seule (fan-out, § 6).
4. **Secrets CI** (optionnels mais recommandés) : `DEV_STACK_B_RENDER_API_KEY`
   (reseed à distance), front Vercel B : déployer une fois
   (`vercel deploy --prod` depuis `front/web`, projet du compte B, avec
   l'env qui pointe sur l'API B — cf. la note travel-web de
   `DEPLOYMENT_URLS.md` pour le déploiement CLI sans « Login Connection »).
5. **Registre** : PR qui renseigne les URLs/ids réels de `b` dans
   `deploy/dev-stacks.json` et passe son statut à `ready`.
6. **Test** : `scripts/dev-stack-switch.sh b` puis retour `a` (données
   fictives — le test de bascule EST gratuit).

Répéter pour `c` quand les quotas de deux comptes ne suffisent plus.

## 8. Dépannage

| Symptôme | Diagnostic | Action |
|---|---|---|
| `dev-stack-health.sh` → HTTP 503 + page « suspended by its owner » | stack suspendue billing | attendre le reset du 1er ou basculer (`dev-stack-switch.sh`) |
| HTTP 000 / timeout > 60 s | cold start ou build en cours | réessayer dans 60–90 s ; dashboard Render |
| Healthcheck 200 mais « version » ancienne | hook sans effet (#6973) | redéployer via `deploy-main.yml` dispatch `force_deploy` |
| Bascule auto refusée « cooldown » | une bascule a eu lieu < 24 h | manuelle assumée : workflow dispatch `force` |
| Watchdog muet | crons GitHub en panne (déjà vu 2026-09-21) | vérifier le Worker Cloudflare ; relancer à la main (`workflow_dispatch`) |

## Références

- `deploy/dev-stacks.json` (registre), `scripts/dev-stack-health.sh`,
  `scripts/dev-stack-switch.sh`
- `.github/workflows/dev-keepalive.yml`, `.github/workflows/dev-stack-switch.yml`,
  fan-out dans `.github/workflows/deploy-main.yml`
- `infra/cloudflare/dev-keepalive-worker/`, `render.dev-b.yaml`
- Contexte existant : `render.yaml` (#7845), `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`,
  `db-standby-failover.yml` (modèle watchdog, **prod**), `DEPLOYMENT_URLS.md`
