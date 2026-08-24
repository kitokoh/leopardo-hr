# Plan comptable multi-pays — écritures salariales (Paie → Comptabilité)

> Issue #5256 — Référentiel des comptes utilisés pour les écritures comptables
> d'un `PayrollRun` validé, consommé par le module Accounting en Phase C
> (#5239). Modèle : `COMPTABILITE_CONCEPTION.md` §6.3 — le module Payroll reste
> maître du calcul ; le module Accounting consomme la paie validée.
> Code : `PayrollCountryChartOfAccounts` + `PayrollAccountingExportService::journalLines()`.

## Modèle d'écritures (partie double)

Pour chaque bulletin **validé** d'un run (règle #2223), le journal produit :

| Sens | Rôle | Compte DZ/FR (PCG/PCN) | Montant |
|---|---|---|---|
| D | Salaires bruts | 641 | `gross_salary` |
| D | Charges patronales | 645 | `employer_contributions` |
| C | Net à payer | 421 | `net_salary` |
| C | Cotisations (salariales + patronales) | 431 | part salariale + `employer_contributions` |
| C | Impôt retenu à la source | 4421 | `income tax` (IRG/IRPP/ITS/PAS + taxe forfaitaire) |
| C | Autres déductions (avances…) | 425 | résidu `total_deductions − social − impôt` |

**Équilibre garanti par construction** : `brut + patronales = net + (social + patronales) + impôt + autres`
⇔ `brut − net = total_deductions`, toujours vrai par définition du bulletin.

La décomposition des déductions est **déterministe** (pas de devinette de
libellés) : lignes `Cotisations salariales` → part sociale ; lignes
`Impot sur le revenu` + taxe forfaitaire du pays (`flatPayrollTaxLabel`) →
impôt ; **résidu** → autres déductions (avances, retenues personnalisées,
régularisations).

## Registre par pays

| Pays | Référentiel | Salaire (D) | Charges patron. (D) | Net à payer (C) | Cotisations (C) | Impôt retenu (C) | Autres (C) | Confiance |
|---|---|---|---|---|---|---|---|---|
| 🇩🇿 DZ | PCN 2009 | 641 | 645 | 421 | 431 | 4421 | 425 | production |
| 🇫🇷 FR | PCG (ANC 2014-03) | 641 | 645 | 421 | 431 | 4421 | 425 | production |
| 🇲🇦 MA | PCG marocain 1993 | 641 | 645 | 421 | 431 | 4421 | 425 | pilot |
| 🇹🇳 TN | PCG tunisien | 641 | 645 | 421 | 431 | 4421 | 425 | pilot |
| 🇸🇳 SN | SYSCOHADA 2017 | 641 | 645 | 421 | 431 | 4421 | 425 | pilot |
| 🇨🇮 CI | SYSCOHADA 2017 | 641 | 645 | 421 | 431 | 4421 | 425 | pilot |
| 🇨🇲 CM | SYSCOHADA 2017 | 641 | 645 | 421 | 431 | 4421 | 425 | pilot |
| 🇹🇷 TR | Tekdüzen Hesap Planı | 770 | 770 | 335 | 361 | 360 | 135 | pilot |
| 🇬🇧 GB | Pratique UK (HMRC) | 622 | 622 | 2300 | 2210 | 2210 | 2310 | pilot |
| 🇺🇸 US | Pratique US (GAAP/QuickBooks) | 6010 | 6040 | 2020 | 2030 | 2040 | 1010 | pilot |
| 🇨🇦 CA | Pratique CA (CRA) | 6010 | 6040 | 2020 | 2030 | 2040 | 1010 | pilot |

### Pays dérivés (même référentiel SYSCOHADA)

| Zone | Membres | Base |
|---|---|---|
| CEDEAO/UEMOA (XOF) | ML, BF, BJ, TG, NE | 🇸🇳 SN |
| CEMAC (XAF) | GA, CG, TD, CF, GQ | 🇨🇲 CM |

→ **Les 21 pays de `CountryDefaults` ont un plan comptable** (explicite ou
dérivé), vérifié par `PayrollCountryChartOfAccounts::all()` (DoD #5256).

## Sources

- `COMPTABILITE_CONCEPTION.md` §6.3 — flux Paie → Comptabilité (641/645/421/431/4421).
- PCN 2009 (Algérie), PCG français (ANC 2014-03), PCG marocain 1993, PCG
  tunisien, SYSCOHADA 2017 (OHADA), Tekdüzen Hesap Planı (Turquie), pratiques
  UK (HMRC payroll) / US / CA.
- Niveau `production` : codes ancrés dans un référentiel officiel documenté.
  Niveau `pilot` : codes de pratique courante — **à valider par un
  expert-comptable local avant généralisation** (constitution §III).
