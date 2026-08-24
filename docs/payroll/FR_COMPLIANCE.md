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

## 2. Cotisations sociales (simplifié — pilot)

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| Sécurité sociale | 7,5 % / 30,0 % | salarié / employeur | non plafonné (modèle) |
| CSG | 9,2 % | salarié | base 98,25 % du brut |
| CRDS | 0,5 % | salarié | base 98,25 % du brut |

**Gap E3** : modèle agrégé (2 lignes) vs structure URSSAF réelle (~20 lignes : maladie 0 %/13 %, vieillesse 6,9/8,55 % plafonnée, retraite complémentaire ~3,15/4,72 %, prévoyance, chômage 0/4,05 %…). Le taux salarié réel ≈ 11,31 % hors CSG/CRDS (7,5 % + CSG 9,2 % + CRDS 0,5 % sur 98,25 % ≈ 17,03 % total salarié). À affiner avec un expert-comptable.

## 3. SMIC

**1 867,02 €/mois** (151,67 h × 12,31 €/h) depuis le **1er juin 2026** (+2,41 %) — 1 823,03 € du 1er janvier au 31 mai 2026 (+1,18 %).

## 4. PAS (prélèvement à la source) — gap E2

`calculateIncomeTax` = impôt ANNUEL progressif / 12 (équivalent « taux neutre » mensuel, sans quotient familial ni réductions). Non modélisés : taux personnalisé transmis par l'administration, plan de prélèvement, crédits d'impôts, quotient familial.

## 5. Gaps 2026 documentés

- **E1** — Export DSN (structure minimale) : non implémenté (issue #5254, à traiter avec le socle exports).
- **E2** — PAS taux personnalisé : cf. §4.
- **E3** — Cotisations URSSAF agrégées : cf. §2.
- Bulletin PDF FR (net social obligatoire depuis 2023) : non implémenté.

## Sources

- Barème IR 2026 : LF 2026, economie.gouv.fr / service-public.fr (vérifié 2026-08-23).
- SMIC 2026 : décrets 2025-… et 2026-… (1er janvier +1,18 % → 1 823,03 € ; 1er juin +2,41 % → 1 867,02 €), info.gouv.fr (vérifié 2026-08-23).
- CSG/CRDS assiette 98,25 % : CGI art. 154 quinquies (constante légale, issue #2220).
- Heures supplémentaires : Code du travail art. L3121-36 (25 % / 50 %).
