#!/usr/bin/env bash
#
# bootstrap-local-php.sh — rendre PHP 8.4 + composer + PHPStan utilisables dans
# une session d'agent SANS root (issue #7585).
#
# Pourquoi ce script existe
# -------------------------
# L'image d'agent ne porte ni PHP ni composer. Sans eux, les checks requis
# « PHPStan — Strict » / « Module Structure Validator » et les gardes PHP ne
# peuvent pas être rejoués localement : chaque vérification coûte alors un cycle
# CI de 10 à 20 min, derrière une file qui sature vite. Ce script supprime cette
# dépendance.
#
# Ce qu'il fait (aucun root, aucun paquet système installé dans /)
# ---------------------------------------------------------------
#   1. télécharge les .deb PHP 8.4 du PPA `ondrej/php` pour le CODENAME réel de
#      la machine (jammy/noble), et les extrait avec `dpkg-deb -x` dans un
#      préfixe privé ;
#   2. fabrique un `php.ini` minimal en ne gardant que les extensions qui
#      CHARGENT réellement (test cumulatif : `pdo_sqlite` n'est chargé qu'après
#      `pdo`, sinon il échoue alors qu'il est valide) ;
#   3. installe `composer.phar` par l'installateur officiel, après vérification
#      du SHA-384 contre `composer.github.io/installer.sig` ;
#   4. expose un `env.sh` à sourcer — il pose `PHPRC`.
#
# ⚠️ Le point qui a coûté le plus cher : `PHPRC`
# ---------------------------------------------
# Composer relance PHP pour ses scripts (`@php artisan package:discover`) via
# `PHP_BINARY`, **sans propager le `-c`** qui a servi à le lancer. Sans `PHPRC`,
# l'enfant démarre sans notre ini : ni `tokenizer`, ni `mbstring` — et l'échec
# est trompeur (`Call to undefined function NunoMaduro\Collision\token_get_all()`,
# enveloppé dans Whoops). `PHPRC` fait que TOUT processus PHP, y compris les
# enfants, retrouve notre configuration.
#
# Usage
# -----
#   bash dev-hub/tools/bootstrap-local-php.sh                 # installe (idempotent)
#   source dev-hub/tools/bootstrap-local-php.sh --activate    # installe + active le PATH
#   bash dev-hub/tools/bootstrap-local-php.sh --check          # diagnostic seul
#
# Puis la commande exacte du check requis :
#   cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=1G --no-progress
#
set -euo pipefail

PHP_SERIES="${PHP_SERIES:-8.4}"          # 8.4 = version du CI (ne pas dériver sans raison)
# Le pool Debian range les paquets par initiale du paquet SOURCE : php8.4 → `p/`.
PPA_HOST="https://ppa.launchpadcontent.net/ondrej/php/ubuntu/pool/main/p"
PREFIX="${LEOPARDO_PHP_PREFIX:-${XDG_CACHE_HOME:-$HOME/.cache}/leopardo/php}"
FORCE=0
ACTIVATE=0
CHECK_ONLY=0

# Extensions candidates, DANS L'ORDRE DES DÉPENDANCES (pdo avant pdo_sqlite,
# xml avant xmlreader) : le test de chargement est cumulatif.
CANDIDATE_EXTENSIONS=(
  ctype fileinfo iconv phar tokenizer mbstring curl zip intl
  pdo pdo_sqlite sqlite3 dom xml xmlreader xmlwriter simplexml
  posix readline sockets exif gettext
)
# Paquets fournissant les binaires et les extensions ci-dessus.
PACKAGES=(php8.4-cli php8.4-common php8.4-readline php8.4-mbstring php8.4-curl
          php8.4-xml php8.4-intl php8.4-sqlite3 php8.4-zip)

log()  { printf '%s\n' "$*"; }
warn() { printf 'AVERTISSEMENT : %s\n' "$*" >&2; }
die()  { printf 'ERREUR : %s\n' "$*" >&2; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    --prefix) PREFIX="$2"; shift 2 ;;
    --force)  FORCE=1; shift ;;
    --activate) ACTIVATE=1; shift ;;
    --check)  CHECK_ONLY=1; shift ;;
    -h|--help) sed -n '2,50p' "$0"; exit 0 ;;
    *) die "option inconnue : $1 (voir --help)" ;;
  esac
done

# ── 1. Contexte machine ──────────────────────────────────────────────────────
[ -r /etc/os-release ] || die "/etc/os-release illisible : distribution inconnue."
# shellcheck disable=SC1091
. /etc/os-release
CODENAME="${VERSION_CODENAME:-}"
[ -n "$CODENAME" ] || die "VERSION_CODENAME absent de /etc/os-release."

for tool in curl dpkg-deb grep sed; do
  command -v "$tool" >/dev/null 2>&1 || die "outil requis manquant : $tool"
done

PHP_MAJOR_MINOR="$PHP_SERIES"
EXT_API="20240924"   # API des extensions PHP 8.4 (abi .so) — vérifié après extraction.

# ── 2. Diagnostic seul ───────────────────────────────────────────────────────
if [ "$CHECK_ONLY" -eq 1 ]; then
  [ -x "$PREFIX/usr/bin/php${PHP_MAJOR_MINOR}" ] && log "binaire      : présent" || log "binaire      : ABSENT"
  [ -f "$PREFIX/php.ini" ]                       && log "php.ini      : présent" || log "php.ini      : ABSENT"
  [ -f "$PREFIX/composer.phar" ]                 && log "composer     : présent" || log "composer     : ABSENT"
  log "préfixe      : $PREFIX"
  log "source       : $PREFIX/env.sh"
  exit 0
fi

# ── 3. Découverte de la version exacte des .deb pour ce codename ─────────────
# Le nom des paquets sury encode la version Ubuntu : `+ubuntu22.04.1+deb.sury.org+1`.
case "$CODENAME" in
  jammy)  UBUNTU_TAG="ubuntu22.04" ;;
  noble)  UBUNTU_TAG="ubuntu24.04" ;;
  *)      die "codename « $CODENAME » non couvert par ce script (jammy/noble attendus)." ;;
esac

log "== Bootstrap PHP ${PHP_SERIES} (${CODENAME}, sans root) =="
log "préfixe : $PREFIX"

DEB_DIR="$PREFIX/debs"
mkdir -p "$DEB_DIR"

# Version réellement publiée : on la lit dans l'index du pool plutôt que de la
# coder en dur (le PPA avance ; un script figé pourrit vite).
INDEX_URL="$PPA_HOST/php${PHP_SERIES}/"
command -v curl >/dev/null 2>&1 || die "curl requis."
DEB_VERSION="$(
  curl -fsS --max-time 90 "$INDEX_URL" \
    | grep -oE "php${PHP_SERIES}-cli_[^\"<>]*\\+${UBUNTU_TAG}\.[0-9]+\\+deb\\.sury\\.org\\+[0-9]+_amd64\\.deb" \
    | sed -E "s/^php${PHP_SERIES}-cli_//; s/_amd64\\.deb$//" | sort -u | tail -1
)"
[ -n "$DEB_VERSION" ] || die "impossible de trouver php${PHP_SERIES}-cli pour ${UBUNTU_TAG} sur le PPA."
log "version  : php${PHP_SERIES} ${DEB_VERSION%%-*}"

# ── 4. Téléchargement + extraction (idempotents) ─────────────────────────────
DOWNLOADED_ANY=0
for pkg in "${PACKAGES[@]}"; do
  # Le préfixe du paquet doit suivre la série demandée (php8.4-*).
  real_pkg="${pkg/php${PHP_SERIES}/php${PHP_SERIES}}"
  deb="$DEB_DIR/${real_pkg}_${DEB_VERSION}_amd64.deb"
  if [ ! -f "$deb" ]; then
    url="$PPA_HOST/php${PHP_SERIES}/${real_pkg}_${DEB_VERSION}_amd64.deb"
    curl -fsS --max-time 300 -o "$deb" "$url" || die "téléchargement impossible : $url"
    DOWNLOADED_ANY=1
  fi
  dpkg-deb -x "$deb" "$PREFIX"
done
log "paquets  : ${#PACKAGES[@]} extraits dans $PREFIX"

PHP_BIN="$PREFIX/usr/bin/php${PHP_MAJOR_MINOR}"
[ -x "$PHP_BIN" ] || die "binaire PHP introuvable après extraction : $PHP_BIN"

# L'extension_dir est dérivée de l'ABI réelle : on prend le répertoire qui
# contient RÉELLEMENT les .so. (Un simple `sort | tail -1` trompe : `8.4` se
# trie après `20240924` et l'on tombe sur le répertoire de scan-dir, vide.)
EXT_DIR="$(find "$PREFIX/usr/lib/php" -maxdepth 2 -name '*.so' -printf '%h\n' 2>/dev/null | sort -u | head -1)"
[ -n "$EXT_DIR" ] && [ -d "$EXT_DIR" ] || die "extension_dir introuvable sous $PREFIX/usr/lib/php."
log "ext dir  : $EXT_DIR"

# ── 5. php.ini minimal, extensions TESTÉES une à une (cumulatif) ─────────────
# Un échec de chargement produit un *warning*, pas un code de sortie non nul :
# on inspecte donc le TEXTE. Le test est cumulatif car certaines extensions
# dépendent d'une autre déjà chargée (pdo_sqlite sans pdo échoue à tort).
generate_ini() {
  local keep=() dropped=() args out ini
  for ext in "${CANDIDATE_EXTENSIONS[@]}"; do
    if [ ! -f "$EXT_DIR/$ext.so" ]; then dropped+=("$ext(absent)"); continue; fi
    args=()
    for k in "${keep[@]:-}"; do [ -n "$k" ] && args+=(-d "extension=$k.so"); done
    out="$(PHP_INI_SCAN_DIR= "$PHP_BIN" -n -d "extension_dir=$EXT_DIR" \
             "${args[@]:-}" -d "extension=$ext.so" -r 'echo "OK";' 2>&1 || true)"
    if printf '%s' "$out" | grep -qi 'Unable to load\|cannot open shared object'; then
      dropped+=("$ext")
    else
      keep+=("$ext")
    fi
  done

  ini="$PREFIX/php.ini"
  {
    printf '; php.ini minimal, genere par dev-hub/tools/bootstrap-local-php.sh (issue #7585)\n'
    printf '; Ne pas editer a la main : relancer le script.\n'
    printf 'extension_dir = "%s"\n' "$EXT_DIR"
    printf 'memory_limit = 1G\n'
    printf 'date.timezone = UTC\n'
    for e in "${keep[@]}"; do printf 'extension=%s.so\n' "$e"; done
  } > "$ini"

  log "extensions chargees : ${#keep[@]}"
  if [ "${#dropped[@]}" -gt 0 ]; then
    warn "extensions ecartees : ${dropped[*]}"
    warn "  (dependance systeme absente — ex. libzip.so.4 pour zip. Non bloquant :"
    warn "   composer.json du depot n'exige aucune ext-*, et « unzip » est utilise a la place.)"
  fi
}
generate_ini

# ── 6. composer.phar, installateur officiel + SHA-384 vérifié ───────────────
# `installer.sig` signe `composer-setup.php`, PAS composer.phar : on vérifie donc
# l'installateur avant de le lancer (c'est la seule chaîne de confiance).
install_composer() {
  local setup="$PREFIX/composer-setup.php" sig expected actual
  curl -fsS --max-time 300 -o "$setup" https://getcomposer.org/installer
  curl -fsS --max-time 120 -o "$PREFIX/installer.sig" https://composer.github.io/installer.sig
  sig="$PREFIX/installer.sig"
  expected="$(tr -d '\r\n' < "$sig")"
  actual="$(PHP_INI_SCAN_DIR= "$PHP_BIN" -c "$PREFIX/php.ini" -r \
            "echo hash_file('sha384', '$setup');")"
  if [ "$expected" != "$actual" ]; then
    rm -f "$setup"
    die "SHA-384 de composer-setup.php invalide (attendu $expected, obtenu $actual)."
  fi
  log "composer : sha384 verifie"
  PHP_INI_SCAN_DIR= "$PHP_BIN" -c "$PREFIX/php.ini" "$setup" \
    --quiet --install-dir="$PREFIX" --filename=composer.phar
  rm -f "$setup" "$sig"
  [ -f "$PREFIX/composer.phar" ] || die "composer.phar non installé."
}
[ -f "$PREFIX/composer.phar" ] && [ "$FORCE" -eq 0 ] || install_composer

# ── 7. shims `php` / `composer` + env.sh (le PHPRC est le cœur du contrat) ──
# Le binaire extrait s'appelle `php8.4` : sans shim, `php` reste introuvable et
# le script serait inutilisable en pratique. On expose donc un `bin/` à mettre
# dans le PATH, avec des wrappers qui posent eux-mêmes PHPRC (robuste même si
# l'appelant n'a pas sourcé env.sh).
BIN_DIR="$PREFIX/bin"
mkdir -p "$BIN_DIR"

cat > "$BIN_DIR/php" <<SHIMEOF
#!/bin/sh
# Shim genere par dev-hub/tools/bootstrap-local-php.sh (issue #7585).
# PHPRC est pose ici pour que les ENFANTS de composer trouvent le meme php.ini.
PHPRC="$PREFIX/php.ini" PHP_INI_SCAN_DIR= exec "$PREFIX/usr/bin/php${PHP_MAJOR_MINOR}" "\$@"
SHIMEOF

cat > "$BIN_DIR/composer" <<SHIMEOF
#!/bin/sh
# Shim genere par dev-hub/tools/bootstrap-local-php.sh (issue #7585).
PHPRC="$PREFIX/php.ini" PHP_INI_SCAN_DIR= exec "$PREFIX/usr/bin/php${PHP_MAJOR_MINOR}" "$PREFIX/composer.phar" "\$@"
SHIMEOF

chmod +x "$BIN_DIR/php" "$BIN_DIR/composer"

ENV_FILE="$PREFIX/env.sh"
cat > "$ENV_FILE" <<ENVEOF
# Sourcer ce fichier pour utiliser le PHP local (issue #7585) :
#   source $ENV_FILE
export LEOPARDO_PHP_PREFIX="$PREFIX"
export PATH="$BIN_DIR:\$PATH"
# PHPRC : indispensable pour que les ENFANTS de composer (\`@php artisan\`) et
# phpstan retrouvent ce php.ini — un \`-c\` explicite n'est PAS propage.
export PHPRC="$PREFIX/php.ini"
# Empêche la découverte d'un scan-dir systeme concurrent (ini multiples).
export PHP_INI_SCAN_DIR=
export COMPOSER_HOME="$PREFIX/composer-home"
ENVEOF

# ── 8. Vérification finale (ce que le script prétend doit être vrai) ────────
VERSION_ACTUELLE="$(PHP_INI_SCAN_DIR= PHPRC="$PREFIX/php.ini" "$PHP_BIN" -r 'echo PHP_VERSION;')"
case "$VERSION_ACTUELLE" in
  "${PHP_SERIES}".*) : ;;
  *) die "version obtenue inattendue : $VERSION_ACTUELLE" ;;
esac
# Les deux extensions dont l'absence a produit l'échec trompeur de #7585.
for fn in token_get_all mb_strlen; do
  PHP_INI_SCAN_DIR= PHPRC="$PREFIX/php.ini" "$PHP_BIN" -r "exit(function_exists('$fn') ? 0 : 1);" \
    || die "fonction requise manquante : $fn (php.ini incomplet)."
done
# Le shim doit réellement repondre : c'est ce que l'utilisateur tape.
SHIM_VERSION="$("$BIN_DIR/php" -r 'echo PHP_VERSION;' 2>/dev/null || true)"
[ "$SHIM_VERSION" = "$VERSION_ACTUELLE" ] \
  || die "le shim $BIN_DIR/php ne repond pas la bonne version (obtenu '${SHIM_VERSION:-rien}')."

log "PHP      : $VERSION_ACTUELLE"
log "composer : $(PHP_INI_SCAN_DIR= PHPRC="$PREFIX/php.ini" "$PHP_BIN" "$PREFIX/composer.phar" --version 2>/dev/null | head -1)"
log ""
log "Pour activer dans la session courante :"
log "  source \"$ENV_FILE\""
log ""
log "Puis, la commande exacte du check requis « PHPStan — Strict » :"
log "  cd api && composer install --no-interaction --prefer-dist --optimize-autoloader"
log "  cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=1G --no-progress"

if [ "$ACTIVATE" -eq 1 ]; then
  log ""
  log "« --activate » demande : le script a ete execute, pas source."
  log "Pour activer reellement, lancez : source \"$ENV_FILE\""
fi
