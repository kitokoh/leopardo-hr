#!/usr/bin/env bash
# check-env-example-safety.sh — Garde anti-régression .env.example (#1605).
#
# Le parcours onboarding (docker compose + cp .env.example .env) était rouge
# pendant des jours à cause de valeurs invalides dans .env.example :
#   - expressions PHP `::class` stockées comme chaînes (AUTH_MODEL, handlers
#     Monolog) → instanceof/classes inexistantes en prod locale ;
#   - placeholders réseau (VOTRE_HOST, CHANGER_...) dans des clés sensibles
#     (REDIS_URL → getaddrinfo VOTRE_HOST.upstash.io → 500 sur toute requête).
#
# Vérifications :
#   1. le fichier est parsable par phpdotenv (Dotenv::createImmutable()->load()) ;
#   2. aucune valeur active ne contient `::class` ;
#   3. aucun placeholder réseau dans les valeurs actives des clés sensibles.
#
# Usage : dev-hub/tools/check-env-example-safety.sh [api_dir]
#   api_dir : racine du backend Laravel (défaut : api)
set -euo pipefail

API_DIR="${1:-api}"
ENV_FILE="${API_DIR}/.env.example"

if [[ ! -f "${ENV_FILE}" ]]; then
  echo "::error::${ENV_FILE} introuvable"
  exit 1
fi

fail=0

# ── 1. Parse phpdotenv (mêmes règles que le bootstrap Laravel) ──────────────
if command -v php >/dev/null 2>&1 && [[ -d "${API_DIR}/vendor" ]]; then
  if ! ( cd "${API_DIR}" && php -r '
      require "vendor/autoload.php";
      $env = \Dotenv\Dotenv::createImmutable(getcwd(), ".env.example");
      $env->safeLoad();
      echo "phpdotenv parse OK\n";
  ' >/dev/null 2>&1 ); then
    echo "::error::.env.example invalide pour phpdotenv — le bootstrap Laravel échouerait (issue #1605)."
    ( cd "${API_DIR}" && php -r '
      require "vendor/autoload.php";
      \Dotenv\Dotenv::createImmutable(getcwd(), ".env.example")->load();
    ' 2>&1 | tail -3 | sed 's/^/::error::  /' ) || true
    fail=1
  else
    echo "✓ phpdotenv : .env.example parsable"
  fi
else
  echo "::warning::php/vendor indisponible — parse phpdotenv ignoré"
fi

# ── 2. Valeurs `::class` actives ─────────────────────────────────────────────
class_matches=$(grep -E "^[A-Z0-9_]+=" "${ENV_FILE}" | grep "::class" || true)
if [[ -n "${class_matches}" ]]; then
  echo "::error::Valeurs '::class' interdites dans .env.example (chaîne inutilisable, cf. #1605) :"
  sed 's/^/::error::  /' <<< "${class_matches}"
  fail=1
else
  echo "✓ Aucune valeur '::class' active"
fi

# ── 3. Placeholders réseau dans les clés sensibles ──────────────────────────
# Clés dont un placeholder casse le BOOT ou le parcours onboarding (docker
# compose les surcharge pour DB_* ; MAIL_* n'est utilisé qu'à l'envoi).
SENSITIVE_KEYS='REDIS_URL|REDIS_HOST|REDIS_PORT|REDIS_PASSWORD|DB_HOST|DB_USERNAME|DB_DATABASE|AUTH_MODEL|AWS_ACCESS_KEY_ID|AWS_SECRET_ACCESS_KEY|SENTRY_DSN|SENTRY_LARAVEL_DSN|PAYMENT_|STRIPE_|PAPERTRAIL_URL'
# Clés tolérées : placeholder de documentation, surchargées par l'env (compose/Render).
ALLOWED_PLACEHOLDER_KEYS='DB_PASSWORD|MAIL_HOST|MAIL_USERNAME|MAIL_PASSWORD'
placeholder_matches=$(grep -E "^(${SENSITIVE_KEYS})=" "${ENV_FILE}" | grep -E "VOTRE_|CHANGER_|VOTRE_HOST|upstash|XXX|YOUR_" || true)
if [[ -n "${placeholder_matches}" ]]; then
  echo "::error::Placeholders réseau dans des clés sensibles de .env.example (le onboarding planterait, cf. #1605) :"
  sed 's/^/::error::  /' <<< "${placeholder_matches}"
  fail=1
else
  echo "✓ Aucun placeholder réseau dans les clés bloquantes"
fi

warn_matches=$(grep -E "^(${ALLOWED_PLACEHOLDER_KEYS})=" "${ENV_FILE}" | grep -E "VOTRE_|CHANGER_|VOTRE_HOST|upstash|XXX|YOUR_" || true)
if [[ -n "${warn_matches}" ]]; then
  echo "::warning::Placeholders dans des clés tolérées (surchargées par l'env) :"
  sed 's/^/::warning::  /' <<< "${warn_matches}"
fi

if [[ "${fail}" -eq 1 ]]; then
  echo "::error::Garde check-env-example-safety : échec (issues #1591/#1605)."
  exit 1
fi

echo "✓ check-env-example-safety : .env.example sain."
exit 0
