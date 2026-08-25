# 🇫🇷 Référentiel de conformité paie — France (FR)

> Fiche issue #2119 (golden tests) — **audit 2026 (#5254)**. ⚠️ À valider par un expert-comptable local avant passage à « production » (issue #1904). Niveau courant : `pilot` — modèle SIMPLIFIÉ.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème IR 2026 | ✅ implémentée | CGI art. 197 (LF 2026, +0,9 %) | 2026 |
| Cotisations sociales | ✅ implémentée (pilot, simplifié) | SS + CSG + CRDS (URSSAF) | 2026 |
| SMIC | ✅ 1 867,02 €/mois | SMIC 1er juin 2026 (+2,41 %) | 2026 |
| PAS (prélèvement à la source) | ⚠️ partiel (taux neutre annuel/12) | CGI art. 204 A-H | gap E2 |
| Export DSN | ❌ non implémenté | — | gap E1 |

## 1. Barème IR 2026 (mensuel = annuel / 12)

Tranches ANNUELES (assiette = brut − cotisations salariales) :

| Tranche | Taux |
|---|---|
| 0 – 11 600 € | 0 % |
| 11 601 – 29 579 € | 11 % |
| 29 580 – 84 577 € | 30 % |
| 84 578 – 181 917 € | 41 % |
| > 181 917 € | 45 % |

Revalorisation LF 2026 : **+0,9 %** (inflation) par rapport au barème 2025.
Bornes verrouillées par `GoldenFrPayrollTest` (± 1 € : 11 601 → 0,11 €/an ; 29 580 → 1 977,99 €/an ; 84 578 → 18 477,50 €/an ; 181 918 → 58 386,94 €/an).

## 2. Cotisations sociales (structure URSSAF détaillée — pilot, #5438)

PMSS 2026 = **4 005 €/mois** (PASS 48 060 €, +2 %, arrêté 2026).

| Cotisation | Code | Taux salarié | Taux employeur | Plafond |
|---|---|---|---|---|
| Maladie | `MAL_FR` | 0,00 % | 13,00 % | — |
| Vieillesse plafonnée | `VIE_PLF_FR` / `VIE_PLF_PAT_FR` | 6,90 % | 8,55 % | PMSS |
| Vieillesse déplafonnée | `VIE_DPL_FR` / `VIE_DPL_PAT_FR` | 0,40 % | 1,90 % | — |
| Retraite complémentaire T1 | `RET_T1_FR` / `RET_T1_PAT_FR` | 3,15 % | 4,72 % | PMSS |
| Prévoyance (pilot) | `PREV_FR` / `PREV_PAT_FR` | 1,50 % | 1,50 % | — |
| Chômage | `CHO_FR` | 0,00 % | 4,05 % | — |
| FNGS | `FNGS_FR` | 0,00 % | 0,50 % | — |
| CSG (base 98,25 %) | `CSG_FR` | 9,20 % | — | base 98,25 % |
| CRDS (base 98,25 %) | `CRDS_FR` | 0,50 % | — | base 98,25 % |

Salarié ≈ 11,95 % hors CSG/CRDS (vieillesse 6,9 + 0,4 + retraite 3,15 + prévoyance 1,5) + CSG/CRDS sur 98,25 % — **gap E3 réduit** (structure réelle, taux à confirmer par expert-comptable avant passage production).

## 2bis. Réduction générale des cotisations patronales (ex-Fillon) — #5438

Coefficient = (T/0,6) × (1,6 × SMIC_annuel / rémunération_annuelle − 1), borné [0 ; T].
T = **0,3206** (entreprises ≥ 20 salariés — pilot, à confirmer). Zéro au-delà de 1,6 × SMIC mensuel (2 987,23 € en 2026). Exemple SMIC : coefficient max 0,3206 → réduction 598,57 €/mois (vérifié `GoldenFrPayrollTest`).

## 2ter. Net social (bulletin, obligatoire depuis 2023) — #5438

Définition pilot : brut − cotisations salariales (CSG/CRDS comprises). Exposé par `FrancePayrollRules::netSocial()`.

## 3. SMIC

**1 867,02 €/mois** (151,67 h × 12,31 €/h) depuis le **1er juin 2026** (+2,41 %) — 1 823,03 € du 1er janvier au 31 mai 2026 (+1,18 %).

## 4. PAS (prélèvement à la source) — gap E2 partiel (#5438)

`calculateIncomeTax` = impôt ANNUEL progressif / 12 (équivalent « taux neutre » mensuel, sans quotient familial ni réductions). `FrancePayrollRules::withholdingTax(base, taux)` applique un **taux personnalisé** (ex. 8 % → 117,28 €/mois sur assiette SMIC, vérifié en golden). Non modélisés : plan de prélèvement, crédits d'impôts, quotient familial, flux réel de taux transmis par l'administration.

## 5. Gaps 2026 documentés

- **E1** — Export DSN : **implémenté (structure S21.G00 minimale, #5438)** — `DsnExportService` + `payroll:export-dsn {run}` (run validé exigé) ; blocs S21.G00.01/.02/.06/.11 testés. Validation URSSAF complète (contrôle technique du fichier) **hors périmètre** — à brancher en CI avec un validateur public.
- **E2** — PAS taux personnalisé : implémenté (cf. §4), flux réel non branché.
- **E3** — Cotisations URSSAF : structure détaillée implémentée (cf. §2) — taux à confirmer par expert-comptable.
- Bulletin PDF FR : rendu générique `payslip.blade.php` (net social exposé via `netSocial()` — à afficher dans un lot suivant).

## Sources

- Barème IR 2026 : LF 2026, economie.gouv.fr / service-public.fr (vérifié 2026-08-23).
- SMIC 2026 : décrets 2025-… et 2026-… (1er janvier +1,18 % → 1 823,03 € ; 1er juin +2,41 % → 1 867,02 €), info.gouv.fr (vérifié 2026-08-23).
- CSG/CRDS assiette 98,25 % : CGI art. 154 quinquies (constante légale, issue #2220).
- Heures supplémentaires : Code du travail art. L3121-36 (25 % / 50 %).
