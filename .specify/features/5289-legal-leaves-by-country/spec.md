# Feature Specification: Congés légaux par pays — DZ / MA / TN / SN (issue #5289)

**Feature Branch**: `mod/planning/5289-legal-leaves-by-country`

**Created**: 2026-08-22

**Status**: Draft → Implemented

**Input**: Issue #5289 — « Absence/HR — congés légaux par pays (soldes, acquisition, règles) DZ/MA/TN/SN d'abord ».

## Problème

Le moteur de congés (`leave_policies`, `leave_balances`, commandes `leave:accrue` /
`leave:carry-forward`, module **Planning**) acquiert les soldes selon la politique
configurée par l'entreprise — sans connaître les **minimums légaux du pays** :
une entreprise algérienne peut légalement offrir 30 j/an (Loi 90-11, art. 14),
marocaine 24 j/an (Code du travail, art. 231), tunisienne 30 j/an (convention
collective 1966), sénégalaise 26 j/an (Code du travail) ; le moteur ne le sait pas
et n'applique aucun plancher. Résultat : des soldes sous-légaux possibles, aucune
traçabilité de la règle appliquée, aucun lien structuré avec le calendrier des
fériés légaux.

Les jours fériés légaux existent déjà en données (`public_holidays`, seeder
`PublicHolidaySeeder` — DZ/MA/TN/SN couverts, fériés nationaux `company_id = null`,
récurrents `is_recurring`), servis par l'API `GET /public-holidays` (module Payroll).
Il manque : la **consommation côté congés** (lecture seule, sans toucher Payroll) et
surtout le **registre de règles légales** par pays qui pilote l'acquisition.

## User Scenarios & Testing

### User Story 1 — Une entreprise DZ acquiert au moins 2,5 j/mois de congés légaux (Priority: P1)

Le `leave:accrue` mensuel applique le **plancher légal** du pays : pour une politique
de congés déductibles (absence type `deducts_leave = true`, `accrual_type = monthly`)
dont l'acquisition configurée est inférieure au minimum légal mensuel du pays de
l'entreprise, le moteur acquiert le minimum légal et journalise un log d'avertissement.
Aucune entreprise ne peut se retrouver sous le minimum légal par simple configuration.

**Why this priority**: C'est le cœur du DoD « Soldes DZ conformes au calcul manuel
d'un RH pilote » — la conformité légale de l'acquisition.

**Independent Test**: `php artisan test tests/Feature/Leave/LegalLeaveAccrualTest.php` —
une politique DZ configurée à 1 j/mois produit un solde de 2,5 j/mois après `leave:accrue --force`.

**Acceptance Scenarios**:

1. **Given** une entreprise DZ (country = DZ) et une politique mensuelle de congés annuels
   (`deducts_leave = true`) configurée à `accrual_amount = 1` (sous-légal),
   **When** on exécute `leave:accrue --force` avec un employé actif,
   **Then** le solde de l'employé augmente de **2,5** (plancher légal DZ), un log
   `planning.legal_leave.floor_applied` est émis, et l'écriture `LeaveAccrual` mentionne la règle légale.
2. **Given** la même entreprise mais une politique configurée à `accrual_amount = 3` (≥ plancher),
   **When** on exécute `leave:accrue --force`,
   **Then** le solde augmente de **3** (aucun changement de comportement).
3. **Given** une entreprise dans un pays non supporté par le registre (ex. pays hors DZ/MA/TN/SN),
   **When** on exécute `leave:accrue --force`,
   **Then** le comportement historique est conservé (aucun plancher, aucun échec).

### User Story 2 — Le droit légal annuel est calculable depuis l'ancienneté (Priority: P1)

Un service pur `LegalLeaveEntitlementService` calcule le **droit légal projeté** d'un
employé pour une année : mois travaillés (depuis `employees.contract_start`) ×
acquisition mensuelle légale, plafonné au droit annuel légal. Ce calcul est utilisé
par les tests golden et expose la donnée « combien de jours l'employé doit avoir à
date » — base de la conformité et du futur pont vers la paie (#5245).

**Why this priority**: Task 2 de l'issue — « soldes calculés à partir de l'ancienneté + période légale ».

**Independent Test**: `php artisan test tests/Unit/LegalLeaveEntitlementServiceTest.php`.

**Acceptance Scenarios**:

1. **Given** un employé DZ embauché le 2026-03-15,
   **When** on calcule le droit légal annuel pour 2026 (10 mois complets : mars → décembre),
   **Then** le droit est de **25,0 j** (10 × 2,5), plafonné à 30.
2. **Given** un employé DZ embauché le 2025-01-10,
   **When** on calcule le droit pour 2026,
   **Then** le droit est plafonné à **30,0 j** (droit annuel légal).
3. **Given** un pays non supporté,
   **When** on résout ses règles légales,
   **Then** une exception explicite est levée (`UnsupportedLeaveCountryException`) — **jamais de fallback silencieux** (constitution + spec MULTI_PAYS_RULES_ENGINE.md).

### User Story 3 — Les fériés légaux du pays alimentent le calendrier congés (Priority: P2)

`LegalLeaveCalendarService` expose les **jours fériés légaux** d'un pays + année
(nationaux `company_id = null` + récurrents) en lecture seule de la table globale
`public_holidays` — sans modification du module Payroll. Le calendrier des congés
(et le futur calcul de jours ouvrés côté congés) peut ainsi exclure les fériés légaux.

**Why this priority**: Task 3 de l'issue — liaison PublicHoliday, sans collision module Payroll.

**Independent Test**: `php artisan test tests/Unit/LegalLeaveCalendarServiceTest.php`.

**Acceptance Scenarios**:

1. **Given** le seeder `PublicHolidaySeeder` exécuté,
   **When** on demande les fériés légaux DZ 2026,
   **Then** au moins les 4 fériés fixes DZ (01-01, 05-01, 07-05, 11-01) sont retournés avec `company_id = null`.
2. **Given** une année sans données,
   **When** on demande les fériés,
   **Then** un tableau vide est retourné (pas d'erreur).

### User Story 4 — L'inventaire légal est documenté et auditable (Priority: P2)

Un document `docs/payroll/LEGAL_LEAVES.md` (inventaire DZ/MA/TN/SN : acquisition,
plafonds, report, monétisation, sources) est livré avec la spec ; chaque règle du
registre porte sa **référence légale** en commentaire PHP et un `confidenceLevel`
(`pilot` — validation expert RH pilote requise, conformément à l'issue).

**Why this priority**: Task 1 + DoD de l'issue (recette pilote).

**Independent Test**: relecture du registre + `docs/payroll/LEGAL_LEAVES.md` ; les
valeurs du registre correspondent au tableau de l'inventaire.

## Edge Cases

- Pays non supporté par le registre → **exception explicite**, jamais de fallback silencieux.
- Politique non déductible (`deducts_leave = false`, ex. maladie) → plancher NON appliqué.
- `accrual_type` ≠ `monthly` (yearly/manual) → plancher non appliqué (hors périmètre).
- Employé embauché en cours d'année → droit projeté proratisé par mois entiers.
- Employé sans `contract_start` → l'ancienneté part du 1er janvier de l'année (comportement documenté).
- Fériés récurrents (`is_recurring`) → retournés pour toutes les années (cf. #1936).
- `leave:accrue` en dehors du 1er du mois sans `--force` → comportement inchangé (skip).
- Plafond de report légal : documenté dans l'inventaire (le report reste piloté par la politique entreprise — enforcement report hors périmètre de cette issue).

## Requirements

### Functional Requirements

- **FR-001**: Le système DOIT exposer un registre de règles légales de congés par pays (DZ/MA/TN/SN) : droit annuel (jours), acquisition mensuelle (jours), report (oui/non + plafond), monétisation (oui/non), source légale, `confidenceLevel`.
- **FR-002**: Le système DOIT résoudre les règles d'un pays via un point d'entrée unique (`LegalLeaveRulesService`), avec exception explicite pour les pays non supportés.
- **FR-003**: `leave:accrue` DOIT appliquer le plancher légal mensuel aux politiques de congés déductibles des entreprises des pays supportés, sans jamais acquérir moins que le minimum légal.
- **FR-004**: Chaque application de plancher DOIT être tracée (log `planning.legal_leave.floor_applied` + description de l'écriture `LeaveAccrual`).
- **FR-005**: Le système DOIT fournir un calcul pur de droit légal projeté depuis l'ancienneté (`LegalLeaveEntitlementService`), plafonné au droit annuel.
- **FR-006**: Le système DOIT exposer les fériés légaux d'un pays/année en lecture seule (`LegalLeaveCalendarService`), sans modification du module Payroll.
- **FR-007**: Les tests golden par pays DOIVENT couvrir : plancher mensuel, droit annuel, prorata ancienneté, plafond, fériés légaux.

### Key Entities

- **LegalLeaveCountryRuleInterface**: contrat d'une règle pays (annuel, mensuel, report, monétisation, sources).
- **LegalLeaveRulesRegistry**: registre pays → classe de règle ; résolution sans fallback.
- **LegalLeaveRulesService**: facade applicative (résolution pour une entreprise, plancher d'une politique).
- **LegalLeaveEntitlementService**: calcul pur du droit projeté depuis `employees.contract_start`.
- **LegalLeaveCalendarService**: lecture des fériés légaux dans `public_holidays` (global, lecture seule).

## Success Criteria

### Measurable Outcomes

- **SC-001**: `php artisan test tests/Feature/Leave/LegalLeaveAccrualTest.php tests/Unit/LegalLeaveEntitlementServiceTest.php tests/Unit/LegalLeaveCalendarServiceTest.php` → tout vert.
- **SC-002**: 0 régression sur les tests existants du module Planning/Leave (`tests/Feature/Leave`, `tests/Feature/Absences`, `tests/Unit/AccrueLeaveBalancesTest`).
- **SC-003**: Le DoD « Soldes DZ conformes au calcul manuel d'un RH pilote » est mécaniquement vérifiable par les golden tests (2,5 j/mois, plafond 30 j/an) ; la validation pilote réelle reste un prérequis de recette (hors code).
- **SC-004**: `docs/payroll/LEGAL_LEAVES.md` publié avec sources ; CHANGELOG mis à jour en tête d'[Unreleased].

## Assumptions

- Le pays de l'entreprise est `companies.country` (ISO-3166 alpha-2, majuscule) — cohérent avec `PublicHolidaySeeder` et les tests existants.
- Les chiffres légaux (30/24/30/26 j/an) sont ceux énoncés dans l'issue #5289 ; le registre porte `confidenceLevel: pilot` et exige la validation d'un RH/expert pilote avant toute certification « production » (constitution §III).
- Le plancher s'applique aux politiques dont l'absence type est `deducts_leave = true` et `accrual_type = monthly` (congés annuels payés) — les absences non déductibles (maladie…) restent hors plancher.
- L'intégration paie (« congés pris/payés → composant du run ») est livrée par #5245 (module Payroll, autre agent) : cette issue fournit la donnée (soldes légaux calculés) et le contrat.
- Aucune route API nouvelle (pas de modification d'`openapi.yaml`) : le registre est consommé par le moteur et les tests ; le calendrier fériés est déjà exposé par `GET /public-holidays`.
- Fichiers générés / `.claim-marker` jamais commités (PLAN_100PCT.md §2).
