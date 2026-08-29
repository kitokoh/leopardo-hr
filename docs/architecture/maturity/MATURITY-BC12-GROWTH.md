# Rapport de maturité — BC-12 GROWTH

> **DEP-BC12 (issue #5888)** — Deep maturity, BC-12 Marketing & Growth.
> Audité le 2026-08-28 (main `228c382`). Agent propriétaire : 12.
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json` (BC-12).

## Périmètre

Segments, campagnes, templates, audiences et consentements marketing
tenant-scoped : `api/app/Modules/Growth` (DDD) + `api/app/Modules/Marketing`
(leads commerciaux). Le CRM commercial Leopardo reste séparé dans
Platform/Marketing (schéma public) — cf. ADR-CRM-002.

## Verdict par dimension

| # | Dimension | Verdict | Preuves / constats |
|---|---|---|---|
| D1 | Domaine | 🟢 PRÉSENT | Growth : DDD complet (Application/Actions+DTOs, Domain/Contracts+Exceptions, Interfaces). Marketing : contrôleurs leads + conversion + social. Vocabulaire : segments, campagnes, consentements (programme CRM V1 #5722→#5728 en cours chez d'autres agents). |
| D2 | Données | 🟢 PRÉSENT | Migrations tenant (growth, marketing leads), index cohérents, garde de schéma vert. |
| D3 | Tenant | 🟢 PRÉSENT | Scopage tenant prouvé (`GrowthTenantIsolationTest`, `GrowthPartnerTenantIsolationTest`), partner race testé (GrowthPartnerRaceTest). |
| D4 | API | 🟢 PRÉSENT | Contrôleurs Growth (admin + tenant), MarketingLead + Conversion + Social (posts/comptes), routes versionnées, OpenAPI couvert. |
| D5 | Autorisation | 🟢 PRÉSENT | Policies + gardes admin/manager, consentements préalables aux envois (programme CRM), séparation CRM commercial. |
| D6 | Transactions | 🟡 PARTIEL | Conversion lead → account/contact idempotente (MarketingLeadConversionTest) ; campagnes/segments V1 en cours (autres agents). |
| D7 | Asynchronisme | 🟡 PARTIEL | Envois marketing via canal global (BC-13) ; pas de jobs Growth dédiés sur main. |
| D8 | Sécurité | 🟢 PRÉSENT | Consentements + préférences (BC-12/CRM), PII gérée, pas de secret dans les fixtures. |
| D9 | Frontend | 🟡 PARTIEL | Espaces admin (campagnes) en cours (V1) ; pas d'UI tenant dédiée sur main. |
| D10 | Performance | 🟢 PRÉSENT | Listes paginées, index ; volume modéré. |
| D11 | Exploitation | 🟢 PRÉSENT | Logs structurés, audit des envois via canal comms. |
| D12 | Produit | 🟡 PARTIEL | Parcours lead → conversion → social testé (32 tests locaux verts) ; campagnes/segments en cours (V1). |

## Vérification locale (preuve)

```
php artisan test --filter="GrowthControllerTest|GrowthAdminControllerTest|GrowthTenantIsolationTest|MarketingLeadControllerTest|MarketingLeadConversionTest"
→ 32 passed (94 assertions)
```

## Recommandations (PR futures, non bloquantes)

1. **Coordination CRM V1** : segments/campagnes/consentements (#5722→#5728)
   sont pilotés par le programme CRM — les PRs de maturité BC-12 doivent
   attendre le merge du V1 puis verrouiller les invariants (consentement
   vérifié avant envoi, désinscription coupante).
2. **Contrat d'envoi** (D7) : formaliser l'interface vers BC-13 (événement
   versionné + idempotence) plutôt qu'appels directs.
3. **Golden journey** (D12) : seed pilote segment → campagne → envoi →
   consentement, avec test end-to-end.

## Non-régression

Aucun code de production modifié. Rapport + vérifications uniquement. Le CRM
commercial Leopardo (BC-01) n'est pas touché.
