# Harness de fixtures & tests cross-tenant CRM (issue #5738)

- **Statut :** actif — socle de test du programme CRM client
- **Date :** 2026-08-28
- **Prépare :** toutes les issues CRM fonctionnelles (V0/V1)

## Contenu

| Fichier | Rôle |
|---|---|
| `CrmTenantFixture.php` | Fixtures déterministes : 2+ tenants, utilisateurs par rôle, entités CRM synthétiques (activées quand les tables V0 existent), rapport seed |
| `CrossTenantAssertions.php` | Assertions réutilisables d'isolation : TenantManager/`company_id` présents, lecture scopée, mutation scopée, relation indirecte, HTTP 404, cache, export, isolation 2 tenants |
| `Performance/` | **Volumes de charge séparés** des fixtures fonctionnelles — jamais chargés par les tests Feature (voir `Performance/README.md`) |

## Règles d'usage

1. **Réinitialisable** : tout est créé dans la transaction du test
   (`RefreshTenantDatabase`) — aucun état partagé entre tests.
2. **Zéro secret / zéro PII réelle** : données générées (faker) ; emails,
   téléphones, noms synthétiques ; pas de mot de passe réel en clair.
3. **Le harness détecte les absences** : `assertTenantManagerResolvable()`
   (TenantManager introuvable → échec) et `assertCompanyIdPresent()`
   (modèle sans `company_id` → échec).
4. **Identifiant d'un autre tenant → réponse sûre** : `assertCrossTenantHttp404()`
   (404, jamais 403/200), `assertScopedReadHidesCrossTenant()` (null).
5. **Entités CRM** : `seedCrmDataIfAvailable()` crée une ligne synthétique par
   table présente et retourne `{created, missing}`. Aujourd'hui (avant le
   socle V0) `created=[]` : le seed devient actif automatiquement au merge
   des migrations CRM. Si une table V0 impose des colonnes NOT NULL
   supplémentaires, étendre `insertSyntheticCrmRow()` aux colonnes réelles —
   l'échec bruyant est le comportement voulu (contrat de fixture explicite).

## Dimensions couvertes (critères d'acceptation)

| Dimension | Helper / test |
|---|---|
| Lecture | `assertScopedReadHidesCrossTenant` |
| Mutation | `assertCreatedRowIsTenantScoped` |
| Relation indirecte | `assertIndirectRelationDoesNotLeak` |
| Job | `TenantlessCrmProbeJob` / `TenantContextProbeJob` (rejet sans tenant, restauration) |
| Cache | `assertCacheTenantScoped` (clés `tenant:{id}:{key}`) |
| Export | `assertArtifactNameTenantScoped` (artefacts étiquetés tenant) |
| Webhook | `WebhookEndpoint` (modèle tenant-scopé réel) + HTTP 404 |
| Détection d'absence | `assertTenantManagerResolvable` / `assertCompanyIdPresent` |
| Perf séparée | `Performance/CrmBenchmarkDataset` (jamais en suite Feature) |
