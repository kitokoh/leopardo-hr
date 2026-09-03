# Topologie de déploiement Render — dev vs prod

## Contexte

Historiquement, `render.yaml` déployait un unique groupe de services à
chaque push sur `main`, configuré avec `APP_ENV=production` et des clés de
paiement réelles (Stripe/Chargily). Il n'existait aucune séparation entre
un environnement de développement continu et une production stable : tout
merge sur `main` atteignait directement ce qui était nommé (et configuré
comme) la production, et les tags de version (`v4.24.0`, `v4.25.0`, ...)
ne déclenchaient qu'une création de changelog GitHub (`release.yml`),
jamais un déploiement.

Le domaine `leopardo-rh.com` n'étant pas encore acheté au moment de ce
chantier, aucun trafic client réel ne dépendait de cet environnement — ce
qui a permis de le requalifier sans opération de bascule à chaud.

## Nouvelle topologie

| Tier | Déclencheur | Fichier | Workflow | Coût |
|------|-------------|---------|----------|------|
| **dev** (continu) | push sur `main` | `render.yaml` | `deploy-main.yml` | Plan existant (Starter), inchangé |
| **prod** (stable) | tag `vX.Y.Z` validé | `render.prod.yaml` | `deploy-prod.yml` | Phase 1 : tier gratuit (web + Key Value) + Neon Postgres |

### ⚠️ Dérive constatée entre `render.yaml` (dev) et la config réelle

En vérifiant la config live du service dev (`gestionemployerBackend`,
workspace Render `africanovatech`) avant de répliquer son schéma en prod,
constat empirique (pas une supposition) :

- `render.yaml` déclare un Postgres géré par Render (`databases:
  leopardo-db`) — **ce Postgres n'existe pas** dans le workspace dev
  (`GET /v1/postgres` → liste vide). La base réelle est `DB_URL` pointé
  manuellement vers **Neon** (projet `leopardorh`, host
  `...eu-west-2.aws.neon.tech`).
- `render.yaml` déclare Redis (`CACHE_STORE=redis`, `SESSION_DRIVER=redis`,
  `fromDatabase: leopardo-redis`) — la config live utilise en réalité
  `CACHE_STORE=file` et `SESSION_DRIVER=file` (pas de Redis actif),
  bien qu'une instance Key Value existe (`leopardoai`, apparemment inutilisée
  pour ça).

`render.yaml` (dev) n'a pas été corrigé dans ce chantier (hors périmètre
initial), mais cette dérive doit être traitée séparément pour que le
fichier redevienne une source de vérité fiable.

### Pourquoi `render.yaml` n'a pas été renommé

Renommer les `name:` de service dans `render.yaml` aurait fait perdre à
Render le mapping avec les services déjà déployés : Render aurait recréé
de nouveaux services au lieu de renommer les existants, laissant les
anciens orphelins (facturés, inutilisés). Le fichier reste donc identique
sur ce point ; seule son rôle (dev/test continu) est désormais documenté
en tête de fichier.

### Phase 1 — tier gratuit pour `render.prod.yaml`

Limite constatée (docs Render 2026) : les services de type **Background
Worker ne sont pas éligibles au tier gratuit** (seuls Web Service, Postgres
et Key Value le sont). `render.prod.yaml` ne provisionne donc, pour cette
phase de validation, que :

- le service web (API)
- Key Value (Redis-compatible, en mémoire uniquement)

La base de données n'est **pas** un Postgres Render : après avoir constaté
que le Postgres gratuit Render expire au bout de 30 jours, et que le dev
utilise déjà Neon en pratique (voir dérive ci-dessus), la prod a été
alignée sur le même schéma — **Neon Postgres** (projet `LEOPARDO`, branche
`production`, région `eu-central-1`), câblé via `DB_URL` (`sync: false`).
Le Postgres Render initialement créé pour Phase 1 a été supprimé après
validation croisée (healthcheck OK sur Neon).

**`leopardo-queue-worker` et `leopardo-scheduler` ne sont pas encore
répliqués en prod** — la paie, les notifications asynchrones et les tâches
planifiées ne fonctionneront pas sur cette prod tant que ces deux services
n'auront pas été ajoutés sur un plan payant (Starter, ~7 $/mois chacun).

### Suite recommandée avant un vrai lancement client

1. Valider que `deploy-prod.yml` déclenche correctement le déploiement et
   le healthcheck sur un tag de test (ex. `v0.0.1-rc1`).
2. Acheter le nom de domaine et le plan Render payant.
3. Ajouter `leopardo-queue-worker` et `leopardo-scheduler` à
   `render.prod.yaml` sur un plan payant.
4. Passer le projet Neon `LEOPARDO` (actuellement plan gratuit) sur un
   plan payant avant tout trafic réel (sauvegardes, quotas de calcul).
5. Renseigner les secrets réels dans l'environnement Pulumi ESC
   `solarnyxss/leopardo-hr/prod` (`pulumi env set ... --secret`), après
   rotation des clés Render/Stripe/Chargily déjà partagées en clair.
6. Une fois la prod validée en continu, envisager un renommage progressif
   des services `render.yaml` (dev) pour lever toute ambiguïté de nommage.

## Secrets et variables GitHub Actions requis

`deploy-prod.yml` échoue explicitement (fail-closed, cohérent avec le reste
du repo — cf. issues #4524/#4720) si l'un des éléments suivants manque. Rien
n'est pré-rempli automatiquement : ces valeurs doivent être saisies après
rotation des clés Render déjà partagées en clair dans ce chat.

**Secrets** (`Settings > Secrets and variables > Actions > Secrets`) :

| Secret | Rôle |
|---|---|
| `RENDER_PROD_API_KEY` | Clé API Render du workspace prod ("ALI MAHADI's workspace") — utilisée pour déclencher le déploiement, interroger son statut et effectuer un rollback réel via l'API (pas un simple deploy hook) |

Le `DB_URL` (connexion Neon) est configuré directement comme variable
d'environnement `sync: false` sur le service Render (pas un secret GitHub
Actions) — valeur dans Pulumi ESC `solarnyxss/leopardo-hr/prod`
(`neon.databaseUrl`).

**Variables** (`Settings > Secrets and variables > Actions > Variables`) :

| Variable | Rôle |
|---|---|
| `RENDER_PROD_SERVICE_ID` | ID du service web `leopardo-prod` (`srv-dacsr6gae00c73ddk150`) |
| `PROD_RENDER_API_BASE_URL` | URL publique du service prod (`https://leopardo-prod.onrender.com`), utilisée pour le healthcheck post-déploiement |

Ces noms sont volontairement distincts des secrets/variables déjà utilisés
par `deploy-main.yml` (`RENDER_DEPLOY_HOOK_URL`, `RENDER_ROLLBACK_HOOK_URL`,
`vars.PROD_API_BASE_URL`) pour éviter toute collision avec l'environnement
dev/continu existant.

**Ressources déjà provisionnées (2026-09-03)**, workspace prod Render
(`tea-da9svvpsrm7s73d8o2j0`) et organisation Neon (`org-shiny-snow-15524747`,
"ALI MAHADI") :
- Service web `leopardo-prod` (`srv-dacsr6gae00c73ddk150`, URL
  `https://leopardo-prod.onrender.com`) — recréé sous ce nom pour un
  sous-domaine propre (le slug Render est figé à la création : un simple
  renommage du service `gestionemployerbackend-prod` d'origine
  (`srv-dacr2j6k1f9s73adbj7g`, supprimé) ne changeait pas l'URL)
- Key Value `leopardo-redis-prod` (`red-dacr1rfavr4c739gtan0`)
- Postgres Neon — projet `LEOPARDO` (`rough-cherry-23234590`), branche
  `production` (`br-broad-forest-b18ek61g`), région `eu-central-1`.
  Le Postgres Render `leopardo-db-prod` initialement créé pour Phase 1 a
  été **supprimé** après migration validée vers Neon (healthcheck OK) —
  il n'apparaît plus dans `render.prod.yaml`.

Les clés Render et Neon partagées en clair dans la conversation ont été
utilisées pour cette création initiale ; elles doivent être régénérées
avant tout lancement public, conformément au plan de bascule ci-dessus.

**Source de vérité recommandée :** l'environnement Pulumi ESC
`solarnyxss/leopardo-hr/prod` (créé dans ce chantier) contient déjà la
structure attendue pour ces valeurs et pour les secrets applicatifs
(Stripe, Chargily, Google, Firebase, mail, admin). Un mainteneur ayant
accès à l'organisation Pulumi peut les résoudre avec :

```bash
pulumi env open solarnyxss/leopardo-hr/prod
```

puis les recopier dans les secrets/variables GitHub ci-dessus. Une
synchronisation automatisée ESC → secrets GitHub (script ou action dédiée)
pourra être ajoutée une fois ces valeurs réelles disponibles.
