# Issue #3964 — install.sh : `curl | sh` pour Docker

## Problème

`edge/install.sh` exécutait `curl -fsSL https://get.docker.com | sh` en root :
un téléchargement partiel/échoué (réseau, MITM, réponse tronquée) était
interprété directement. (`set -euo pipefail` est déjà en place depuis #3792 —
le volet restant est le pipe direct.)

## Correctif

Téléchargement vers un fichier temporaire → vérifications (non-vide + shebang
`#!/bin/sh`) → exécution → nettoyage. Échec fail-closed avec message explicite.

## Critères de succès

1. `bash -n edge/install.sh` : 0 erreur.
2. `grep 'curl.*|.*sh' edge/install.sh` : 0 occurrence.
3. Une réponse vide/partielle → exit 1 sans exécution.
