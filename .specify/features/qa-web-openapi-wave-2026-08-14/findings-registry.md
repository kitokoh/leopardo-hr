# Registre des manquements — QA Plateforme 2026-08-14

> Session de test de la plateforme Leopardo RH (repo kitokoh/leopardo-hr, main @ 0feb18ad).
> Mission : workflows API, vues, boutons, logique — tout doit être fonctionnel et propre.
> Chaque manquement → tâche rédigée (technique spec kit) puis implémentée.
> Plusieurs agents travaillent en parallèle : vérifier les issues existantes avant d'en créer (règle anti-doublon).

## A. Backend — logique (validation par la suite de tests complète)
- [ ] Lancer `php artisan test` (1917 tests) sur main — statut à reporter ici.
- [ ] Lancer PHPStan strict level 8 + Pint + Module Structure Validator.

## B. Contrat API ↔ frontends (P1) — DÉJÀ COUVERT par spec qa-hardening-wave-2026-08-14 (agent parallèle, PR #2191)
Les issues #2177-#2180 + tasks T003-T008 couvrent : ChatView /admin/ai/conversations, ExportsView /admin/hr-reports,
FleetView /admin/fleet/alerts, MarketingOAuthView /admin/platform/marketing/oauth-config,
TrainingView (GET /training/sessions + /training/enrollments à créer), WebhooksView (POST /webhooks/{id}/test à créer),
mobile employee (/me/training-enrollments, /me/vehicles). → NE PAS DUPLIQUER.

## C. Mes findings NON couverts (à traiter dans cette session)

### C1. Vitrine front/web — liens/ancres morts + contenus factices (P2)
- [x] Confirmé : `(landing)/docs/page.tsx` — ~35 ancres `#intro/#onboarding/#team/#dashboard/#leaves/#payroll/#contracts/#mobile-*/#api-*/#webhooks-*/#sdk-*/#playground-*/#security/#rbac/#deploy/#api-auth/#zkteco/#exports/#calendar/#multi-tenant/#partner-api` référencées mais non définies (seules 4 existent : api-quickstart, kiosk, sdk-overview, webhooks-overview). Clic sans effet.
- [x] Confirmé : `(landing)/videos/page.tsx` — clic "play" affiche « Vidéo en cours de chargement... » au lieu d'embarquer le player (youtubeId présent mais pas d'iframe). Bouton factice.
- [x] Confirmé : `sitemap.ts` utilise `getAllPosts()` (content/blog/*.mdx → slugs `paie-multi-pays-defis`, `pointage-biometrique-entreprise`) alors que le blog réel (`modules/vitrine/data/blog.ts`) utilise `pointage-biometrique-avantages` etc. → 2 URLs du sitemap → 404.
- [x] Confirmé : `public/manifest.json` déclare `share_target.action=/share` (POST) mais aucune route `/share` n'existe → 404 PWA.
- [x] Confirmé : `layout.tsx:136` skip-link `#main-content` — l'id n'existe que sur `(landing)/page.tsx` → lien mort sur toutes les autres pages.
- [ ] À traiter : PricingSection.tsx — CTA plan Enterprise en ar détourné vers /checkout (lié au mojibake #2173, déjà couvert par l'autre agent — vérifier).

### C2. OpenAPI ↔ routes (P2) — alignement contrat
- [x] Confirmé (audit) : 16 opérations documentées sans route implémentée (bank-exports ×2, exports/* pluriel ×8, i18n/{locale}, partner/* ×4, smart-attendance validate) + 6 mismatch de verbes + 3 méthodes mortes EdgeController + 230 routes non documentées.
- [ ] Décision : l'alignement complet est volumineux — traiter au moins les mismatch de verbes et le doublon EdgeController (petits, sûrs) ; documenter le reste.

### C3. Admin dashboard — restes non couverts (P2)
- [x] Confirmé : `views/system/LogsView.vue` — vue orpheline (aucune route router n'y pointe).
- [ ] Les boutons morts admin sont couverts par la spec parallèle T013 (Growth Gérer, CompanyDetail Super-Console, Analytics Voir détails, LoginView #) — NE PAS DUPLIQUER, sauf si la spec parallèle ne les traite pas (vérifier à la fin).

### C4. Points remarqués en staging (information — pas des bugs main)
- Staging API v4.23.5 en retard sur main v4.24.0 : `/i18n/catalog/fr` → 500 (fix #1773 présent dans main/Dockerfile — à confirmer par déploiement).
- `/supported-countries` sur staging → 404 (route ajoutée sur main #2127, non déployée).
- Login demo admin `password123` refusé en prod (DISABLE_DEMO_SEEDING) mais bouton "Acces Demo" toujours affiché → mauvaise UX (P3, optionnel).

## D. Tâches rédigées (spec kit) — liens
- (à compléter après validation des tests backend)
