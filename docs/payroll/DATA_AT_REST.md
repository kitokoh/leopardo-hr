# Chiffrement des données au repos (paie)

> ⚠️ Document de pointe créé le 2026-08-17 — le contenu canonique vit sous
> `docs/security/DATA_AT_REST.md` (stratégie, exceptions, réévaluation).

## Synthèse

- **F-17** (issue #1595) : les colonnes paie sensibles sont chiffrées via des casts
  Eloquent `encrypted:array` sur `payment_documents`, `payment_batches`,
  `payment_items` et `payment_confirmations` (migrations de backfill idempotentes,
  format `json` → `text` pour recevoir les payloads chiffrés).
- **Exception documentée** : `base_salary`, `pay_slips` et `payroll_runs` restent
  en clair (agrégats/exports) — choix assumé et documenté dans
  `docs/security/DATA_AT_REST.md`, à réévaluer.
- **Garde** : les migrations de backfill résolvent le schéma via
  `current_schemas(false)` (même ordre que la résolution `DB::table`) pour ne plus
  dépendre du `current_schema()`.

## Références

- Stratégie complète + exceptions : `docs/security/DATA_AT_REST.md`
- Audit de suivi : `docs/audits/AUDIT_NEO_2026-08-09_passe2.md` (F-17)
- Durcissement S-3 : `docs/specifications/SPECS_AUDIT_AGENT_2026-08-10.md`
