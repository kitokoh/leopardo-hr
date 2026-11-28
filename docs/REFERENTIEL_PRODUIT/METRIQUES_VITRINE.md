# Registre des métriques vitrine (datées)

> **Règle** : aucun chiffre public (site, README, gh-pages, stores, contenus GTM) sans une
> ligne **datée** dans ce registre. Toute métrique publiée doit être mesurable/reproductible
> avec la méthode indiquée. Les valeurs sont vérifiées sur `main` à la date de mesure.

## Mesures au 2026-11-28

| Métrique | Valeur | Mesuré le | Source / méthode de mesure |
|---|---|---|---|
| Modules DDD (`api/app/Modules/*`) | 42 | 2026-11-28 | `ls -d api/app/Modules/*/ \| wc -l` |
| Chemins dans `api/openapi.yaml` | 1 984 | 2026-11-28 | `grep -c '^  /' api/openapi.yaml` |
| Fichiers de tests backend | 1 537 (`*Test.php`) | 2026-11-28 | `find api/tests -name '*Test.php' \| wc -l` |
| Fichiers de tests Dart | 114 (`*_test.dart`) | 2026-11-28 | `find front/mobile_apps -name '*_test.dart' \| wc -l` |
| Apps mobiles Flutter | 11 apps + `leopardo_core` (12 packages CI) | 2026-11-28 | `front/mobile_apps/README.md` (canonique) ; `ls -d front/mobile_apps/leopardo_*/` renvoie 21 dossiers, dont 9 apps hors périmètre de lancement |
| Workflows CI | 89 | 2026-11-28 | `ls .github/workflows/*.yml \| wc -l` |
| Documentation Markdown | 1 289 fichiers `.md` | 2026-11-28 | `find docs -name '*.md' \| wc -l` |
| Pays couverts (règles de paie) | 21 | 2026-11-28 | `api/app/Support/CountryDefaults.php` (codes pays distincts) — **1 seul en production** |
| Essai gratuit | 14 jours | 2026-11-28 | `api/config/billing.php` (`trial_days`, défaut 14) |

> **Écart à connaître** : `ls -d front/mobile_apps/leopardo_*/` renvoie **21** dossiers,
> alors que la source canonique (`front/mobile_apps/README.md`) décrit **11 apps +
> `leopardo_core`**. Les 9 dossiers en trop (`leopardo_pos_retail`,
> `leopardo_pos_restaurant`, `leopardo_travel_app`, `leopardo_retail_app`,
> `leopardo_restaurant_app`, `leopardo_hospitality_app`, `leopardo_pharmacy_app`,
> `leopardo_mobility_app`, `leopardo_careers_app`, `leopardo_surface`,
> `leopardo_trade_app`) ne sont pas dans la matrice de distribution. **Publier
> « 11 apps »**, jamais 21 : c'est le nombre d'apps réellement distribuées.

## Mesures au 2026-09-18 (historique)
| Modules activables (catalogue) | 39 | 2026-11-28 | `docs/GOTO_MARKET/PACK_LANCEMENT/04_PAGE_VENTE_PAIE_POINTAGE_DZ.md` §6 |

| Métrique | Valeur | Mesuré le | Source / méthode de mesure |
|---|---|---|---|
| Modules DDD (`api/app/Modules/*`) | 27 | 2026-09-18 | `ls -d api/app/Modules/*/ \| wc -l` |
| Chemins dans `api/openapi.yaml` | 798 | 2026-09-18 | `grep -c '^  /' api/openapi.yaml` |
| Fichiers de tests backend | 1045 (`*Test.php`) | 2026-09-18 | `find api/tests -name '*Test.php' \| wc -l` |
| Fichiers de tests Dart | 58 (`*_test.dart`) | 2026-09-18 | `find front/mobile_apps -name '*_test.dart' \| wc -l` |
| Apps mobiles Flutter | 7 apps + `leopardo_core` | 2026-09-18 | `ls -d front/mobile_apps/leopardo_*/` (canonique : `front/mobile_apps/README.md`) |
| Workflows CI | 69 | 2026-09-18 | `ls .github/workflows/*.yml \| wc -l` |
| Documentation Markdown | ≈ 1002 fichiers `.md` | 2026-09-18 | `find docs -name '*.md' \| wc -l` |
| Pays supportés (API) | 21 | 2026-09-09 | `GET /api/v1/supported-countries` (instance de référence — non re-mesuré au 2026-09-18) |

## Mesures au 2026-09-09 (commit `015f16c0e`) — historique

| Métrique | Valeur | Mesuré le | Source / méthode de mesure |
|---|---|---|---|
| Modules DDD (`api/app/Modules/*`) | 27 | 2026-09-09 | `ls -d api/app/Modules/*/ \| wc -l` |
| Bounded contexts au registre | 28 (BC-01..BC-28) | 2026-09-09 | `dev-hub/governance/bounded-context-registry.json` |
| Chemins dans `api/openapi.yaml` | 738 | 2026-09-09 | `grep -c '^  /' api/openapi.yaml` |
| Apps mobiles Flutter | 7 apps + `leopardo_core` | 2026-09-09 | `ls -d front/mobile_apps/leopardo_*/` (canonique : `front/mobile_apps/README.md`) |
| Fichiers de tests backend | 990 (`*Test.php`) | 2026-09-09 | `find api/tests -name '*Test.php' \| wc -l` (≈4 000 cas, cf. `docs/testing/`) |
| Fichiers de tests Dart | 58 (`*_test.dart`) | 2026-09-09 | `find front/mobile_apps -name '*_test.dart' \| wc -l` |
| Pays supportés (API) | 21 | 2026-09-09 | `GET /api/v1/supported-countries` (instance de référence) |

## Métriques à ne PAS publier sans mesure

- « 18 modules » (périmé : 42 mesurés au 2026-11-28) — **interdit sans re-mesure**.
- « 700+ endpoints » — remplacer par la valeur mesurée (1 984 au 2026-11-28) ou « 700+ » avec la date.
- « 1 900+ tests » — périmé (1 537 fichiers mesurés au 2026-11-28) — re-mesurer avant publication.
- « 5 apps Flutter » — périmé (11 apps + core).
- « 7 apps Flutter » — périmé (11 apps + core, cf. `front/mobile_apps/README.md`).
- « 21 apps Flutter » — **interdit** : 21 est le nombre de dossiers, pas d'apps distribuées.
- « 21 pays en production » — **interdit** : 21 pays couverts, **1 seul en production**.
- Toute métrique sans date de mesure → ne pas publier (cf. `docs/REFERENTIEL_PRODUIT/STATUTS.md` pour les statuts « live »).

## Liens

- Surfaces publiques à auditer : `README.md` (badges), `site/gh-pages/index.html`, vitrines Next.js (`front/web/src/`), `docs/REFERENTIEL_PRODUIT/APV.md` (wording).
- Issu de la moisson 2026-09-09 (issue #7081).
