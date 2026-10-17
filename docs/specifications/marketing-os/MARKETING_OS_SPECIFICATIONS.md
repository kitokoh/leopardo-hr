# Marketing OS — Spécifications (TAD · TRD · AppFlow · UIX · UI Design · Backend Schema · Plan)

> **Issue** : #8528 · **PR** : #8529 · **Branche** : `mkt/marketing-os-8528`
> **Audit amont obligatoire** : [`docs/specifications/MARKETING_OS_AUDIT.md`](../MARKETING_OS_AUDIT.md)
> **Périmètre livré** : noyau fonctionnel (stratégie, médiathèque, publications) greffé sur
> l'existant, **sans dupliquer** les services déjà en place.

Ce document couvre les 7 livrables demandés (TAD, TRD, AppFlow, UIX, UI Design, Backend Schema,
Implementation Plan) et les 2 annexes (matrice des plateformes, plan de mesure). Ils sont
**consolidés dans un document unique versionné** plutôt qu'éclatés en sept fichiers : le périmètre
est un module cohérent, et une spécification unique évite la dérive entre documents que le dépôt a
déjà connue. Chaque livrable garde sa section numérotée.

---

## 1. TAD — Architecture technique cible

### 1.1 Principe directeur : étendre, jamais dupliquer

L'audit a montré qu'une grande partie du socle existe. Le Marketing OS **n'introduit donc aucune
brique parallèle** : il ajoute une couche de pilotage éditorial au-dessus des services en place.

| Brique existante | Réutilisation | Ce qui est ajouté |
| --- | --- | --- |
| `social_accounts`, `social_posts`, `post_publications`, `SocialPublishingService`, publishers Ayrshare | **Réutilisable** | Rien : conservé tel quel, aucune modification |
| `ShowcaseMedia` + disk privé hors webroot | Réutilisable comme **patron** | `MarketingAssetStorage` (médiathèque marketing ≠ vitrine) |
| `MarketingAiService`, `LLMClient` | **Existante, à étendre** | Aucun appel IA ajouté dans ce lot (voir §7) |
| `TenantManager`, `BelongsToCompany`, `PlatformCompanyLookup` | **Réutilisable** | Aucune extension nécessaire |
| `PlatformPermission`, `PlatformRole` | **Réutilisable** | 2 permissions déclarées |
| Module `Geo` | **Absent** (stub vide) | Rien dans ce lot : voir §7, lot ultérieur |

### 1.2 Règle d'isolation inter-modules (#5584) — respectée et vérifiée

Trois conséquences structurelles, toutes validées par `dev-hub/tools/check-module-isolation.sh`
(**0 nouveau couplage croisé**) :

1. `MarketingAssetStorage` **reproduit** le patron de stockage de Showcase au lieu de l'importer :
   un module ne peut pas importer un autre module. La duplication est ici *imposée* par la
   gouvernance, et porte sur ~120 lignes au comportement volontairement identique.
2. La promotion d'une idée en tâche de production **n'importe pas** `Modules/Planning` : voir §6.3
   pour la décision motivée par le schéma réel.
3. Aucun accès direct au tenant par `where('company_id', …)` recopié : l'isolation vient du
   `search_path` installé par `TenantManager::withinTenant()` et du scope `BelongsToCompany`.

### 1.3 Surface plateforme : pourquoi `/admin/companies/{company}/marketing-os/*`

Les routes **tenant** existantes (`routes/modules/marketing.php`, préfixe `/marketing/*`, garde
`api.manager:marketing,principal`) sont réservées au rôle manager d'**une** entreprise. L'espace
demandé vit dans le **Super Admin** : il passe donc par `auth:super_admin_api` +
`platform.permission:*`, avec le tenant ciblé **explicite** dans l'URL.

Le précédent retenu est `PlatformFundraisingSettingsController` (#8441) : résolution de
l'entreprise par `PlatformCompanyLookup::findOrFail()`, puis exécution **dans son contexte** via
`TenantManager::withinTenant()`. C'est ce qui rend l'isolation structurelle et non déclarative.

### 1.4 Diagramme de flux technique

```
Super Admin (API)          Marketing OS                    Tenant ciblé
──────────────────         ────────────                    ────────────
auth:super_admin_api
platform.permission:  ──►  PlatformMarketingOsController
  marketing_os.view/manage   │
                             ├─► PlatformCompanyLookup::findOrFail(company)
                             │
                             └─► TenantManager::withinTenant(target)
                                    │  (installe le search_path du tenant)
                                    ├─► Dashboard     ─┐
                                    ├─► Campaign      ─┤
                                    ├─► ContentIdea   ─┤ BelongsToCompany :
                                    ├─► MarketingAsset┤ isolation framework
                                    └─► Publication   ─┘
                                    │
                                    └─► MarketingAssetStorage ──► disk `local` (privé, hors webroot)
```

---

## 2. TRD — Exigences techniques et fonctionnelles

### 2.1 Exigences fonctionnelles → implémentation

| # | Exigence (brief) | Implémentation | Vérifié par |
| --- | --- | --- | --- |
| F1 | Idée → tâche de production **sans perte** | `PromoteContentIdeaToProduction` → `marketing_production_tasks` | `Marketing OS : une idée se transforme en tâche de production` |
| F2 | Ressource reliée à **plusieurs** publications | `marketing_publications.marketing_asset_id` + `GET /assets/{asset}/publications` | `une ressource peut alimenter plusieurs publications` |
| F3 | Publications suivies par plateforme **et** statut | 1 ligne par plateforme, 8 statuts distincts, `status_counts` | `les publications sont suivies par plateforme et par statut` |
| F4 | **Direct vs assisté** distingué | `MarketingPublicationMode` + `MarketingPublicationModeResolver` | `Publication directe et assistée sont distinguées` |
| F5 | Liens et dates **réels** conservés | `published_url`, `published_at`, `attempt_history` | `confirm_published exige l'URL réelle` |
| F6 | Médiathèque : stocker **et** retrouver | `marketing_assets` versionné + filtres nom/description/tags/campagne/langue/type/dates | `la recherche…` (filtres de la médiathèque) |
| F7 | Grosses vidéos **hors** base | `disk` + `path` uniquement ; plafond 200 Mo/vidéo | assertion `has_file` + absence de colonne binaire |
| F8 | Permissions et secrets sécurisés | `platform.permission:marketing_os.view/manage` ; secrets Ayrshare restent côté serveur | `un rôle sans marketing_os.view est refusé` (403) |

### 2.2 Exigences techniques

- **Base** : PostgreSQL, tables dans le schéma tenant (`shared_tenants`), `company_id` sur chaque
  table, JSONB pour `tags`, `platforms`, `brief`, `stats`, `attempt_history`.
- **Idempotence des migrations** : chaque `Schema::create` est gardé par `schemaTableExists()`
  (convention du dépôt) ; `down()` ne détruit que les tables créées par le même fichier (#8207).
- **Nommage gouverné** : `2026_10_15_000100_8528_create_marketing_os_tables.php` — le numéro
  d'issue est exigé par la garde de collisions de basename (#5431).
- **API** : Laravel, réponses JSON paginées `{data, meta}`, validation par FormRequest.
- **Statut** : la validation de transition est **structurelle** — `confirm_published` exige
  `published_url`, `failed` exige `error_message`, `scheduled` exige `scheduled_at`.

### 2.3 Sécurité et permissions

| Rôle plateforme | `marketing_os.view` (lecture) | `marketing_os.manage` (écriture) |
| --- | --- | --- |
| `marketing` | ✅ | ❌ |
| `admin` / `super_admin` | ✅ | ✅ |

La lecture est ouverte au rôle `marketing` (il administre déjà vitrine et CRM) ; l'**écriture** —
donc la confirmation d'une mise en ligne, qui engage publiquement la marque — reste à
`admin`/`super_admin`. Toute opération sensible est refusée en `403` par le middleware avant
d'atteindre le contrôleur.

**Secrets** : aucun mot de passe de réseau social n'est stocké par le Marketing OS. Le mode direct
s'appuie sur les `social_accounts` existants (agrégateur Ayrshare, secrets côté serveur) ; le mode
assisté n'a besoin d'aucun secret.

---

## 3. AppFlow — Flux utilisateurs et workflows

### 3.1 Chaîne complète demandée par le brief

```
Idée ──► Stratégie ──► Brief ──► Production ──► Validation ──► Publication ──► Mesure ──► Réutilisation
 │         │            │           │               │              │              │            │
 │    campagne     fiche studio  tâche prod.   validation    directe/assistée  stats      ressource
 │    (B)          `brief` JSON  (B)           (C)           (E/F)            (A)        réutilisée (G)
 └─ ContentIdea.status : idea → briefing → in_production → in_review → approved → scheduled → published
```

### 3.2 Scénario 1 — « de l'idée au contenu produit »

1. L'utilisateur crée une **idée** (`POST /ideas`) : objectif, cible, produit, canal, format, langue,
   dates de production et de publication, responsable, budget.
2. Il la rattache à une **campagne** (`campaign_id`) ou la laisse en réserve.
3. `POST /ideas/{idea}/promote` → **`marketing_production_tasks`** créée (titre, brief, échéance,
   priorité) et statut passé à `in_production`. **Aucune saisie n'est perdue** : la tâche reprend
   objectif, format, langue, produit, verticale, public, message, description et date souhaitée.
4. L'opération est **idempotente** : un second appel ne crée pas de doublon et se contente de
   conserver le lien.

### 3.3 Scénario 2 — « retrouver et réutiliser une ancienne ressource »

1. Recherche dans la médiathèque : `search="publicité restaurant"`, filtres `kind=video`,
   `campaign_id`, `langue`, `product`, `platform`, `tag`, plage de dates.
2. La recherche couvre **nom, description et tags** (`tags::text ILIKE`), donc une vidéo ancienne
   reste trouvable par mots-clés métier.
3. `GET /assets/{asset}/publications` répond à : déjà publiée ? où ? quand ? quel compte ? quel
   lien ? quels résultats ? → et signale `reusable`.
4. **L'historique n'est jamais écrasé** : une nouvelle version crée une **nouvelle ligne** de
   ressource (`version` incrémentée) et les anciennes publications restent rattachées à l'ancienne.

### 3.4 Scénario 3 — publication **directe** (connecteur configuré)

1. Le tenant a un `social_account` **actif** → `mode = direct`, `direct_available = true`.
2. `POST /publications` prépare la publication ; l'agrégateur peut l'exécuter.
3. Le succès enregistre `published_at`, `published_url`, `attempt_history`.

### 3.5 Scénario 4 — publication **assistée** (aucun connecteur)

1. Aucun compte actif → `mode = assisted`, `mode_explanation` explique **pourquoi**.
2. Le front affiche clairement l'étape manuelle. Le statut ne bouge pas tout seul.
3. Après publication réelle sur la plateforme : `PATCH /publications/{id}/status`
   avec `action=confirm_published` — **`published_url` obligatoire**. Sans URL, `422`.
4. **Ouvrir la page d'un réseau social ne marque jamais rien comme publié** : aucun chemin de code
   n'écrit `published` ailleurs que dans `marketing_os.manage` + URL fournie.

### 3.6 Scénario 5 — échec de publication

`action=failed` avec `error_message` → statut `failed`, `attempts++`, entrée ajoutée à
`attempt_history`. L'échec remonte dans le tableau de bord (`next_actions`) et reste consultable.

---

## 4. UIX — Expérience utilisateur et navigation

### 4.1 Point d'entrée

Entrée **« Marketing OS »** dans la navigation plateforme (groupe *plateforme*), icône mégaphone,
visible avec la permission `marketing_os.view`, route `/marketing-os`.

### 4.2 Sélection de l'entreprise — explicite, jamais devinée

Les données marketing appartiennent à un tenant : le bandeau
`MarketingOsCompanyPicker` demande **sur quelle entreprise on travaille**. Tant qu'aucune
entreprise n'est choisie, aucun panneau ne charge. C'est la différence assumée avec la surface
tenant (où l'entreprise est implicite) : ici, une erreur de cible est une fuite potentielle.

### 4.3 Parcours et onglets

| Onglet | Contenu | Source de données |
| --- | --- | --- |
| **Tableau de bord** (A) | contenus à imaginer/produire/valider/publier, retards, planifiées, dernières publications + liens, campagnes actives, idées non exploitées, dernières ressources, performances, prochaines actions | `GET /overview` |
| **Stratégie & calendrier** (B) | vue campagne / liste / **calendrier** ; création d'idée ; promotion en production ; filtres (statut, format, verticale, retard) | `GET/POST /campaigns`, `GET/POST /ideas`, `POST /ideas/{id}/promote` |
| **Médiathèque** (C) | recherche + filtres puissants, upload avec type, version, statut de validation, publication associées | `GET/POST /assets`, `GET /assets/{id}/publications` |
| **Publications** (E/F/G) | publications par plateforme et statut, préparation, planification, confirmation de mise en ligne avec URL réelle | `GET/POST /publications`, `PATCH /publications/{id}/status` |

### 4.4 Règle d'honnêteté appliquée à l'interface

L'interface **ne peut pas** présenter comme automatisée une publication qui exige une action
manuelle : le mode est calculé par le serveur, renvoyé dans `mode`, et le libellé du mode assisté
est explicite. De même, quand aucune plateforme n'a remonté de statistiques, l'écran affiche
**« indisponible »** — jamais une estimation présentée comme un fait.

---

## 5. UI Design — Composants et règles visuelles

### 5.1 Composants livrés

| Composant | Rôle |
| --- | --- |
| `MarketingOsCompanyPicker.vue` | Sélection du tenant cible (`defineModel()`) |
| `MarketingOsDashboardPanel.vue` | Tableau de bord (A) |
| `MarketingOsStrategyPanel.vue` | Stratégie, idées, calendrier (B) |
| `MarketingOsStatusBadge.vue` | Badge de statut, couleur dérivée de l'énumération |
| `MarketingOsView.vue` | Orchestration des onglets et de l'état |

### 5.2 Règles visuelles

- **Design system existant uniquement** : Tailwind, échelle `gray`/`indigo`, `rounded-lg`,
  `shadow-sm`. Aucun token ad hoc — validé par la garde « Web Design Tokens ».
- **Statut = couleur + libellé** : jamais la couleur seule (accessibilité).
- **Tables denses** : en-têtes `text-xs uppercase tracking-wide text-gray-500`, lignes séparées.
- **Erreurs visibles** : un bandeau d'erreur explicite par action, jamais un échec silencieux.
- **i18n intégral** : tous les libellés passent par le catalogue (`fr`/`en`/`ar`/`tr`) ; les tables
  d'énumération du service portent des **clés** de traduction, pas du texte.

---

## 6. Backend Schema — Modèle de données, permissions et API

### 6.1 Modèle de données (5 tables, schéma tenant)

```
marketing_campaigns ──1..n──► content_ideas ──1..n──► marketing_assets
      │                             │                        │
      │                             └──1..n──► marketing_production_tasks
      │                             │
      └─────────────────────────────┴──1..n──► marketing_publications ◄── marketing_asset_id
```

| Table | Rôle | Points structurants |
| --- | --- | --- |
| `marketing_campaigns` | Campagne d'un tenant | `objective`, `sector` (verticale), `audience`, `product`, `budget_amount/currency`, `status`, `starts_at`/`ends_at` |
| `content_ideas` | Idée + fiche studio | `objective`, `format`, `channel`, `locale`, `audience`, `product`, `sector`, `key_message`, `production_due_at`, `publish_due_at`, `owner_id`, `budget_*`, `status`, `next_action`, **`brief` JSONB**, `production_task_id` |
| `marketing_assets` | Médiathèque | `kind`, `disk`+`path` (**jamais de binaire en base**), `size`, `mime_type`, `locale`, `platforms` JSONB, `tags` JSONB, **`version`**, `validation_status`, `rights`, `rights_expires_at` |
| `marketing_publications` | 1 ligne = 1 plateforme | `platform`, **`mode`** (direct/assisté), `status` (8 valeurs), `title`, `body`, `hashtags`, `call_to_action`, `destination_url`, `scheduled_at`, **`published_at`**, **`published_url`**, `platform_account`, `stats` JSONB, `stats_source`, `error_message`, `attempts`, `attempt_history` |
| `marketing_production_tasks` | Tâche de production | `due_date`, `priority`, `category`, `status`, `created_by` (nullable) |

Index posés : `company_id`, `campaign_id`, `content_idea_id`, `status`, `kind`, `platform`,
`name`, `production_task_id`.

### 6.2 Décision documentée — pourquoi `marketing_production_tasks` et non `tasks`

`tasks.created_by` est une **clé étrangère obligatoire** vers `employees` (migration
`2026_04_01_000104`). L'acteur qui promeut une idée depuis le Super Admin est un compte
**plateforme**, sans fiche employé. Créer une ligne Planning exigerait soit un employé fictif,
soit l'assouplissement d'une contrainte du module Planning — deux effets de bord inacceptables.

La tâche de production est donc décrite **dans le module Marketing**, et la chaîne
Idée → Production reste tracée par `content_ideas.production_task_id`. Le jour où un acteur employé
est disponible, un adaptateur pourra créer **en plus** une tâche Planning, sans migration de
données ni rupture d'historique. Cette décision a été **imposée par la vérification du schéma
réel**, après qu'un premier essai de pont inter-modules a été écarté (voir §7, leçon n°1).

### 6.3 API — 13 routes Super Admin

| Méthode | Chemin (préfixe `/api/v1/admin`) | Permission | Rôle |
| --- | --- | --- | --- |
| GET | `/companies/{company}/marketing-os/overview` | `view` | Tableau de bord (A) |
| GET | `/companies/{company}/marketing-os/campaigns` | `view` | Lister/filtrer les campagnes |
| POST | `/companies/{company}/marketing-os/campaigns` | `manage` | Créer une campagne |
| GET | `/companies/{company}/marketing-os/ideas` | `view` | Lister/filtrer les idées |
| POST | `/companies/{company}/marketing-os/ideas` | `manage` | Créer une idée / un brief |
| PATCH | `/companies/{company}/marketing-os/ideas/{idea}` | `manage` | Mettre à jour l'idée |
| POST | `/companies/{company}/marketing-os/ideas/{idea}/promote` | `manage` | **Idée → tâche de production** |
| GET | `/companies/{company}/marketing-os/assets` | `view` | Médiathèque + recherche/filtres |
| POST | `/companies/{company}/marketing-os/assets` | `manage` | Ajouter une ressource (+ fichier) |
| GET | `/companies/{company}/marketing-os/assets/{asset}/publications` | `view` | **Suivi d'usage d'une ressource** |
| GET | `/companies/{company}/marketing-os/publications` | `view` | Publications par plateforme/statut |
| POST | `/companies/{company}/marketing-os/publications` | `manage` | Préparer une publication |
| PATCH | `/companies/{company}/marketing-os/publications/{publication}/status` | `manage` | Transition de statut / **confirmation** |

Filtres de la médiathèque : `kind`, `campaign_id`, `content_idea_id`, `locale`, `product`, `sector`,
`objective`, `validation_status`, `platform`, `tag`, `search`, `from`, `to`, `per_page`.
Filtres de publication : `platform`, `status`, `mode`, `content_idea_id`, `marketing_asset_id`,
`from`, `to`. Filtres d'idée : `status`, `campaign_id`, `format`, `locale`, `sector`, `product`,
`search`, `from`, `to`, `late_only`.

---

## 7. Implementation Plan — Phases, dépendances, risques, tests

### 7.1 Phases

| Phase | Périmètre | État |
| --- | --- | --- |
| **P1 — Audit** | Diagnostic classé des 4 buckets, risques | ✅ livré (`MARKETING_OS_AUDIT.md`) |
| **P2 — Socle stratégie** | Table `marketing_campaigns`, `content_ideas`, idée → production | ✅ livré |
| **P3 — Médiathèque** | `marketing_assets`, stockage hors base, versionnement, recherche | ✅ livré |
| **P4 — Publications** | `marketing_publications`, direct/assisté, confirmation avec URL | ✅ livré |
| **P5 — Tableau de bord** | Agrégats réels, retards, performances déclarées | ✅ livré |
| **P6 — Front Super Admin** | Entrée de navigation, 4 panneaux, i18n 4 langues | ✅ livré |
| **P7 — Import statistiques plateformes** | Remontée réelle des KPIs (à connecter) | ⏳ lot suivant |
| **P8 — SEO/SEA/AEO/GEO** | Réveil du module `Geo` + liaison contenu↔objectif de visibilité | ⏳ lot suivant |
| **P9 — Publication directe multi-plateformes** | Étendre les publishers aux plateformes non couvertes | ⏳ lot suivant |
| **P10 — Génération assistée par IA** | Briefs/scripts/prompts via `MarketingAiService` | ⏳ lot suivant |

### 7.2 Dépendances et intégrations externes

| Plateforme | Publication directe disponible ? | Prérequis |
| --- | --- | --- |
| LinkedIn, Meta (Facebook/Instagram), X | **Oui, via l'agrégateur existant** | `social_accounts` **actif** + clé Ayrshare côté serveur |
| YouTube, TikTok | **Non configuré** | Connecteur dédié non présent → **mode assisté** obligatoire |
| Autres canaux | **Non** | Mode assisté |

Tant qu'un compte n'est pas configuré et actif, l'API **impose** le mode assisté et l'expose ;
elle ne promet jamais une automatisation indisponible.

### 7.3 Risques et mesures

| Risque | Mesure |
| --- | --- |
| Fuite inter-tenant | Routes plateforme → `withinTenant()` + `BelongsToCompany` ; garde `assert*BelongsToTenant` sur les rattachements ; test d'isolation |
| Binaire volumineux en base | `disk`+`path` uniquement, plafonds par type (200 Mo max vidéo) |
| Marquage abusif en « publié » | `published_url` obligatoire pour `confirm_published` ; test dédié |
| Perte d'historique | Ressources **versionnées** ; publications en ajout seul ; `attempt_history` jamais écrasé |
| Duplication de service existant | Audit préalable ; réutilisation de `social_*`, patron de stockage Showcase |
| Échec silencieux | `error_message` + `attempt_history` + remontée dans `next_actions` |
| Statistiques trompeuses | `stats_source` explicite ; « indisponible » plutôt qu'estimation |

### 7.4 Tests — 12 cas, 91 assertions, tous verts

`api/tests/Feature/Marketing/MarketingOsPlatformTest.php`

1. Tableau de bord : agrégats réels issus de la base
2. Idée → tâche de production (sans perte de données)
3. Promotion **idempotente** (aucun doublon de tâche)
4. Ressource reliée à **plusieurs** publications
5. Ressource versionnée : deux versions coexistent, historique préservé
6. Publications suivies par plateforme et par statut
7. **Direct et assisté distingués** (mode calculé serveur)
8. `confirm_published` **exige** l'URL réelle (`422` sinon)
9. Statistiques **déclarées indisponibles** faute de remontée plateforme
10. **Isolation tenant** : une ressource d'un tenant n'est pas visible depuis un autre
11. **Permission** : un rôle sans `marketing_os.view` est refusé (`403`)
12. Échec de publication : statut `failed`, `attempt_history` conservé

### 7.5 Gardes du dépôt exécutées

| Garde | Résultat |
| --- | --- |
| `check-module-isolation.sh` | ✅ 0 nouveau couplage croisé |
| `check-migration-basename-collision.sh` (#5431) | ✅ |
| `check-migration-duplication.sh` (une table → une migration) | ✅ |
| `check-bounded-context-dependencies.sh` (MAT-002) | ✅ arête BC-12→BC-02 déclarée |
| `check-i18n-diff.js` (PA2-I18N-014) | ✅ aucune chaîne en dur |
| `check-i18n-catalog-parity.sh` + `validate.js` | ✅ 4 locales |
| `check-openapi-route-coverage.py` (#1473) | ✅ 0 nouvelle dérive (allowlist justifiée) |
| `check-mvp-schema-parity.sh` | ✅ avertissements non bloquants seulement |
| Pint (style PHP) | ✅ |
| ESLint (front) | ✅ |

### 7.6 Leçons d'implémentation

1. **Le schéma réel tranche l'architecture** : le pont « contrat partagé » vers Planning a été
   écrit puis **retiré** parce que `tasks.created_by` est obligatoire. Vérifier les contraintes
   avant de dessiner une intégration.
2. **Le `search_path` est l'isolation** : interroger les tables tenant hors contexte produit des
   « relation does not exist » trompeurs. Toujours passer par `withinTenant()`.
3. **Un `down()` ne détruit que ce qu'il a créé** (#8207).
4. **Toute nouvelle route doit être documentée ou déclarée** (#1473) — l'allowlist exige une
   justification écrite, pas un silence.

---

## Annexe A — Matrice des plateformes et capacités API

| Plateforme | Publication directe | Prérequis / quota | Repli |
| --- | --- | --- | --- |
| LinkedIn | ✅ via agrégateur | compte actif ; quotas de l'API partenaire | assisté |
| Meta (Facebook, Instagram) | ✅ via agrégateur | compte actif ; règles de contenu Meta | assisté |
| X | ✅ via agrégateur | compte actif ; quotas du plan | assisté |
| YouTube | ❌ | connecteur absent ; quota Google (uploads limités) | **assisté** |
| TikTok | ❌ | connecteur absent ; API Content Posting à valider | **assisté** |
| Autres | ❌ | — | **assisté** |

Une même ressource peut alimenter plusieurs de ces plateformes : chaque destination est une
**ligne distincte** de `marketing_publications`, avec son propre statut, son propre texte et son
propre lien réel.

## Annexe B — Plan de mesure de l'efficacité marketing

| Étape | Indicateur | Source | Statut |
| --- | --- | --- | --- |
| Idée | idées créées, non exploitées | `content_ideas` | ✅ mesuré |
| Production | délai idée → production, retards | `marketing_production_tasks`, `production_due_at` | ✅ mesuré |
| Validation | ressources en attente / refusées | `marketing_assets.validation_status` | ✅ mesuré |
| Publication | publiées, planifiées, en échec, retards | `marketing_publications` | ✅ mesuré |
| Diffusion | impressions, clics, vues, engagement | API des plateformes | ⏳ à connecter (P7) |
| Conversion | trafic et conversions attribuées | analytics du site | ⏳ à connecter (P8) |
| Réutilisation | nombre de publications par ressource | `marketing_publications` | ✅ mesuré |

**Règle d'affichage** : tant qu'une plateforme ne remonte pas ses statistiques,
`publicationPerformance.stats_source = "unavailable"` et l'interface affiche « indisponible ».
**Aucune estimation n'est présentée comme une mesure**, et aucune visibilité n'est promise dans les
moteurs de recherche ou les assistants IA.
