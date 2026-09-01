# Feature Specification: Vague QA Web & OpenAPI 2026-08-14

**Feature Branch**: `qa-web-openapi-wave-2026-08-14`

**Created**: 2026-08-14

**Status**: Draft

**Input**: Mission de test de la plateforme (session 2026-08-14, main `0feb18ad`) — audit fonctionnel des workflows API, vues, boutons et logiques. Cette spec couvre les manquements trouvés **non couverts** par la vague parallèle `qa-hardening-wave-2026-08-14` (agent concurrent, PR #2191) : vitrine `front/web` (liens/ancres morts, contenus factices, sitemap, PWA share_target, skip-link) et alignement OpenAPI ↔ routes (verbes, doublons morts, drift).

## User Scenarios & Testing

### User Story 1 — La documentation vitrine `/docs` n'expose pas de liens morts (Priority: P2)

Un visiteur qui clique sur une carte d'accès rapide de `/docs` (intro, onboarding, team, dashboard, leaves, payroll, contracts, mobile, api, webhooks, sdk, playground…) est amené à la section correspondante de la page. Aujourd'hui ~35 ancres `#xxx` sont référencées mais seules 4 existent → le clic ne fait rien.

**Independent Test**: audit statique — chaque `href="/docs#ancre"` de `front/web/src/app/(landing)/docs/page.tsx` a une cible `id="ancre"` dans le même fichier (ou l'ancre est retirée).

**Acceptance Scenarios**:

1. **Given** la page `/docs`, **When** un visiteur clique sur une carte d'accès rapide, **Then** la page scrolle vers la section ciblée (ou le lien est un lien réel sans ancre morte).
2. **Given** la page `/docs`, **When** on audite les ancres, **Then** aucune `href="/docs#X"` n'est référencée sans `id="X"` défini.

### User Story 2 — La page `/videos` embarque de vrais lecteurs (Priority: P2)

Un visiteur qui clique sur « play » sur une vidéo de démonstration voit la vidéo se lancer (lecteur YouTube embarqué) au lieu d'un placeholder « Vidéo en cours de chargement... » permanent.

**Independent Test**: interaction navigateur sur `/videos` — clic sur play → un `iframe` (youtube-nocookie) est inséré dans le DOM ; pas de placeholder résiduel.

**Acceptance Scenarios**:

1. **Given** une vidéo avec `youtubeId` valide, **When** on clique sur play, **Then** un iframe `https://www.youtube-nocookie.com/embed/{youtubeId}?autoplay=1` remplace la vignette.
2. **Given** une vidéo sans `youtubeId` exploitable, **When** on clique, **Then** un message clair « vidéo indisponible » s'affiche (pas de faux chargement infini).

### User Story 3 — Le sitemap ne référence que des pages réelles (Priority: P2)

Les moteurs de recherche reçoivent un sitemap dont chaque URL `/blog/*` correspond à un article existant. Aujourd'hui le sitemap est généré depuis `content/blog/*.mdx` (slugs `paie-multi-pays-defis`, `pointage-biometrique-entreprise` absents du blog réel) alors que le blog réel vit dans `src/modules/vitrine/data/blog.ts` (slugs `pointage-biometrique-avantages`, etc.) → 2 URLs 404 + 9 vrais articles absents du sitemap.

**Independent Test**: `npx tsx` ou lecture — l'ensemble des slugs du sitemap = ensemble des slugs de `getBlogPosts()` (toutes locales confondues).

**Acceptance Scenarios**:

1. **Given** le blog réel `data/blog.ts`, **When** le sitemap est généré, **Then** chaque `/blog/{slug}` est un slug réel du blog (aucun 404).
2. **Given** un article ajouté à `data/blog.ts`, **When** le sitemap est régénéré, **Then** il apparaît automatiquement.

### User Story 4 — La PWA et le skip-link n'annoncent pas de routes fantômes (Priority: P2)

Le manifeste PWA déclare un `share_target` vers `/share` (POST) qui n'existe pas → échec du partage PWA ; le skip-link `#main-content` n'existe que sur la landing → lien mort partout ailleurs.

**Independent Test**: `ls front/web/src/app/share` absent → soit créer la route, soit retirer `share_target` ; grep `id="main-content"` → présent sur toutes les pages ou skip-link retiré.

**Acceptance Scenarios**:

1. **Given** le manifeste PWA, **When** un utilisateur partage vers l'app, **Then** la cible `/share` existe et gère le POST (ou `share_target` est retiré du manifeste).
2. **Given** n'importe quelle page, **When** on active le skip-link, **Then** le focus se déplace sur le contenu principal (id présent sur toutes les pages).

### User Story 5 — Le contrat OpenAPI ne ment pas sur les verbes et les routes (Priority: P2)

Un client qui suit la spec OpenAPI (`api/openapi.yaml`) n'obtient pas de 405 : les verbes documentés correspondent aux routes réelles. Les méthodes mortes (`EdgeController::{installScript, downloadDockerCompose, licensePublicKey}`) sont retirées.

**Independent Test**: parseur routes vs spec — les 6 mismatch de verbes documentés disparaissent ; `grep` des 3 méthodes mortes → 0 référence.

**Acceptance Scenarios**:

1. **Given** `PUT /smart-attendance/config` documenté, **When** on compare aux routes, **Then** la route est bien `PUT` (ou la spec alignée sur la route réelle).
2. **Given** `POST /cabinet/documents/{id}/move` documenté, **When** on compare, **Then** la route réelle est `POST` (ou la spec alignée).
3. **Given** `EdgeController::installScript/downloadDockerCompose/licensePublicKey`, **When** on grep le code, **Then** plus aucune référence (ou routes réelles branchées).

---

## Technical Context

### Périmètre

- `front/web/src/app/(landing)/docs/page.tsx` — ancres mortes
- `front/web/src/app/(landing)/videos/page.tsx` — player factice
- `front/web/src/app/sitemap.ts` — source de slugs erronée
- `front/web/public/manifest.json` + route `/share` — share_target
- `front/web/src/app/layout.tsx` — skip-link
- `api/openapi.yaml` + `dev-hub/openapi/v1.yaml` (miroir) — verbes/drift
- `api/app/Modules/EdgeSync/Interfaces/Api/V1/Controllers/EdgeController.php` — méthodes mortes

### Hors périmètre (couverts par la vague parallèle qa-hardening-wave-2026-08-14)

Chemins `/admin/*` du cockpit, TrainingView/WebhooksView endpoints, mobile `/me/*`, boutons morts admin, mojibake (#2173/#2174-#2180).

### Contraintes

- Ne pas casser le build Next.js (`npm run build` vert) ni les tests Playwright existants.
- Miroir OpenAPI `dev-hub/openapi/v1.yaml` régénéré en même temps que `api/openapi.yaml` (script `generate-openapi-sdk.mjs`).
- CHANGELOG.md à jour.
