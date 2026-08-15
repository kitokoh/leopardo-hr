# Mini-spec — Issue #3741

## Intention
Rendre l’installation Edge déterministe : tous les fichiers montés par Docker Compose doivent être téléchargés, vérifiés et présents dans le répertoire d’installation.

## Changements

| Élément | Correction |
|---|---|
| Caddyfile | Nouvel endpoint public `/api/v1/edge/download/Caddyfile.edge` et téléchargement par `install.sh` |
| Vérification | L’installateur refuse un fichier vide ou dépourvu des reverse-proxy `edge-api:80` et `edge-ui:3000` |
| Compose | Les blocs `build` dépendant de contextes absents sont supprimés ; les images préconstruites sont utilisées après `docker compose pull` |
| Montage | `./Caddyfile.edge` existe désormais dans `/opt/leopardo-edge` avant `docker compose up -d` |

## Critères d’acceptation

L’installation ne démarre pas si le compose ou le Caddyfile ne sont pas téléchargés. Le Caddyfile réellement monté par `edge-proxy` est fourni par le backend. Le compose téléchargé ne tente plus de construire depuis `..` ou `../front/web`, chemins qui n’existent pas dans une installation autonome.

## Validation

`bash -n edge/install.sh`, `php -l` du contrôleur et `git diff --check` passent localement. La validation `docker compose config` n’a pas pu être exécutée car Docker n’est pas installé dans le sandbox ; elle devra être vérifiée par la CI ou le runner de déploiement.
