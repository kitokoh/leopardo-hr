# Congés légaux par pays — inventaire multi-marchés (issues #5289, #7930, #7931)

> **Statut** : inventaire `pilot` — valeurs issues de l'issue #5289 et de la
> pratique RH courante. **Validation requise** par un RH / expert pilote par
> pays (DoD #5289 : « Soldes DZ conformes au calcul manuel d'un RH pilote »)
> avant certification `production` (constitution §III).
>
> Implémentation : `api/app/Modules/Planning/Infrastructure/Services/CountryRules/`
> (miroir du pattern Payroll `CountryRules`) + `LegalLeaveRulesService`.
> Spec : `.specify/features/5289-legal-leaves-by-country/spec.md`.

## Registre (résumé)

| Pays | Droit annuel (j/an) | Acquisition mensuelle (j/mois) | Report légal | Monétisation | Source |
|---|---|---|---|---|---|
| DZ | 30 | 2,5 | autorisé (usage ; pas de plafond légal explicite) | oui (indemnité compensatrice) | Loi n° 90-11 du 21/04/1990, art. 14 |
| MA | 24 | 2 | autorisé (avant échéance annuelle selon usage) | oui (indemnité compensatrice) | Code du travail (loi 65-99), art. 231 |
| TN | 30 | 2,5 | autorisé | oui | Convention collective-cadre 1966 ; Code du travail |
| SN | 26 | ≈ 2,17 | autorisé | oui | Code du travail (loi 97-17), art. L.151 s. |
| CM | 18 | 1,5 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 92/007 du 14/08/1992), art. 89 |
| GA | 24 | 2 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 022/2021 du 19/11/2021), art. 185 |
| CG | 26 | ≈ 2,17 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 45-75 du 15/03/1975) — *article à confirmer par expert* |
| TD | 24 | 2 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 038/PR/96 du 11/12/1996), art. 212 — *à confirmer par expert* |
| CF | 24 (max 30 avec ancienneté) | 2 (ouvrables) | autorisé (cumul 2 ans possible) | oui (fin de contrat uniquement) | Code du travail (loi 09.004 du 29/01/2009), art. 280 s. |
| GQ | 30 (« 1 mois ») | 2,5 (calendaires) | autorisé (usage) | oui | Ley General de Trabajo (ley 4/2021 du 03/12/2021), art. 40 — *à confirmer par expert* |
| CI | 26,4 | 2,2 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 2015-532 du 20/07/2015), art. 25.1 |
| BF | 30 | 2,5 (calendaires) | autorisé (usage) | oui | Code du travail (loi 028-2008/AN du 13/05/2008), art. 156 |
| ML | 30 | 2,5 (jours non ouvrables compris) | autorisé (usage) | oui | Code du travail (loi 92-020 du 23/09/1992), art. L.148 s. |
| TG | 30 | 2,5 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 2021-012 du 18/06/2021) — *article à confirmer par expert* |
| BJ | 24 | 2 (ouvrables) | autorisé (usage) | oui | Code du travail (loi 98-004 du 27/01/1998), art. 158 |
| NE | 30 | 2,5 (calendaires) | autorisé (usage) | oui | Code du travail (loi 2012-45 du 25/09/2012), art. 116 s. — *à confirmer par expert* |
| FR | 30 | 2,5 (ouvrables) | encadré (accord/usage) | oui (indemnité compensatrice, fin de contrat) | Code du travail, art. L3141-3 |
| TR | barème 14/20/26 (ouvrables) | ≈ 1,17 (barème d'entrée) | non prévu par la loi (usage) | oui (fin de contrat, m. 59) | İş Kanunu n° 4857, m. 53 et 56 |
| CA (fédéral) | barème 10/15/20 (2/3/4 semaines) | ≈ 0,83 (barème d'entrée) | report encadré (10 mois) | indemnité 4/6/8 % du brut | Code canadien du travail, art. 183-184.01 |

## Détail par pays

### 🇩🇿 DZ — Algérie

- **Droit** : 30 jours ouvrables de congé annuel payé (Loi n° 90-11 du
  21 avril 1990 relative aux relations de travail, art. 14).
- **Acquisition** : 2,5 jours/mois de service effectif.
- **Report** : admis par usage ; aucun plafond légal explicite (à confirmer
  par le pilote).
- **Monétisation** : indemnité compensatrice de congés non pris au départ
  (art. 18 s.).
- **Jours fériés légaux (fixes)** : 01-01, 05-01, 07-05, 11-01
  (`PublicHolidaySeeder`) ; fêtes islamiques mobiles via `IslamicCalendarService` (#1812).

### 🇲🇦 MA — Maroc

- **Droit** : 24 jours ouvrables/an (Code du travail marocain, loi n° 65-99,
  art. 231) : 1,5 j/mois pour les salariés de moins de 18 ans, 2 j/mois au-delà.
- **Acquisition** : 2 jours/mois (cas général).
- **Report** : autorisé selon l'usage (les congés non pris doivent être pris
  avant l'échéance annuelle suivante, sauf accord).
- **Monétisation** : indemnité compensatrice au départ (art. 256).
- **Jours fériés légaux (fixes)** : 01-01, 01-11, 05-01, 07-30, 08-14, 08-20,
  08-21, 11-06, 11-18 ; Aïds mobiles via calendrier islamique.

### 🇹🇳 TN — Tunisie

- **Droit** : 30 jours/an (convention collective-cadre 1966 — valeur retenue
  par l'issue #5289, à confirmer par le pilote).
- **Acquisition** : 2,5 jours/mois.
- **Report** : autorisé selon l'usage.
- **Monétisation** : indemnité compensatrice au départ (Code du travail).
- **Jours fériés légaux (fixes)** : 01-01, 01-14, 03-20, 04-09, 05-01, 07-25,
  08-13, 10-15, 12-17 ; Aïds mobiles via calendrier islamique.

### 🇸🇳 SN — Sénégal

- **Droit** : 26 jours/an (Code du travail sénégalais, loi n° 97-17,
  art. L.151 s.) : 2 j/mois de service + 1 jour par tranche de 5 ans
  d'ancienneté (jusqu'à 26) — valeur retenue par l'issue #5289.
- **Acquisition** : ≈ 2,17 jours/mois (26/12).
- **Report** : autorisé selon l'usage.
- **Monétisation** : indemnité compensatrice au départ.
- **Jours fériés légaux (fixes)** : 01-01, 04-04, 05-01, 08-15, 11-01, 12-25 ;
  fêtes islamiques mobiles via calendrier islamique.

## Détail CEMAC — CM GA CG + TD CF GQ en pilot prudent (issue #7930)

### 🇨🇲 CM — Cameroun

- **Droit** : 1,5 jour ouvrable/mois de service effectif → 18 jours ouvrables/an
  (Code du travail, loi n° 92/007 du 14 août 1992, art. 89). Moins de 18 ans :
  2,5 j/mois (non modélisé, pilot). Majorations d'ancienneté conventionnelles
  possibles (non modélisées).
- **Source** : art. 89 CT 1992 (texte ILO/NATLEX, juriafrica).

### 🇬🇦 GA — Gabon

- **Droit** : 2 jours ouvrables/mois → 24 jours ouvrables/an (Code du travail,
  loi n° 022/2021 du 19 novembre 2021, art. 185). Moins de 18 ans : 2,5 j/mois
  (non modélisé).
- **Source** : art. 185 loi 022/2021 (Journal officiel gabonais).

### 🇨🇬 CG — Congo-Brazzaville

- **Droit** : 26 jours ouvrables/an de service effectif (≈ 2,17 j/mois),
  + 2 jours par tranche de 5 ans d'ancienneté (non modélisé, pilot)
  (Code du travail, loi n° 45-75 du 15 mars 1975). **Numéro d'article à
  confirmer par expert local.**

### 🇹🇩 TD — Tchad (pilot prudent)

- **Droit** : 2 jours ouvrables/mois → 24 jours ouvrables/an (Code du travail,
  loi n° 038/PR/96 du 11 décembre 1996, art. 212). **Valeur communauté RH la
  plus citée — à confirmer par expert local.**

### 🇨🇫 CF — Centrafrique (pilot prudent)

- **Droit** : 2 jours ouvrables/mois → 24 jours ouvrables/an, plafond légal de
  30 jours ouvrables ; majorations d'ancienneté (2 j par tranche de 5 ans) et
  jours supplémentaires mères de famille non modélisés (Code du travail, loi
  n° 09.004 du 29 janvier 2009, art. 280 s.). Monetisation interdite hors
  rupture/terme de contrat (indemnité compensatrice au prorata uniquement).

### 🇬🇶 GQ — Guinée équatoriale (pilot prudent)

- **Droit** : « un mois » de vacances annuelles payées, interprété 30 jours
  calendaires/an (≈ 2,5 j/mois) — Ley General de Trabajo, ley n° 4/2021 du
  3 décembre 2021, art. 40. **Interprétation la plus communément citée — à
  confirmer par expert local (jours calendaires vs ouvrables).**

## Détail CEDEAO — CI BF ML TG BJ NE (issue #7930)

### 🇨🇮 CI — Côte d'Ivoire

- **Droit** : 2,2 jours ouvrables/mois → 26,4 jours ouvrables/an (Code du
  travail, loi n° 2015-532 du 20 juillet 2015, art. 25.1 ; décret n° 98-39 du
  28/01/1998). Majorations d'ancienneté conventionnelles (CCI 1977) non
  modélisées.

### 🇧🇫 BF — Burkina Faso

- **Droit** : 2,5 jours **calendaires**/mois → 30 jours calendaires/an (Code du
  travail, loi n° 028-2008/AN du 13 mai 2008, art. 156).

### 🇲🇱 ML — Mali

- **Droit** : 2,5 jours/mois, jours non ouvrables compris → 30 jours/an (Code
  du travail, loi n° 92-020 du 23 septembre 1992, art. L.148 s.).

### 🇹🇬 TG — Togo

- **Droit** : 2,5 jours ouvrables/mois → 30 jours ouvrables/an (Code du
  travail, loi n° 2021-012 du 18 juin 2021). **Numéro d'article à confirmer
  par expert local.**

### 🇧🇯 BJ — Bénin

- **Droit** : 2 jours ouvrables/mois → 24 jours ouvrables/an (Code du travail,
  loi n° 98-004 du 27 janvier 1998, art. 158).

### 🇳🇪 NE — Niger

- **Droit** : 2,5 jours **calendaires**/mois → 30 jours calendaires/an (Code du
  travail, loi n° 2012-45 du 25 septembre 2012, art. 116 s. — majorations
  après 20/25/30 ans non modélisées). **Numéro d'article à confirmer par
  expert local.**

## Détail France / Turquie / Canada (issue #7931)

### 🇫🇷 FR — France

- **Droit** : 2,5 jours **ouvrables**/mois de travail effectif, plafond de
  30 jours ouvrables/an (Code du travail, art. L3141-3).
- **Report/monetisation** : report encadré (période de prise, accords) ;
  indemnité compensatrice à la rupture (L3141-28) — défauts pilot conservés.

### 🇹🇷 TR — Turquie

- **Droit** : barème d'ancienneté (İş Kanunu n° 4857, m. 53), en jours
  **ouvrables** (m. 56), ouvert après **1 an** d'ancienneté :
  - 1 à 5 ans (5 ans inclus) : **14 jours** ;
  - plus de 5 ans et moins de 15 ans : **20 jours** ;
  - 15 ans et plus : **26 jours**.
- Salariés ≤ 18 ans ou ≥ 50 ans : minimum 20 jours (non modélisé, pilot).
- **Modélisation** : `legalAnnualDaysForSeniority()` (ancienneté au 1er janvier
  de l'année cible) ; < 1 an → 0 jour (droit légal inexistant) ; le droit
  « de base » exposé est le barème d'entrée (14 j → plancher mensuel ≈ 1,17 j).

### 🇨🇦 CA — Canada (fédéral, Code canadien du travail)

- **Droit** (art. 184 et 184.01) : 2 semaines (< 5 ans) → 10 jours ouvrables ;
  3 semaines (5 à 10 ans) → 15 jours ; 4 semaines (10 ans et +) → 20 jours.
- **Indemnité de congé annuel** (art. 183) : **4 % / 6 % / 8 %** du salaire
  brut selon les mêmes seuils — exposée par `vacationPayRatePercent()`.
- **Périmètre** : employeurs sous réglementation FÉDÉRALE uniquement ; les
  normes provinciales (Québec LNT, Ontario ESA…) sont hors modèle (pilot).

## Moteur (ce que fait le code)

1. **Registre** (`LegalLeaveRulesRegistry`) : résolution stricte par pays —
   pays non supporté → `UnsupportedLeaveCountryException` (422,
   `UNSUPPORTED_LEAVE_COUNTRY`), **aucun fallback silencieux**.
2. **Plancher légal** (`LegalLeaveRulesService::monthlyFloorForPolicy`) :
   `leave:accrue` applique l'acquisition mensuelle légale quand la politique
   de congés déductibles (`accrual_type = monthly` + absence type
   `deducts_leave = true`) est configurée sous le minimum légal du pays.
   Trace : log `planning.legal_leave.floor_applied` + mention « plancher
   légal {pays} » dans la description de l'écriture `LeaveAccrual`.
3. **Droit projeté** (`LegalLeaveEntitlementService`) : calcul pur depuis
   `employees.contract_start` — mois entiers × acquisition mensuelle,
   plafonné au droit annuel ; mois d'embauche compté en entier si embauche
   le 15 ou avant. Pays à barème d'ancienneté (#7931 : TR, CA) : le droit
   annuel est résolu par `legalAnnualDaysForSeniority()` avec l'ancienneté
   au 1er janvier de l'année cible — les pays sans barème gardent le calcul
   historique à l'identique.
4. **Calendrier fériés** (`LegalLeaveCalendarService`) : lecture seule de la
   table globale `public_holidays` (fériés nationaux `company_id = null`,
   récurrents appliqués à toutes les années via `month_day`, règle #1936) —
   module Payroll **intouché**.

## Hors périmètre de cette issue

- Enforcement des plafonds de report légaux (le report reste piloté par la
  politique entreprise : `carry_forward`, `carry_forward_max`).
- Intégration paie (« congés pris/payés → composant du run ») : issue #5245
  (module Payroll).
- Majorations d'ancienneté additives des codes africains (SN +1 j/5 ans,
  CG/CF +2 j/5 ans, NE après 20 ans…) : la valeur de base légale est
  modélisée, les majorations restent à la politique entreprise (pilot).
- Normes provinciales canadiennes (Québec, Ontario…) : seul le régime
  fédéral CLC est modélisé (#7931).
- Minima TR ≤ 18 ans / ≥ 50 ans (20 j) et jeunes travailleurs CM/GA/MA :
  non modélisés (cas général adulte).
- Jours fériés des nouveaux pays (seeders `public_holidays`) : lot ultérieur.
