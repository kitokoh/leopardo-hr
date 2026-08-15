# Feature Specification: Audit Expert Web — Cohérence Vitrine & Admin Dashboard — 2026-08-15

**Feature Branch**: `qa-audit-expert-web-2026-08-15`

**Created**: 2026-08-15

**Status**: In progress

**Input**: Mission propriétaire — audit expert complet ; ce feature couvre **front/web** (vitrine Next.js 16 + dashboard client) et **front/admin-dashboard** (SPA Vue 3 super-admin). Audit : workstreams parallèles + vérifications manuelles (`smart-attendance` et `announcements` vérifiés OK en backend — écartés).

## Contexte

L'audit expert 2026-08-15 (fronts) a confirmé : le module edge-nodes du dashboard web appelle des endpoints inexistants (404), des liens d'action rapide cassés, une qualité texte dégradée (133+ accents manquants), 18 pages marketing en français dur uniquement, des incohérences SEO (domaines), du contenu mort, et côté admin : un endpoint impersonation 404, des actions utilisateur simulées, un header search stub, des composants orphelins.

## User Scenarios & Testing

### User Story 1 — Dashboard web : plus d'écrans cassés (Priority: P1)

Un utilisateur du dashboard client peut gérer ses edge nodes et utiliser les raccourcis du tableau de bord sans tomber sur une 404.

**Why this priority**: 3 liens d'action rapide + le module edge-nodes entier sont cassés (404) — c'est la surface la plus visible du produit.

**Independent Test**: `npm run lint` + `npm run build` verts ; `rg 'apiFetch'` des pages concernées → chemins existants dans `api/routes/*.php`.

**Acceptance Scenarios**:

1. **Given** la page edge-nodes, **When** elle charge (`GET /edge`), **Then** pas de 404 — soit endpoint backend scopé ajouté, soit la page est retirée du dashboard client (décision : si `edge_nodes` est une table platform, retirer la page de la navigation et router — voir plan).
2. **Given** le dashboard client, **When** on clique « Employés / Absences / Rapports », **Then** navigation vers les routes réelles (`/employees`, `/absences`, `/reports`) — aujourd'hui `/dashboard/*` → 404 (`dashboard/page.tsx:611-614`).

### User Story 2 — Qualité texte vitrine : accents + i18n (Priority: P2)

Les textes visibles (vitrine, blog, FAQ, pages légales) sont correctement accentués en français, et les pages marketing principales sont traduisibles (fr/en/ar/tr).

**Why this priority**: 133+ occurrences d'accents manquants (« transforme », « equipe », « donnees ») dégradent la crédibilité perçue ; 18 pages ignorent l'i18n existant.

**Independent Test**: script `check-mojibake.mjs` vert ; `rg` accents manquants ciblés → 0 sur les fichiers corrigés ; build + lint verts ; pages ciblées lisent `useVitrineLocale`.

**Acceptance Scenarios**:

1. **Given** la vitrine FR, **When** on lit FAQ/témoignages/blog/pages légales, **Then** accents corrects (é, è, à, ç) — `data/faq.ts`, `data/blog.ts`, `data/testimonials.ts:19`, `legal-content.ts`.
2. **Given** la bascule de locale, **When** on visite `/about`, `/careers`, `/contact`, `/faq`, **Then** le contenu suit la locale (aujourd'hui français dur).
3. **Given** le navbar, **When** on lit les libellés FR, **Then** accents corrects (« Tarifs », « Équipes »).

### User Story 3 — Cohérence SEO & domaines (Priority: P2)

Les canonicals, sitemap, robots et données structurées pointent vers le domaine officiel `leopardo-rh.com` ; pas de robots.txt dupliqué ; sitemap complet ; dates de contenu à jour.

**Why this priority**: Les défauts pointent vers des domaines Vercel/Render de dev (`gestionemployer-backend.vercel.app`, `gestionemployerbackend.onrender.com`) — canonicals faux en prod, impact SEO et confiance.

**Independent Test**: `rg 'gestionemployer|vercel.app|onrender.com' front/web/src` → 0 (hors commentaires historiques) ; `npm run build` vert ; sitemap contient les pages principales.

**Acceptance Scenarios**:

1. **Given** la prod, **When** on inspecte `sitemap.ts`/`robots.ts`/canonicals, **Then** URL de base = `https://leopardo-rh.com` (env surchargable).
2. **Given** `/api/robots`, **Then** plus de route dupliquée (suppression de la legacy).
3. **Given** le sitemap, **Then** `/blog`, `/signup`, `/checkout`, `/offline` présents.
4. **Given** les données structurées, **Then** `sameAs` cohérent (x.com/leopardo_hr + github org) — `seo.ts:372-374`.
5. **Given** le blog, **Then** pas d'article daté 2024 encore affiché en 2026 (dates corrigées) et pas de fichiers markdown morts (`src/content/blog/*.md`, `content/blog/*.mdx` jamais importés).

### User Story 4 — Admin dashboard : actions réelles, zéro simulacre (Priority: P2)

Le super-admin peut impersonner un utilisateur (endpoint existant), gérer un utilisateur sans boutons simulés, et utiliser la recherche du header — ou les surfaces non supportées sont retirées.

**Why this priority**: Des actions simulées (setTimeout + toast) et un endpoint 404 trompent l'opérateur ; le header search stub est un dead-end UX.

**Independent Test**: lint admin 0 erreur/0 warning ; build vert ; `rg 'setTimeout' src/views/users` → uniquement debounce légitime ; routes SPA → routes backend vérifiées.

**Acceptance Scenarios**:

1. **Given** la vue Users, **When** on impersonne, **Then** POST `/admin/impersonations` existe (fix backend dans la feature backend) et la session démarre — aujourd'hui 404 (`UsersView.vue:435`).
2. **Given** `EditUserModal`, **When** on clique « Réinitialiser le mot de passe / Email de bienvenue / Forcer la déconnexion », **Then** appel API réel OU boutons retirés (aujourd'hui `setTimeout`+toast, `EditUserModal.vue:330-357`) ; « Changer l'avatar » actionnable ou retiré (`:35`).
3. **Given** le header, **When** on tape dans la recherche, **Then** résultat réel (filtrage client) OU champ retiré — aujourd'hui `console.log` stub (`Header.vue:237-241`).
4. **Given** l'app, **Then** aucun composant orphelin avec boutons morts (RevenueForecastWidget, 8 composants système) et clés i18n `users.errors.password_min`/`users.toast.bulkDone` présentes.

### Edge Cases

- Le retrait de la page edge-nodes ne casse pas les liens entrant/sitemap existants (mise à jour de `sitemap.ts`).
- La correction d'accents ne doit pas réintroduire de mojibake (valider avec `check-mojibake.mjs`).
- Les pages i18n : conserver le fallback FR pour les locales incomplètes.
- Impersonation : la SPA appelle `/admin/impersonations` — le fix backend (feature backend T011) est une dépendance.

## Requirements

### Functional Requirements

- **FR-001**: Le module edge-nodes du dashboard client DOIT fonctionner (endpoint backend scopé) OU être retiré de la navigation/sitemap.
- **FR-002**: Les liens d'action rapide du dashboard DOIVENT pointer vers des routes existantes.
- **FR-003**: Les textes FR de la vitrine DOIVENT être accentués correctement (0 occurrence des listes de mots connues).
- **FR-004**: `/about`, `/careers`, `/contact`, `/faq` DOIVENT être rendus depuis les données localisées (`useVitrineLocale`).
- **FR-005**: L'URL de base SEO DOIT être `https://leopardo-rh.com` par défaut (surchargeable par env).
- **FR-006**: La route `/api/robots` dupliquée DOIT être supprimée ; le sitemap DOIT inclure les pages principales.
- **FR-007**: `sameAs` JSON-LD DOIT être cohérent avec le footer.
- **FR-008**: Les fichiers de contenu morts DOIVENT être supprimés ; les dates de blog obsolètes corrigées.
- **FR-009**: L'admin DOIT impersonner via un endpoint réel ; les actions utilisateur DOIVENT être réelles ou retirées ; le header search DOIT fonctionner ou disparaître.
- **FR-010**: Les composants orphelins DOIVENT être supprimés ou câblés ; les clés i18n manquantes ajoutées.

### Key Entities

- **EdgeNode**: device edge (gestion platform vs client — décision produit).
- **LocaleContent**: données vitrine par locale (fr/en/ar/tr).
- **SiteURL**: domaine canonique centralisé (`leopardo-rh.com`).
- **ImpersonationSession**: session d'impersonation (backend).
- **UserAction**: reset-password / welcome-email / force-logout (réel ou retiré).

## Success Criteria

### Measurable Outcomes

- **SC-001**: 0 lien interne cassé sur les surfaces corrigées (build + vérification manuelle).
- **SC-002**: 0 accent manquant sur les fichiers vitrine corrigés ; `check-mojibake` vert.
- **SC-003**: 4 pages marketing majeures localisées ; 14 pages restantes documentées en tâches.
- **SC-004**: 0 référence `gestionemployer*`/`vercel.app`/`onrender.com` dans les métadonnées SEO.
- **SC-005**: Admin : 0 action simulée sur Users ; 0 composant orphelin ; lint/build verts.

## Assumptions

- Les 14 pages marketing restées en français dur sont documentées comme tâches futures (pas de blocage de ce run).
- La vue `requiresTenant` du router admin (12 vues) est un choix d'architecture (SPA super-admin) — documentée, pas modifiée dans ce run, sauf décision contraire du propriétaire.
- Le fix impersonation backend (T011 de la feature backend) est une dépendance de la US4.
