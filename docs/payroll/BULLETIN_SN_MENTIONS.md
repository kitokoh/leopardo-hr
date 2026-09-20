# 🇸🇳 Bulletin de paie SN — mentions obligatoires (#7932)

> Lot BC-07 « conformité légale multi-marchés » (épic #7924, conception
> `docs/specifications/CONFORMITE_LEGALE_MULTI_MARCHES_2026.md`), gabarit
> `BULLETIN_DZ_MENTIONS.md`. ⚠️ Référentiel **pilot** — SN est le pays cœur
> le plus avancé du moteur (`SenegalPayrollRules`, `SN_COMPLIANCE.md`,
> `SN_VALIDATION.md`) mais les mentions du bulletin restent à valider par un
> expert-comptable sénégalais.

## Sources légales

- **Code du travail sénégalais (loi n° 97-17 du 1er décembre 1997)** :
  - **art. L.115** — périodicité du paiement (≤ 15 jours à l'heure/journée, ≤ 1 mois au mois) ;
  - **art. L.116** — le paiement du salaire fait l'objet d'une **pièce justificative dite « bulletin de paie », dressée et certifiée par l'employeur**, remise au travailleur au moment du paiement ; les mentions sont fixées par arrêté du Ministre chargé du Travail ; tenue du **registre des paiements** ;
  - la mention « pour solde de tout compte » / l'acceptation sans réserve du bulletin ne valent pas renonciation (dispositions du même chapitre, art. L.118 ss.).
- **Arrêté ministériel fixant les mentions du bulletin de paie** (application de l'art. L.116 — référence exacte à confirmer en validation experte).
- **Textes IPRES / CSS** (retraite, sécurité sociale) et **CGI** (IR retenu à la source, **TRIMF**) pour le détail des retenues — cf. `SN_COMPLIANCE.md`.

## Mentions obligatoires (contexture usuelle, art. L.116 + arrêté)

### Bloc employeur
- Raison sociale, adresse, **NINEA**, RCCM, **n° d'immatriculation IPRES / CSS employeur**.

### Bloc employé
- Nom et prénoms, matricule, emploi et catégorie (classification CCNI), date d'embauche, **n° IPRES du travailleur**, situation de famille (parts fiscales pour l'IR).

### Bloc période & temps
- Période de paie, jours/heures travaillés, heures supplémentaires par palier (10 %, 35 %, 60 %, 100 % — CCNI).

### Bloc rémunération
- Salaire de base (≥ SMIG), sursalaire, primes et indemnités détaillées (transport, ancienneté…), avantages en nature.
- **Salaire brut** (brut social/cotisable et brut fiscal distincts).
- Retenues détaillées avec assiette, taux, montant : **IPRES RG salarié (5,6 %)** (+ RC cadres 2,4 %), **IR retenu à la source** (barème progressif + réductions pour charges de famille), **TRIMF** (taxe représentative du minimum fiscal — règle `max(IR, TRIMF)` du contrat de calcul #1934), autres retenues.
- Cotisations patronales : IPRES RG/RC, CSS (prestations familiales 7 %, AT 1-5 %), CFCE (3 %).
- **Total retenues**, **net à payer**.

## Mentions interdites / sans effet

- ❌ Mention « **pour solde de tout compte** » ou équivalente : non opposable au travailleur — ne doit pas figurer sur le bulletin.

## Couverture par le générateur PDF (`PaySlipPdfGenerator` + vue `pdf.payslip`)

| Mention | État |
|---|---|
| Raison sociale + adresse employeur | ✅ |
| NINEA / RCCM / n° IPRES-CSS employeur | ❌ manquant (pattern F-09 `company.metadata.legal_*` à décliner en libellés SN) |
| Nom, matricule salarié | ✅ |
| Catégorie, date d'embauche, n° IPRES salarié, parts fiscales | ❌ manquant |
| Période, jours travaillés, heures sup (volume) | ✅ |
| Salaire de base + primes détaillées | ✅ (lignes earnings) |
| Brut, retenues détaillées (IPRES, IR, TRIMF), net | ✅ (le moteur SN génère IR + TRIMF avec la règle `max(IR, TRIMF)` — contrat #1934) |
| Cotisations patronales | ✅ (section dédiée) |
| Mention légale pays | ✅ (`COUNTRY_LEGAL['SN']` : « Conformément au Code du travail sénégalais. IPRES/CSS incluses. ») |
| Cumuls annuels | ✅ |
| Absence de « solde de tout compte » | ✅ par construction |

**Écart global : FAIBLE à MOYEN** — le calcul SN est le plus mûr du moteur ;
l'écart porte sur les identifiants légaux (NINEA, IPRES) et les attributs
salarié (catégorie, parts). Générateur **non modifié** dans ce lot ; issue de
suivi à créer pour les métadonnées `legal_ninea`, `legal_ipres_employer`.

## Statut

- [x] Référentiel sourcé (art. L.115/L.116 loi 97-17 ; arrêté de contexture à référencer en validation experte).
- [x] Checklist de couverture générateur.
- [ ] Validation experte locale — niveau `pilot` (le cœur de calcul SN est déjà `production`, pas le bulletin).
