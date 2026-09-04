# RUNBOOK MERGE — kitokoh/leopardo-hr

> **But** : standardiser le cycle de merge pour éliminer le temps perdu
> (issue #6749). Un seul « gardien du merge » (chef de projet ou agent PM)
> exécute ce runbook de bout en bout.

## Règles d'or

1. **main est toujours vert.** Toute dette CI se répare dans une PR `fix/ci-main-*`,
   jamais au passage dans une PR feature.
2. **Une PR ne se merge que `clean`** : `mergeable_state == clean` et aucun check
   requis en échec. Jamais de merge à checks rouges (même avec droits admin —
   cf. `BRANCH_PROTECTION_REQUIRED.md`, recommander `enforce_admins=true`).
3. **PR verte à la création** : l'auteur exécute `scripts/pre-push-checks.sh`
   avant le push.
4. **Squash uniquement**, suppression de branche après merge.

## Procédure

### 1. Avant le merge
```bash
# La branche doit être à jour avec main (protection « up to date »)
git fetch origin main
git checkout <branche> && git rebase origin/main   # ou merge
```

### 2. Vérifier les checks
```bash
# Via l'API (token requis pour /merge ; lecture possible sans token)
curl -s https://api.github.com/repos/kitokoh/leopardo-hr/pulls/<N> \
  | jq '{state, mergeable, mergeable_state}'
```
- **`clean`** → étape 3.
- **`unstable`** → attendre la fin des checks (nouvelle passe dans 10-15 min).
- **échec** → diagnostiquer (annotations du check) ; si dette main → issue
  `fix/ci-main-*` dédiée, sinon corriger dans la PR.

### 3. Merge squash
```bash
curl -X PUT -H "Authorization: token $GH_TOKEN" \
  -H "Accept: application/vnd.github+json" \
  https://api.github.com/repos/kitokoh/leopardo-hr/pulls/<N>/merge \
  -d '{"merge_method":"squash"}'
```

### 4. Post-merge
```bash
# Supprimer la branche
curl -X DELETE -H "Authorization: token $GH_TOKEN" \
  https://api.github.com/repos/kitokoh/leopardo-hr/git/refs/heads/<branche>

# Rebaser les PRs stackées sur main (ex. #6705)
git fetch origin main
git checkout -B <branche-stackée> origin/<branche-stackée>
git rebase origin/main
git push --force-with-lease origin <branche-stackée>
```

### 5. Vérifications finales
- L'issue référencée par `Closes #XXX` est fermée automatiquement par le merge.
- `main` redevient vert (checks du push main).
- Vercel : surveiller le déploiement (rate-limit 24 h possible).

## Pièges connus

| Piège | Symptôme | Remède |
|---|---|---|
| main avance pendant les checks | `mergeable_state: behind` | rebase puis re-vérifier |
| Vercel rate-limited | statut Vercel = failure | attendre 24 h ou upgrade plan |
| Quota merges quotidiens | check « Quota merges quotidiens » | vérifier le quota avant de merger plusieurs PRs |
| Miroir OpenAPI périmé | Lint OpenAPI échec | `node dev-hub/tools/generate-openapi-sdk.mjs` |
| ARB sans gen-l10n | Mobile split guard | `flutter gen-l10n` dans `leopardo_core` |
| Dette Pint main | Backend Quality échec | PR `fix/ci-main-*` (pint --dirty) |

## Outillage

- `scripts/pre-push-checks.sh` — validation locale avant push (pint, php -l,
  phpstan, tsc, eslint, gardes gouvernance).
- `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` — gouvernance des scénarios
  (toute PR API doit mettre à jour le scénario ou le registre).
