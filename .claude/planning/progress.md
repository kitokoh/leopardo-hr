# Progress Log — Session 2026-08-15

## 06:10 — Démarrage
- Token fourni par l'utilisateur, révoqué en fin de session
- Clone + fetch main (9569e938)
- 79 issues ouvertes réelles, 73 claimées par PR/branches, 6 non-claimées
- File Actions: ~659 runs queued, 18 in_progress → saturée (#2413)
- Protection main: 0 review requise (contrairement à #2414 qui dit 1) — à vérifier

## Erreurs / apprentissages
- Script python: pagination cassée car `isinstance(d, dict)` break toujours (les réponses
  succès sont des dicts). Toujours checker `'workflow_runs' in d` ou `d.get('message')`.

## 06:45 — PR #2479 ouverte (Closes #2467)
- Porté 3 tests uniques (session/enroll/isolation) dans Training/TrainingControllerTest.php, supprimé l'ancien doublon
- TrainingView.vue: scheduled → planned (map + filtre)
- openapi.yaml: enum ongoing → in_progress + miroir/SDK régénérés (dérive #2450 découverte)
- .claude/ ajouté au .gitignore (scratch agent)
- Vérifs locales: --check openapi OK; PHP non testable localement → CI

## Suite
- [ ] Fermer #2465 (migration app_notifications existe sur main via PR #2446 mergée)
- [ ] Review issues + création tickets manquants
- [ ] Vérifier CI PR #2479 puis merge si vert
