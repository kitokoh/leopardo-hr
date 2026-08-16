# QA Session — Expert 18 : Audit 360° & Consolidation (2026-08-16)

> Session : 2026-08-16 | Agent : expert18 (audit 360° + intégration branches)
> Portée : audit multi-surface, consolidation du backlog, remise au vert CI/main.

---

## 1. Bilan de session

| Action | Détail |
|---|---|
| Branches fusionnées/nettoyées | 13 branches « content-merged » supprimées + 2 superseded (fix/4091-authme-ordinary-stage, fix/4096-oidc-der-spki) + 16 branches dupliquées/stales supprimées (dont docs/qa-expert-audit-360, fix/3248-videos-locale, fix/4124-lfs-validate-sync…) |
| PRs ouvertes à la prise en main | 14 PRs (4094→4157) toutes `mergeable=true`, en attente CI ; conflit AGENTS.md de #4113 résolu |
| Faux positif CI identifié | Garde mobile-workflow-contracts : scan leopardo_core (#4102) matche `mock_interceptor.dart` `'/attendance'` comme route interdite platform_admin → doublon #4212/#4164, PR canonique #4166 |
| P0 reproduit | #4151 : suite Feature rouge sur main depuis #3677 (create/update non-fillable) — reproduit localement (ManagerValidationTest 403 vs 404) ; correctif #4203 validé localement (SmartAttendance vert) |
| Issues créées (audit 360°) | 14 issues : #4183 (CTA plan=free → pilot payant), #4185 (checkout 100% FR), #4187 (Zkteco cross-tenant P1), #4189 (FleetView 401 super-admin), #4190 (56 routes OpenAPI manquantes), #4191 (messages API FR hardcodés), #4194 (#1650 chaînes FR mobile), #4196 (pages vitrine FR), #4197 (NumberFormat fr), #4199 (extractDataList), #4200 (tokens legacy admin), #4201 (SEO JSON-LD locale), #4202 (badge 20% vs 17%), #4205 (10 jobs sans failed()), #4206 (i18n admin) |
| PRs créées | #4233 : fix Zkteco push-users tenant-scope (Closes #4187) — PHPStan OK, Pint PASS, tests verts |

## 2. Constats live production (vérifiés par requêtes réelles)

| Endpoint | Résultat | Issue liée |
|---|---|---|
| `GET /api/v1/health` | 200, version **4.23.5** (main est bien plus avancé) | #2812/#3767 (prod figée) |
| `POST /api/v1/platform/auth/login` (admin@leopardo-rh.com/password123) | **401 INVALID_CREDENTIALS** | #2646/#3775 |
| `GET /api/v1/i18n/catalog` | **500** | #3882 |
| `POST /api/v1/trial/signup` (payload valide) | **500 après ~61 s** | #3879/#3259 |
| `GET /api/v1/supported-countries` | **404** | #2813 |
| `GET /api/v1/admin/dashboard/stats` | **404** | #2812 |
| `https://leopardo.vercel.app` | Site d'une **autre entreprise** (« Marketing Automatizado con IA ») | PA2-MKT-008 |

## 3. Audit 360° par surface (résumé)

### Web vitrine (`front/web`)
- **P1** : CTA « Commencer gratuitement » → `/checkout?plan=free` → alias silencieux `free→pilot` 29 €/mois payant (#4183) ; checkout + success 100 % FR hardcodé (#4185).
- **P2** : pages docs/modules/case-studies/contact/branding FR-only malgré metadata localisées (#4196).
- **P3** : JSON-LD locale via accept-language seul + canonical sans `?lang=` (#4201) ; badge « 20 % » vs réel 17 % (#4202) ; dark mode cassé sur case-studies.

### Admin dashboard (`front/admin-dashboard`)
- **P1** : FleetView/VehicleDetailModal appellent `/v1/vehicles*` tenant-guardés → 401 → **session super-admin détruite** (#4189).
- **P2/P3** : tokens legacy `rounded-lg bg-white shadow` dans ~10 fichiers + 4 MetricCard dupliqués (#4200) ; dizaines de vues FR hardcodé (#4206).

### API Laravel (`api/`)
- **P1** : `ZktecoController::pushUsers` lookup serial-only → cross-tenant (#4187, corrigé PR #4233).
- **P2** : 56 routes modules absentes d'openapi.yaml (#4190) ; ~20 contrôleurs messages FR hardcodés (#4191).
- **P3** : 10 jobs sans `failed()` (#4205, résiduel #3600).

### Mobile Flutter (`front/mobile_apps/`)
- **P2** : ~1650 chaînes FR hardcodées dans les 3 apps, l10n utilisé dans 7 fichiers seulement (#4194) ; 6 sites `NumberFormat.decimalPattern('fr')` (#4197, résiduel #3957).
- **P3** : 4 repositories ré-implémentent le déballage d'enveloppe au lieu d'`extractDataList`/`extractDataMap` (#4199).

## 4. Leçons / pièges

- **Le nom de branche EST le lock** : plusieurs agents ont créé des branches parallèles pour #4151 (3 branches, 4 PRs) et #4212/#4164 (2 issues, 2 PRs). Vérifier `branches?per_page=100 | grep <issue>` AVANT de créer une branche (pas seulement les PRs).
- **Les PRs « dirty » peuvent être un artefact GitHub** : 14/14 PRs montraient `mergeable_state=dirty` alors que le merge local était propre (branches déjà à jour avec main). Toujours simuler le merge localement avant de rebaser inutilement.
- **La prod est figée en 4.23.5** : les constats live (trial/signup 500, i18n/catalog 500, demo login KO) sont des bugs **déjà ouverts** (#3879, #3882, #2646) — la priorité ops reste le déblocage du déploiement (#3767/#3545).

## 5. Travail restant recommandé

1. Merger les 14 PRs en attente CI (monitor actif) puis re-vérifier main CI.
2. Implémenter les issues P1 : #4183, #4185, #4189 (après décision d'architecture), #4190 par lots.
3. Clôturer #4151 via #4203 (validé localement).
4. Suivre la clôture des doublons #4212/#4164 (PR canonique #4166).
