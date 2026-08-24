# Spec — TVA multi-pays + déclaration simplifiée (issue #5271)

- **Module** : `accounting` — périmètre : `api/app/Modules/Accounting/**` + routes module + OpenAPI/CHANGELOG.
- **Source** : `docs/architecture/COMPTABILITE_CONCEPTION.md` §4 (AccountingSettings, AccountingDocument) + §8 (TVA paramétrable, jamais en dur).
- **Statut** : implémentée — PR `mod/accounting/5271-vat-declaration`.

## 1. Objectif

TVA paramétrée par pays (défauts multi-taux) + déclaration TVA simplifiée par
période, recoupable par un expert-comptable (DoD pilote).

## 2. Multi-taux TVA par pays (défauts settings)

`AccountingSettingsDefaults::for()` renvoie désormais la liste des taux par
pays (au lieu d'un taux unique) — modifiable dans l'UI settings (#5232) :

DZ 19/9 · MA 20 · TN 19 · SN 18 · CI 18 · ML 18 · BF 18 · BJ 18 · TG 18 ·
NE 19 · CM 19,25 · GA 18 · CG 18,9 · TD 18 · CF 19 · GQ 15 · FR 20 · TR 20 ·
GB 20 · US 0 · CA 5 — défaut inconnu : 19 %.

## 3. Déclaration TVA par période

`GET /api/v1/accounting/reports/vat-declaration?period=YYYY-MM[&format=json|csv]`
(RBAC `api.manager:comptable,principal`, throttling hérité des routes).

Agrégation `VatDeclarationService` sur `accounting_documents` (tenant-scoped,
fail-closed #3727 — aucun id d'URL) :

| Côté | Types inclus | Statuts inclus |
|---|---|---|
| TVA collectée | invoice, receipt | ≠ draft, ≠ cancelled |
| TVA déductible | credit_note | ≠ draft, ≠ cancelled |

- Assiette = `subtotal_ht`, taxe = `tax_amount`, total = `total_ttc` ;
- détail par `tva_rate` + totaux arrondis à 2 décimales (arrondi comptable) ;
- net = collectée − déductible ;
- devise : `AccountingSettings.currency` (défaut : devise entreprise).

Export CSV : `Content-Type: text/csv; charset=UTF-8`, nom
`vat-declaration-<period>.csv` (valeurs numériques — pas d'injection formule).

## 4. Mentions légales par pays

`AccountingSettingsDefaults::LEGAL_MENTIONS_BY_COUNTRY` : modèles par pays
(DZ/MA/TN/SN/CI/FR/TR, placeholders `{rc}`, `{nif}`…), modifiables par
l'entreprise ; pays absent → null.

## 5. DoD

- [x] Taux TVA par pays (multi-taux) + assiettes
- [x] Déclaration par période : collectée/déductible/net par taux + export CSV
- [x] Déclaration pilote exacte recoupée par des golden tests calculés à la main
      (août 2026 : collectée 6 200/678/6 878 · déductible 300/57/357 · net
      5 900/621/6 521)
- [x] Mentions légales par pays (défauts)
- [x] 8 tests Feature `VatDeclarationTest` (golden, période vide, CSV, isolation
      tenant, RBAC ×3, validation 422)
- [x] OpenAPI : 1 opération + 2 schémas (`VatDeclaration`, `VatDeclarationTotals`),
      couverture 757/757, redocly 0 erreur, miroir + SDK régénérés
- [x] CHANGELOG en tête d'[Unreleased]
