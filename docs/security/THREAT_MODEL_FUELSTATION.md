# Threat model — verticale FuelStation (API, outbox, imports, alertes)

> **Issue :** [FUEL-020 #5814](https://github.com/kitokoh/leopardo-hr/issues/5814)
> **Registre :** `dev-hub/tools/security-threat-models.json` (surface `fuelstation`)
> **Garde CI :** `dev-hub/tools/check-security-threat-models.sh` (job Hygiene Guards)
> **Périmètre :** API `/fuel-station/*`, outbox événementielle (contrat Accounting FUEL-015), imports CSV (FUEL-018), notifications/alertes (FUEL-019)

## Contrôles appliqués (catalogue MAT-017)

| Contrôle | Application verticale FuelStation |
|---|---|
| `permissions` | RBAC deny-by-default (`FuelReferencePolicy`, `FuelCrmPolicy`, `FuelIncidentPolicy`, `FuelStockPolicy`) — manager uniquement pour l'administration ; pompiste limité à ses endpoints self-service `/fuel-station/me/*` ; isolation tenant fail-closed (404 cross-tenant via `company_id` + FK composites). |
| `secrets` | Descriptions REDACTED (incidents/tâches), contact chiffré au stockage (RGPD, `encrypted` cast), consentements chiffrés, aucune PII dans les payloads d'outbox (agrégats de synthèse uniquement), logs d'erreur sans payloads provider. |
| `replay` | Idempotence partout : livraisons (`external_id`), ventes (`external_id`), relevés (`idempotency_key`), visites (`external_id`), outbox (`idempotency_key` unique par tenant), imports (commit atomique), rapprochement (unique station/date), snapshots de reporting (unique station/type/période), alertes (dédup par type+clé). |
| `type_taille_mime` | Imports CSV : `mimes:csv,txt`, taille ≤ 2 Mo, lignes ≤ 5000, validation ligne par ligne ; pièces jointes d'incidents : métadonnées contrôlées (MIME allowlist, taille ≤ 10 Mo). |
| `audit` | Workflow incidents/tâches tracé dans `audit_logs` ; clôture de caisse → agrégat outbox + événement de domaine ; imports journalisés (`fuel_imports`) ; alertes journalisées (`fuel_alert_log`) ; export CSV audité. |
| `signatures` | Non applicable en l'état (aucun callback externe dans la verticale) — les callbacks de paiement restent hors périmètre FuelStation (POS, BC dédié). |

## Surfaces analysées

### 1. API de référentiel et d'exploitation (`/fuel-station/*`)
- **Menaces :** fuite cross-tenant (lecture/écriture sur la station d'un autre tenant), escalade pompiste → manager, exfiltration des données de contact (RGPD), abus d'endpoints d'import/export.
- **Contrôles :** Policies deny-by-default ; résolution tenant via `company_id` + FK composites `(x, company_id)` ; requests de validation stricte ; pagination bornée (≤ 100) ; rate limit dédié sur les écritures ; contact jamais exposé dans les réponses (payload sans `contact`/`contact_encrypted`).
- **Tests :** `FuelReferenceApiTest`, `FuelCrmApiTest`, `FuelStockApiTest`, `FuelIncidentApiTest` — couvrent 401/403/404 cross-tenant et négatifs.

### 2. Outbox du contrat Accounting (FUEL-015)
- **Menaces :** double consommation (écritures comptables dupliquées), consommation hors tenant (search_path incorrect), dead-letter silencieuse, injection via payload.
- **Contrôles :** `idempotency_key` unique par tenant ; `EnsureTenantContext` sur le job de dispatch ; retry avec backoff borné → dead-letter explicite (`last_error`) ; `schema_version` vérifié par le consommateur (payload inconnu → permanent → dead-letter).
- **Tests :** `FuelAccountingOutboxTest` — idempotence, retry transitoire, dead-letter permanent, isolation tenant.

### 3. Imports CSV (FUEL-018)
- **Menaces :** CSV malveillant (injection CSV/formules), déni de service (fichiers énormes), commit partiel (lignes valides persistées malgré erreurs), lecture d'imports d'un autre tenant.
- **Contrôles :** limites taille/lignes ; preview sans effet ; commit dans une transaction avec rollback logique au premier échec ; isolation tenant (404) ; statuts explicites (previewed/committing/committed/failed/cancelled).
- **Tests :** `FuelImportApiTest` — preview sans effet, erreurs ligne par ligne, rollback, rejeu idempotent, 403 pompiste, 404 cross-tenant.

### 4. Alertes et notifications (FUEL-019)
- **Menaces :** spam de notifications (boucle de rejeu), fuite d'informations opérationnelles vers le mauvais destinataire, PII dans les corps de notification.
- **Contrôles :** dédup par `fuel_alert_log` (type+clé unique par tenant) ; destinataires = managers du tenant uniquement ; corps sans PII (identifiants + libellés génériques) ; canaux contrôlés par les préférences de notification (catégorie `fuel`).
- **Tests :** `FuelAlertServiceTest` — détection, dédup, absence d'anomalies → aucune notification.

## Performance et observabilité (p95/p99)

- **Lecture** : les dashboards consomment des read models PRÉ-AGRÉGÉS (`fuel_report_snapshots`, FUEL-017) — aucune jointure profonde à la volée ; recalcul idempotent hors du chemin critique.
- **Écriture** : périmètre borné par station/période ; index tenant-first sur toutes les tables (`company_id` + colonnes de filtre) ; N+1 éliminé par eager loading (`with`, `withCount`) dans les listes.
- **Corrélation** : les jobs d'outbox et d'alertes s'exécutent via `EnsureTenantContext` (contexte tenant) ; `correlation_id` porté par la plateforme (QueueCorrelationServiceProvider).
- **Alertes queue/DB** : dead-letter d'outbox exposée (`status=failed` + `last_error`) ; alertes de supervision FuelStation documentées dans `RUNBOOK_PILOT_FUELSTATION.md` §7.
- **Limites** : rate limit dédié `fuel` sur les routes d'écriture (voir `AppServiceProvider::boot`) ; pagination ≤ 100 ; imports ≤ 5000 lignes / 2 Mo.

## Vérification continue

- Garde registre : `bash dev-hub/tools/check-security-threat-models.sh` (Hygiene Guards CI).
- Scans secrets : TruffleHog/secret-scan sur chaque PR (aucun secret/PII dans fixtures, logs, commits).
- Tests négatifs : chaque surface ci-dessus a son test Feature dédié dans `api/tests/Feature/Fuel/`.
