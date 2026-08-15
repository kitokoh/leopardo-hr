# Mini-spécification — Issue #3868

## Objectif

Corriger deux incohérences commerciales de la vitrine : un pourcentage d'économie inexact et une checklist datée de 2024 toujours en ligne en 2026.

## Correction

1. **Badge annuel** : « Économisez 20 % » était faux — prix réels 29→24 € (17,24 %), 79→66 € (16,46 %), 199→166 € (16,58 %). Remplacé par « jusqu'à 17 % » dans les 4 locales (`pricing/page.tsx`).
2. **Checklist Paie 2024** : le PDF `/downloads/checklist-paie-2024.pdf` datait l'édition de 2024 (« taux 2024 »). Nouveau PDF `checklist-paie.pdf` à l'édition courante (année neutre, structure identique, mentions « taux en vigueur »), liens de la page mis à jour, ancien fichier supprimé.

## Critères d'acceptation

1. Aucun « 20 % » ni « 2024 » visible sur `/pricing` et `/guides/checklist-paie`.
2. Le pourcentage annoncé est mathématiquement exact pour tous les plans.
3. `npm run lint`, `npx tsc --noEmit`, `jest` et `check-i18n-diff.js` verts.

## Trace Spec Kit

Issue : #3868
Branche : `fix/3868-pricing-checklist-honesty`
Date : 2026-08-15
