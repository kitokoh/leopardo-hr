## Contexte

Sur `main`, la sidebar et la palette de commandes de `admin-dashboard` affichent des **entrées sans libellé**. Le E2E `front/admin-dashboard/e2e/sidebar-unique-entries.spec.js` échoue sur ce point, et `web-ci.yml` est **rouge sur 6 runs consécutifs de `main`** — donc sur toutes les PR ouvertes.

## Cause racine (mesurée, pas supposée)

`git log -S '"team": "Équipe plateforme"'` → le merge `8890f8ba3` a fait passer le bloc `navigation` de `shared/i18n/locales/*.json` de **72 à 39 clés**. Son « deep-merge » a **remplacé** le bloc au lieu de le fusionner.

`shared/i18n/sync/sync-web.js` écrit `deepMerge(adminCurrent, sharedData)`, où **la valeur partagée gagne** (design #3853). La perte dans `shared` a donc été **propagée** au catalogue `front/admin-dashboard/src/i18n/locales/*.json`, pourtant déclaré **UNION** (censé préserver les clés admin-only).

`translate()` retourne `''` quand une clé manque → lignes vides.

**28 clés cassées** sur les 82 déclarées par `navigation.js` (68 `titleKey` + 14 `descKey`) : « Équipe plateforme » (#7557), « Comptabilité » et ses 9 sous-écrans, « Stations-service », « Site vitrine », « Marketing OS », « Funnel d'acquisition »… et les 7 titres de groupes.

## Décisions

**1. La réparation va dans le catalogue admin, pas dans `shared`.**
Ces entrées sont admin-only : « Équipe plateforme », « Marketing OS », « Plan comptable » n'ont aucun sens pour le portail client. Les remettre dans la source canonique les ferait livrer à `front/web` par `sync-web.js`, qui écrit le catalogue partagé **pur** — on aurait échangé un bug d'affichage contre de la pollution de contrat client. Le bloc `navigation` admin redevient la source de vérité pour ses propres clés, conformément au design #3853.

**2. Les 4 locales sont complétées, pas seulement le FR.**
`shared/i18n/validators/validate.js` impose la **parité stricte** des clés (`missing key` / `orphan key`). N'ajouter qu'au FR aurait rendu `validate-and-sync` rouge immédiatement. EN/AR/TR reçoivent une traduction réelle, pas un repli sur le français.

**3. Vérification par la garde du dépôt, pas à l'œil.**
Contrôle programmatique : **0 clé manquante ou vide** dans les 4 locales (contre 28 avant).

## Preuves

| Commande | Résultat |
|---|---|
| `node shared/i18n/validators/validate.js` | `I18N_VALIDATION_OK (4 locales)` |
| `bash dev-hub/tools/check-i18n-catalog-parity.sh` | parité OK (backend, shared, ARB mobile) |
| `python3 dev-hub/tools/check-i18n-locale-parity.py` | `7168 clés, 0 manquante, 0 surnuméraire` ×4 |
| `node shared/i18n/sync/sync-web.js` ×2 | `git status` inchangé → idempotent |

## Portée

5 fichiers, **+129 / −9**, **aucun changement de code de production** (catalogues i18n + `versions.json` régénéré par le script officiel).

## Ce que ce lot ne fait pas

Il ne corrige pas l'**origine** de la perte : `sync-web.js` continue d'écraser les clés admin-only quand `shared` en perd une. Un garde-fou (comparer le nombre de clés `navigation` avant/après sync, ou ne jamais supprimer une clé admin) mérite un lot dédié ; il est hors périmètre ici et doit être suivi comme dette.
