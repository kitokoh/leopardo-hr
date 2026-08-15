# Tasks — Réparer la famine du pipeline de déploiement main → Render

**Spec**: `./spec.md` | **Plan**: `./plan.md`

- [ ] T1 `deploy-main.yml` : ajouter le déclencheur `push` sur `main` (conserver `workflow_run` + `workflow_dispatch`)
- [ ] T2 Gate `workflow_gate` : mode `push` = polling borné des check-runs requis du SHA (timeout 30 min, intervalle 60 s)
- [ ] T3 Skip visible : `::warning::` + `$GITHUB_STEP_SUMMARY` quand `should_deploy=false`
- [ ] T4 Journaliser `APP_VERSION` + SHA à chaque déploiement effectif
- [ ] T5 `CHANGELOG.md` — entrée `Fixed`
- [ ] T6 `AGENTS.md` — leçon pending-run cancellation
- [ ] T7 Post-merge : vérifier le run déclenché, puis `/api-explorer` 200 en prod ; commenter #2632/#2627 avec la preuve
