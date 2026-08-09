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
- [ ] Archivage automatique Cabinet + horodatage (suivi #1548).
