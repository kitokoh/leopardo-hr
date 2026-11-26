#!/usr/bin/env bash
# check-env-example-parity.sh — Parité `api/config/` ↔ `api/.env.example`.
#
# Usage : dev-hub/tools/check-env-example-parity.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
#
# ## Historique (à lire avant de « réparer » cette garde)
#
# Cette garde a été introduite par l'issue #1487 pour vérifier qu'une clé
# `env('X')` de `api/config/` est documentée dans `.env.example`. Elle faisait
# cela avec `grep -rhoE "env\("` sur tout `api/config/`, **commentaires PHP
# compris**.
#
# Conséquence mesurée (audit architecture 2026-11-26) : `config/database.php`
# et `config/mail.php` contiennent des clés **commentées** (`DB_ENCRYPT`,
# `DB_TRUST_SERVER_CERTIFICATE`, `POSTMARK_MESSAGE_STREAM_ID`). Le `grep` les
# comptait comme vivantes, donc la garde EXIGEAIT de documenter dans
# `.env.example` des variables que l'application ne lit pas — c'est-à-dire
# exactement l'inverse de son but. Un opérateur réglait `DB_ENCRYPT=yes` en
# croyant chiffrer la connexion ; rien ne se passait.
#
# ## Ce que fait cette garde maintenant
#
# Elle délègue à `check-env-example-parity.py`, qui :
#   - retire les commentaires PHP avant d'extraire les `env()` (donc ne compte
#     que les clés réellement lues) ;
#   - vérifie la parité **dans les deux sens** (lue non documentée ET
#     documentée non lue) ;
#   - porte une allow-list qui EXIGE une raison pour chaque exception ;
#   - a un `--self-test` (une garde cassée ne peut pas passer pour un vert).
#
# Le nom et l'interface (`[api_dir]`) sont conservés : `architecture-check.yml`
# appelle ce script depuis #1487 et il ne doit pas changer de contrat.
set -euo pipefail

API_DIR="${1:-api}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

if [[ ! -d "${ROOT}/${API_DIR}/config" ]]; then
  echo "::error::${API_DIR}/config introuvable — API_DIR invalide ?"
  exit 1
fi

if [[ "${API_DIR}" != "api" ]]; then
  echo "::error::check-env-example-parity.py ne gère que 'api' (reçu : '${API_DIR}')."
  echo "::error::Étendre le garde Python si un second backend apparaît."
  exit 1
fi

# Le garde Python résout ses chemins depuis la racine du dépôt ; on s'y place
# pour que `[api_dir]` reste un simple préfixe relatif.
cd "${ROOT}"

exec python3 dev-hub/tools/check-env-example-parity.py
