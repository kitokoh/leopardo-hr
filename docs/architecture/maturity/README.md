# Rapports de maturité par bounded context

> Point d'entrée unique des audits deep-maturity (issues DEP-*).
> Cadre : `docs/architecture/BOUNDED-CONTEXT-DEEP-MATURITY-BACKLOG.md` (12 dimensions).
> Registre : `dev-hub/governance/bounded-context-registry.json`.

## Statut par BC

| BC | Rapport | Issue | Statut | Remarques |
|---|---|---|---|---|
| BC-01 PLATFORM | [MATURITY-BC01-PLATFORM.md](./MATURITY-BC01-PLATFORM.md) | #5877 | ✅ audité + correctif | Audit features (traçabilité changements de modules) |
| BC-02 TENANT | [MATURITY-BC02-TENANT.md](./MATURITY-BC02-TENANT.md) | #5878 | ✅ audité + correctifs | Tests rotation contexte + **découverte R1** (fix #5967/#5968) |
| BC-03 IDENTITY | [MATURITY-BC03-IDENTITY.md](./MATURITY-BC03-IDENTITY.md) | #5879 | ✅ audité | 15 tests auth verts |
| BC-04 HR | [MATURITY-BC04-HR.md](./MATURITY-BC04-HR.md) | #5880 | ✅ audité | 21 tests HR verts |
| BC-05..08 | — (autres agents) | #5881..#5884 | 🔄 en cours | — |
| BC-09 EXPENSE | [MATURITY-BC09-EXPENSE.md](./MATURITY-BC09-EXPENSE.md) | #5885 | ✅ audité | 33 tests verts |
| BC-10 RECRUITMENT | [MATURITY-BC10-RECRUITMENT.md](./MATURITY-BC10-RECRUITMENT.md) | #5886 | ✅ audité | 24 tests verts |
| BC-11 CRM | [MATURITY-BC11-CRM.md](./MATURITY-BC11-CRM.md) | #5887 | ✅ audité | Programme V0/V1 en cours (autres agents) |
| BC-12 GROWTH | [MATURITY-BC12-GROWTH.md](./MATURITY-BC12-GROWTH.md) | #5888 | ✅ audité | 32 tests verts |
| BC-13..17 | — (autres agents) | #5889..#5893 | 🔄 en cours | — |
| BC-18 FIELD | [MATURITY-BC18-FIELD.md](./MATURITY-BC18-FIELD.md) | #5894 | ✅ audité | 22 tests verts |
| BC-19 DEVICE | [MATURITY-BC19-DEVICE.md](./MATURITY-BC19-DEVICE.md) | #5895 | ✅ audité | 22 tests verts |
| BC-20..22 | — (autres agents) | #5896..#5898 | 🔄 en cours | — |
| BC-23 AI | [MATURITY-BC23-AI.md](./MATURITY-BC23-AI.md) | #5899 | ✅ audité | 17 tests verts |

## Découvertes transverses

- **R1 — `absence_types.code`** : contrainte UNIQUE globale (schéma partagé) →
  seed des types d'absence standard cassé pour tous les tenants après le
  premier. **Fix livré** : issue #5967, PR #5968 (unicité `(company_id, code)`).
- **FuelStation absente de main** : 16 PRs FUEL-001..008 fermées sans merge →
  BC-15 non implémentable tant que le socle n'est pas livré (14 issues FUEL-009+
  bloquées). À arbitrer en comité.

## Méthode

Chaque audit couvre les 12 dimensions (D1 Domaine → D12 Produit) avec verdicts
et preuves (chemins de code, tests locaux exécutés). Les correctifs livrés sont
des PRs courtes, vérifiées localement (tests + PHPStan level 8 + Pint) et
soumises aux guards CI du dépôt.
