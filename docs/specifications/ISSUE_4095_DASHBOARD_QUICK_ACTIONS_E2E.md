# Mini-spec — Issue #4095

## Problème

`e2e/dashboard-quick-actions.spec.ts:188` échouait en strict mode : `a[href="/employees"]`
résout **2 éléments** (sidebar « Employes » — `client-features.ts:50` — et action
rapide « Nouvel employé » — `dashboard/page.tsx:648`). Deux problèmes distincts :
1. doublon de destination (sidebar + carte actions rapides) ;
2. la sidebar contient des liens **désactivés** par feature gate
   (`/reports` avec `aria-disabled="true"`) : `locator(...).last()` tombait dessus.
3. le regex `/message envoye a l.equipe/i` ne matchait plus le libellé
   accentué « Message envoyé à l'équipe » (accents restaurés #3249).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Navigation actions rapides (employees/absences/reports) | Vert (sélecteurs scopés) |
| Annonce Leo IA « Oui, envoyer » | Succès visible (regex accentué) |
| Aucune régression sur la sidebar (feature gates inchangés) | — |
| tsc / eslint | 0 erreur |

## Correctif

- `dashboard/page.tsx` : `data-testid="quick-actions"` sur la carte actions rapides
  (pattern #3834 : sélecteurs stables, indépendants de la locale).
- `dashboard-quick-actions.spec.ts` : locators scopés à `getByTestId('quick-actions')` ;
  regex du message de succès compatible accents (`envoy[ée] à l.équipe`).

## Validation

e2e `dashboard-quick-actions.spec.ts` : **4/4 passés** (échouait 2/4 sur main).
tsc/eslint verts.

Closes #4095
