# 🇬🇦 Référentiel de conformité paie — Gabon (GA)

> **Issue #1824** — Référentiel légal versionné du moteur de paie gabonais
> (CEMAC). Passe `CemacPayrollRules` (instance GA) de « placeholder » à
> « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux DGI Gabon, CNSS, Code du travail).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRPP (8 tranches annuelles) | ✅ implémentée (pilot) | DGI Gabon | à confirmer |
| CNSS retraite 2,5 % / 5,0 % (plaf. 3 000 000) | ✅ implémentée (pilot) | CNSS | à confirmer |
| CNSS famille patronale 8,0 % (même plaf.) | ✅ implémentée (pilot) | CNSS | à confirmer |
| CNSS AT patronale 3,0 % (non plafonné) | ✅ implémentée (pilot) | CNSS | à confirmer |
| SMIG 150 000 XAF/mois | ✅ implémentée | — | à confirmer |
| HS CEMAC (+20 % / +30 %) | ✅ implémentée (pilot) | Code du travail | à confirmer |
| Préavis (8 j / 1 m / 3 m) | ✅ implémentée (pilot, niveau employé) | Code du travail | à confirmer |

## 1. IRPP annuel (DGI Gabon)

| Tranche annuelle (XAF) | Taux |
|---|---|
| 0 – 1 500 000 | 0 % |
| 1 500 001 – 1 920 000 | 5 % |
| 1 920 001 – 2 700 000 | 10 % |
| 2 700 001 – 3 600 000 | 15 % |
| 3 600 001 – 5 160 000 | 20 % |
| 5 160 001 – 7 500 000 | 25 % |
| 7 500 001 – 11 000 000 | 30 % |
| > 11 000 000 | 35 % |

## 2. Assiette IRPP

```
assiette IRPP mensuelle = brut − CNSS salariale
```

## 3. CNSS

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS retraite salariale | 2,5 % | salarié | 3 000 000 XAF/mois |
| CNSS retraite patronale | 5,0 % | employeur | 3 000 000 XAF/mois |
| CNSS prestations familiales patronale | 8,0 % | employeur | 3 000 000 XAF/mois |
| CNSS risques professionnels patronale | 3,0 % | employeur | **non plafonné** |

Codes : `CNSS_GA_RET_EMP`, `CNSS_GA_RET_PAT`, `CNSS_GA_FAM_PAT`,
`CNSS_GA_AT_PAT`.

## 4. SMIG

**150 000 XAF/mois**.

## 5. Congés payés

📝 à documenter/test.

## 6. Heures supplémentaires

Palier CEMAC partagé : +20 % pour les 8 premières heures/semaine, +30 %
au-delà (à confirmer pour GA).

## 7. Préavis

| Catégorie | Préavis |
|---|---|
| Ouvriers | 8 jours |
| Employés / Techniciens | 1 mois |
| Cadres | 3 mois |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — catégorie du contrat = suivi.

## 8. Jours fériés

Fixes : 1ᵉʳ janvier, 12 mars (fête de la Rénovation), 1ᵉʳ mai, 17 août (Fête
Nationale), 15 août, 1ᵉʳ novembre, 25 décembre + fêtes islamiques mobiles
(via table `islamic_calendar`, #1812). Gestion dynamique via #1811.
