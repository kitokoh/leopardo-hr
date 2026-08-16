# Mini-spec — Issue #4091

## Problème

`src/app/auth/logout/page.tsx` planifiait le `router.replace('/auth/login')`
(2,5 s) uniquement **après** `await apiFetch('/auth/logout')`. Or `apiFetch`
(api-client.ts) = timeout 20 s × 3 tentatives + backoff 3-9 s → jusqu'à ~70 s
de blocage si le backend est lent/indisponible. L'utilisateur restait coincé
sur « Déconnexion en cours » (fausse barre de progression, pattern #3809).

Repro e2e : `manager-workday-smoke.spec.ts:272` (échec `toHaveURL(/auth/login)`).

## Contrat

| Vérification | Résultat attendu |
|---|---|
| Clic déconnexion avec backend lent/down | `/auth/login` en ≤ 3 s |
| Nettoyage local `auth_token`/`auth_user` | Immédiat, jamais bloqué par le réseau |
| Appel API `/auth/logout` | Best-effort (catch), non bloquant |
| e2e `manager-workday-smoke:237` | Vert |
| tsc / eslint / jest | 0 erreur |

## Correctif

- Nettoyage localStorage immédiat dans l'effet ;
- `apiFetch('/auth/logout')` fire-and-forget avec catch ;
- redirection `/auth/login` sur timer fixe 1,5 s, indépendant de l'API ;
- barre de progression honnête (100 % — nettoyage local effectué).

## Validation

`tsc`, `eslint`, jest 480/480 verts ; e2e ciblée `manager-workday-smoke` : 1/1
passé (le test qui échouait sur main passe avec le correctif).

Closes #4091
