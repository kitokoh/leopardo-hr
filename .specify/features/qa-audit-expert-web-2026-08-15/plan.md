# Plan: Audit Expert Web — Cohérence Vitrine & Admin Dashboard — 2026-08-15

**Input**: spec.md (US1-US4) + Constitution + registre project-state

## Architecture / Décisions techniques

### US1 — Dashboard web cassé (P1)
- **Edge nodes** : `front/web/src/app/(dashboard)/edge-nodes/page.tsx:57,77,94` appelle `GET/POST /edge` + `POST /edge/{nodeId}/sync`. Vérification backend : `edge_nodes` est géré en super-admin (`/platform/edge/nodes` + `/admin/edge-nodes`, `api/routes/api.php:289-313`) — **pas de concept company pour l'edge**. Décision : **retirer la page du dashboard client** (nav + route + sitemap), car la gestion d'edge est une surface platform ; éviter d'inventer un endpoint métier sans modèle. Le menu `System` de la SPA admin couvre déjà cette surface.
- **Liens action rapide** : `(dashboard)/dashboard/page.tsx:611-614` — corriger `/dashboard/employees` → `/employees`, `/dashboard/absences` → `/absences`, `/dashboard/reports` → `/reports`. Vérifier le router Next (route group `(dashboard)`) pour les routes réelles (`src/app/(dashboard)/employees/...`).

### US2 — Qualité texte (P2)
- **Accents** : script Python ciblé (table de remplacement mot→mot, uniquement les mots ASCII connus : `transforme`→`transformé`, `equipe`→`équipe`, `donnees`→`données`, `experience`→`expérience`, `phenomenal`→`phénoménal`, `generer`→`générer`, `deployer`→`déployer`, `securisees`→`sécurisées`, `biometrisque`→`biométrique`, `Telechargez`→`Téléchargez`, `Accedez`→`Accédez`, `Reessayer`→`Réessayer`…) appliqué à `front/web/src` (fichiers `data/*.ts`, `lib/*.ts`, pages ciblées) — **jamais sur les chaînes i18n EN/TR/AR** (ne toucher que les clés FR) ni sur les slugs/paths. Vérification : build + `check-mojibake` + diff manuel.
- **i18n pages** : cibler `/about`, `/careers`, `/contact`, `/faq` — déplacer le contenu vers `data/*.ts` par locale (modèle existant `data/faq.ts`) et rendre avec `useVitrineLocale`. Les 14 autres pages (branding, videos, mobile, case-studies, comptabilite, documents, employes, marketing, docs, guides×3, privacy, terms, offline, testimonials) → documentées en tâches futures.
- **Navbar** : accents des libellés FR (`Navbar.tsx:79-104`).

### US3 — SEO & domaines (P2)
- **Domaine** : centraliser dans `front/web/src/lib/site.ts` (nouveau) : `export const SITE_URL = process.env.NEXT_PUBLIC_SITE_URL ?? 'https://leopardo-rh.com'` ; remplacer les défauts `gestionemployer-backend.vercel.app` (`sitemap.ts:11`, `robots.ts:4`, `api/robots/route.ts:4`) et les canonicals durs ; aligner le proxy backend `next.config.ts:45` + `api-client.ts:17` + CORS `api/v1/[...path]/route.ts:105-106` sur `https://api.leopardo-rh.com` (env surchargeable, défaut cohérent documenté).
- **Robots/sitemap** : supprimer `src/app/api/robots/route.ts` (legacy, disallow périmé) ; ajouter `/blog`, `/signup`, `/checkout`, `/offline`, `/share` au sitemap.
- **Structured data** : `sameAs` → `https://x.com/leopardo_hr` + `https://github.com/kitokoh/leopardo-hr` (`seo.ts:372-374`, `structured-data.ts:32-34,233`).
- **Contenu mort** : supprimer `front/web/src/content/blog/*.md` (10) + `front/web/content/blog/*.mdx` (3) jamais importés (vérifier avec rg avant suppression) ; corriger les dates 2024 → 2026 dans `data/blog.ts` (3 posts « tendances-rh-2024 », « automatiser-paie-2024 ») et les titres si nécessaire.
- **Pages orphelines** : ajouter `/about`, `/branding`, `/videos`, `/mobile` au footer/nav (`Footer.tsx:15-49`, `Navbar.tsx`) OU les retirer du sitemap — décision : les lier (contenu existant, valeur marketing) ; `/mobile` lié depuis `/download`.

### US4 — Admin dashboard (P2)
- **Impersonation** : dépend du fix backend (T011 feature backend) — ici : vérifier que `UsersView.vue:435` appelle bien `/admin/impersonations` (aucun changement SPA nécessaire si backend ajoute la route).
- **EditUserModal** (`src/views/users/EditUserModal.vue`) : `resetPassword`/`sendWelcomeEmail`/`forceLogout` simulés (`:330-357`) — vérifier l'existence d'endpoints backend (`/admin/users/{id}/reset-password` ? inexistant) → **retirer les 3 boutons simulés** (décision : pas de demi-mesure) + supprimer le handler. « Changer l'avatar » (`:35`) sans @click → retirer le bouton (pas d'endpoint avatar admin). `CreateUserModal`/`UserDetailModal` : vérifier (déjà corrigés selon audit #2341 — mock submit retiré).
- **Header search** (`components/layout/Header.vue:237-241`) : implémenter un filtrage client de la liste de navigation (query → menu items filtrés + shortcut clavier) OU retirer le champ. Décision : **filtrage client** minimal (le header est déjà dans l'app, pas de backend nécessaire) — sinon suppression.
- **Orphelins** : supprimer `RevenueForecastWidget.vue` + 8 composants système jamais importés (`CreateTaskModal`, `BackupManagement`, `ApiTestingTools`, `SecurityMonitoring`, `SystemConfiguration`, `ResourceUsageWidget`, `RealTimeMetricsChart`, `ImportConfigModal`) — vérifier 0 référence avant suppression (rg).
- **i18n** : ajouter `users.errors.password_min` + `users.toast.bulkDone` aux 4 locales (`src/i18n/locales/*.json`).
- **console.log** : retirer les leftovers (`Header.vue:240`, `stores/realtime.js:72,80`).

## Phases

### Phase 1 — US1 dashboard web (P1)
- T001 Retirer la page edge-nodes du dashboard client (nav + route + sitemap) OU endpoint scopé — décision : retrait, vérification rg
- T002 Corriger les 3 liens d'action rapide (employees/absences/reports)

### Phase 2 — US2 texte + i18n (P2)
- T003 Script accents + application fichiers vitrine ciblés (data/lib/pages FR)
- T004 i18n /about /careers /contact /faq (contenu par locale) + navbar accents
- T005 Vérification : build web vert, check-mojibake vert, diff manuel

### Phase 3 — US3 SEO (P2)
- T006 Centralisation SITE_URL + remplacement domaines dev dans sitemap/robots/canonicals/proxy/CORS
- T007 Suppression /api/robots legacy + complétion sitemap + sameAs JSON-LD
- T008 Contenu mort (md/mdx) supprimé + dates blog 2024→2026 + pages orphelines liées (footer/nav)

### Phase 4 — US4 admin (P2)
- T009 EditUserModal : retrait boutons simulés + avatar inerte
- T010 Header search : filtrage client OU retrait
- T011 Suppression composants orphelins (9) + console.log leftovers
- T012 i18n keys manquantes (4 locales) + vérification impersonation SPA ↔ backend (T011 backend)

## Validation finale
`npm run lint` + `npm run build` (web), `npm run lint` + `npm run build` (admin-dashboard), `check-mojibake`, `rg` zéro référence aux fichiers supprimés, entrée CHANGELOG.
