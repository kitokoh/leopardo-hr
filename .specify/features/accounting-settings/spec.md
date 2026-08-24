# Spec — Paramétrage comptable par entreprise (issue #5232)

- **Module** : `accounting` — périmètre : `api/app/Modules/Accounting/**` + routes module + `front/admin-dashboard` (vue settings) + OpenAPI/CHANGELOG.
- **Source** : `docs/architecture/COMPTABILITE_CONCEPTION.md` §4 (AccountingSettings) + §5 (endpoints, RBAC).
- **Statut** : implémentée — PR `mod/accounting/5232-settings`.

## 1. Objectif

Donner à chaque entreprise un paramétrage comptable : taux de TVA (défauts par
pays, modifiables), mentions légales, devise, séries de numérotation par type
de document, langue des documents et conditions de paiement par défaut.

## 2. Contrat API

`GET /api/v1/accounting/settings` — retourne la ligne persistée, ou les défauts
dérivés du pays de l'entreprise (registre `CountryDefaults` + TVA standard par
pays) si aucune ligne n'existe.

`PUT /api/v1/accounting/settings` — upsert (une ligne unique par `company_id`),
champs optionnels, validation :

| Champ | Règle |
|---|---|
| `currency` | nullable, `in` devises CountryDefaults (DZD/MAD/TND/XOF/XAF/EUR/TRY/GBP/USD/CAD) |
| `document_language` | nullable, `in` fr/ar/tr/en |
| `template_style` | nullable, string ≤ 60 |
| `payment_terms` | nullable, string ≤ 60 |
| `legal_mentions` | nullable, string ≤ 2000 |
| `tva_rates` | nullable, tableau 1..20 de `{label ≤ 80, rate 0..100}` |
| `number_series` | nullable, `[DocumentType => préfixe]` ≤ 20, alnum + `-` |

RBAC : `api.manager:comptable,principal` (matrice comptabilité §5).
Isolation tenant : résolution par compagnie courante (aucun id d'URL) +
`BelongsToCompany` (fail-closed #3727).

## 3. Provisioning à la création d'entreprise

Listener `ProvisionAccountingSettings` sur `CompanyCreated` (enregistré dans
`AccountingServiceProvider::boot`, local au module) : `withinTenant()` →
`firstOrCreate(company_id)` avec les défauts (devise/langue via CountryDefaults,
TVA standard pays, séries FAC/PRO/DEV/AVOIR/BL/REC). Additif et non bloquant :
tout échec est loggé (`accounting.settings_provision_skipped`) et le GET sert
les défauts à la volée — la création de compte ne casse jamais.

## 4. UI

`/accounting/settings` (admin dashboard) — formulaire glass-* : devise, langue
des documents, modèle, conditions de paiement, taux de TVA (lignes dynamiques),
séries par type, mentions légales. Entrée sidebar réservée aux managers
comptable/principal. i18n ×4 (clés `accounting.settings.*` + `navigation.accountingSettings`).

## 5. Défauts TVA standard par pays (2026, modifiables)

DZ 19 · MA 20 · TN 19 · SN 18 · CI 18 · ML 18 · BF 18 · BJ 18 · TG 18 · NE 19 ·
CM 19,25 · GA 18 · CG 18,9 · TD 18 · CF 19 · GQ 15 · FR 20 · TR 20 · GB 20 ·
US 0 · CA 5 — défaut inconnu : 19 %.

## 6. DoD

- [x] UI settings (comptable/principal)
- [x] Validation (FormRequest + garde clés `number_series`)
- [x] Défauts pays appliqués à la création d'entreprise (événement `CompanyCreated`)
- [x] Tests Feature : 12 (`AccountingSettingsTest` — défauts, upsert, validation
      422 ×4, RBAC, isolation tenant, provisioning + idempotence)
- [x] OpenAPI : 2 opérations + 2 schémas, miroir/SDK régénérés, couverture 100 %
- [x] i18n ×4 à parité ; CHANGELOG en tête d'[Unreleased]
