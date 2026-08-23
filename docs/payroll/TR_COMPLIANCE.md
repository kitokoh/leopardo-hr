# 🇹🇷 Référentiel de conformité paie — Turquie (TR)

> Fiche issue #2119 (golden tests) — **audit 2026 (#5253)**. ⚠️ À valider par un mali müşavir local avant passage à « production » (issue #1904). Niveau courant : `pilot`.

## Statut

| Règle | État | Référence | Validité |
|---|---|---|---|
| Barème gelir vergisi 2026 | ✅ implémentée | GVK art. 103 (tarife 01.01.2026) | 2026 |
| Cotisations sociales | ✅ implémentée (pilot) | SGK + işsizlik sigortası | 2026 |
| Asgari ücret | ✅ 33 030 TRY brut | 2026 (net officiel 28 075,50) | 2026 |
| Damga vergisi | ❌ non implémentée | 0,759 % du brut (art. 14/1) | gap E1 |
| İstisna asgari ücret | ❌ non implémentée | L. 488 (2022) | gap E2 |
| Tavan SGK | ❌ non implémenté | 7,5 × asgari ücret = 247 725 TRY | gap E3 |

## 1. Barème gelir vergisi 2026 — salariés (mensuel = annuel / 12)

Assiette = brut − (SGK salariale 14 % + chômage salariale 1 %).

| Tranche annuelle | Taux |
|---|---|
| 0 – 190 000 TRY | 15 % |
| 190 001 – 400 000 TRY | 20 % |
| 400 001 – 1 500 000 TRY | 27 % |
| 1 500 001 – 5 300 000 TRY | 35 % |
| > 5 300 000 TRY | 40 % |

Bornes verrouillées par `GoldenTrPayrollTest` (± 1 TRY : 190 001 → 28 500,20/an ; 400 001 → 70 500,27 ; 1 500 001 → 367 500,35 ; 5 300 001 → 1 697 500,40).

## 2. Cotisations sociales

| Cotisation | Taux | Type | Plafond |
|---|---|---|---|
| SGK (malullük + sağlık) | 14,0 % / 20,5 % | salarié / employeur | non plafonné (modèle) |
| İşsizlik sigortası (chômage) | 1,0 % / 2,0 % | salarié / employeur | non plafonné (modèle) |

**Gap E3** : le tavan SGK réel (üst sınır) = **7,5 × asgari ücret = 247 725 TRY/mois** — non modélisé (cap null). Goldens volontairement limités à des bruts ≤ 240 000 pour rester légalement valides.

## 3. Asgari ücret 2026

**33 030,00 TRY brut / mois** (net officiel **28 075,50 TRY**) — annoncé décembre 2025, en vigueur au 1er janvier 2026. Net moteur sans istisna : 23 252,07 TRY (gap E2).

## 4. Gaps 2026 documentés

- **E1** — Damga vergisi (timbre) : 0,759 % du brut salarial (0,75 % + 0,009 %) — non modélisée. Depuis 2022, la part asgari ücret est exonérée (cf. E2).
- **E2** — İstisna asgari ücret : depuis janvier 2022, la tranche de revenu égale à l'asgari ücret brut est exonérée de gelir vergisi ET de damga vergisi → le net officiel (28 075,50 sur 33 030 brut) n'est atteignable qu'avec cette exemption, non modélisée.
- **E3** — Tavan SGK : cf. §2.
- Bulletin PDF TR + export virement : non implémentés.

## Sources

- Barème 2026 : GVK art. 103, tarife 01.01.2026 (muhasebetr.com, verginet.net — vérifié 2026-08-23).
- Asgari ücret 2026 : annonce officielle décembre 2025 (33 030 brut / 28 075,50 net ; journalier 1 101,00 TRY).
- SGK 14/20,5 % + işsizlik 1/2 % : SGK mevzuat (stable 2026).
- Damga vergisi : muhasebetr exemple calcul 2026 (0,00759 du brut).
