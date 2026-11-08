# MODULE RECRUTEMENT — Portail public d'offres d'emploi

> **Statut** : Spécification de référence (lot BC-10 RECRUITMENT).
> **Bounded context** : BC-10 RECRUITMENT (`dev-hub/governance/bounded-context-registry.json`).
> **Surfaces** : `api/` (Laravel), `front/web/` (Next.js — portail public),
> `front/admin-dashboard/` (Vue — console plateforme).
> **Règle d'or AGENTS.md** : ce document précède la création des issues GitHub
> et l'implémentation. Il est la référence unique du lot.

---

## 1. Contexte et problème

Le module `api/app/Modules/Recruitment` (DDD complet) expose déjà un ATS :
offres (`job_postings`), candidats (`applicants`), entretiens (`interviews`),
et une surface publique `PublicCareerController` (liste, détail, flux XML,
candidature) résolue par `{companySlug}`.

Le rapport de maturité `docs/architecture/maturity/MATURITY-BC10-RECRUITMENT.md`
(issue #5886) relève trois manques structurels, et l'audit de production
constate que **le portail public n'est pas exploitable** :

| Constat | Impact |
|---|---|
| Aucune entreprise n'a d'offre publiée en production ; le portail `/{slug}/careers` renvoie 404 | Le portail n'existe pas pour un visiteur |
| La publication d'une offre n'est possible que par un manager du tenant (`principal`/`rh`) | La plateforme ne peut pas publier d'offres (vitrine, postes internes) |
| Aucun mécanisme d'import d'offres depuis des agrégateurs externes | Le portail ne peut pas s'enrichir de sources tierces |
| La surface publique n'est pas auditée (champs exposés, RGPD, anti-spam) | Risque juridique et d'abus |
| Le flux XML ne couvre pas les champs requis par Google Jobs | Offres non indexables |

## 2. Objectifs

1. **Publication côté tenant** — une entreprise cliente publie une offre vers le
   portail public, avec un cycle de vie explicite et une date de clôture.
2. **Publication côté plateforme** — les responsables Leopardo publient des
   offres depuis l'espace super admin, sans passer par un compte tenant.
3. **Intégrations externes** — depuis le super admin, connecter des API
   d'agrégateurs / job boards et faire apparaître leurs offres sur le portail.
4. **Portail public réellement fonctionnel** — liste, détail, candidature,
   flux XML Google Jobs/Indeed, branding par tenant.
5. **Conception propre** — architecture, modèle de données, API, sécurité,
   RGPD, rate limiting, gestion des erreurs.

## 3. Décisions d'architecture

### 3.1 Où vivent les offres importées ?

**Décision : les offres agrégées vivent dans le schéma `public`, pas dans un
schéma tenant.**

Justification : une offre importée depuis un agrégateur n'appartient à aucun
tenant — elle est publiée par la plateforme. La placer dans un schéma tenant
obligerait à choisir un tenant arbitraire (fuite de données entre clients) ou à
créer un tenant « plateforme » fictif (contournement de la constitution §II).
Le portail public agrège donc **deux sources** :

- `job_postings` (schéma tenant) — offres publiées par l'entreprise cliente ;
- `public.external_job_offers` (schéma public) — offres importées par la
  plateforme, rattachées à une `public.job_sources`.

La fusion se fait dans un service de lecture dédié, jamais par une jointure
cross-schéma implicite.

### 3.2 Cycle de vie d'une offre

```
draft ──publish──> published ──close──> closed ──archive──> archived
  ^                    |                    |
  └────── reopen ──────┘                    └── reopen ──> published
```

- `publish` : `draft` → `published`, pose `published_at`.
- `close` : `published` → `closed`, pose `closes_at` si absent.
- `archive` : `closed` → `archived` (sortie du portail, conservation légale).
- `reopen` : `closed`/`archived` → `published` (republication).
- Toute transition invalide → `422` avec clé de traduction
  (`JobPostingStateTransitionException`).

### 3.3 Connecteurs d'agrégateurs

Réutilisation du patron éprouvé `SearchIntelligence\IntegrationLifecycle`
(GSI-8, #8519) :

- liste **fermée** de fournisseurs (`PROVIDERS`) ;
- identifiants **chiffrés au repos** (cast `encrypted`), jamais sérialisés ;
- clés d'identifiants **validées** par fournisseur (clé inconnue → 422) ;
- `configure` / `test` / `revoke` ; `revoke` **efface** le secret ;
- toute mutation **auditée** (`AuditLog`, `company_id = null`), sans jamais
  journaliser de valeur (empreinte SHA-256 tronquée seulement) ;
- l'état reste **honnête** : un test local ne prétend jamais `connected`.

### 3.4 Synchronisation

Job `SyncExternalJobOffers` (queue, `ShouldQueue`) :

- **idempotent par construction** : clé métier `(source_id, external_id)`
  unique en base, écriture par `upsert` ;
- **ne crée jamais** une source ni un connecteur ;
- **ne marque jamais** `connected` tout seul : `error` + `last_error` lisible
  en cas d'échec, `last_synced_at` en cas de succès ;
- erreurs **rétentives** (backoff) vs **définitives** (arrêt, reconnexion) ;
- les offres expirées côté source sont **clôturées**, jamais supprimées
  (traçabilité).

### 3.5 Sécurité et RGPD de la surface publique

- **Rate limiting** : bucket `public-careers` (lecture) et bucket **strict**
  dédié à la candidature (`public-careers-apply`), par IP.
- **Champs exposés** : liste blanche stricte. Jamais `created_by`, `notes`,
  `rating`, `resume_path`, ni aucune donnée interne. Verrouillé par test.
- **RGPD** : consentement explicite obligatoire à la candidature
  (`consent` booléen + horodatage), finalité documentée, purge des
  candidatures au-delà de la durée de conservation configurable.
- **Anti-spam** : honeypot + délai minimal de remplissage côté formulaire,
  doublon `(job_posting_id, email)` → `409 ALREADY_APPLIED` (index unique
  existant, #3860).
- **Gestion d'erreurs** : contrat d'erreur uniforme
  (`error` + `message`), jamais de trace ni de détail SQL.

### 3.6 Flux XML Google Jobs / Indeed

Champs requis par la spécification Google Jobs : `title`, `date`,
`referencenumber`, `url`, `company`, `city`, `country`, `description`,
`jobtype`, `salary` (optionnel), `expirationDate`, `remote` (télétravail).
Le flux inclut les offres tenant **et** les offres agrégées publiées.

## 4. Modèle de données

### 4.1 Schéma `public` (nouveau)

**`public.job_sources`** — un connecteur d'agrégateur.

| Colonne | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `provider` | string(40) | liste fermée, unique |
| `label` | string(120) | libellé affiché |
| `status` | string(20) | `not_connected` / `connected` / `error` |
| `credentials_encrypted` | text nullable | chiffré, jamais sérialisé |
| `config` | jsonb nullable | filtres d'import (pays, catégories, limite) |
| `last_synced_at` | timestamptz nullable | |
| `last_error` | string(500) nullable | |
| `offers_imported` | unsigned int | compteur |
| `created_at` / `updated_at` | timestamptz | |

**`public.external_job_offers`** — une offre importée.

| Colonne | Type | Note |
|---|---|---|
| `id` | bigint PK | |
| `source_id` | bigint FK → `job_sources` | cascade |
| `external_id` | string(191) | identifiant chez la source |
| `title` | string(200) | |
| `description` | text nullable | |
| `company_name` | string(200) | |
| `location` | string(200) nullable | |
| `country` | string(2) nullable | ISO-3166 alpha-2 |
| `remote_policy` | string(20) | `onsite`/`hybrid`/`remote` |
| `contract_type` | string(20) | `cdi`/`cdd`/`stage`/`freelance` |
| `salary_min` / `salary_max` | decimal(12,2) nullable | |
| `currency` | string(3) nullable | |
| `apply_url` | string(500) | lien de candidature externe |
| `published_at` | timestamptz nullable | |
| `expires_at` | timestamptz nullable | |
| `status` | string(20) | `published` / `closed` |
| `raw_payload` | jsonb nullable | charge utile d'origine (traçabilité) |
| `created_at` / `updated_at` | timestamptz | |

Contraintes : `unique(source_id, external_id)`, index `(status, published_at)`.

### 4.2 Schéma tenant (extension)

**`job_postings`** — colonnes additives (migration `Schema::table`
idempotente, jamais un second `Schema::create` — garde #7452) :

| Colonne | Type | Note |
|---|---|---|
| `published_by_platform` | boolean default false | offre publiée par la plateforme |
| `platform_created_by` | unsigned bigint nullable | id super admin auteur |
| `archived_at` | timestamptz nullable | date d'archivage |

**`applicants`** — colonnes additives :

| Colonne | Type | Note |
|---|---|---|
| `consent_given_at` | timestamptz nullable | RGPD — horodatage du consentement |
| `retention_until` | timestamptz nullable | RGPD — fin de conservation |

## 5. API

### 5.1 Public (sans authentification, `throttle:public-careers`)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/api/v1/public/careers/{companySlug}` | Liste (filtres `location`, `contract_type`, `remote_policy`, `q`, pagination) |
| GET | `/api/v1/public/careers/{companySlug}/jobs/{id}` | Détail |
| GET | `/api/v1/public/careers/{companySlug}/feed.xml` | Flux Google Jobs / Indeed |
| POST | `/api/v1/public/careers/{companySlug}/jobs/{id}/apply` | Candidature (bucket strict) |

### 5.2 Tenant (Sanctum + RBAC manager `principal`/`rh`)

| Méthode | Route | Rôle |
|---|---|---|
| POST | `/api/v1/recruitment/jobs/{id}/publish` | Publier (existant, étendu) |
| POST | `/api/v1/recruitment/jobs/{id}/close` | Clôturer (existant) |
| POST | `/api/v1/recruitment/jobs/{id}/archive` | **Nouveau** — archiver |
| POST | `/api/v1/recruitment/jobs/{id}/reopen` | **Nouveau** — republier |

### 5.3 Plateforme (super admin, `auth:super_admin_api`)

| Méthode | Route | Permission |
|---|---|---|
| GET | `/api/v1/platform/recruitment/jobs` | `recruitment.view` |
| POST | `/api/v1/platform/recruitment/jobs` | `recruitment.manage` |
| POST | `/api/v1/platform/recruitment/jobs/{id}/publish` | `recruitment.manage` |
| POST | `/api/v1/platform/recruitment/jobs/{id}/close` | `recruitment.manage` |
| GET | `/api/v1/platform/recruitment/sources` | `recruitment.view` |
| PUT | `/api/v1/platform/recruitment/sources/{provider}` | `recruitment.manage` |
| POST | `/api/v1/platform/recruitment/sources/{provider}/test` | `recruitment.manage` |
| DELETE | `/api/v1/platform/recruitment/sources/{provider}` | `recruitment.manage` |
| POST | `/api/v1/platform/recruitment/sources/{provider}/sync` | `recruitment.manage` |
| GET | `/api/v1/platform/recruitment/offers` | `recruitment.view` |

Nouvelles permissions plateforme : `recruitment.view`, `recruitment.manage`
(portées par `admin`, `super_admin`, et `marketing` en lecture).

## 6. Critères d'acceptation

1. Un manager tenant publie une offre ; elle apparaît sur
   `/{slug}/careers` et dans le flux XML.
2. Un super admin publie une offre pour un tenant ciblé ; elle apparaît sur le
   portail de ce tenant, marquée `published_by_platform`.
3. Un super admin configure un connecteur d'agrégateur, le teste, le révoque ;
   aucun secret ne sort d'aucune réponse API.
4. Une synchronisation importe des offres externes, idempotente (deux
   exécutions = même état), et elles apparaissent sur le portail public.
5. Le portail public expose liste, détail, candidature, flux XML et branding
   par tenant, avec filtres et pagination.
6. La surface publique est verrouillée par test : champs exposés, rate
   limiting, consentement RGPD, doublon 409.
7. `main` est propre et verte après merge ; la branche de travail est supprimée.

## 7. Hors périmètre

- Scoring / matching IA des candidats.
- Scan antivirus des CV (recommandation séparée).
- Webhooks sortants vers LinkedIn / ATS externes.
- Application mobile dédiée au recrutement.

## 8. Risques et parades

| Risque | Parade |
|---|---|
| Fuite cross-tenant via la surface publique | Résolution par `{companySlug}` + `withinTenant` + test d'isolation |
| Secret d'agrégateur exposé | Cast `encrypted`, `$hidden`, projection publique booléenne, test |
| Doublons d'offres importées | Contrainte unique `(source_id, external_id)` + `upsert` |
| Spam de candidatures | Bucket strict + honeypot + index unique + 409 |
| Non-conformité RGPD | Consentement horodaté + purge + liste blanche de champs |
| Collision de migration | Garde `check-migration-basename-collisions.sh` avant push |
