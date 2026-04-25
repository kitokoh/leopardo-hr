# Validation - statut documentaire

Ce dossier contient les referentiels de validation fonctionnelle et QA utilises pour verifier qu'un module est reellement terminable et testable.

## Statut des documents

| Fichier | Statut | Usage autorise | Source canonique qui prime |
|---|---|---|---|
| `Leopardo_RH_Pointage_Validation_Finale.pdf` | Referentiel QA canonique du module pointage | Executer les scenarios de test, verifier la readiness du module, documenter PASS/FAIL/BLOCK | Le code sur `main` pour le comportement final, puis `PILOTAGE.md` pour le statut programme |

## Regle

Ce PDF peut faire foi pour la validation du **module pointage** tant qu'il reste aligne avec :

- les routes/API reelles,
- les roles effectivement supportes,
- et les tests backend/mobile executes en CI.

Il ne constitue pas une source de verite globale pour :

- l'infrastructure,
- la gouvernance programme,
- ou les autres modules hors pointage.