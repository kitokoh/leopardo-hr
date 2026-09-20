# 🇨🇮 Bulletin de paie CI — mentions obligatoires (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel **pilot** — à valider par un
> expert-comptable ivoirien avant tout usage statutaire.

## Sources légales

- **Code du travail ivoirien (loi n° 2015-532 du 20 juillet 2015), art. 32.5** :
  « Le paiement du salaire doit être constaté par une pièce dressée ou certifiée
  par l'employeur […]. **Les employeurs sont tenus de délivrer au travailleur,
  au moment du paiement, un bulletin individuel de paie dont la structure est
  fixée par voie réglementaire.** Mention est faite par l'employeur du paiement
  du salaire sur un registre manuel ou électronique tenu à cette fin. »
- **Art. 32.6** : la mention « **pour solde de tout compte** » n'est pas
  opposable au travailleur ; l'acceptation sans réserve du bulletin ne vaut pas
  renonciation.
- **Décret d'application fixant la structure du bulletin** (voie réglementaire
  visée par l'art. 32.5 — référence exacte à confirmer en validation experte) ;
  rubriques usuelles exigées par l'inspection du travail, la **CNPS** et la
  **DGI** (ITS réformé 2024 : ITS unique retenu à la source).

## Mentions obligatoires (structure réglementaire usuelle)

### Bloc employeur
- Raison sociale, adresse, **n° CNPS employeur**, **n° de compte contribuable (NCC/DGI)**, RCCM.

### Bloc employé
- Nom et prénoms, matricule, emploi et **catégorie professionnelle** (classification CCI), date d'embauche, **n° CNPS du travailleur**, situation familiale (parts IGR historiques → paramètres ITS).

### Bloc période & temps
- Période de paie, jours/heures travaillés, heures supplémentaires par palier (15 %, 50 %, 75 %, 100 % — Code du travail + convention collective interprofessionnelle).

### Bloc rémunération
- Salaire de base catégoriel (≥ SMIG 75 000 FCFA/mois depuis 2023), sursalaire, primes et indemnités (transport, ancienneté, panier…), avantages en nature.
- **Salaire brut** (brut imposable et brut cotisable distincts).
- Retenues détaillées avec assiette, taux, montant : **ITS** (impôt sur les traitements et salaires, barème progressif réformé — annexe fiscale 2024), **CNPS retraite salarié (6,3 %)**, autres retenues (avances, saisies).
- Cotisations patronales : CNPS (retraite 7,7 %, prestations familiales 5,75 %, AT 2-5 %), taxes sur salaires côté employeur.
- **Total retenues**, **net à payer**, date et mode de paiement.

## Mentions interdites / sans effet

- ❌ Mention « **pour solde de tout compte** » ou équivalente : non opposable (art. 32.6) — ne doit pas figurer sur le bulletin.

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Raison sociale + adresse employeur | ✅ |
| N° CNPS employeur / NCC / RCCM | ❌ manquant (pattern F-09 `company.metadata.legal_*` à décliner en libellés CI) |
| Nom, matricule salarié | ✅ |
| Catégorie professionnelle, date d'embauche, n° CNPS salarié | ❌ manquant |
| Période, jours travaillés, heures sup (volume) | ✅ |
| Paliers de majoration HS | ⚠️ dépend des lignes du run |
| Salaire de base + primes détaillées | ✅ (lignes earnings) |
| Brut, retenues détaillées, net | ✅ (lignes deductions : ITS/CNPS si le moteur CI les génère — `CedeaoPayrollRules::forMemberCountry('CI')`) |
| Cotisations patronales | ✅ (section dédiée) |
| Absence de « solde de tout compte » | ✅ par construction |
| Mention légale pays | ❌ manquant — `COUNTRY_LEGAL` ne couvre pas CI (chaîne vide) |

**Écart global : MOYEN** — même profil que CM : structure en lignes OK,
identifiants légaux et mention pays manquants. Générateur **non modifié**
dans ce lot ; issues de suivi à créer.

## Statut

- [x] Référentiel sourcé (art. 32.5 / 32.6 loi 2015-532 ; décret de structure à référencer en validation experte).
- [x] Checklist de couverture générateur.
- [ ] Validation experte locale — niveau `pilot`.
