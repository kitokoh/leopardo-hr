# Session QA Agent 360° — 2026-08-16 (expert Moclaw, run 2)

> Audit 360° + implémentation + consolidation. Spec-kit respecté (constitution
> `.specify/constitution.md`, anti-doublon #2400, `Closes #` dans le body des PR).

## Phase 0 — Consolidation des branches ouvertes (résultat)

Toutes les PR ouvertes en début de session ont été résolues (certaines par des
agents parallèles, certaines par ce run) :

| PR | Sujet | État |
|---|---|---|
| #4275 | fix/3237 api FR strings | ✅ mergée |
| #4276 | fix/4197 mobile number locale | ✅ mergée |
| #4277 + #4280 | fix/4191 i18n API batch 1+2 | ✅ mergées |
| #4279 | docs expert14 (rebase + fusion des 2 rapports, SHA 395e0033) | ✅ mergée |
| #4282 | fix/4206 admin i18n lot 3 (conflit versions.json régénéré via sync i18n) | ✅ mergée |
| #4283 | fix/4190 OpenAPI 16 routes (rebase propre) | ✅ mergée |
| #4284 | fix/4206 lot 4 ExportsView | ✅ mergée |
| #4270 | ADR-0014 plans (superseded par #4184/#3883) | fermée |

Leçon confirmée : fenêtre de collision ~2 min entre agents ; protocole gagnant =
self-assign → checkout → claim marker → push immédiat.

## Phase 0 — Vérification « audits précédents réellement implémentés »

- **#3842 (mobile workflow contracts)** : vérifié sur main SHA 3903f209 — les 5
  violations d'origine ne se reproduisent plus (routes `/evaluations`,
  `/notifications`, `/history` présentes dans `leopardo_hr/lib/app.dart` ;
  `/device-tokens` câblé dans `leopardo_core` PushNotificationService ;
  `mark-all-read`/`vehicle-map` retirés du contrat). **Fermée avec preuve.**
- **#3846 (OpenGraph determinism)** : le workflow `i18n-enterprise.yml`
  (validate-and-sync) ne contient plus aucune étape régénérant les images
  `public/og/**` — aucun générateur OG dans le repo ; le diff fantôme binaire ne
  peut plus se reproduire. **Fermée avec preuve.**
- **#2646 (demo KO prod)** : preuve fraîche ajoutée — `POST /auth/login` demo →
  500, `/auth/me` sans token → 500 (HTML vide) au lieu de 401 JSON, `/demo-users`
  → 500, health 200 mais 10-18 s. Le code main contient les renderers corrects
  (`bootstrap/app.php` : 401 `UNAUTHENTICATED`, gate démo 404) → prod stale ou
  env cassé. Cross-référencé #4370 (issue spec-kit dédiée), #3767, #2812.

## Phase 1 — Audit 360° (constats vérifiés)

### Vitrine (front/web)

| Constat | Preuve | Statut |
|---|---|---|
| `/checkout` + `/checkout/success` 100 % FR en dur ×4 locales | grep + lecture des 2 pages | ✅ corrigé par #4287 (data/checkout.ts, 937 lignes) |
| 4 pages modules (employes/comptabilite/documents/marketing) 100 % FR | `modulePageContent` monolingue | ✅ #4290 lots 1+2 (ce run : lot 2 complet ×3 locales, parité de clés vérifiée par script node) |
| Résiduel cosmétique : libellé « jours/gün/أيام » en ternaire inline (checkout page.tsx:819) hors catalogue | lecture | P3 — à basculer dans le catalogue au prochain passage |
| hreflang / canonical localisés | layout.tsx + sitemap.ts (x-vitrine-lang #4004) | ✅ déjà implémenté (#3250 couvert) |
| Pages faq/videos/guides/about/case-studies/testimonials localisées | scan `useVitrineLocale` | ✅ |
| A11y landing : zéro `<img>` sans alt, zéro bouton icône-only sans aria-label | script python | ✅ |
| `/signup` localisé ×4 | signupCopy Record | ✅ |

### Back-office admin (front/admin-dashboard)

| Constat | Preuve | Statut |
|---|---|---|
| i18n lots 1-4 (Dashboard, Companies, CompanyDetail, Exports) | PR #4281/#4282/#4284/#4206 | ✅ mergées |
| **SupportView — dernière vue majeure 100 % FR codée en dur** (~38 chaînes : titre, KPIs, file de qualification, filtres, états, toasts, statuts) | lecture du fichier | ✅ corrigé par PR #4437 (ce run, namespace `support.*` ×4) |
| Catchs vides / console.log / img sans alt | scans | ✅ aucun |

### Mobile (5 apps Flutter)

| Constat | Preuve | Statut |
|---|---|---|
| Dette i18n massive (10 118 signaux, 2 636 P1) — surtout mobile | `dev-hub/tools/i18n-debt.js` (rapport du jour) | suivie #2755/#4194 (chantier de fond) |
| **Main rouge : garde Plan 29 vise le mauvais workflow** (`mobile-distribute.yml` vs `mobile-distribute-main.yml`, trigger déplacé par #1396) | runs main 16b33e86/751bfdbe/edf5893e/4318d924 | ✅ corrigé PR #4389 (ce run) |
| **Main rouge : Gradle 8.11.1/8.10.2 < 8.14 exigé par Flutter 3.47** → `flutter build apk --release` KO | logs Mobile Distribute - Main | ✅ corrigé PR #4389 (bump 8.14.3 ×5 apps) |

### API (Laravel)

| Constat | Preuve | Statut |
|---|---|---|
| i18n API : batches 1-3 + résiduels (#4191/#3237/#4292/#4310-#4314) | contrôlés FR codés en dur → 0 accented littéral dans les 168 contrôleurs | ✅ en cours par agents parallèles |
| OpenAPI : 634/723 routes couvertes, 89 gaps **tous allowlistés**, 0 drift nouveau | `check-openapi-route-coverage.py` | #3885 suit (89 routes restantes) |
| Prod : login 500 / auth/me 500 / demo-users 500 / health 10-18 s | probes curl 2026-08-16 | #4370 + #2646 + #3767 |

## Phase 2/3 — Contributions livrées par ce run

1. **PR #4389** — `fix(ci/mobile): main vert` (Closes #4388) : garde Plan 29
   ciblée `mobile-distribute-main.yml` + Gradle 8.14.3 ×5 apps. Issue #4388 créée
   avec critères d'acceptation spec-kit.
2. **PR #4437** — `fix(admin): SupportView 100 % localisée` (résiduel #4206) :
   namespace `support.*` (38 clés ×4), helper `t()` local, filtres computed.
3. **PR #4290 (contribution lot 2)** — `fix(web): pages modules localisées` :
   documents/comptabilite/marketing ×3 locales (contenu aligné sur le FR réel —
   « comptabilite » = paie, « marketing » = email/SMS/réseaux), pages branchées
   sur `getModulePageContent(locale)`, test garde renforcé (parité clés + anti-placeholder).
4. **Fermetures prouvées** : #3842, #3846.
5. **Issue #4388** créée (spec-kit : problème/impact/critères).

## Leçons du run

- Le pipeline CI reste saturé (30+ PR ouvertes) : les checks « pending » ne
  signifient pas un blocage ; les merges-bots des autres agents fusionnent
  toutes les ~75 s — rebaser souvent, garder les PR petites.
- `git checkout --theirs` + régénération via `sync-*.js` est le seul moyen sûr de
  résoudre les conflits `versions.json` (checksums calculés sur les fichiers mergés).
- Vérifier la parité des clés de traduction par script (node, deepKeys) AVANT le
  push — le test jest ne tourne pas sans node_modules local.
- Les scans « FR codé en dur » par regex ne capturent pas les textes de nœuds
  Vue (template) : utiliser `i18n-debt.js` (canonical) plutôt que grep pour mesurer.
