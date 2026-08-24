# 🇩🇿 Bulletin de paie DZ — mentions obligatoires (F-09)

> Programme FOCUS — référentiel des mentions du bulletin de paie algérien.
> ⚠️ À valider avec un comptable DZ avant mise en production ; la vue
> `pdf.payslip` (GeneratePaySlipPdfJob) doit couvrir chaque mention.

## Bloc employeur
- Raison sociale, adresse, NIF, RC (registre de commerce), n° CNAS employeur.
- ID.Nat (identifiant national) si applicable.

## Bloc employé
- Nom, prénom, matricule, poste, date d'embauche, n° de sécurité sociale (CNAS).

## Bloc période & présence
- Période (mois/année), jours ouvrés, jours travaillés, heures sup (quantité).

## Bloc rémunération
- Salaire de base, primes et indemnités détaillées (ancienneté, panier…), heures sup majorées.
- **Brut total**.
- Cotisations salariales détaillées (CNAS 9 %), IRG (assiette, taux, montant), autres retenues.
- **Total retenues**, **net imposable**, **net à payer**.
- Cumuls annuels (brut, retenues, net) — régularité légale.

## Contrôles de cohérence (testés)
- brut = Σ lignes earning · net = brut − Σ déductions · totaux de run = Σ bulletins.
- Chaque mention du bulletin a une donnée source dans PaySlip/PaySlipLine (test F-09).

## État d'avancement (2026-08-09)
- [x] Données disponibles dans PaySlip/PaySlipLine (gross, net, lines typées).
- [x] Vue `pdf.payslip` alignée : bloc employeur (adresse + **NIF/RC/CNAS employeur/ID.Nat** via `company.metadata`), bloc employé, période/présence, rémunération détaillée, **cumuls annuels** (brut/retenues/net) — PR #1643.
- [x] Test automatique des mentions : `PaySlipDzMentionsTest` (NIF/RC/CNAS/ID.Nat + cumuls annuels).
- [x] Archivage automatique Cabinet + horodatage — #1817 (`ArchivePaySlipsToCabinetJob`, dispatché par `PayrollClosingService::lock()` ; test `PaySlipCabinetArchiveTest`). Couverture du flux API complétée par `PayrollRunClosingE2ETest` (#5150) : les bulletins `validated`/`sent` sont archivés au verrouillage.

## État RTL (2026-08-23, issue #5242)
- [x] **Rendu arabe RTL SANS cassure** : `ArabicPdfText` (shaping contextuel
      + inversion RTL par runs) appliqué dans la vue `pdf.payslip` via le
      helper `$t()` quand la locale est RTL ; police **Almarai** (OFL)
      embarquée et enregistrée dompdf (fallback DejaVu sans crash).
- [x] **Numérotation** : « Bulletin N° :n » sous le titre (i18n ×4).
- [x] **Test golden-ish (DoD #5242)** : `PaySlipBilingualRenderTest` — rendu
      fr (mentions + numérotation, zéro U+FFFD) et ar (titre « كشف الراتب »
      en formes jointes + ordre RTL, police Almarai, zéro U+FFFD) ;
      `ArabicPdfTextTest` (5 cas unitaires).
- [ ] **Validation comptable** : revue humaine des mentions par un
      expert-comptable DZ (à planifier avec la recette pilote #5247).
