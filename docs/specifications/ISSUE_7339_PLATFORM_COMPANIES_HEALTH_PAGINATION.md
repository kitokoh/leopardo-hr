# Spécification — Portefeuille clients : pagination de `GET /platform/companies/health` (#7339)

- **Statut :** implémentée (branche `perf/7339-companies-health-pagination`).
- **BC :** BC-01 PLATFORM (super-admin), surface API `api/app/Modules/Platform/`.
- **Issues :** #7339 (ce lot) — suite de #7302 (livré par la PR #7340, mergée `c3cc25c`).
- **Consommateurs :** `front/admin-dashboard` (`CompaniesView.vue`, `DashboardView.vue`,
  `SubscriptionsView.vue`) et l'app mobile `front/mobile_apps/leopardo_platform_admin`.

---

## 1. Constat — ce que #7340 avait déjà réglé

L'issue #7339 a été écrite sur les mesures du **2026-09-13** (`GET /platform/companies/health`
= 24,6 s pour 44 sociétés, ≈ 560 ms/société) ; la PR **#7340** (`c3cc25c`) a été mergée
**après** et a supprimé la cause racine du coût :

| Ce que #7340 a livré | Preuve |
|---|---|
| Suppression du N+1 : agrégats **groupés** (`group by company_id`) employés, pointages, onboarding, plans, dernier pointage | `api/app/Modules/Platform/Infrastructure/Services/PlatformCompanyHealthService.php` (`employeesMany()`, `attendanceMany()`, `attendanceExistsMany()`, `plansMany()`, `OnboardingProgressReader::readMany()`) |
| Anomalies groupées : `summarizeMany()` = 2 requêtes au lieu de 3 par société, chaîne de détection extraite (`items()`/`summarizeItems()`) | `api/app/Modules/Attendance/Infrastructure/Services/AttendanceAnomalyService.php` |
| Mesure : **674 requêtes → 12** pour 45 sociétés, indépendant du nombre de tenants ; 0 requête sur un appel servi par le cache | message de commit `c3cc25c`, `CHANGELOG.md` (entrée #7302) |
| Cache 60 s documenté (`PORTFOLIO_CACHE_TTL_SECONDS`, invalidation **temporelle**) + `?refresh=1` | `PlatformCompanyHealthService::PORTFOLIO_CACHE_TTL_SECONDS`, `PlatformCompanyHealthController::index()` |
| Test de non-régression du nombre de requêtes | `api/tests/Feature/PlatformCompanyHealthApiTest.php` |

**Conséquence :** le N+1 de #7339 est **déjà supprimé** ; ce lot ne le refait pas. Il ne
traite que ce qui restait réellement ouvert.

## 2. Périmètre restant (traité ici)

1. **Le portefeuille n'était pas paginé.** L'endpoint n'acceptait que `limit`
   (défaut 50, **plafonné à 100**) : au-delà de 100 sociétés, le reste du portefeuille
   était **inatteignable**, et la réponse n'exposait aucune métadonnée de pagination.
   Un « score unitaire par société » (`GET /platform/companies/{company}/health`) existe
   déjà pour le détail, mais rien ne permettait de parcourir le portefeuille.
2. **Aucun test de contrat de pagination** n'existait, et la borne de coût de #7302
   reposait sur un comptage de requêtes **faussé** (voir §5).
3. **Le défaut de pagination de `GET /platform/companies` (20) était implicite** :
   un client qui demande « toutes les sociétés » recevait 20 lignes sans que rien ne
   l'indique dans la réponse.

## 3. Décision — contrat d'API

### 3.1 Requête

`GET /api/v1/platform/companies/health` (guard `super_admin_api`, inchangé)

| Paramètre | Type | Défaut | Bornage | Note |
|---|---|---|---|---|
| `page` | entier | `1` | `max(1, …)` | nouveau (#7339) |
| `per_page` | entier | `50` | `1..100` (`PORTFOLIO_MAX_PER_PAGE`) | nouveau (#7339) |
| `limit` | entier | — | `1..100` | **rétro-compatibilité** #7302 : alias de `per_page`, `per_page` l'emporte s'il est fourni |
| `refresh` | booléen | `false` | — | inchangé : purge le cache **de cette page** avant calcul |

Le bornage est appliqué par le service (`normalizePerPage()`), pas par la requête : un
`per_page` hors bornes est **ramené** dans l'intervalle (jamais un 422), ce qui préserve
strictement le contrat tolérant de #7302 pour `limit`.

### 3.2 Réponse (rétro-compatible : `meta` est additif)

```json
{
  "data": {
    "summary": { "companies": 50, "active_companies": 41, "mrr": 4821.0,
                 "risk": { "high": 3, "medium": 6, "low": 41 } },
    "items": [ { "company": { "id": "…" }, "plan": {…}, "subscription": {…},
                 "health_score": 78, "risk_level": "low", "next_action": {…} } ]
  },
  "meta": { "current_page": 1, "per_page": 50, "total": 137, "last_page": 3,
            "from": 1, "to": 50 }
}
```

- **`data.items` / `data.summary` : forme inchangée** — les trois vues du back-office
  (`CompaniesView`, `DashboardView`, `SubscriptionsView`) continuent de lire
  `data.items` et `data.summary` sans modification.
- **`meta` est nouveau et additif.** `from`/`to` valent `null` sur une page vide (contrat
  Laravel), et une page hors bornes renvoie `200` avec `items: []` — jamais un `500`.
- **`data.summary` décrit la page, pas tout le portefeuille.** C'était **déjà le cas**
  avant (#7302 bornait la synthèse à `limit` : au-delà de 50 sociétés, elle ne décrivait
  déjà plus tout le portefeuille). Le total réel est exposé séparément dans
  `meta.total`, **sans** obliger à scorer les sociétés hors page — ce qui réintroduirait
  exactement le coût linéaire que #7302 a supprimé. Un client qui veut une synthèse
  globale parcourt les pages.

### 3.3 Ordre de pagination

`ORDER BY created_at DESC, id DESC`. `created_at` seul n'est pas un ordre **total** :
des sociétés créées dans la même seconde (horloge figée en test, provisioning en rafale)
seraient ordonnées de façon instable et deux pages consécutives pourraient **répéter ou
sauter** une société. `id` sert de départage.

### 3.4 Coût

Les agrégats groupés sont restreints aux identifiants de la page
(`whereIn('company_id', …)`) : le nombre de requêtes **et** les lignes scannées ne
dépendent que de `per_page`, jamais du nombre de sociétés hors page. Une requête
supplémentaire (`Company::count()`) fournit `meta.total`.

### 3.5 Cache (TTL, invalidation) — inchangé depuis #7302

- **TTL : 60 s** (`PORTFOLIO_CACHE_TTL_SECONDS`), donnée **dérivée** (agrégats de
  pointage, anomalies, progression) : pas besoin d'exactitude à la seconde.
- **Invalidation : temporelle uniquement.** Aucune invalidation à l'écriture (une fiche
  société modifiée peut rester jusqu'à 60 s dans le portefeuille) — choix assumé : le
  portefeuille est un tableau de bord de supervision, pas une source transactionnelle, et
  invalider à l'écriture exigerait de câbler chaque écriture de chaque module.
- **Clé de cache par page** : `platform.companies.health.page.{page}.per_page.{per_page}`.
  Une purge (`?refresh=1`, bouton « Actualiser ») ne vise **que** la page demandée.
- **Alternative écartée** : précalcul par job planifié (le scheduler existe dans
  `render.yaml`). Non retenue : elle ajoute un worker et un état intermédiaire pour un
  gain nul sur le temps de réponse *à chaud* (déjà < 1 s servi par cache), alors que le
  coût de calcul a déjà été réduit de 98 % par #7340 (#7302).

### 3.6 Défaut de `GET /platform/companies` (annuaire)

L'annuaire est paginé à **20 par défaut** (`PlatformCompanyController::index()`,
`per_page` max 100) et c'était **implicite**. La réponse expose désormais
`meta.per_page` (et `meta.current_page`/`meta.total`/`meta.last_page` déjà présents) :
un client qui demande « toutes les sociétés » peut **détecter** qu'il n'en reçoit que 20
et paginer. `front/admin-dashboard` demande déjà explicitement `?per_page=100`.

## 4. Critères d'acceptation (#7339)

| Critère | Sort |
|---|---|
| `GET /platform/companies/health` < 2 s pour 50 sociétés | **Atteint, mesuré indirectement.** #7340 a ramené le coût de 674 → 12 requêtes pour 45 sociétés (mesure locale : 2 129 ms → une poignée de requêtes groupées). À 40 ms l'aller-retour distant, 12 requêtes ≈ 0,5 s ; le plafond de 2 s est donc structurellement respecté. **Pas de mesure directe en production** dans ce lot (cf. §5). |
| Le coût ne dépend plus des sociétés hors page | **Atteint.** Agrégats restreints aux identifiants de la page ; `test_portfolio_query_count_does_not_grow_with_company_count` compare 5 sociétés (aucune hors page) et 15 sociétés (10 hors page) sur une page de 5 : le nombre de requêtes ne doit pas augmenter de plus de 2. |
| Cache/précalcul documenté (TTL, invalidation) | **Atteint** (§3.5 : TTL 60 s, invalidation temporelle, clé par page, `?refresh=1`) + cette spécification. |
| Test de contrat sur le nombre de requêtes | **Atteint.** `test_portfolio_query_count_does_not_grow_with_company_count` (borne de coût, comptage réparé — §5) + `test_portfolio_exposes_page_metadata_and_disjoint_pages` (contrat de pagination) + `test_portfolio_defaults_and_legacy_limit_param_stay_compatible` (rétro-compatibilité). |

## 5. Vérifications (et ce qui n'a pas pu l'être)

Vérifié localement : `php -l` sur les 5 fichiers PHP touchés ; gardes de gouvernance
(`check-i18n-diff.js`, `check-hardcoded-accented-messages.sh`, `check-module-isolation.sh`).

**Corrigé au passage (défaut de test, pas de production)** : le helper
`countPortfolioQueries()` de #7302 utilisait `DB::listen()`. Or un écouteur `DB::listen()`
n'est **jamais retiré** : dans un même test, la **deuxième** mesure comptait chaque requête
**deux fois** — la borne de coût devenait un artefact de comptage. Le helper passe au
journal de requêtes de la connexion (`flushQueryLog()` + `enableQueryLog()` +
`getQueryLog()`), comme le fait déjà `AccountingPerformanceTest`.

**Non vérifié dans cet environnement** : la CI est le seul vérificateur exécutable —
`phpstan` (level 8), `pint`, et `phpunit` exigent `api/vendor` (absent) et PostgreSQL
(absents localement) ; l'admin Vue ne peut pas être bâti/linté (`node_modules` absents).
Le raisonnement de coût repose sur les mesures de #7340 (requêtes), pas sur un
chronométrage de production.

## 6. Risques

- **Synthèse par page** : un consommateur qui lit `data.summary` **sans** paginer voit la
  synthèse de la page 1 (50 sociétés les plus récentes). C'est le comportement d'avant
  (#7302), mais il devient visible dès que le portefeuille dépasse `per_page`. Le total
  réel est dans `meta.total`; une synthèse globale est un choix produit (scorer tout le
  portefeuille = coût linéaire) qui n'est pas tranché ici.
- **Back-office au-delà de `per_page`** : `CompaniesView` affiche l'annuaire sur 100 lignes
  (`?per_page=100`) mais ne charge les scores que sur la page 1 (défaut 50). Entre 51 et
  100 sociétés, des lignes resteront sans score (« — »). Correctif : consommer `meta` et
  itérer les pages — suivi séparément, hors de ce lot (surface Vue non vérifiable ici).
- **Cache par page** : chaque couple (page, per_page) a sa propre entrée ; un client qui
  parcourt tout le portefeuille chauffe autant d'entrées. Borné par le nombre de pages et
  le TTL de 60 s.
