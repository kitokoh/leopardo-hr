# Marketing OS — Conception (TAD · TRD · AppFlow · UIX · UI Design · Backend Schema · Implementation Plan)

> **Chantier** : issue [#8528](https://github.com/kitokoh/leopardo-hr/issues/8528) · branche `mkt/marketing-os-8528`
> **Audit préalable** : `docs/specifications/MARKETING_OS_AUDIT.md` (obligatoire, réalisé avant toute ligne de code)
> **Principe directeur** : **étendre** l'existant (`Modules/Marketing`, `Modules/Showcase`) sans rien dupliquer.

---

## 1. TAD — Architecture technique cible

### 1.1 Où vit le code

```
api/app/
├── Shared/Contracts/Marketing/
│   └── ContentProductionTaskSpawner.php        ← contrat partagé (isolation #5584)
└── Modules/
    ├── Marketing/                              ← TOUT le nouveau domaine
    │   ├── Domain/Enums/                       ← 7 énumérations (vocabulaire fermé)
    │   ├── Domain/Models/                      ← 4 modèles tenant
    │   ├── Application/Actions/                ← PromoteContentIdeaToProduction
    │   ├── Infrastructure/Services/            ← stockage, mode direct/assisté, agrégats
    │   ├── Interfaces/Api/V1/Requests|Controllers/
    │   └── Providers/MarketingServiceProvider.php
    └── Planning/
        └── Infrastructure/Services/PlanningContentProductionTaskSpawner.php   ← implémentation
```

### 1.2 Décisions structurantes (et pourquoi)

| # | Décision | Justification |
|---|---|---|
| 1 | **Aucun module `MarketingOS` séparé** | Le domaine est déjà `Modules/Marketing`. Un module neuf créerait un import croisé interdit (#5584) et dupliquerait les policies, le cron et les publishers. |
| 2 | **Contrat partagé pour créer une tâche** | `Modules/Marketing` ne peut pas importer `Modules/Planning`. Le canal prévu est `app/Shared/Contracts/<Domaine>/` — même patron que `PublishedProductsProvider` (Catalog → Showcase) et `EmailContactDirectory` (CRM → Communication). |
| 3 | **Deux surfaces d'authentification distinctes** | La surface tenant existante (`api.manager:marketing,principal` + Sanctum) **reste inchangée**. La surface Super Admin (`auth:super_admin_api` + `platform.permission:*`) est nouvelle. Un espace « Super Admin » ne peut pas vivre sous une garde de rôle manager d'entreprise. |
| 4 | **Logique exécutée dans le contexte du tenant ciblé** | `TenantManager::withinTenant()` bascule le `search_path` PostgreSQL : le scope `BelongsToCompany` s'applique donc réellement, et l'isolation est garantie par le framework plutôt que par un `where('company_id', …)` recopié. Patron repris de #8441. |
| 5 | **Binaires hors base de données** | `disk` + `path` uniquement. Les master vidéo (plafond 200 Mo) resteraient sinon en `jsonb`/`bytea`, ce que le brief interdit explicitement. |
| 6 | **Migrations idempotentes + `down()` non destructeur** | Gardes #7452 (une table, une migration), #8207 (le `down()` ne détruit que ce que le `up()` du même fichier a créé) et #5431 (numéro d'issue dans le nom). |
| 7 | **Pas de clé étrangère entre tables tenant** | Convention des migrations tenant (`§2.6`) : les références internes sont des identifiants stables. La cohérence est vérifiée applicativement (`assertCampaignBelongsToTenant`, etc.). |

### 1.3 Flux

```
Super Admin (admin-dashboard)
   │  Sanctum + auth:super_admin_api
   ▼
routes/api.php  /api/v1/admin/companies/{company}/marketing-os/*
   │  middleware platform.permission:marketing_os.view | marketing_os.manage
   ▼
PlatformMarketingOsController
   │  1. PlatformCompanyLookup::findOrFail($company)   (fail-closed : 404 si inconnue)
   │  2. TenantManager::withinTenant($target, fn () => …)   (search_path = tenant)
   ▼
Domain (MarketingCampaign · ContentIdea · MarketingAsset · MarketingPublication)
   ├── MarketingAssetStorage        → disk privé `local`, hors webroot
   ├── MarketingPublicationModeResolver → direct si social_accounts actif, sinon assisté
   ├── MarketingOsDashboardService  → agrégats réels
   └── ContentProductionTaskSpawner → implémenté par Modules/Planning
```

---

## 2. TRD — Exigences techniques et fonctionnelles

### 2.1 Exigences fonctionnelles (traçabilité brief → code)

| Brief | Exigence | Implémentation |
|---|---|---|
| A | Tableau de bord : à imaginer / produire / valider / publier, retards, dernières publications + liens, campagnes actives, idées non exploitées, ressources récentes, performances, prochaines actions | `GET …/marketing-os/overview` → `MarketingOsDashboardService::overview()` |
| B | Stratégie : objectif, cible, secteur, produit, message, canal, format, langue, dates production/publication, responsable, budget, statut, prochaines actions ; vues calendrier / tableau / campagne ; idée → tâche de production | `marketing_campaigns` + `content_ideas` ; `POST …/ideas/{idea}/promote` |
| C | Médiathèque : classer/rechercher/retrouver ; métadonnées complètes ; filtres multi-axes ; miniatures ; fichiers volumineux hors base | `marketing_assets` + filtres `indexAssets` + `MarketingAssetStorage` |
| D | Studio : fiche de production (concept, script, storyboard, scènes, narration, montage, prompts vidéo/image, ressources, format/durée, versions linguistiques) ; versions successives | `content_ideas.brief` (JSON) + `marketing_assets.version` (unicité nom+version) |
| E | Publications par plateforme : statuts distincts, média, texte, titre, description, hashtags, CTA, lien, dates, compte, plateforme, stats, erreurs, historique des tentatives | `marketing_publications` + `updatePublicationStatus` |
| F | Publication directe **ou** assistée, sans jamais prétendre à une automatisation inexistante ; pas de mot de passe en clair | `MarketingPublicationModeResolver` (mode **calculé**) ; secrets jamais stockés (référence chiffrée existante) |
| G | Suivi : déjà publiée ? où ? quand ? avec quelle description ? quel compte ? quel lien ? quels résultats ? réutilisable ? | `GET …/assets/{asset}/publications` |
| H | Lien avec le centre de visibilité SEO/SEA/AEO/GEO ; indicateurs mesurés ou explicitement estimés | `objective`/`sector`/`product` + `stats_source` (`platform` \| `manual` \| `unavailable`) — voir §2.4 |
| I | Campagnes regroupant idées, contenus, fichiers, publications, résultats ; filtres par verticale | `campaign_id` sur les 3 tables + filtres `sector` |
| J | Permissions Super Admin existantes, secrets côté serveur, journalisation, suppressions gérées | `marketing_os.view` / `marketing_os.manage` (matrice `PlatformRole`) ; `Auditable` disponible |

### 2.2 Exigences non fonctionnelles

- **Isolation tenant** : toute requête s'exécute sous `search_path` du tenant ; `company_id` NON nullable + indexé sur les 4 tables.
- **Traçabilité** : contrôles de cohérence inter-entités applicatifs à chaque écriture (422 explicite, jamais d'écriture silencieuse dans le mauvais tenant).
- **Sécurité des secrets** : aucun identifiant de réseau social n'est enregistré par ce lot ; le socle existant ne conserve qu'une **référence de profil chiffrée**.
- **Performance** : index sur `(company_id, status)`, `(company_id, platform, status)`, `(company_id, marketing_asset_id)`, `(company_id, sector)` ; pagination plafonnée à 100.
- **Idempotence** : `promoteIdea` ne crée pas une seconde tâche si `production_task_id` existe déjà.

### 2.3 Contrat d'API (surface Super Admin)

| Méthode | Chemin | Permission |
|---|---|---|
| GET | `/admin/companies/{company}/marketing-os/overview` | `marketing_os.view` |
| GET/POST | `…/campaigns` | `view` / `manage` |
| GET/POST | `…/ideas` | `view` / `manage` |
| PATCH | `…/ideas/{idea}` | `manage` |
| POST | `…/ideas/{idea}/promote` | `manage` |
| GET/POST | `…/assets` | `view` / `manage` |
| GET | `…/assets/{asset}/publications` | `view` |
| GET/POST | `…/publications` | `view` / `manage` |
| PATCH | `…/publications/{publication}/status` | `manage` |

### 2.4 Règle de véracité (le point le plus sensible du brief)

Trois garde-fous **structurels** — pas seulement documentaires :

1. **`published` exige une URL réelle.** `UpdateMarketingPublicationStatusRequest` déclare `published_url` en `required_if:action,confirm_published`. Il est donc impossible de marquer un contenu publié sans preuve.
2. **Le mode est calculé côté serveur.** Le client peut *demander* `direct` ; le serveur ne l'accorde que si un compte social est réellement actif, sinon il retombe sur `assisted`. `direct_available` est renvoyé pour l'expliquer à l'écran.
3. **Les statistiques non mesurées sont déclarées indisponibles.** `stats_source = 'unavailable'` et un message explicite ; aucune estimation n'est présentée comme un fait. Aucune promesse de visibilité garantie en SEO/AEO/GEO.

---

## 3. AppFlow — Flux utilisateurs

### 3.1 Chaîne principale

```
① IDÉE            POST …/ideas            statut `idea`
                   └ brief facultatif (concept, cible, message)
② STRATÉGIE       rattachement campagne    objectif, secteur, produit, budget, dates
③ BRIEF           PATCH …/ideas/{idea}     `brief` JSON (script, storyboard, prompts…)
④ PRODUCTION      POST …/ideas/{idea}/promote
                   └ crée une tâche Planning (contrat partagé) + statut `in_production`
                   └ dépôt des livrables : POST …/assets (version n+1)
⑤ VALIDATION      validation_status : draft → pending → approved | rejected
⑥ PUBLICATION     POST …/publications      une ligne PAR plateforme
                   └ mode = direct (connecteur actif) ou assisted (manuel)
⑦ MISE EN LIGNE   PATCH …/publications/{id}/status
                   └ action `confirm_published` + URL réelle → statut `published`
⑧ MESURE          stats / stats_source (platform | manual | unavailable)
⑨ RÉUTILISATION   GET …/assets/{asset}/publications → historique complet conservé
```

### 3.2 Scénario « retrouver une ancienne vidéo »

```
Recherche « publicité restaurant »
  → GET …/assets?search=publicité+restaurant&kind=video&sector=restaurant
  → chaque résultat porte version, campagne, statut de validation, tags
  → GET …/assets/{asset}/publications
      réponse : publications[], published_count, platforms[], reusable=true
  → l'utilisateur sait si la vidéo a déjà servi, où, quand, avec quel lien
     et peut la réutiliser sans écraser l'historique (version n+1)
```

### 3.3 Scénario d'échec (publication)

```
PATCH …/publications/{id}/status   action=failed   error_message requis
  → statut `failed`, attempts +1, attempt_history enrichi
  → l'échec n'est pas muet : message exposé + compté dans `next_actions`
  → correction puis nouvelle tentative : l'historique des tentatives est CONSERVÉ
```

---

## 4. UIX — Expérience et navigation

### 4.1 Emplacement

Le Marketing OS est un **espace de la console Super Admin** (`front/admin-dashboard`), rattaché à la fiche de l'entreprise consultée : les données marketing sont celles d'un tenant, pas de la plateforme.

- Entrée de navigation : groupe **Marketing**, libellé « Marketing OS », icône cohérente avec le groupe.
- La navigation est déclarée dans `src/navigation/navigation.js` (**source de vérité unique** : permission + groupe + sous-menu `children`), le routeur dans `src/router/index.js`.
- Chaque entrée porte sa **permission** (`marketing_os.view`), donc un rôle sans la permission ne voit rien plutôt que de tomber sur une erreur.

### 4.2 Écrans

| Écran | Rôle | Contenu |
|---|---|---|
| **Tableau de bord** | Où en est-on ? | Pipeline (à imaginer / produire / valider / publier), contenus en retard, publications planifiées, dernières publications **avec leur lien**, campagnes actives, idées non exploitées, ressources récentes, performances + **provenance de la donnée**, prochaines actions |
| **Stratégie & calendrier** | Quoi, quand, pour qui ? | Vues **calendrier**, **tableau** et **par campagne** ; filtres secteur / objectif / format / langue ; passage idée → production |
| **Médiathèque** | Où est ce fichier ? | Recherche plein texte + filtres (type, campagne, langue, produit, secteur, statut de validation, plateforme, tag, période) ; action « où cette ressource a-t-elle été publiée ? » |
| **Publications** | Qu'est-ce qui sort, où ? | Liste par plateforme et par statut ; mode direct/assisté affiché **explicitement** ; confirmation avec URL réelle |

### 4.3 Trois règles d'interaction non négociables

1. **Jamais de faux « publié ».** Le bouton de confirmation demande l'URL et rappelle que l'ouverture d'une page ne publie rien.
2. **Jamais de fausse précision.** Sans statistiques de plateforme, l'écran affiche « non disponible », pas « 0 ».
3. **Jamais de perte silencieuse.** Toute erreur d'écriture affiche le message serveur et n'efface pas la saisie.

---

## 5. UI Design — Composants et règles visuelles

- **Alignement strict sur le design system existant** de `admin-dashboard` : cartes de statistiques, tableaux paginés, filtres en ligne, dialogues de confirmation, badges de statut.
- **Badges de statut** : couleur + libellé (jamais la couleur seule) — le vocabulaire vient des énumérations serveur (`ContentIdeaStatus::label()`), donc l'écran et la base ne peuvent pas diverger.
- **Mode de publication** : badge distinct `Direct (API)` / `Assisté (manuel)`, affiché sur chaque ligne **et** dans le détail, pour qu'aucune capture d'écran ne puisse laisser croire à une automatisation inexistante.
- **Provenance de la donnée** : toute métrique de performance affiche sa source (`platform`, `manual`, `unavailable`).
- **États vides explicites** : « Aucune campagne », « Aucune ressource », jamais un écran blanc ambigu.
- **Aucune image d'illustration factice** : les aperçus média ne s'affichent que si un binaire existe réellement (`has_file`).

---

## 6. Backend Schema — Modèle de données, permissions, API

### 6.1 Tables (toutes avec `company_id` uuid NON nullable + index)

**`marketing_campaigns`** — campagne / verticale
`id`, `company_id`, `name`, `description`, `objective`, `audience`, `sector`, `product`, `key_message`, `channel`, `locale`, `starts_at`, `ends_at`, `owner_id`, `budget_amount`, `budget_currency`, `status`, timestamps.

**`content_ideas`** — idée / brief de production
`id`, `company_id`, `campaign_id`, `title`, `description`, `objective`, `format`, `channel`, `locale`, `audience`, `product`, `sector`, `key_message`, `production_due_at`, `publish_due_at`, `owner_id`, `budget_amount`, `budget_currency`, `status`, `next_action`, `brief` (json), `production_task_id`, timestamps.

**`marketing_assets`** — médiathèque (versionnée)
`id`, `company_id`, `campaign_id`, `content_idea_id`, `name`, `description`, `kind`, `mime_type`, `extension`, `size`, `disk`, `path`, `preview_path`, `thumbnail_path`, `locale`, `product`, `objective`, `sector`, `platforms` (json), `tags` (json), `version`, `validation_status`, `author_id`, `rights`, `rights_expires_at`, timestamps.
**Contrainte** : `unique(company_id, name, version)` — publier une version n'écrase jamais la précédente.

**`marketing_publications`** — une ligne par plateforme, append-only
`id`, `company_id`, `content_idea_id`, `marketing_asset_id`, `social_post_id`, `platform`, `mode`, `status`, `title`, `body`, `hashtags`, `call_to_action`, `destination_url`, `scheduled_at`, `published_at`, `published_url`, `platform_account`, `stats` (json), `stats_source`, `error_message`, `attempts`, `attempt_history` (json), `created_by`, timestamps.

### 6.2 Vocabulaire fermé (énumérations)

`MarketingObjective` · `MarketingContentFormat` · `MarketingCampaignStatus` · `ContentIdeaStatus` · `MarketingAssetKind` · `MarketingAssetValidationStatus` · `MarketingPublicationStatus` · `MarketingPublicationMode`.

Chaque énumération expose `values()` (validation), `label()` (UI) et, quand c'est utile, une règle métier (`isOpen()`, `requiresFile()`, `allowedExtensions()`).

### 6.3 Permissions

| Permission | Rôles | Portée |
|---|---|---|
| `marketing_os.view` | `marketing`, `admin`, `super_admin` | lecture stratégie / contenus / médiathèque / publications |
| `marketing_os.manage` | `admin`, `super_admin` | écriture **et** confirmation de mise en ligne (action qui engage publiquement la marque) |

### 6.4 Rôles demandés au brief §J

Le brief demande des rôles distincts stratégie / création / validation / publication. Le registre plateforme existant décrit des **familles d'écrans**, pas des boutons : la séparation retenue est **lecture (`marketing_os.view`) vs écriture (`marketing_os.manage`)**, plus la distinction structurelle `mode` direct/assisté. La séparation en quatre rôles fins est documentée en feuille de route (§8.2) : elle exige d'étendre `PlatformRole`, décision d'architecture qui dépasse cette tranche et doit être arbitrée avec la matrice existante (#7553).

---

## 7. Implementation Plan — Phases, dépendances, risques, tests

### 7.1 Phases

| Phase | Contenu | État |
|---|---|---|
| 0 | Audit classé + issue + branche de mission | ✅ |
| 1 | Migration + modèles + énumérations | ✅ |
| 2 | Permissions, routes, contrôleur Super Admin, services | ✅ |
| 3 | Espace front (tableau de bord, stratégie/calendrier, médiathèque) | ✅ |
| 4 | Tests Feature + gardes du dépôt | ✅ |
| 5 | PR, fusion, vérification de `main` | ✅ |

### 7.2 Dépendances

- **Externes** : clé API de l'agrégateur de publication (sinon mode assisté uniquement) ; API statistiques de chaque plateforme (quotas, autorisations).
- **Internes** : `Modules/Planning` (tâche de production), registre `PlatformPermission`, socle Sanctum Super Admin.
- **Absentes du projet** : GD/Imagick → **génération de miniatures non incluse** (nouvelle dépendance = décision de budget).

### 7.3 Risques et parades

| Risque | Parade |
|---|---|
| Import croisé inter-modules → CI rouge | Contrat partagé `app/Shared/Contracts/Marketing/` ; vérifié par `check-module-isolation.sh` (0 nouvel import croisé) |
| Collision de migration | Nom avec numéro d'issue (#5431) ; `check-migration-basename-collisions.sh` vert |
| Table re-déclarée | `check-duplicate-schema-create.py --strict` vert |
| `down()` destructeur | Ne droppe que les 4 tables du même fichier |
| Fuite cross-tenant | `withinTenant` + `BelongsToCompany` + contrôle de cohérence inter-entités + test d'isolation |
| Publication annoncée à tort | `published_url` obligatoire ; mode calculé serveur ; test dédié |
| Binaire en base | `disk` + `path` uniquement ; plafonds par type (vidéo 200 Mo) |

### 7.4 Stratégie de tests

**Feature** (`api/tests/Feature/`) :
1. isolation tenant — le tenant A ne voit ni ne modifie les données du tenant B (404/422 explicites) ;
2. cycle complet idée → promotion en production → ressource → publication → confirmation avec URL → historique ;
3. garde de véracité — `confirm_published` sans URL = 422 ; `mode` forcé à `assisted` sans connecteur ;
4. une ressource reliée à **plusieurs** publications, historique conservé ;
5. statuts et compteurs du tableau de bord issus de données réelles ;
6. permissions — `marketing_os.view` sans `manage` : lecture 200, écriture 403.

**Gardes du dépôt** : lint PHP (tous les fichiers), isolation modules, collisions de migrations, duplication de schéma, formatage Pint.

---

## 8. Annexes

### 8.1 Matrice des plateformes et capacités API

| Plateforme | Publication directe | Ce que Leopardo fait réellement | Prérequis |
|---|---|---|---|
| LinkedIn | Via l'agrégateur existant (`LinkedInPublisher`) | Publication automatique si un compte social **actif** existe | Compte rattaché au profil de l'agrégateur + clé API |
| Meta (Facebook / Instagram) | Via l'agrégateur (`MetaPublisher`) | idem | idem |
| X / Twitter | Via l'agrégateur (`TwitterPublisher`) | idem | idem |
| TikTok | **Assisté** | Préparation du texte/description, sélection et ouverture du média, copie de la description, ouverture de la page officielle, confirmation utilisateur, enregistrement de l'URL et de la date réelles | Aucune clé requise |
| YouTube | **Assisté** | idem (dépôt manuel de la vidéo) | Aucune clé requise ; API à étudier séparément (quota d'upload) |
| Autres canaux | **Assisté** | idem | — |

**Règles respectées** : conditions et quotas de chaque plateforme ; aucune publication déclenchée sans autorisation du compte ; **aucun mot de passe de réseau social stocké** ; aucune publication assistée présentée comme automatisée.

### 8.2 Plan de mesure de l'efficacité marketing

| Niveau | Indicateur | Source | Statut |
|---|---|---|---|
| Volume | Idées créées, contenus produits, publications sorties | base (réel) | ✅ mesuré |
| Processus | Délai idée → publication, taux de contenus en retard, taux d'échec de publication | base (réel) | ✅ mesuré |
| Qualité | Ressources validées / refusées, tentatives par publication | base (réel) | ✅ mesuré |
| Réutilisation | Publications par ressource, plateformes couvertes | base (réel) | ✅ mesuré |
| Portée | Impressions, clics, vues | API de la plateforme | ⚠️ **uniquement si fournie**, sinon `unavailable` |
| Conversion | Trafic et conversions attribués | analytics externe | ⚠️ non branché dans cette tranche |
| Visibilité SEO / SEA / AEO / GEO | Position, trafic organique, citations en moteurs de réponse | outil externe | ⚠️ **non implémenté** — cf. §9 |

**Aucun de ces indicateurs n'est extrapolé** : une valeur absente est affichée comme non disponible.

### 8.3 Ce que cette tranche ne fait pas (feuille de route assumée)

1. **Studio de production complet** — le brief structuré est stocké (`content_ideas.brief`) mais l'éditeur visuel dédié (storyboard, scènes) reste à construire.
2. **Assistant IA de brief** — `MarketingAiService` existe (posts, réponses, bilan) : l'étendre aux scripts/prompts est un lot isolé, avec la même règle « l'IA n'a rien généré si aucun outil ne l'a fait ».
3. **Connecteurs directs supplémentaires** (TikTok, YouTube) — dépendent d'autorisations et de quotas externes.
4. **Moteur de visibilité SEO / SEA / AEO / GEO** — **réellement absent du dépôt** : `Modules/Geo` ne traite que la géolocalisation (Haversine/PostGIS) et ne doit pas être confondu avec le « GEO » du brief. La vitrine Next.js a des briques SEO techniques (`robots.ts`, `JsonLd`, sitemap) mais **aucune donnée exploitable côté administration**. Construire ce moteur est un chantier à part entière, à cadrer avec le centre de visibilité existant.
5. **Génération de miniatures** — dépendance GD/Imagick absente.
6. **Quatre rôles fins** (stratégie / création / validation / publication) — cf. §6.4.
