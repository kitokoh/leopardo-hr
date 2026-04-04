# RUNBOOK - ROLLBACK PRODUCTION
# Version 4.1.3 | 04 Avril 2026

## Conditions de declenchement
- Incident P1 apres deploy
- Taux erreur HTTP > seuil critique
- Corruption fonctionnelle bloquante

## Option A - Code rollback (rapide)
1. `git revert <commit>`
2. Push `main`
3. Relancer deploy
4. Verifier health + login + endpoint critique

## Option B - Code + DB rollback (majeur)
1. Passer en maintenance mode
2. Restore DB: `pg_restore -Fc -d leopardo_db <backup.dump>`
3. Checkout commit stable precedent
4. Deploy complet
5. Verifications fonctionnelles minimales

## Regles
- Toute migration en production doit avoir `down()`
- Aucun rollback sans ticket incident et trace dans journal
