# 🇩🇿 Guide de conformité paie — Algérie (DZ)

> Document consolidé pour les comptables, RH et pilotes. Le référentiel
> versionné exhaustif vit dans [`../DZ_COMPLIANCE.md`](../DZ_COMPLIANCE.md)
> (validé par expert-comptable DZ le **2026-08-08** pour le cœur : IRG,
> CNAS, SMIG). Niveau de confiance courant : `pilot` sur tout le périmètre —
> le passage à `production` exige la revue formelle des valeurs restantes
> (préavis, licenciement, HS, congés), même procédure que SN #1912.

## 1. Règles et taux (résumé exécutif)

| Règle | Valeur | Référence légale | Statut |
|---|---|---|---|
| **IRG — barème mensuel** | 0–20 000 0 % · 20 001–40 000 23 % · 40 001–80 000 27 % · 80 001–160 000 30 % · 160 001–320 000 33 % · > 320 000 35 % | CIDTA art. 104 (LF 2022) | ✅ validé expert 08/08 |
| **IRG — abattement** | 40 % de l'impôt annuel, plancher 12 000 DZD/an, plafond 18 000 DZD/an | CIDTA art. 104 bis | ✅ validé expert 08/08 |
| **Assiette IRG** | brut − CNAS salariale | — | ✅ |
| **CNAS salariale / patronale** | 9 % / 26 % du brut (sans cap implémenté) | CNAS | ✅ validé expert 08/08 |
| **Assurance chômage (CNAC)** | **inclus** dans les agrégats CNAS (1 % patron + 0,5 % salarié) — pas de lignes séparées | décret législatif n° 94-11, art. 94-188 ; décrets exécutifs n° 22-70 (10/02/2022), n° 26-87 (21/01/2026) | ✅ verrouillé (#1819/#1943) |
| **SMIG / SNA** | 20 000 DZD/mois | — | ✅ validé expert 08/08 |
| **Congés payés** | 2,5 j/mois (30 j/an) ; indemnité = max(maintien de salaire, 1/10ᵉ des 12 mois de référence) | loi 90-11 ; F-07 (#1537) | ✅ implémenté + golden (assiette « 12 mois » à confirmer) |
| **Heures supplémentaires** | palier unique ×1,5 (pilot) ; 25 %/50 % conventionnel à confirmer | loi 90-11 art. 33 | ⚙️ implémenté (seuil conventionnel à confirmer) |
| **Prorata** | jours travaillés / 22 jours ouvrés (source `AttendanceLog`, fallback contrat) | F-05, #1816 | ✅ |
| **Préavis** | 1 mois si ancienneté < 10 ans, 2 mois si ≥ 10 ans — **jours ouvrés** (22/44), conditionné (CDI + licenciement hors faute lourde + préavis non effectué), défaut prudent = 0 | loi 90-11 art. 73-4/98 | ⚙️ implémenté (#1819/#1943) — validation requise |
| **Indemnité de licenciement** | 1 mois/an (loi 90-11 art. 72) | **plafond légal 15 mois non appliqué** — à paramétrer | ⚙️ implémenté |
| **Solde de tout compte + certificat** | ✅ implémenté + golden | F-08 (#1538) | ✅ |
| **Rétroactifs / régularisations** | ✅ implémenté (#1818) | — | ✅ |

## 2. Écarts identifiés (audit #5240 — spec `payroll-dz-100`)

Les écarts E1-E6 de l'audit légal sont tracés dans la spec
`.specify/features/payroll-dz-100/spec.md` et adressés par les issues de
complétion du programme 100 % :

| Écart | Sujet | Issue de complétion |
|---|---|---|
| E1 | Primes non soumises (`is_taxable=false` non consommé par le moteur) | #5241 (complétion moteur) |
| E2 | Plafond légal de l'indemnité de licenciement (15 mois) | #5241 |
| E3 | Seuil conventionnel des heures sup (25 %/50 %) | #5266 |
| E4 | Assiette des « 12 mois » pour la règle du 1/10ᵉ | #5245 |
| E5 | Validation experte des valeurs `pilot` (préavis, HS, congés) | #5247 (recette pilote) |
| E6 | Golden tests manquants (maladie, 13ᵉ mois — non applicables DZ) | #5244 |

## 3. Sources officielles

- **CIDTA** (Code des impôts directs et taxes assimilées) — art. 104 / 104 bis : IRG.
- **Loi n° 90-11 du 21/04/1990** (Code du travail) — art. 26/27 (durée du travail,
  repos hebdo), art. 33 (HS), art. 72 (licenciement), art. 73-4 (recherche
  d'emploi), art. 98 (préavis), congés (F-07).
- **CNAS** — taux 9 %/26 % (vérifié expert 08/08).
- **Décret législatif n° 94-11, art. 94-188** + décrets exécutifs n° 22-70 et
  n° 26-87 — assurance chômage CNAC incluse dans CNAS.

## 4. Vérifier un bulletin (checklist rapide)

1. **Brut** : base × prorata (jours travaillés/22) + HS + primes (⚠️ E1).
2. **CNAS salariale** = brut × 9 % (arrondi à 2 décimales).
3. **Assiette IRG** = brut − CNAS salariale ; barème mensuel → IRG annuel × 12
   → abattement 40 % (12 000–18 000) → IRG mensuel.
4. **Net** = brut − CNAS − IRG (+ retenues éventuelles).
5. **Employeur** : CNAS patronale 26 % (charge).

Exemple validé (golden F-03) — brut 60 000 DZD : CNAS 5 400 → assiette 54 600
→ IRG 8 542/mois avant abattement → abattement plafonné 18 000 → **IRG 7 042** →
**net 47 558** ; charge patronale 15 600.

## 5. Mise à jour

Toute modification de taux/barème = PR dédiée qui met à jour **simultanément**
ce guide, `../DZ_COMPLIANCE.md`, `AlgeriaPayrollRules`, les golden tests et le
CHANGELOG, avec référence légale + période de validité.
