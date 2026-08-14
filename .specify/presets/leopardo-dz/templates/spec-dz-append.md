---

## 🇩🇿 Conformité Paie DZ — Algérie (preset leopardo-dz v1.0.0)

> Référence : `docs/payroll/DZ_COMPLIANCE.md` — validé expert-comptable 2026-08-08

### Règles légales applicables (CGI DZ + Loi 90-11)

| Règle | Valeur | Référence |
|-------|--------|-----------|
| IRG tranche 0–20k | 0 % | LF 2022 |
| IRG tranche 20k–40k | 23 % | LF 2022 |
| IRG tranche 40k–80k | 27 % | LF 2022 |
| IRG tranche 80k–160k | 30 % | LF 2022 |
| IRG tranche 160k–320k | 33 % | LF 2022 |
| IRG tranche > 320k | 35 % | LF 2022 |
| Abattement IRG | 40 % annuel, plancher 12k, plafond 18k DZD/an | LF 2022 |
| Assiette IRG | brut − CNAS salariale | DZ_COMPLIANCE.md §1bis |
| CNAS salariale | 9 % (pas de plafond) | CNAS |
| CNAS patronale | 26 % (pas de plafond) | CNAS |
| SMIG | 20 000 DZD/mois | Décret |
| Congés | 2,5 j/mois, règle 1/10ème vs maintien | Loi 90-11 |
| Weekend DZ | Vendredi + Samedi (ISO 5 + 6) | Loi 90-11 |

### Golden tests DZ obligatoires pour cette spec
- [ ] Test SMIG (20 000 DZD) → IRG = 0, CNAS = 1 800
- [ ] Test cadre 60 000 DZD → IRG = 7 042, CNAS = 5 400, net = 47 558
- [ ] Test haut salaire 350 000 DZD → IRG = 101 200, CNAS = 31 500
- [ ] Tests calculés à la main dans `GoldenDzPayrollTest.php`

### Assurance chômage DZ
- Statut : **à identifier** — `DZ_COMPLIANCE.md §7`
- Ne pas implémenter sans confirmation légale (CNAC)
