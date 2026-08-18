# 🇪🇺 Validation experte des règles pays — statut & procédure

> **Issues #1904 / #1912** — cadre de validation experte des taux légaux
> multi-pays avant tout passage « production » (`confidenceLevel()`).
> Dernière mise à jour : 2026-08-14.

## Pourquoi ce fichier

La vague Afrique (#1820→#1830) a livré les règles pays en
`confidenceLevel = pilot` avec références légales documentées dans
`docs/payroll/*_COMPLIANCE.md`. Un passage en production sans validation
experte exposerait des bulletins/CSV déclaratifs incorrects. Ce fichier est
le **registre unique** du statut de validation par pays et des questions
bloquantes ouvertes.

## Statut par pays (2026-08-14)

| Pays | Règles | `confidenceLevel()` | Fiche compliance | Validation experte | Bloqué sur |
|---|---|---|---|---|---|
| DZ (Algérie) | `AlgeriaPayrollRules` | pilot* | `DZ_COMPLIANCE.md` | ❌ à valider | barèmes IRG/CNAS à confirmer |
| FR | `FrancePayrollRules` | pilot* | — | ❌ à valider | barème IR/charges à confirmer |
| MA (Maroc) | `MoroccoPayrollRules` | pilot | `MA_COMPLIANCE.md` | ❌ à valider | abattement frais pro 35 % (art. 58) ✅ implémenté (#2260) ; CNSS/AMO/IR à confirmer |
| TN (Tunisie) | `TunisiaPayrollRules` | pilot | (fiche à créer) | ❌ à valider | abattement IRPP 10 % (art. 39) ✅ implémenté (#2261) ; reste à confirmer |

> \* DZ/FR sont **pilot dans le code** (`confidenceLevel()`), pas production —
> aucun pays n'a de validation experte signée à ce jour ; la colonne
> « production » était une erreur de cette fiche (corrigée 2026-08-14).
| CM (Cameroun) | `CemacPayrollRules` | pilot | `CM_COMPLIANCE.md` | ❌ à valider | IRPP art. 68, CNPS plafonds |
| BF (Burkina) | `CedeaoPayrollRules` | pilot | `BF_COMPLIANCE.md` | ❌ à valider | IUTS 27,5 % CGI 2024, CNSS 2024 |
| ML (Mali) | `CedeaoPayrollRules` | pilot | `ML_COMPLIANCE.md` | ❌ à valider | ITS 6 tranches, INPS 2024 |
| GA (Gabon) | `CemacPayrollRules` | pilot | `GA_COMPLIANCE.md` | ❌ à valider | IRPP 8 tranches (art. 174 vs 135), abattement DGI 20 %/833 333 ✅ implémenté (#2118, voir #2124) |
| CG (Congo) | `CemacPayrollRules` | pilot | `CG_COMPLIANCE.md` | ❌ à valider | IRPP 6 tranches, CNSS 2024 |
| SN (Sénégal) | `SenegalPayrollRules` | **production** ✅ | `SN_COMPLIANCE.md` | ✅ **validé 2026-08-18** (#1912) | Toutes les règles validées — voir `SN_VALIDATION.md`. Résiduel : CSS AT taux variable, plafond 80k à confirmer. |
| CI (Côte d'Ivoire) | `CedeaoPayrollRules` | pilot | `CI_COMPLIANCE.md` | ❌ à valider | **Réforme ITS 2024 (art. 119 bis, ord. 2023-718/719)** — l'ancien barème annuel est abrogé ; CN supprimée ; abattement 20 % obsolète |
| BJ/TG/NE/CF/TD/GQ | placeholder | placeholder | — | ❌ (bloqué par construction) | pas de règles livrées |

## Procédure de validation (une fois par pays)

1. **Préparer le ticket** : copier `docs/payroll/_TEMPLATE_VALIDATION_EXPERTE.md`
   dans `docs/payroll/<PAYS>_VALIDATION.md`, remplir les valeurs implémentées
   (taux, plafonds, tranches) depuis `*_COMPLIANCE.md` et le code.
2. **Faire valider** par un expert-comptable local (OHADA/CEDEAO/CEMAC) —
   taux, plafonds, tranches, préavis, arrondis, canaux de déclaration.
3. **Reporter** la décision dans `*_COMPLIANCE.md` (colonne « Validité » :
   `validé expert <date>`), la date de vérification et la source confirmée.
4. **Basculer** `confidenceLevel()` → `production` pour le pays (avec la
   date dans la doc), lever le `complianceWarning()` associé (affiché aux
   utilisateurs, suivi #1872).
5. **Verrouiller** : un golden test couvrant chaque valeur validée (règle
   d'or n°5 du README golden — jamais de défaut générique présenté comme
   légal, #1938).

## Règle de release

- **Aucun pays Afrique ne peut être annoncé « production » tant que son
  ticket de validation n'est pas fermé** (critère de sortie #1904).
- Une PR qui bascule `confidenceLevel()` d'un pays vers `production` DOIT
  référencer le ticket de validation fermé dans son corps, et mettre à jour
  ce registre.
- `complianceWarning()` dérive automatiquement de `confidenceLevel()`
  (PA2-COUNTRY-006) — aucune autre source de vérité.

## Liens

- #1904 (validation experte multi-pays), #1912 (Sénégal — prioritaire P1),
  #1939 (GA — barème IRPP + abattement), #1918 (CI — réforme ITS 2024),
  #1872 (affichage des avertissements), #1938 (goldens sourcés),
  #1875 (playbook d'onboarding pays + garde CI de complétude).
