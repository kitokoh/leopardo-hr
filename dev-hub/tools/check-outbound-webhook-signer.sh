#!/usr/bin/env bash
# check-outbound-webhook-signer.sh — garde CI BOS-021 (#8210)
#
# Le défaut que cette garde empêche de réapparaître : chaque nouvelle verticale
# transactionnelle recopiait sa propre primitive HMAC de webhook sortant
# (`hash_hmac('sha256', …)` en dur) au lieu de consommer le contrat partagé
# `App\Contracts\Queue\OutboundWebhookSigner`. Trois implémentations ont
# coexisté (Billing, Travel, Platform) ; Travel était la dernière, migrée par
# #8210.
#
# La duplication n'est pas le vrai coût : c'est la DIVERGENCE. Une copie peut
# oublier `hash_equals` (comparaison non constante → reconstruction octet par
# octet de la signature), ou changer d'algorithme sans versionner le contrat.
#
# Portée : uniquement les fichiers qui SIGNENT des webhooks SORTANTS. Les
# vérifications de webhooks ENTRANTS (Stripe, Chargily, Glovo, WhatsApp…) sont
# hors périmètre : elles vérifient une signature émise par un tiers, avec
# l'algorithme imposé par ce tiers — les router vers notre contrat n'aurait
# aucun sens.
#
# Usage : bash dev-hub/tools/check-outbound-webhook-signer.sh [api_dir]
# Sortie : 0 si aucun signeur hors contrat, 1 sinon.

set -euo pipefail

API_DIR="${1:-api}"
APP_DIR="${API_DIR}/app"

if [[ ! -d "${APP_DIR}" ]]; then
  echo "❌  Répertoire '${APP_DIR}' introuvable." >&2
  exit 1
fi

# Fichiers autorisés à appeler hash_hmac pour un webhook SORTANT :
#   - l'implémentation unique du contrat (c'est SON travail) ;
#   - les producteurs dont la chaîne signée est un contrat public figé et qui
#     passent DÉJÀ par le contrat (aucun hash_hmac direct attendu) — donc rien
#     à exempter ici.
ALLOWED=(
  "app/Jobs/Services/HmacWebhookSigner.php"
)

# Motifs de détection : un fichier « sortant » est un fichier dont le nom ou le
# chemin évoque un webhook sortant ET qui contient un hash_hmac direct.
is_outbound_webhook_file() {
  local path="$1"
  case "${path}" in
    *WebhookDispatcher.php|*WebhookEnvelopeBuilder.php|*WebhookSigner.php|*OutboundWebhook*.php)
      return 0
      ;;
  esac
  return 1
}

violations=()

while IFS= read -r file; do
  rel="${file#"${API_DIR}/"}"

  # Exemptions explicites.
  skip=0
  for allowed in "${ALLOWED[@]}"; do
    if [[ "${rel}" == "${allowed}" ]]; then
      skip=1
      break
    fi
  done
  [[ "${skip}" -eq 1 ]] && continue

  is_outbound_webhook_file "${rel}" || continue

  if grep -qE "hash_hmac\(" "${file}"; then
    violations+=("${rel}")
  fi
done < <(find "${APP_DIR}" -name "*.php" -type f | sort)

if [[ ${#violations[@]} -gt 0 ]]; then
  echo "❌  Webhook sortant signé hors du contrat partagé (BOS-021, #8210) :"
  for v in "${violations[@]}"; do
    echo "    - ${v}"
  done
  echo ""
  echo "    Injectez App\\Contracts\\Queue\\OutboundWebhookSigner et appelez"
  echo "    \$signer->sign(\$secret, \$signedPayload) au lieu de hash_hmac()."
  echo "    La chaîne signée reste propre au producteur : seule la primitive"
  echo "    cryptographique est mutualisée."
  exit 1
fi

echo "✅  Aucun webhook sortant ne signe hors du contrat OutboundWebhookSigner."
