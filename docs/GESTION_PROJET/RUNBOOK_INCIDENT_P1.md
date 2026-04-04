# RUNBOOK - INCIDENT P1
# Version 4.1.3 | 04 Avril 2026

## Definition P1
Incident qui bloque l'usage principal (login, pointage, paie, API indisponible).

## Procedure 0-30 min
1. Declarer incident (heure, impact, scope)
2. Geler tout merge/deploy non lie a la correction
3. Evaluer decision: rollback immediat ou hotfix
4. Assigner owner unique incident

## Procedure 30-120 min
1. Correction ou rollback execute
2. Verification smoke:
   - login
   - /api/v1/health
   - pointage check-in
3. Communication interne: status toutes les 30 min

## Cloture
- RCA documentee (cause racine, detection, prevention)
- Action preventive convertie en ticket backlog
- Journal racine + changelog mis a jour si impact produit
