# Feature Specification: Données démo/seed + E2E parcours facture — module Accounting (issue #5274)

**Feature Branch**: `mod/accounting/5274-demo-seed-e2e`
**Issue**: #5274 (P2, backend, QA, tests)
**Created**: 2026-08-24
**Status**: Implementation
**Module**: `accounting` — périmètre : `api/app/Modules/Accounting/**`, `api/tests/Feature/Accounting/**`, `.specify/features/5274-accounting-demo-seed-e2e/`, CHANGELOG. Aucun fichier hors module touché (protocole anti-collision PLAN_100PCT §2).

## Contexte

Le module Comptabilité est en Phase A/B (socle data #5221 mergé, CRUD contacts #5222 mergé,
paramétrage #5232 mergé, workflow/numérotation #5223 mergé ; PDF #5224 et email/portail #5225 en vol).
Issue #5274 : un compte démo doit montrer le module **immédiatement** (vitrine sans données réelles,
activable en 1 clic) et le parcours facture doit être couvert **de bout en bout** par un test E2E.

État réel au 2026-08-24 sur `main` :

| Brique | Existant (main) |
|---|---|
| Seed démo entreprise | ✅ `DemoCompanySeeder` / `DemoCompanyOnceSeeder` (sans données Accounting) |
| Données Accounting | ❌ aucun seeder dédié |
| E2E parcours facture | ❌ aucun test bout-en-bout (tests unitaires #5221/#5222/#5223/#5228) |
| Activation démo 1 clic | ❌ aucune commande dédiée |

## Décisions

1. **Scope module uniquement** : tout le code de seed vit dans `api/app/Modules/Accounting/**`
   (action + commande artisan enregistrée par `AccountingServiceProvider::commands()`).
   Zéro modification de `api/database/seeders/**` (hors périmètre module, risque de collision).
2. **Action `SeedAccountingDemoData`** (Application/Actions) : seed idempotent et marqué —
   chaque enregistrement demo porte `metadata.demo_seed = true` ; re-exécution sans `--force` =
   no-op (`ALREADY_SEEDED`) ; `--force` supprime UNIQUEMENT les lignes marquées `demo_seed`
   (jamais de données réelles) puis re-seed.
3. **Commande `artisan accounting:demo-seed`** : cible une entreprise par id ou slug
   (`{company?}`), option `--force`. C'est le « démo exploitable en 1 clic » documenté.
4. **E2E** : test Feature qui déroule le parcours réel contact → devis → facture → PDF (fake
   `PdfRendererInterface`, implémentation #5224 non mergée) → email (`Mail::fake`, envoi #5225
   non mergé) → paiement → rapprochement → statuts. Le test est vert sur main **sans dépendre
   des PRs en vol** : PDF/email sont fakes, tout le reste passe par le code réel mergé.
5. **Données réalistes DZ** (pays par défaut du registre, CF `CountryDefaults`) : 3 clients +
   2 fournisseurs, séries de numérotation FAC/PRO/DEV/AVOIR/BL/REC, 6 documents dans des états
   variés (devis envoyé, facture payée, facture partiellement payée, proforma, avoir lié,
   bordereau, reçu) + paiements rapprochés. Totaux HT/TVA/TTC cohérents (TVA 19 % DZ).
6. **Isolation tenant** : le seed passe par les modèles du module (trait `BelongsToCompany`,
   fail-closed #3727) — jamais de `DB::table` brut. Le test vérifie l'invisibilité cross-tenant.

## User Stories

### US1 — Un compte démo montre le module immédiatement (seed réaliste)

**Why this priority**: la vitrine est le premier contact produit — sans données, le module paraît vide.

**Independent Test**: `php artisan accounting:demo-seed {company}` → contacts, documents,
paiements et settings existent ; re-run = no-op ; `--force` = données demo recréées sans
effet sur d'éventuelles données réelles.

**Acceptance Scenarios**:

1. **Given** une entreprise démo, **When** la commande s'exécute, **Then** ≥ 5 contacts
   (3 clients / 2 fournisseurs), ≥ 5 documents (factures, devis, avoir lié, bordereau, reçu),
   ≥ 2 paiements dont 1 rapproché, et une ligne `accounting_settings` (séries + TVA 19 % DZ)
   sont créés.
2. **Given** le seed déjà exécuté, **When** il est rejoué, **Then** aucune donnée supplémentaire
   n'est créée (idempotence, message `ALREADY_SEEDED`).
3. **Given** `--force`, **When** exécuté, **Then** seules les lignes `metadata.demo_seed = true`
   sont supprimées puis recréées ; les lignes réelles sont intactes.
4. **Given** les données demo de l'entreprise A, **When** l'entreprise B interroge ses contacts
   via l'API, **Then** elle ne voit rien (isolation tenant, 404/0 résultat).

### US2 — Le parcours facture est couvert de bout en bout (E2E)

**Why this priority**: la DoD exige un E2E vert en CI — c'est la garantie de non-régression
du parcours de facturation complet.

**Independent Test**: `php artisan test tests/Feature/Accounting/AccountingDemoE2ETest` → vert.

**Acceptance Scenarios**:

1. **Given** une entreprise démo seedée, **When** un comptable crée un contact via l'API,
   **Then** 201 + persistance tenant.
2. **Given** le contact, **When** un devis est créé (numérotation réelle
   `SequentialDocumentNumbering`, lignes, totaux TVA 19 %), **Then** il passe draft → sent
   via `DocumentWorkflowService`.
3. **Given** le devis envoyé, **When** transformé en facture et marquée envoyée (PDF fake
   `PdfRendererInterface`, `Mail::fake`), **Then** la facture est `sent`, `sent_at` posé,
   `pdf_path` produit par le fake.
4. **Given** la facture, **When** un paiement partiel puis le solde sont enregistrés et
   rapprochés, **Then** les transitions sent → partially_paid → paid s'appliquent et
   `paid_amount == total_ttc`.
5. **Given** l'échéance dépassée d'une facture envoyée, **When** `refreshOverdue` s'exécute,
   **Then** la facture passe `overdue` (relance).

### US3 — La démo est activable en 1 clic (sans données réelles)

**Why this priority**: « Démo exploitable en 1 clic » (DoD) — la commande est le point d'entrée
documenté pour staging/démo.

**Independent Test**: `php artisan accounting:demo-seed --help` documente l'usage ; la commande
s'exécute en local/dev sans autre prérequis que les migrations tenant.

**Acceptance Scenarios**:

1. **Given** une entreprise, **When** `php artisan accounting:demo-seed <slug|id>` est lancé,
   **Then** exit 0 et résumé des données créées (N contacts, N documents, N paiements).
2. **Given** un seed existant, **When** la commande est relancée, **Then** exit 0 avec
   `ALREADY_SEEDED` (idempotent).
3. **Given** une entreprise inexistante, **When** la commande est lancée, **Then** exit ≠ 0
   avec un message clair.

## Edge Cases

- **Pays sans registre** : `AccountingSettingsDefaults::for(null)` (repli documenté).
- **Entreprise déjà pourvue de données réelles** : le seed sans `--force` refuse
  (`ALREADY_SEEDED`) — il n'écrase jamais du réel.
- **PDF/email non mergés** : fakes dans le test, documenté en tête de test (dépendance #5224/#5225).
- **Numérotation** : le seed passe par `SequentialDocumentNumbering` (concurrent-safe) ; les
  numéros générés sont uniques par `(company_id, number)`.
- **Chiffrement** : `tax_id` / `reference` / `metadata` sont des casts chiffrés — le seed écrit
  via les modèles (jamais de SQL brut), le test vérifie le chiffrement au repos d'un NIF demo.

## DoD

- [x] E2E vert en CI (`AccountingDemoE2ETest`, `accounting-ci.yml` gate ≥ 70 % maintenue)
- [x] Démo exploitable en 1 clic : `php artisan accounting:demo-seed {company}` + docs
- [x] CHANGELOG [Unreleased] en tête ; `Closes #5274` dans le body de la PR
- [x] PHPStan Strict 8 + tests Feature verts
