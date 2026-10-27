# D-8718-1 — La couverture doit être mesurée même quand les tests échouent

**Issues** #8718 (écart T-1), #8396 (dette de test) · **Branche** `ci/8718-coverage-measurable`
**Date** 2026-10-27 · **Statut** Implémenté

---

## Le symptôme rapporté

« `Backend Coverage` ne tourne pas sur les PRs — c'est pour ça que la dette de
test (#8396) doit être reconstituée à la main à chaque fois. »

## Ce que j'ai mesuré, et qui contredit l'énoncé

Le workflow est **correctement câblé sur les PRs**. Vérifié dans le fichier et
dans l'historique d'exécution :

- `on: pull_request: branches: [main]` — présent ;
- `paths:` **volontairement absent** (commentaire PA2-OPS-003 : un workflow
  filtré par `paths:` ne poste jamais de statut → pending à vie) ;
- `detect-changes` saute le job lourd quand `api/` n'est pas touché, mais
  **poste quand même un statut vert en ~1 min**.

Le workflow tourne donc. Il ne **produit rien d'utile**.

## La vraie cause, en trois défauts cumulés

Mesuré sur le run `40370012093` (push sur `main`) :

```
Tests:    288 failed, 7 risky, 12 skipped, 9063 passed (42850 assertions)
##[error]Process completed with exit code 2.
```

**1. Un seul test en échec supprime toute la mesure de couverture.**

```bash
php artisan test --coverage-clover=... 2>&1 | tee summary.txt
test_exit=${PIPESTATUS[0]}
if [[ "${test_exit}" -ne 0 ]]; then
  exit "${test_exit}"      # ← le clover n'est jamais écrit
fi
```

`--coverage-clover` n'écrit le fichier qu'à la **fin** d'un run réussi. Sur 288
échecs, `clover.xml` n'existe pas. Or la couverture est précisément ce qu'on
veut connaître **quand** des tests cassent.

**2. Le run relançait la suite complète une seconde fois.**

Le premier passage n'émettait pas de JUnit ; un second passage complet était
lancé pour l'obtenir. Deux exécutions de **4 h 47** = **9 h 30 de runner** par
PR touchant `api/`, pour un check **non requis** au merge. C'est la famine de
runners décrite en #6928, aggravée.

**3. La gate de seuil échouait sur une erreur de script, jamais sur un verdict.**

`clover.xml` absent → `steps.coverage.outputs.percentage` **vide** →
`bc -l` reçoit `"" >= 65` → sort en erreur → le check tombe.

Autrement dit : depuis l'apparition des 288 échecs, le check « Backend
Coverage » n'a **jamais rendu un verdict de couverture**. Il échouait sur une
erreur de script, ce qui est indiscernable d'un échec de seuil dans l'UI.

## Décision

**D-8718-1 — La couverture est un INDICATEUR ; elle doit être mesurée même
quand la suite n'est pas verte.**

Changements :

| Avant | Après |
|---|---|
| `exit` au premier échec de test | `set +e` → la suite va au bout, clover écrit |
| 2 passages complets | 1 passage (`--log-junit` dans le même run) |
| `"" >= 65` → `bc` en erreur | garde explicite → `inconclusive=true` |
| échec indistinct | `tests_failed` + `inconclusive` exportés, warning explicite |

**Ce qui n'a PAS changé :** un seuil réellement non atteint fait **toujours**
échouer le job. Le garde `inconclusive` ne couvre que l'absence de mesure.

## Pourquoi ne pas rendre le check requis

Le check n'est pas dans les 4 contexts requis (`PHPStan Strict`, `Module
Validator`, `ESLint+TS`, `actionlint`). Le rendre requis avec 288 tests rouges
bloquerait **tous** les merges jusqu'à résorption complète de la dette — soit
plusieurs semaines d'arrêt du dépôt.

Je ne le fais pas. À la place, la couverture devient enfin **lisible** : c'est
la condition pour piloter #8396 par lots, avec un chiffre avant/après par lot,
au lieu de reconstituer la dette à la main.

## Ce que ça débloque concrètement

- Un chiffre de couverture **sur chaque PR** touchant `api/`, y compris rouge.
- Le commentaire de PR (déjà implémenté) peut enfin afficher le tableau
  par module au lieu de `_Couverture par module indisponible._`.
- Le coût runner par PR divisé par deux.

## Vérification

```
$ actionlint .github/workflows/coverage-gate.yml
  (aucune sortie = OK)

$ python3 -c "import yaml; yaml.safe_load(open('.github/workflows/coverage-gate.yml'))"
  OK
```

Le comportement réel sera attesté par le prochain run sur une PR touchant
`api/` : le job doit produire `clover.xml`, publier un pourcentage, et afficher
`Suite de tests en échec — couverture mesurée mais non attestante.`

## Suivi

La résorption des 288 échecs reste #8396. Ce correctif ne les traite pas : il
rend la dette **mesurable**, ce qui est la condition pour la traiter par lots.
