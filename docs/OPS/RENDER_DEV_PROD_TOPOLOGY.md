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
| **prod** (stable) | tag `vX.Y.Z` validé | `render.prod.yaml` | `deploy-prod.yml` | Phase 1 : tier gratuit (web + Postgres + Key Value) |

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
- Postgres (⚠️ le plan gratuit expire après 30 jours + 14 jours de grâce —
  ne jamais y stocker de données réelles durablement)
- Key Value (Redis-compatible, en mémoire uniquement)

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
4. Migrer Postgres du plan gratuit vers un plan payant avec sauvegardes
   (le plan gratuit n'a aucune sauvegarde et expire).
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
| `RENDER_PROD_DEPLOY_HOOK_URL` | Hook de déploiement du service `gestionemployerbackend-prod` (`render.prod.yaml`) |
| `RENDER_PROD_ROLLBACK_HOOK_URL` | Hook de rollback (optionnel mais recommandé) |

**Variables** (`Settings > Secrets and variables > Actions > Variables`) :

| Variable | Rôle |
|---|---|
| `PROD_RENDER_API_BASE_URL` | URL publique du service prod (ex. `https://gestionemployerbackend-prod.onrender.com`), utilisée pour le healthcheck post-déploiement |

Ces noms sont volontairement distincts des secrets/variables déjà utilisés
par `deploy-main.yml` (`RENDER_DEPLOY_HOOK_URL`, `RENDER_ROLLBACK_HOOK_URL`,
`vars.PROD_API_BASE_URL`) pour éviter toute collision avec l'environnement
dev/continu existant.

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
