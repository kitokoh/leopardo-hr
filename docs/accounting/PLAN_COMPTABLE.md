# Plan comptable du module Comptabilité — par pays (#5422)

Registre immuable : `api/app/Modules/Accounting/Domain/Registries/AccountingChartOfAccounts.php`.

## Familles de comptes (grand livre)

| Famille | Sens | Usage |
|---|---|---|
| `clients` | D | Créances clients — factures émises non réglées |
| `suppliers` | C | Dettes fournisseurs — achats |
| `bank` | D | Compte bancaire — encaissements/décaissements |
| `cash` | D | Caisse |
| `vat_collected` | C | TVA collectée sur les ventes |
| `vat_deductible` | D | TVA déductible — avoirs/achats |
| `sales_revenue` | C | Produits des ventes/prestations |
| `purchases` | D | Achats |
| `external_charges` | D | Autres charges externes |
| `paid_in_capital` | C | Capital — bilan |
| `retained_earnings` | C | Report à nouveau / résultat — bilan |

## Référentiels par pays

| Zone | Pays | Référentiel | Statut |
|---|---|---|---|
| PCG | DZ, MA, TN, FR (+ fallback) | PCG / PCN (411, 401, 512, 53, 4457, 4456, 706, 607, 628, 101, 110) | production |
| SYSCOHADA | SN, CI, ML, BF, BJ, TG, NE, CM, GA, CG, TD, CF, GQ | SYSCOHADA (521 banques, 571 caisse, 44571/44566 TVA) | production |
| Tekdüzen | TR | Tekdüzen Hesap Planı (120 Alıcılar, 391 Hesaplanan KDV, 600 Yurt içi satışlar) | pilot |
| UK | GB | FRS 102 — plan usuel (1100 Trade debtors, 2200 VAT liability) | pilot |
| US | US | US GAAP — plan usuel (1100 A/R, 2300 Sales tax payable) | pilot |
| CA | CA | Pratique usuelle (2250 GST/HST payable) | pilot |

`confidence = production` : code ancré dans un référentiel officiel documenté.
`confidence = pilot` : pratique courante, à valider par un expert-comptable local
avant généralisation (même règle que `PayrollCountryChartOfAccounts`, #5256).

## Usage

```php
use App\Modules\Accounting\Domain\Registries\AccountingChartOfAccounts;

$chart = AccountingChartOfAccounts::for($company->country);
$clients = $chart['clients']['code']; // ex. '411'
```

Le journal des écritures (#5234) consommera ce registre pour remplacer les
codes hardcodés (ex. `ACCOUNT_VAT = ['4457', …]` dans `JournalPostingService`).
