# Audit des calculs de paie (issue #1874)

> Référentiel : `docs/payroll/AUDIT.md` — Issue **[AUDIT] #1874**
> Statut : actif depuis 2026-08-14.

## Objectif

Chaque calcul de paie — run (`PayrollCalculator::calculateRun()`) ou
simulation (`POST /api/v1/cotisation-simulation`, `POST /api/v1/payroll/simulate`)
— est **traçable** : on peut expliquer, reproduire et auditer un résultat à
partir d'un identifiant de corrélation unique, sans jamais exposer de
données individuelles ni de secrets.

## Modèle d'audit — `payroll_calculation_audits`

Table **immuable** (append-only, jamais modifiée ni supprimée par
l'application) : une ligne par corrélation.

| Champ | Type | Description |
|---|---|---|
| `id` | bigint PK | identifiant séquentiel |
| `correlation_id` | uuid, **unique** | joint les logs (`Log::withContext`), la réponse API et l'audit |
| `company_id` | uuid nullable | tenant propriétaire ; `NULL` pour les calculs plateforme (simulation super-admin sur règles nationales) |
| `actor_type` | `user` \| `job` | acteur du calcul (employé manager/super-admin authentifié, ou job asynchrone) |
| `actor_id` | bigint nullable | id de l'acteur (`employees.id` ou `super_admins.id`) |
| `country_code` | string(2) | pays des règles appliquées |
| `period_start` / `period_end` | date nullable | période du calcul (runs) |
| `rules_version` | string(32) nullable | empreinte stable des règles effectives (`rulesVersion()`) — reproduit le calcul à l'identique |
| `rules_identifier` | string(150) nullable | implémentation des règles pays (ex. `AlgeriaPayrollRules`) |
| `input_snapshot` | jsonb nullable | **paramètres agrégés non sensibles** (nombre d'employés, masse salariale brute, type de run…) |
| `result_snapshot` | jsonb nullable | **résultats agrégés** (net total, coût employeur total…) ; `NULL` en cas d'échec |
| `status` | string(32) | `success` \| `validation_error` \| `rule_missing` \| `provider_error` \| `fallback_forbidden` |
| `error_message` | text nullable | classe d'exception uniquement — jamais le message brut |
| `created_at` | timestamptz | horodatage (défaut `now()`) |

Index : `correlation_id` (unique), `(company_id, created_at)`.

### Statuts de résolution (observabilité / alerting)

| Statut | Signification | Déclencheurs actuels |
|---|---|---|
| `success` | calcul abouti, résultats agrégés persistés | run calculé, simulation réussie |
| `validation_error` | entrée/état invalide (pas une panne moteur) | `CountryRulesContextMismatchException` (simulation) ; futur : gardes métier |
| `rule_missing` | pays sans règles enregistrées (aucun fallback silencieux, cf. `CountryRulesResolver`) | `UnsupportedCountryRulesException` (run + simulation) |
| `provider_error` | échec moteur/stockage inattendu | tout autre `Throwable` dans `calculateRun()` / résolution de règles |
| `fallback_forbidden` | repli interdit par la politique | réservé (le résolveur n'autorise AUCUN fallback → `rule_missing` aujourd'hui) |

### Règle « zéro secret »

L'audit ne contient **jamais** :
- de salaires individuels (seulement des agrégats : nombre d'employés, masses
  totales) ;
- de tokens, mots de passe, données biométriques brutes, ni aucune donnée
  sensible d'employé ;
- de messages d'exception bruts (uniquement la classe, via `error_message`).

Le test dédié `PayrollAuditTest::test_audit_and_logs_contain_no_secrets` vérifie
l'absence de marqueurs (mot de passe, références biométriques) dans les
snapshots d'audit **et** dans tous les logs émis pendant un run + une
simulation (spy sur le logger, `Log::withContext` compris).

## Identifiants de corrélation

1. **Runs de paie** : `payroll_runs.correlation_id` (uuid, index unique,
   migration additive `2026_08_14_000016`). Généré et persisté à la première
   passe de `PayrollCalculator::calculateRun()` ; les runs historiques restent
   `NULL` tant qu'ils ne sont pas recalculés. Exposé dans la réponse API
   (`PayrollRunResource`).
2. **Simulations** : UUID généré par requête dans
   `CotisationSimulationController` et `PayrollSimulationController`, exposé
   dans `data.correlation_id` de la réponse.
3. **Logs** : `Log::withContext(['correlation_id' => ...])` posé en tête de
   `calculateRun()` et des deux contrôleurs de simulation (pattern minimal —
   aucun middleware dédié dans le repo) ; le service d'audit journalise en
   plus `payroll.audit.recorded` (correlation_id, statut, pays, période,
   acteur) sur chaque création d'audit.

## Endpoints

| Endpoint | RBAC | Portée |
|---|---|---|
| `GET /api/v1/payroll/audit` | manager principal/RH du tenant (`api.manager:principal,rh` + `PayrollAuditPolicy`) | audits de SA société (isolation stricte) |
| `GET /api/v1/payroll/audit/{correlationId}` | idem | 404 si l'audit n'appartient pas au tenant |
| `GET /api/v1/admin/payroll/audit` | platform_admin (`PayrollAuditPolicy`) | cross-tenant, filtre `company_id` optionnel |
| `GET /api/v1/admin/payroll/audit/{correlationId}` | idem | n'importe quel tenant |

La consultation est **lecture seule** : l'audit est immuable.

## Reproduction d'un calcul

Depuis un `correlation_id` :
1. `GET /api/v1/payroll/audit/{correlationId}` → pays, période,
   `rules_version`, `rules_identifier`, paramètres agrégés, résultats agrégés,
   statut ;
2. la version des règles (`rules_version`, empreinte de
   `AbstractCountryRules::rulesVersion()`) + les paramètres d'entrée agrégés
   permettent de rejouer le calcul avec les mêmes règles effectives
   (`forCompany()->asOf($period_start)`, cf. `CALCULATION_CONTRACT.md`) ;
3. pour un run, `payroll_runs.correlation_id` relie l'audit au run et à ses
   bulletins (consultables séparément, RBAC).

## Limites connues

- Les simulations ne conservent que des agrégats : un run simulé avec
  `slabs_override` ne stocke pas les tranches elles-mêmes (le statut et la
  version des règles restent tracés ; le détail du barème est rejouable via
  `rules_version`).
- Un run verrouillé (clôture comptable) refusé au recalcul ne produit pas
  d'entrée d'audit (garde F-11, hors périmètre « calcul »).
- `Log::withContext` est posé par les appelants (calcul/simulation) : en
  worker long-running, le contexte de corrélation persiste jusqu'au prochain
  calcul — chaque calcul écrase la valeur précédente.
- Le statut `fallback_forbidden` est prévu par le modèle mais non émis
  aujourd'hui (le résolveur refuse tout fallback → `rule_missing`).
