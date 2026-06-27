# ADR-002 — L'Auth dans Core, pas dans Modules

**Date** : 2026-06-27
**Statut** : Accepted

## Contexte

L'authentification est consommée par TOUS les modules :
- Chaque controller vérifie l'identité de l'utilisateur appelant
- Le middleware auth est appliqué à toutes les routes sauf `/login`
- Le modèle `Employee` est le "user" de l'application

## Question clé

Où mettre l'auth ? Dans `app/Modules/Auth/` ou dans `app/Core/Auth/` ?

## Décision

**`app/Core/Auth/`** — pour les raisons suivantes :

1. **Pas de dépendance circulaire** : si Auth est un Module, les autres modules
   (`HR`, `Payroll`...) doivent l'importer. Mais Auth utilise `Employee` (modèle HR).
   Résultat : `Modules/HR` → `Modules/Auth` → `Modules/HR` = dépendance circulaire.

2. **Transversalité** : l'auth n'est pas un "feature" métier — c'est le socle.
   Elle ne change pas quand on ajoute la gestion de flotte ou le recrutement.

3. **Contrat stable** : `Core/Auth` expose une interface que les modules consomment
   sans la modifier. C'est le sens du mot "Core".

## Règle

```
✅ Modules/HR peut utiliser Core/Auth
✅ Modules/Payroll peut utiliser Core/Auth
❌ Core/Auth ne peut PAS dépendre de Modules/HR
❌ Modules/HR ne peut PAS dépendre de Modules/Payroll
```
