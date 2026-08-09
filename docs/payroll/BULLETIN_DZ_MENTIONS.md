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

## État d'avancement
- [x] Données disponibles dans PaySlip/PaySlipLine (gross, net, lines typées).
- [ ] Vue `pdf.payslip` alignée sur cette checklist (prochaine itération).
- [ ] Archivage automatique Cabinet + horodatage (prochaine itération).
