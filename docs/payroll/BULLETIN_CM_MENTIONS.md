# 🇨🇲 Bulletin de paie CM — mentions obligatoires (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel **pilot** — à valider par un
> expert-comptable camerounais avant tout usage statutaire.

## Sources légales

- **Code du travail camerounais (loi n° 92/007 du 14 août 1992), art. 68** :
  - art. 68 (2) : le paiement du salaire est constaté par un **bulletin de paie individuel**, remis au travailleur au moment du paiement, **dont la contexture est fixée par arrêté du Ministre chargé du Travail**, pris après avis de la Commission Nationale Consultative du Travail ;
  - art. 68 (3) : la mention « **pour solde de tout compte** » ou équivalente n'est **pas opposable** au travailleur ;
  - art. 68 (4) : l'**acceptation sans protestation ni réserve** du bulletin ne vaut pas renonciation au paiement du salaire, indemnités et accessoires.
- **Arrêté ministériel fixant la contexture du bulletin de paie** (pris en application de l'art. 68 (2)) — texte d'application à référencer précisément lors de la validation experte ; les rubriques ci-dessous reflètent la contexture usuelle exigée par l'inspection du travail et la CNPS.
- **Régimes CNPS (loi n° 69-LF-18 et textes PVID/PF/AT)** et **CGI (IRPP sur salaires, CAC, CFC, FNE, taxe communale)** pour le détail des retenues.

## Mentions obligatoires (contexture usuelle, art. 68 + arrêté d'application)

### Bloc employeur
- Raison sociale, adresse, **n° d'immatriculation CNPS employeur**, **NIU** (numéro identifiant unique contribuable), RCCM.

### Bloc employé
- Nom et prénoms, matricule, emploi/catégorie professionnelle et échelon (classification conventionnelle), date d'embauche, **n° d'immatriculation CNPS du travailleur**.

### Bloc période & temps
- Période de paie (mois), nombre de jours/heures travaillés, heures supplémentaires par palier de majoration (20 %, 30 %, 40 %, 50 % — décret n° 95/677).

### Bloc rémunération
- Salaire de base catégoriel, sursalaire, primes et indemnités détaillées (logement, transport, ancienneté…), avantages en nature valorisés.
- **Salaire brut** (et brut taxable / brut cotisable — les assiettes CNPS et fiscales diffèrent).
- Retenues détaillées avec assiette, taux et montant : **PVID CNPS salarié (4,2 %)**, **IRPP sur salaires** (+ **CAC** 10 % de l'IRPP), **CFC salarié (1 %)**, **taxe de développement local/communale**, autres retenues (avances, saisies, cessions).
- Cotisations patronales (PVID, prestations familiales, accidents du travail, FNE, CFC patronal) — usage/lisibilité, exigé pour les contrôles CNPS.
- **Total retenues**, **net à payer**, mode de paiement.

## Mentions interdites / sans effet

- ❌ Mention « **pour solde de tout compte** » ou toute mention équivalente de renonciation : **réputée non opposable** (art. 68 (3)) — ne doit pas figurer sur le bulletin.
- L'acceptation du bulletin sans réserve ne vaut jamais renonciation (art. 68 (4)) — aucune clause du bulletin ne peut prétendre le contraire.

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Raison sociale + adresse employeur | ✅ |
| NIU / RCCM / n° CNPS employeur | ❌ manquant — le pattern F-09 (`company.metadata.legal_*`) est câblé pour les libellés DZ (NIF/RC/CNAS/ID.Nat) ; libellés CM à ajouter |
| Nom, matricule salarié | ✅ |
| Catégorie/échelon, date d'embauche, n° CNPS salarié | ❌ manquant |
| Période, jours travaillés, heures sup (volume) | ✅ |
| Heures sup par palier de majoration | ⚠️ dépend des lignes du run |
| Salaire de base, primes détaillées | ✅ (lignes earnings) |
| Brut, retenues détaillées (assiette/taux/montant), net | ✅ (lignes deductions : PVID/IRPP/CAC/CFC si le moteur CM les génère — `CemacPayrollRules::forMemberCountry('CM')`) |
| Cotisations patronales | ✅ (section dédiée) |
| Absence de mention « solde de tout compte » | ✅ par construction |
| Mention légale pays | ❌ manquant — `COUNTRY_LEGAL` ne couvre pas CM (chaîne vide) |

**Écart global : MOYEN** — cœur couvert par la structure en lignes ; manquent
les identifiants légaux employeur/salarié CM et la mention légale pays.
Générateur **non modifié** dans ce lot ; issues de suivi à créer
(métadonnées `legal_niu`, `legal_rccm`, `legal_cnps_employer` + entrée
`COUNTRY_LEGAL['CM']`).

## Statut

- [x] Référentiel sourcé (art. 68 CT 92/007 ; arrêté d'application à référencer en validation experte).
- [x] Checklist de couverture générateur.
- [ ] Validation experte locale — niveau `pilot`.
