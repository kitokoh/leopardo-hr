# Spec Kit — Gate Dart incrémental

## Contexte

Le job `Mobile Apps CI - Flutter` exécutait `dart format . --set-exit-if-changed` pour chaque application. La PR I18N #4764 a révélé 49 fichiers Dart historiques non formatés dans `leopardo_core`, alors qu’elle ne modifiait que des catalogues ARB générés.

## Objectif

Conserver un contrôle strict sur tout nouveau changement Dart sans faire échouer une PR qui ne touche pas au Dart à cause d’une dette historique préexistante.

## Changement

Le workflow calcule les fichiers `*.dart` modifiés entre le SHA de base et le SHA courant, ou entre les deux derniers commits sur un push sans SHA de base. `dart format --set-exit-if-changed` est ensuite exécuté uniquement sur cette liste. Si aucun fichier Dart n’a changé, l’étape est explicitement verte et l’analyse Flutter continue.

## Critères d’acceptation

- Le YAML reste valide et actionlint passe.
- Une PR qui modifie un Dart mal formaté échoue au formatage.
- Une PR qui ne modifie que des ARB ne formate pas les 95 fichiers Dart historiques.
- Un push `main` avec des fichiers Dart mal formatés échoue.
- Un commit sans fichier Dart affiche une décision explicite et poursuit `flutter analyze`.
- Aucun code métier mobile n’est modifié.

## Alignement du validateur mobile

Le même run CI a révélé un faux rouge Plan 28 : le validateur cherchait littéralement `appdistribution:releases:list` dans chaque workflow alors que la logique a été extraite dans `dev-hub/tools/verify-firebase-readback.sh` par #4723. Le validateur accepte désormais soit la commande directe, soit l’appel au helper partagé, et vérifie dans ce dernier cas que le helper contient bien la commande Firebase réelle. La preuve read-after-write n’est donc pas affaiblie.

## Validation locale

La logique Bash a été relue avec `set -euo pipefail`; le diff utilise `git diff -z` et `xargs -0` pour préserver les chemins. Le validateur PowerShell conserve les contrôles de secrets, de packages Firebase et de documentation. La validation distante actionlint et le run matrix Flutter restent requis.
