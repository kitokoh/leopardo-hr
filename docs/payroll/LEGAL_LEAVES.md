# Congés légaux par pays — inventaire DZ / MA / TN / SN (issue #5289)

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
   le 15 ou avant.
4. **Calendrier fériés** (`LegalLeaveCalendarService`) : lecture seule de la
   table globale `public_holidays` (fériés nationaux `company_id = null`,
   récurrents appliqués à toutes les années via `month_day`, règle #1936) —
   module Payroll **intouché**.

## Hors périmètre de cette issue

- Enforcement des plafonds de report légaux (le report reste piloté par la
  politique entreprise : `carry_forward`, `carry_forward_max`).
- Intégration paie (« congés pris/payés → composant du run ») : issue #5245
  (module Payroll).
- Packs suivants (pays hors DZ/MA/TN/SN) : waves W3→W5 (PLAN_100PCT.md).
