# Spécification — Blog plateforme (BC-01 PLATFORM / vitrine)

- **Statut :** proposition — à valider par le propriétaire avant implémentation (règle d'or AGENTS.md)
- **Issue de dépôt :** #7773
- **Périmètre :** blog **plateforme** (contenu éditorial Leopardo, rédigé par les administrateurs plateforme, affiché sur la vitrine `front/web`). Le blog **côté tenant** est explicitement hors v1 (voir §13 et `SOLUTION_SITE_VITRINE.md` §11).
- **Références :** `front/web/src/modules/vitrine/data/blog.ts` (source actuelle en dur), `front/web/src/app/(landing)/blog/{page.tsx,[slug]/page.tsx}`, `front/web/src/app/sitemap.ts`, `front/web/src/app/llms.txt/route.ts`, routes cockpit `api/routes/api.php` (groupe `auth:super_admin_api` + prefix `admin`), `front/admin-dashboard/src/views/settings/EmailTemplatesView.vue` (pattern éditeur admin multilingue), `docs/specifications/SOLUTION_SITE_VITRINE.md` (BC-27 Showcase)

---

## 1. Vision

Aujourd'hui, le blog de la vitrine est **100 % statique** : ~10 articles markdown inline dans `blog.ts` (~1300 lignes), avec surcharges EN/TR/AR dans le même fichier et un flag `archived` posé à la main. Publier ou corriger un article exige un commit + un déploiement du front.

Objectif : les administrateurs de la plateforme **rédigent, éditent, traduisent, publient et archivent** des articles depuis le cockpit `front/admin-dashboard`, et la vitrine les affiche via l'API **sans redéploiement**, sans dégrader le SEO ni la disponibilité de la vitrine si l'API est indisponible.

## 2. Objectifs et non-objectifs (v1)

**Objectifs v1**

1. CRUD complet des articles depuis le cockpit super-admin (liste, éditeur markdown avec prévisualisation, upload d'image de couverture, workflow `draft → published → archived`).
2. Endpoints publics sans auth, cachés, consommés par la vitrine (liste + détail par slug, par locale).
3. Migration du contenu existant de `blog.ts` (FR + surcharges EN/TR/AR + flag `archived`) par un seed idempotent.
4. Pages vitrine `/blog` et `/blog/[slug]` alimentées par l'API avec ISR/revalidation, **fallback statique si API down**.
5. Sitemap, métadonnées SEO et `llms.txt` alignés sur le contenu dynamique.
6. Sécurité : sanitisation du markdown rendu (anti-XSS), permissions plateforme, validation d'upload.

**Non-objectifs v1**

- Blog des tenants (esquissé en §13, v2 via BC-27 Showcase).
- Éditeur WYSIWYG riche (v1 = markdown + preview), commentaires, réactions, recherche full-text, planification de publication (`scheduled_at`), versionning/historique des articles, workflow de relecture multi-rôles.
- Flux RSS/Atom (candidat v1.1, trivial une fois l'API publique en place).
- Auteurs multiples avec pages auteur dédiées (v1 : auteur = champ éditorial libre, voir §4).
- Suppression physique d'un article publié (on archive ; `DELETE` réservé aux brouillons).

## 3. Cas d'usage v1

| # | Rôle | Action | Résultat |
|---|---|---|---|
| US1 | Admin plateforme | Créer un brouillon (titre, slug auto, corps markdown) | Article `draft`, invisible du public |
| US2 | Admin plateforme | Éditer avec prévisualisation markdown, uploader une couverture | Sauvegarde + aperçu fidèle au rendu vitrine |
| US3 | Admin plateforme | Renseigner les traductions EN/TR/AR (onglets de langue) | Surcharges par locale, fallback FR si absentes |
| US4 | Admin plateforme | Publier / dépublier / archiver | Statut mis à jour, cache public invalidé, vitrine revalidée |
| US5 | Visiteur vitrine | Consulter `/blog` et `/blog/{slug}` dans sa langue | Contenu servi via ISR, SEO complet, badge « Archivé » conservé |
| US6 | Crawler / assistant IA | Lire sitemap et `llms.txt` | URLs blog publiées uniquement, `lastModified` fiables |

## 4. Modèle de données

### 4.1 Table centrale `public.platform_blog_posts`

Le contenu appartient à la **plateforme**, pas à un tenant : migration dans la base **centrale « public »** (pattern des tables plateforme existantes), **jamais** dans les schémas tenant.

```
platform_blog_posts
  id                bigint PK
  slug              varchar(160) UNIQUE NOT NULL   -- kebab-case, immuable après publication (v1)
  title             varchar(255) NOT NULL           -- FR = langue canonique
  excerpt           varchar(500) NOT NULL
  body_markdown     text NOT NULL                   -- markdown brut, jamais de HTML stocké
  cover_image_path  varchar(255) NULL               -- storage plateforme (disque public), servi via URL
  author_name       varchar(120) NOT NULL           -- champ éditorial libre (cf. blog.ts : noms arbitraires)
  author_avatar_path varchar(255) NULL
  category          varchar(80) NOT NULL
  tags              jsonb NOT NULL DEFAULT '[]'
  reading_time      smallint NOT NULL               -- minutes ; recalculé côté serveur à la sauvegarde
  status            varchar(16) NOT NULL DEFAULT 'draft'  -- draft | published | archived (CHECK)
  published_at      timestamptz NULL                -- posée à la 1re publication, éditable (contenu daté migré)
  seo               jsonb NOT NULL DEFAULT '{}'     -- { meta_title, meta_description, og_image_path } (fallback title/excerpt/cover)
  translations      jsonb NOT NULL DEFAULT '{}'     -- cf. §4.2
  created_by        bigint NULL FK → super_admins.id (SET NULL)
  updated_by        bigint NULL FK → super_admins.id (SET NULL)
  created_at / updated_at

Index : UNIQUE(slug) ; (status, published_at DESC) pour la liste publique.
```

Notes :

- `archived` du fichier statique ≙ `status = 'archived'` **mais reste visible publiquement** avec badge « Archivé » (comportement actuel : contenu daté conservé, trié en fin de liste). Seul `draft` est invisible du public. Point de contrat important : *archived ≠ dépublié*. La dépublication = retour à `draft`.
- L'auteur est un **snapshot éditorial** (`author_name`/`author_avatar_path`), pas une FK obligatoire vers `super_admins` : les articles migrés portent des auteurs fictifs sans compte associé ; `created_by`/`updated_by` assurent la traçabilité réelle.

### 4.2 Traductions : JSONB (décision tranchée)

**Choix : colonne `translations` JSONB**, structure miroir des surcharges actuelles de `blog.ts` :

```json
{
  "en": { "title": "...", "excerpt": "...", "body_markdown": "...", "category": "...", "tags": ["..."], "seo": { "meta_title": "...", "meta_description": "..." } },
  "tr": { "...": "..." },
  "ar": { "...": "..." }
}
```

Justification contre une table `platform_blog_post_translations` dédiée :

1. **Le pattern de lecture est unique** : on charge toujours l'article entier pour une locale donnée avec fallback FR — exactement `getBlogPosts()` actuel (`{ ...post, ...overrides[slug] }`). Aucune requête « tous les titres EN » n'existe.
2. **Locales fermées et peu nombreuses** (FR canonique + 3 surcharges) : la surcharge est **partielle** par nature (une clé absente = fallback FR), idiomatique en JSONB, pénible en table (lignes creuses ou colonnes NULL).
3. **Volumétrie triviale** (dizaines d'articles, corps de quelques Ko) : pas d'enjeu TOAST/index.
4. Cohérent avec l'existant plateforme (settings/metadata JSONB, surcharges e-mails par locale).

Contrepartie assumée : validation applicative stricte du shape (FormRequest : clés de locale ∈ {en, tr, ar}, champs autorisés fermés). Si un besoin v2 de requêtage par locale apparaît, l'extraction en table dédiée reste une migration mécanique.

### 4.3 Placement code (API)

Module **BC-01 PLATFORM** existant : `api/app/Modules/Platform/` (objet purement plateforme, pas de nouveau BC). Structure DDD conforme au validator : modèle `Domain/Models/PlatformBlogPost` (connexion centrale), Actions (Create/Update/Publish/Archive/UploadCover), FormRequests, Resource admin + **DTO public dédié** (règle de non-fuite : jamais d'Eloquent brut en public — pattern BC-27 §6), contrôleurs `Interfaces/Api/V1/Controllers/PlatformBlogPostController` (admin) et `PublicBlogController` (public).

## 5. API

### 5.1 Admin (cockpit) — groupe existant `auth:super_admin_api` + `throttle:platform-sensitive`, prefix `/admin`

Nouvelle permission plateforme `blog.manage`, appliquée via `platform.permission:blog.manage` :

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/admin/blog/posts` | Liste paginée + filtres `status`, `category`, `search` |
| POST | `/admin/blog/posts` | Création (slug auto depuis titre, unicité vérifiée) |
| GET | `/admin/blog/posts/{post}` | Détail complet (traductions incluses) |
| PATCH | `/admin/blog/posts/{post}` | Édition (contenu FR, traductions, SEO, auteur, catégorie…) |
| DELETE | `/admin/blog/posts/{post}` | **Brouillons uniquement** ; 409 sinon (on archive) |
| POST | `/admin/blog/posts/{post}/publish` | `draft|archived → published`, pose `published_at` si null, invalide cache + revalidation vitrine |
| POST | `/admin/blog/posts/{post}/unpublish` | `published → draft` |
| POST | `/admin/blog/posts/{post}/archive` | `published → archived` |
| POST | `/admin/blog/posts/{post}/cover` | Upload couverture (multipart ; ≤ 2 Mo ; validation MIME réelle ; SVG interdit, cf. §10) |
| POST | `/admin/blog/preview` | Rendu HTML sanitisé du markdown soumis (preview iso-vitrine) |

Toute écriture est auditée (canal AuditLog plateforme, société nulle).

### 5.2 Public (vitrine) — sans auth, routes isolées, throttle dédié

Pattern des routes publiques existantes (`throttle:public-careers`) : groupe `throttle:public-blog` + prefix `/public/blog` :

| Méthode | Route | Contrat |
|---|---|---|
| GET | `/public/blog/posts?locale=fr&category=&page=&per_page=` | Articles `published` + `archived` uniquement, tri : non-archivés d'abord puis `published_at DESC`. DTO public : slug, title, excerpt, cover_url, published_at, author {name, avatar_url}, category, reading_time, tags, archived:bool |
| GET | `/public/blog/posts/{slug}?locale=fr` | Détail : DTO liste + `body_markdown`. 404 si `draft`/inconnu |
| GET | `/public/blog/slugs` | Slugs + `published_at` + statut archived (pour `generateStaticParams` et le sitemap — payload minimal) |

- La résolution de locale applique le fallback FR **côté serveur** (merge `translations[locale]` sur les champs canoniques), la vitrine ne fait plus de merge.
- **Cache Redis** par clé `(locale, page, filtres)` / `(slug, locale)`, TTL 5-15 min, **invalidation explicite** sur publish/unpublish/archive/update d'un article publié.
- Réponses avec `Cache-Control: public, max-age=300, stale-while-revalidate` — le cache HTTP double le cache Redis.
- Test de non-fuite : le DTO public n'expose jamais `created_by`, `updated_by`, `translations` brutes, ni les brouillons.

## 6. Migration du contenu existant (seed depuis `blog.ts`)

1. **Export** : script one-shot `front/web/scripts/export-blog-json.ts` (exécuté via `tsx`) qui importe `blogPosts` + `localizedBlogPosts` de `blog.ts` et écrit `api/database/seeders/data/platform_blog_posts.json` (posts FR canoniques + traductions partielles + `archived` + dates). On **exporte depuis le module TS lui-même** (pas de parsing regex) pour fiabilité.
2. **Seed** : `PlatformBlogPostSeeder` (Laravel, base centrale) lisant ce JSON. **Idempotent par slug** (upsert, ne touche pas un article déjà modifié en base — garde `updated_by IS NULL` comme critère « jamais édité »). Mapping : `date` → `published_at` (dates historiques conservées, fidèles au contenu), `archived: true` → `status='archived'`, sinon `status='published'` ; images `/blog/*.svg` et avatars : copiés vers le storage plateforme par le seeder (ou, repli v1 acceptable, chemins absolus vers la vitrine conservés — à trancher en implémentation).
3. **Décommissionnement progressif** : `blog.ts` **n'est pas supprimé en v1** — il devient le **jeu de fallback** de la vitrine (§7). Sa suppression est une tâche v1.1 une fois le fallback jugé superflu.

## 7. Intégration vitrine (`front/web`)

Changement structurel : les deux pages blog sont aujourd'hui `'use client'`. Elles passent en **Server Components + ISR** :

- `app/(landing)/blog/page.tsx` : fetch liste via `fetch(API, { next: { revalidate: 300, tags: ['blog'] } })` ; l'interactivité (filtres catégorie, pagination, dark mode) reste dans les composants clients existants (`BlogGrid`…) alimentés en props.
- `app/(landing)/blog/[slug]/page.tsx` : `generateStaticParams` depuis `/public/blog/slugs`, `generateMetadata` (title/description/OG depuis le champ `seo` avec fallback, alternates hreflang alignés sur `seo.ts`), `revalidate` + tag `blog:{slug}`.
- **Revalidation à la demande** : route handler `app/api/revalidate/route.ts` (secret partagé) appelée par l'API Laravel (job/event à la publication) → `revalidateTag('blog')`. L'ISR périodique reste le filet si l'appel échoue.
- **Fallback si API down** : couche d'accès unique `modules/vitrine/lib/blog-api.ts` — en cas d'échec réseau/5xx, retour sur `getBlogPosts()` de `blog.ts` (contenu figé mais vitrine jamais cassée) + log.
- **Sitemap** (`src/app/sitemap.ts`) : la source passe de `getBlogPosts()` à `/public/blog/slugs` (même fallback statique) ; `lastModified = published_at` ; le gate `NEXT_PUBLIC_ENABLE_BLOG` **est conservé tel quel** (invariants existants : jamais d'URL /blog/* dans le sitemap quand le flag est off).
- **`llms.txt`** : consomme la même couche `blog-api.ts` (même fallback, même gate).
- Locale : la vitrine passe `?locale=` résolue par la mécanique existante ; les libellés d'UI restent dans les pages, seuls les **contenus** viennent de l'API.

## 8. UI admin (`front/admin-dashboard`, Vue 3, design system `glass-*`)

- **`views/content/BlogPostsView.vue`** : liste (titre, statut en badge, catégorie, `published_at`, complétude des traductions EN/TR/AR), filtres statut/catégorie, actions publier/dépublier/archiver, bouton « Nouvel article ». Entrée de navigation + route SPA + i18n des catalogues admin (aucune chaîne dure).
- **`views/content/BlogPostEditorView.vue`** : pattern `EmailTemplatesView.vue` — **onglets de langue FR/EN/TR/AR** (FR = canonique obligatoire ; EN/TR/AR = surcharges optionnelles, placeholder = valeur FR) ; champs titre/slug/extrait/catégorie/tags/auteur/SEO ; **éditeur markdown côte à côte avec prévisualisation** (rendu local sanitisé ou appel `POST /admin/blog/preview` — la preview serveur est la référence) ; upload de couverture avec aperçu ; sauvegarde brouillon vs publication distinctes ; garde « modifications non sauvegardées ».
- RTL : l'onglet AR passe l'éditeur/preview en `dir="rtl"`.

## 9. i18n

- **FR = langue canonique** (seuls champs obligatoires). EN/TR/AR = surcharges partielles avec fallback FR champ par champ, côté API (§5.2) — comportement identique à l'actuel `getBlogPosts()`.
- Un article peut être publié sans traductions. L'admin affiche la complétude, sans bloquer.
- Alternates hreflang inchangés (convention `sitemap.ts`/`seo.ts`).

## 10. Sécurité

| Menace | Mesure |
|---|---|
| XSS via markdown | Markdown **brut** stocké ; rendu HTML **toujours sanitisé** : vitrine = pipeline `remark`/`rehype` + `rehype-sanitize` (schéma strict, pas de HTML inline) ; preview admin = sanitisée. Aucune interpolation `v-html`/`dangerouslySetInnerHTML` non sanitisée |
| Upload malveillant | Validation MIME réelle, taille ≤ 2 Mo, images uniquement ; **SVG interdit à l'upload** (vecteur XSS) — les SVG migrés sont servis en statique, les nouveaux uploads sont raster (jpg/png/webp) ; noms de fichiers régénérés |
| Accès non autorisé | Routes admin sous `auth:super_admin_api` + `platform.permission:blog.manage` ; brouillons jamais servis en public (test dédié) |
| Abus des endpoints publics | `throttle:public-blog`, cache Redis, DTO minimal, pagination bornée (`per_page` ≤ 50) |
| Fuite de champs internes | DTO public dédié + test de non-fuite (pattern BC-27 §6) |
| Slug hijacking / SEO | Slug immuable après publication (v1) ; format validé `[a-z0-9-]{3,160}` |

## 11. Critères d'acceptation v1

1. Un admin avec `blog.manage` crée, édite, prévisualise, traduit, publie, dépublie et archive un article depuis le cockpit ; un admin sans la permission reçoit 403.
2. Le seed importe l'intégralité de `blog.ts` (posts FR, surcharges EN/TR/AR, `archived`, dates) ; relancé deux fois, il ne duplique ni n'écrase un article édité.
3. `/blog` et `/blog/{slug}` servent le contenu API dans les 4 langues (fallback FR), avec metadata SEO côté serveur ; un article publié depuis l'admin apparaît sur la vitrine sans redéploiement (revalidation ≤ 5 min ou immédiate via tag).
4. API down au runtime : la vitrine sert le dernier contenu ISR ou le fallback statique — jamais de 500 sur `/blog`.
5. Un brouillon n'apparaît ni sur la vitrine, ni dans l'API publique, ni dans le sitemap, ni dans `llms.txt` ; `NEXT_PUBLIC_ENABLE_BLOG=false` continue de retirer toutes les URLs blog du sitemap.
6. Le markdown contenant `<script>`/HTML hostile est neutralisé au rendu (test XSS vitrine + preview admin) ; l'upload d'un fichier non-image est rejeté.
7. Qualité repo : migration centrale idempotente, PHPStan strict 0 sur delta, Pint, validator Module Structure, tests Feature (CRUD, workflow, non-fuite, cache/invalidation), i18n admin sans chaîne dure, CHANGELOG.

## 12. Découpage en issues suggéré (4)

| # | Issue | Contenu | Dépend de |
|---|---|---|---|
| 1 | **API — socle + CRUD admin** | Migration centrale `platform_blog_posts`, modèle/Actions/DTO, permission `blog.manage`, routes `/admin/blog/*` (CRUD, workflow, upload, preview), audit, tests | — |
| 2 | **API — endpoints publics + cache + seed** | Routes `/public/blog/*`, DTO public, résolution locale + fallback, cache Redis + invalidation, throttle ; script export `blog.ts` → JSON + `PlatformBlogPostSeeder` idempotent | 1 |
| 3 | **Admin-dashboard — liste + éditeur** | `BlogPostsView` + `BlogPostEditorView` (onglets langues, markdown + preview sanitisée, upload, workflow), navigation, i18n, e2e admin | 1 |
| 4 | **Vitrine — bascule API + SEO** | `blog-api.ts` (fetch + fallback statique), refonte Server Components + ISR + revalidation à la demande, `generateMetadata`, sitemap + `llms.txt` dynamiques, sanitisation rendu, e2e Playwright | 2 |

Ordre : #1 → #2 → (#3 ∥ #4).

## 13. Extension future v2 — blog côté client tenant (esquisse, non détaillée)

Le blog tenant reste **hors périmètre** ici, conformément à `SOLUTION_SITE_VITRINE.md` §11 (« hors v1 : blog complet »). Piste actée pour v2 : ne **pas** dupliquer le présent module, mais l'implémenter comme un **nouveau type de section du module Showcase** (BC-27, `api/app/Modules/Showcase/`) :

- type de section `blog` dans le contrat de sections JSON Schema, adossé à une table **tenant** `showcase_blog_posts` (schéma par tenant, isolation existante) réutilisant le même shape (slug, markdown, statut, traductions JSONB) ;
- rendu public via le moteur SSR Showcase (`/vitrine/{slug}/blog/...`), cache et DTO publics du BC-27 ;
- réutilisation transverse à étudier : pipeline de sanitisation markdown et composant éditeur admin extraits en briques partagées.

Aucune décision de modèle ni d'API n'est prise ici pour ce volet.

## 14. Risques

| Risque | Mitigation |
|---|---|
| Refonte client→serveur des pages blog casse l'UX existante (filtres, dark mode, RTL) | Interactivité conservée dans les composants clients existants ; e2e Playwright sur les 4 locales |
| Régression SEO au moment de la bascule | Slugs et URLs strictement identiques (seed conserve les slugs de `blog.ts`) ; sitemap comparé avant/après ; metadata serveur = gain net |
| Divergence fallback statique vs contenu API | Fallback = filet de dernier recours documenté comme figé ; suppression planifiée v1.1 |
| XSS via markdown (contenu devenu dynamique) | Sanitisation systématique testée (payloads hostiles en CI) ; pas de HTML inline autorisé |
| Cache public servant un brouillon ou un contenu périmé | Invalidation explicite sur toute transition de statut + TTL court ; test cache dédié |
| Dérive « CMS complet » | Périmètre v1 verrouillé (markdown + workflow 3 statuts) ; toute demande d'éditeur riche passe par un spike |
