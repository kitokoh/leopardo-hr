# Rapport de maturité — BC-19 DEVICE

> **DEP-BC19 (issue #5895)** — Deep maturity, BC-19 Devices, Cameras & Edge.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 19.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-19).

## Périmètre

Appareils, kiosques, biométrie, synchronisation edge et politiques device :
`api/app/Modules/Cameras` (DDD) + `api/app/Modules/EdgeSync` (synchronisation
edge, conflits), routeurs `cameras.php`, jobs edge, tokens device/kiosk.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | DDD complet dans les deux modules (Application/Domain/Infrastructure/Interfaces). Vocabulaire : caméras, tokens d'accès, flux RTSP, nœuds edge, sync, conflits. |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant + edge (`api/database/migrations/edge`), index cohérents. |
| D3 | Tenant | 🟢 PRÉSENT | Caméras/nœuds scopés tenant ; device tokens portés par company ; `EdgeMultiTenantIsolationTest` présent. |
| D4 | API | 🟢 PRÉSENT | 6 contrôleurs Cameras + EdgeSync (tokens, permissions, viewer public borné, sync/download edge), routes versionnées, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | CameraPermissionController, tokens signés/à durée de vie (RTSP security testé), viewer public restreint (accès par token). |
| D6 | Transactions | 🟡 PARTIEL | Sync edge avec résolution de conflits (EdgeConflictResolutionTest) ; invariants de concurrence edge partiellement documentés. |
| D7 | Asynchronisme | 🟢 PRÉSENT | Jobs edge/sync + téléchargements asynchrones ; files gérées (MAT-008 en cours chez un autre agent). |
| D8 | Sécurité | 🟢 PRÉSENT | **Surface sensible** : tokens RTSP à durée de vie, signatures vérifiées (CameraStreamTokenVerifyTest), pas de secret en clair. |
| D9 | Frontend | 🟢 PRÉSENT | Kiosk web (front/zkteco-kiosk), viewer caméras, apps mobile. |
| D10 | Performance | 🟢 PRÉSENT | Streaming via tokens courts, pagination, index. |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, supervision edge (queue), runbooks device/kiosk. |
| D12 | Produit | 🟡 PARTIEL | Parcours caméra → token → viewer + sync edge → conflit → résolution testés (22 tests locaux verts) ; pas de golden journey device end-to-end ni seed pilote. |

## Vérification locale (preuve)

```
php artisan test --filter="CamerasCrudTest|CameraRtspSecurityTest|CameraAccessTokensTest|EdgeConflictResolutionTest"
→ 22 passed (83 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Politique device versionnée** (D5) : formaliser une matrice
   appareil/kiosque → permissions (alignement avec la biométrie ZKTeco et le
   kiosque) avec tests de révocation immédiate.
2. **Invariants de sync edge** (D6) : documenter et tester les règles de
   conflit (horodatage, autorité) comme contrat de BC-14 (INTEGRATION).
3. **Golden journey** (D12) : seed pilote device + test end-to-end
   enrôlement caméra → token → viewer → révocation.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement.
