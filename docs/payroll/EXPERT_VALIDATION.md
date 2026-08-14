# ✅ Registre de validation experte des règles pays pilotes

> **Issue #1904** — critère de sortie : *« plus aucun pays Afrique livré à
> `pilot` sans ticket de validation ouvert »*.
>
> Ce registre est la source de vérité de la couverture de validation : un
> pays pilote SANS ticket de validation ouvert fait échouer l'objectif.
> Mis à jour le 2026-08-14 (revue documentaire agent) — la validation
> humaine reste un prérequis externe avant `confidenceLevel → production`.

| Pays | `confidenceLevel()` | Ticket de validation ouvert | Points à valider (docs) |
|---|---|---|---|
| 🇧🇫 Burkina Faso (BF) | `pilot` | #1829 (référentiel), #1915 (tranche IUTS) — suivi expert #1904 | CNSS BF (plafond 900 000), IUTS 6 tranches (27,5 % > 6 M) — `BF_COMPLIANCE.md` |
| 🇲🇱 Mali (ML) | `pilot` | #1829 (référentiel) — suivi expert #1904 | CNSS ML, ITS 6 tranches — `ML_COMPLIANCE.md` |
| 🇬🇦 Gabon (GA) | `pilot` | **#1939** (barème IRPP GA — tranches annuelles ×12, placeholder CEMAC vs barème propre) | IRPP GA (CGI art. 135/174), CNSS — `GA_COMPLIANCE.md` |
| 🇨🇬 Congo (CG) | `pilot` | #1939 (barème commun CEMAC) / suivi expert #1904 | IRPP CG, CNSS — `CG_COMPLIANCE.md` |
| 🇸🇳 Sénégal (SN) | `pilot` | **#1912** (fiche de validation SN dans `SN_COMPLIANCE.md`), #2014 (périmètre déclaration IPRES/CSS) | TRIMF, IPRES T2, CSS AT, CFCE, abattement 30 % |
| 🇨🇮 Côte d'Ivoire (CI) | `pilot` | **#1918** (tranche haute ITSAS 27 %/30 %), #1913 (plafonds CNSS/CSS) | ITSAS, CNSS (plafonds par branche), CN — `CI_COMPLIANCE.md` |
| 🇨🇲 Cameroun (CM) | `pilot` | suivi expert #1904 | CNPS, barème CM propre — `CM_COMPLIANCE.md` |
| 🇩🇿 Algérie (DZ) | `pilot` | suivi expert #1904 | CNAC, IRG, préavis — `DZ_COMPLIANCE.md` |

## Règle de gestion

- Un pays reste `pilot` tant qu'aucun expert-comptable local n'a validé sa
  fiche (ex. `SN_COMPLIANCE.md` §« Fiche de validation »).
- Le passage à `production` exige : fiche cochée + signée dans la doc pays +
  `confidenceLevel()` basculé + `complianceVerifiedAt()` renseigné.
- Si un pays pilote n'a plus de ticket ouvert, rouvrir un ticket avant toute
  communication « production ».

## Suivi

- #1904 (cette issue) : objectif de couverture — clos une fois ce registre
  en place et chaque pays pilote couvert par un ticket ouvert.
- #1912 : validation SN (fiche + bascule production).
- #1939 : barème IRPP Gabon (à confirmer/corriger avant promotion pilot).
- #1918 : tranche ITSAS CI (corrigée 27 %/30 % — confirmation expert requise).
