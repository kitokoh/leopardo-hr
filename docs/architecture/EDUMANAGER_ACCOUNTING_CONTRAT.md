# EduManager ↔ Accounting — Contrat d'intégration (EDU-016)

> **Issue :** #5832 (EDU-016) — « Relier inscriptions/frais à Accounting via
> contrat, sans reproduire la comptabilité. »

## Principe

**EduManager ne crée jamais d'écriture comptable.** La comptabilité appartient
au bounded context Accounting (BC-08) ; EduManager expose des *read models*
tenant-scopés que Accounting consomme via son propre flux (import, contrat
`external_reference`). Cette séparation est garantie par la garde d'isolation
de modules (#5584) : aucun import croisé EduManager ↔ Accounting.

## Surface exposée par EduManager

### Table `edu_fees` (tenant-scopée, `company_id` NON nullable)

| Colonne | Rôle pour Accounting |
|---|---|
| `external_reference` | **Clé de rejeu** — `UNIQUE (company_id, external_reference)` : un import Accounting ne crée jamais deux écritures pour le même frais |
| `student_id` / `admission_id` | Références métier (FK composites anti cross-tenant) |
| `amount` | Montant TTC en unités décimales (CHECK `amount > 0`) |
| `due_date` | Échéance |
| `status` | `pending \| paid \| waived \| cancelled` (CHECK) |
| `payment_reference` / `paid_at` | Traçage du règlement (posé par EduManager, lu par Accounting) |

### Cycle de vie (EduManager, idempotent)
- `create` : `external_reference` unique par tenant → rejeu sûr (200 avec le
  frais existant).
- `markPaid` : terminal (EDU_FEE_TERMINAL après paiement/remise/annulation),
  tracé `edu.fee.paid` (AuditLog).
- Audit : `edu.fee.created` / `edu.fee.paid` — journal non altérable.

## Ce que Accounting doit faire (consommateur)

1. Lire `edu_fees` filtré par `company_id` + `status = 'pending'` (ou delta
   `updated_at`).
2. Dédupliquer via `external_reference` (UNIQUE) — jamais de doublon.
3. Poster ses propres écritures (journaux/lettrage) — EduManager n'écrit pas
   dans les tables Accounting.
4. Retourner (optionnel) son identifiant d'écriture dans `payment_reference`
   pour traçage de bout en bout.

## Non-régression
- Test `EduFeeServiceTest::test_fees_never_create_accounting_entries` :
  aucune table comptable touchée par le cycle de vie d'un frais.
- Garde CI d'isolation de modules (#5584) : aucun `use` croisé EduManager ↔
  Accounting.
