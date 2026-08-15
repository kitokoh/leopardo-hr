# Spec — Dashboard KPI : mois invalide

## Contexte

`GET /api/v1/dashboard/kpi` accepte le paramètre optionnel `month` au format `YYYY-MM`. Le contrôleur utilisait `Carbon::createFromFormat('Y-m', $month) ?? now()`. Carbon retourne `false` lorsqu’une valeur est invalide ; l’opérateur `??` ne traite pas `false` comme une absence de valeur, ce qui provoque une erreur fatale lors de l’appel à `startOfMonth()`.

## Objectif

Garantir que le endpoint KPI retourne toujours une réponse HTTP 200 pour une valeur `month` invalide ou absente, en utilisant le mois courant comme fallback. Une valeur valide doit continuer à sélectionner exactement la fenêtre mensuelle demandée.

## Règles fonctionnelles

| Entrée | Comportement attendu |
|---|---|
| Paramètre absent | Utiliser le mois courant. |
| `month=YYYY-MM` valide | Utiliser le premier et le dernier jour de ce mois. |
| `month=2026-13`, `month=invalid` ou valeur non textuelle | Utiliser le mois courant, sans erreur 500. |

Le correctif ne doit modifier ni les agrégations KPI, ni l’isolation par `company_id`, ni le contrat JSON existant.

## Critères d’acceptation

1. `GET /api/v1/dashboard/kpi?month=2026-13` retourne HTTP 200.
2. Une valeur textuelle invalide retourne HTTP 200 et `data.month` correspond au mois courant.
3. Le scénario existant avec un mois valide conserve ses compteurs et `data.month`.
4. Le parsing ne doit jamais appeler une méthode Carbon sur `false` ou `null`.
5. Les tests de la fonctionnalité Dashboard passent.

## Plan de vérification

- Ajouter un test de régression pour `month=2026-13`.
- Ajouter un test de régression pour une chaîne non conforme.
- Exécuter la suite ciblée `DashboardControllerTest` et les contrôles PHP/Pint disponibles.
