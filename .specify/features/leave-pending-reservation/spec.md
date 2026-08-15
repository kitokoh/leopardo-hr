# Feature: Réservation des jours de congés pending (issues #2416, #2418)

**Issue**: #2416 (LeaveCarryForward ignore pending), #2418 (contrôle de solde à la création ignore pending)
**Session**: 2026-08-15 (analyse de #2329)

## Problème
Les jours `pending` (demandes en attente, réservés par #2329) ne sont pas déduits :
- `LeaveCarryForward` : solde reportable = balance − used (pending non déduit) → sur-report
- `AbsenceService::create` : garde de solde sur `leave_balance_logs` seul → sur-réservation possible

## Correctif
- LeaveCarryForward : `balance − used − pending` + fix propriété `carry_forward_max` (bug préexistant `max_carry_forward`)
- AbsenceService : `currentAvailableBalance()` (snapshot balance−used−pending, fallback logs−pending) utilisé à la création

## Tests
- LeavePendingReservationTest : report réduit par pending, pending=0 → report complet, 2e demande bloquée à 422.
