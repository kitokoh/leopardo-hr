# Issue #5723 — Segments CRM tenant simples

> Spec d'implémentation — CRM client (API tenant). Statut : livrée (PR dédiée).

## Objectif

Permettre de définir, versionner et peupler des segments de contacts CRM
tenant-scopés, **sans jamais exposer de SQL utilisateur** : la définition est
une grammaire JSONB allowlistée, chaque version est figée (snapshot
reproductible), le rebuild est audité et le consentement est respecté.

## Décisions de conception

| Sujet | Décision |
|---|---|
| Grammaire | `{"operator":"and\|or","conditions":[{"field","op","value"}]}` — `SegmentDefinitionValidator` : champs allowlistés (`crm_contacts.status/country/source/type/created_at/account_id`, `crm_consents.has_consent`), opérateurs par champ, 1..20 conditions, `in` ≤ 50 valeurs, clés parasites et fragments SQL refusés |
| Versionnement | `crm_segments.definition` = version courante ; `crm_segment_versions` fige chaque version acceptée (changement horodaté) — rejouable |
| Appartenance | `crm_segment_members` tenant-scopée, source `computed` (rebuild) ou `manual` (ajout explicite) ; le rebuild remplace les computed, préserve les manual |
| Rebuild | `SegmentService::rebuild()` → `SegmentContactSourceInterface` (contrat) — implémentation par défaut `CrmContactSegmentSource` (tenant-scopée, valeurs liées, garde `schemaTableExists` : tables CRM absentes → 422 explicite, jamais de crash) ; source inconnue à la CI = fake injecté en test |
| Consentement | La condition `has_consent` n'inclut que les contacts avec consentement `granted` actif (canal, finalité marketing) — les segments respectent le consentement et la rétention (voir `docs/security/CRM_SEGMENTS.md`) |
| Isolation tenant | `company_id` uuid NON nullable + trait `BelongsToCompany` ; pas de FK vers `crm_contacts` (#5708) |
| Audit | `segment.created` / `segment.updated` / `segment.toggled` / `segment.rebuilt` / `segment.member_added` / `segment.member_removed` / `segment.deleted` dans `audit_logs` |
| RBAC | Lecture : tout manager ; écritures / rebuild : `principal` / `marketing` (middleware + Policy `CrmSegmentPolicy`) |

## Périmètre

- Migration tenant `2026_08_28_000006_5723_create_crm_segments_tables.php`
- `api/app/Modules/CRM/` : `Domain/Support/SegmentDefinitionValidator`,
  `Domain/Contracts/SegmentContactSourceInterface`, `Domain/Models/{CrmSegment,
  CrmSegmentMember, CrmSegmentVersion}`, `Domain/Enums/SegmentOperator`,
  `Application/Services/SegmentService`,
  `Infrastructure/Services/CrmContactSegmentSource`,
  `Interfaces/Api/V1/Controllers/CrmSegmentController` + `Requests/`
- `api/app/Policies/CrmSegmentPolicy.php`, routes `/api/v1/crm/segments*`
- Tests : `tests/Unit/CRM/SegmentDefinitionValidatorTest.php` (13) +
  `tests/Feature/CRM/CrmSegmentTest.php` (12)

## Hors périmètre

- Interface web des segments (#5715), recherche (#5719), dashboard (#5721).
- Tables `crm_contacts` / `crm_consents` (issues #5708 / #5722) : la source
  devient pleinement opérationnelle à leur merge — aucun changement de code.
