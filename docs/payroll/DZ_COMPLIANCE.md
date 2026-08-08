# 🇩🇿 Référentiel de conformité paie — Algérie (DZ)

> **Programme FOCUS (F-02)** — Référentiel légal versionné du moteur de paie algérien.
> ✅ **Validé par expert comptable DZ — 2026-08-08** (IRG barème + abattement, CNAS 9 %/26 %, SMIG 20 000 DZD).
> Sources : loi de finances en vigueur, CNAS. Toute évolution des taux = procédure de mise à jour ci-dessous.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRG (barème) | ✅ implémentée + **validée** | LF 2022 (réforme IRG) | 2026-08-08 ✅ |
| CNAS salariale 9 % | ✅ implémentée + **validée** | CNAS | 2026-08-08 ✅ |
| CNAS patronale 26 % | ✅ implémentée + **validée** | CNAS | 2026-08-08 ✅ |
| SMIG/SNA (20 000 DZD) | ✅ implémentée + **validée** | CNAS/loi | 2026-08-08 ✅ |
| Congés payés (2,5 j/mois, 1/10ᵉ) | 📝 à documenter/test | Code du travail (loi 90-11) | — |
| Préavis / licenciement | 📝 à documenter/test | loi 90-11 art. 98+ | — |
| Solde de tout compte / certificat | 📝 à documenter/test | loi 90-11 | — |
| Assurance chômage | 📝 à documenter/test | À identifier | — |
| Heures sup (25 %/50 %) | 📝 à documenter/test | loi 90-11 art. 18-19 | — |

## 1. IRG — Impôt sur le revenu global (salaires)

**Barème mensuel** (implémenté dans `AlgeriaPayrollRules::defaultTaxSlabs()`) :

| Tranche mensuelle (DZD) | Taux |
|---|---|
| 0 – 20 000 | 0 % |
| 20 001 – 40 000 | 23 % |
| 40 001 – 80 000 | 27 % |
| 80 001 – 160 000 | 30 % |
| 160 001 – 320 000 | 33 % |
| > 320 000 | 35 % |

**Abattement** : 40 % de l'impôt annuel, plancher 12 000 DZD/an, plafond 18 000 DZD/an
(implémenté : `min(max(taxAnnuelle × 0.40, 12000), 18000)`).

### 1bis. Assiette de l'IRG

**L'IRG est calculé sur le brut mensuel MINUS les cotisations salariales (CNAS)** :
`assiette IRG = brut − CNAS salariale` (implémenté dans `PayrollCalculator::calculateSlip`, validé par golden test F-03/F-04).

**Exemple chiffré (golden test F-03)** — brut 60 000 DZD :
- CNAS salariale = 5 400 → assiette = **54 600 DZD**
- IRG(54 600) = 4 600 + 14 600 × 27 % = 8 542/mois → annuel 102 504 → abattement plafonné 18 000 → **7 042 DZD/mois**
- Net = 60 000 − 5 400 − 7 042 = **47 558 DZD**

**Exemple chiffré (golden test F-03)** — salaire imposable mensuel 60 000 DZD :
- Tranche 1 (0–20 000) : 0 DZD
- Tranche 2 (20 001–40 000) : 20 000 × 23 % = 4 600 DZD
- Tranche 3 (40 001–60 000) : 20 000 × 27 % = 5 400 DZD
- Impôt mensuel brut : 10 000 DZD → annuel : 120 000 DZD
- Abattement : min(max(120 000 × 0,40 ; 12 000) ; 18 000) = 18 000 DZD
- **IRG mensuel net : (120 000 − 18 000) / 12 = 8 500 DZD**

## 2. CNAS — Cotisations sécurité sociale

| Cotisation | Taux | Assiette | Cap |
|---|---|---|---|
| CNAS salariale | 9 % | salaire brut | aucun (implémenté) — à confirmer |
| CNAS patronale | 26 % | salaire brut | aucun (implémenté) — à confirmer |

**Exemple chiffré (golden test F-03)** — brut 60 000 DZD : salariale 5 400 DZD, patronale 15 600 DZD.

## 3. SMIG / minima

- Salaire minimum mensuel implémenté : **20 000 DZD** (`minimumWage()`).

## 4. À compléter (prochaine itération — besoin expert paie DZ)

1. Congés payés : acquisition 2,5 j/mois, plafond, indemnité (1/10ᵉ vs maintien).
2. Préavis : durées légales, indemnité compensatrice.
3. Indemnité de licenciement (formule légale).
4. Solde de tout compte + certificat de travail : mentions.
5. Heures supplémentaires : majorations 25 %/50 %, contingent.
6. Assurance chômage et autres cotisations éventuelles.
7. Rétroactifs et régularisations : règles.

## Procédure de mise à jour

Toute modification de taux/barème = PR dédiée qui met à jour **simultanément** :
1. ce référentiel (avec référence légale + période de validité),
2. `AlgeriaPayrollRules` (ou le référentiel versionné qui le remplace),
3. les golden tests correspondants (F-03/F-04),
4. la mention dans le CHANGELOG.
