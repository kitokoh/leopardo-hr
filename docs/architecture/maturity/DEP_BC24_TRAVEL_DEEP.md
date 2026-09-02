# DEP-BC24 — Maturité PROFONDE TravelAgency (issue #6275)

> **Statut : livré (lot 3c).** Synthèse de maturité du BC-24 après livraison
> des épics 1xx→10xx (back-end, intégrations, qualité, pilote).
> Référence : `docs/architecture/maturity/DEP_BC24_TRAVEL_MATURITY.md`.

## 1. Vue d'ensemble

| Périmètre | État |
|---|---|
| Fondations & gouvernance (1xx) | ✅ livré (PR #6127) |
| Schéma & domaine (2xx) | ✅ livré (PR #6127) |
| API back-office (3xx) | ✅ livré (PR #6127 + #6330) |
| Vente en ligne, paiements, billets (4xx) | ✅ livré (PR #6273 + #6330) |
| Rapports & exports (5xx) | ✅ livré (PR #6273) |
| UI admin web (6xx) | ✅ livré (PR #6316, agent admin-screens) |
| Mobile & portail (7xx) | 🟡 contrats backend livrés (703/704) ; apps Flutter à build |
| Extensions métier (8xx) | ✅ complet (lot 1 + lot 3a) |
| Contenu & monétisation (9xx) | ✅ complet (lot 2) |
| Boutique publique, import, qualité, pilote (10xx) | ✅ complet (lots 3a/3b) |

## 2. Métriques de qualité

- **Tests** : 309+ tests Feature Travel verts (784+ assertions), zéro
  régression sur la suite existante.
- **PHPStan modules** : 0 erreur ; **Pint** : propre.
- **OpenAPI** : 1009+ opérations, coverage routes→spec **0 drift**,
  Redocly **0 erreur**.
- **Événements outbox** : 14 types `travel.*.v1` documentés, multi-consommation
  (webhooks, notifications, push agents, Accounting).
- **PII** : chiffrement (`SensitiveDataEncryptor`), hash, jamais exposée.

## 3. Dimensions de maturité (12, spec §11)

| Dimension | État | Preuve |
|---|---|---|
| DDD & architecture | ✅ | Actions applicatives, Policies, outbox, registre BC |
| Tests & isolation | ✅ | 309 tests, cross-tenant 401/403/404, RBAC travel.* |
| API & contrats | ✅ | OpenAPI + Postman + guide partenaires |
| Sécurité & RGPD | ✅ | Audit livré (`AUDIT_SECURITE_RGPD_TRAVEL.md`) |
| Observabilité | ✅ | Outbox + dead-letter, jobs planifiés, structured logs |
| Performance | 🟡 | Budgets p95 à valider au pilote (gate MAT-014) |
| Golden journey | ✅ | GJ-TRAVEL-01 + tunnel public E2E |
| Pilote & ops | ✅ | Runbook, recette UAT, pilot gates, pilot-check |

## 4. Risques résiduels avant GO pilote

1. Apps Flutter (701) et portail client (702) : build à réaliser dans la
   chaîne mobile.
2. Budgets p95 (performance) : à mesurer sur le tenant pilote.
3. Import legacy (1003/1004) : nécessite un **dump de production** réel.
4. Merge `main` de la chaîne travel (PRs #6127/#6273 + lots) : prérequis
   de la mise en production — verrouillé par les gates CI.
