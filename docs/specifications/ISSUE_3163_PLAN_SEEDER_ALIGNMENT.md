# Mini-spécification — Issue #3163

## Objectif

Aligner le seeder des plans publics backend sur la grille canonique de la vitrine : **Free**, **Pilot**, **Operations**, **Enterprise**.

## Contrat canonique

| Plan | Mensuel | Annuel | Employés inclus | Essai |
|---|---:|---:|---:|---:|
| Free | 0 € | 0 € | 5 | 30 jours |
| Pilot | 29 € | 290 € | 30 | 30 jours |
| Operations | 99 € | 948 € | 250 | 30 jours |
| Enterprise | Sur devis | Sur devis | Illimité | 30 jours |

## Migration

Le seeder reste idempotent. Lorsqu’il rencontre un plan historique Starter ou Business, il le renomme en Pilot ou Operations si le plan canonique n’existe pas encore. Si les deux lignes existent, les entreprises référencées sont déplacées vers le plan canonique puis le doublon historique est supprimé. Les IDs canoniques existants sont ainsi conservés.

## Critères d’acceptation

1. Une exécution sur base vide crée exactement les quatre noms canoniques.
2. Starter et Business ne sont plus générés.
3. Les montants et limites correspondent à la vitrine actuelle.
4. Une seconde exécution ne crée aucun doublon.
5. Les références `companies.plan_id` historiques restent valides après migration.
6. Le seeder passe la syntaxe PHP et respecte `git diff --check`.

## Plan de retour arrière

Réversion du commit et restauration des anciennes lignes uniquement si la migration n’a pas encore été appliquée en production. Les références d’entreprises sont migrées transactionnellement.

## Trace Spec Kit

Issue : #3163  
Branche : `fix/3163-plan-seeder-alignment`  
Date : 2026-08-15
