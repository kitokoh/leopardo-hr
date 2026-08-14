# 🇨🇬 Référentiel de conformité paie — Congo Brazzaville (CG)

> **Issue #1824** — Référentiel légal versionné du moteur de paie congolais
> (CEMAC). Passe `CemacPayrollRules` (instance CG) de « placeholder » à
> « pilot ». ⚠️ **À valider par un expert-comptable local avant passage à
> « production »** (taux DGI Congo, CNSS, Code du travail).

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| IRPP (6 tranches annuelles) | ✅ implémentée (pilot) | DGI Congo | à confirmer |
| CNSS retraite 4,0 % / 8,0 % (plaf. 2 500 000) | ✅ implémentée (pilot) | CNSS | à confirmer |
| CNSS famille patronale 10,0 % (même plaf.) | ✅ implémentée (pilot) | CNSS | à confirmer |
| CNSS AT patronale 3,0 % (non plafonné) | ✅ implémentée (pilot) | CNSS | à confirmer |
| SMIG 90 000 XAF/mois | ✅ implémentée | — | à confirmer |
| HS CEMAC (+20 % / +30 %) | ✅ implémentée (pilot) | Code du travail | à confirmer |
| Préavis (8 j / 1 m / 3 m) | ✅ implémentée (pilot, niveau employé) | Code du travail | à confirmer |

## 1. IRPP annuel (DGI Congo)

| Tranche annuelle (XAF) | Taux |
|---|---|
| 0 – 464 000 | 0 % |
| 464 001 – 1 000 000 | 1 % |
| 1 000 001 – 3 000 000 | 10 % |
| 3 000 001 – 8 000 000 | 25 % |
| 8 000 001 – 13 000 000 | 40 % |
| > 13 000 000 | 45 % |

## 2. Assiette IRPP

```
assiette IRPP mensuelle = brut − CNSS salariale
```

## 3. CNSS

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| CNSS retraite salariale | 4,0 % | salarié | 2 500 000 XAF/mois |
| CNSS retraite patronale | 8,0 % | employeur | 2 500 000 XAF/mois |
| CNSS prestations familiales patronale | 10,0 % | employeur | 2 500 000 XAF/mois |
| CNSS risques professionnels patronale | 3,0 % | employeur | **non plafonné** |

Codes : `CNSS_CG_RET_EMP`, `CNSS_CG_RET_PAT`, `CNSS_CG_FAM_PAT`,
`CNSS_CG_AT_PAT`.

## 4. SMIG

**90 000 XAF/mois**.

## 5. Congés payés

📝 à documenter/test.

## 6. Heures supplémentaires

Palier CEMAC partagé : +20 % pour les 8 premières heures/semaine, +30 %
au-delà (à confirmer pour CG).

## 7. Préavis

| Catégorie | Préavis |
|---|---|
| Ouvriers | 8 jours |
| Employés / Techniciens | 1 mois |
| Cadres | 3 mois |

⚠️ L'interface n'expose que l'ancienneté : implémentation pilote au niveau
**employé/technicien** (30 jours) — catégorie du contrat = suivi.

## 8. Jours fériés

Fixes : 1ᵉʳ janvier, 1ᵉʳ mai, 15 août, 15 octobre (Fête Nationale),
1ᵉʳ novembre, 25 décembre + fêtes islamiques mobiles (via table
`islamic_calendar`, #1812). Gestion dynamique via #1811.
