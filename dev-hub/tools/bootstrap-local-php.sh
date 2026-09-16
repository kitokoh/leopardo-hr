#!/usr/bin/env bash
#
# bootstrap-local-php.sh — PHP 8.4 + composer **portables et sans root**, pour
# rejouer en local les checks requis avant de pousser (issue #7585).
#
# Pourquoi : l'image d'agent n'a ni PHP ni composer (seulement node/python3/git).
# Sans outillage, chaque itération sur du code API coûtait un cycle CI de 10 à
# 20 min sur une file saturée — c'est le facteur qui allonge les sessions. La
# reconstitution tient en quelques minutes et ne consomme aucun runner.
#
# Méthode (validée, cf. docs/GOUVERNANCE/PROTOCOLE_LOTS_MULTI_AGENTS.md §6.4) :
#   1. lire l'index du PPA `ondrej` pour connaître la version **réellement
#      servie** (`apt-get download` échoue souvent sur un index périmé) ;
#   2. `dpkg -x` chaque .deb dans un préfixe temporaire (aucun droit root) ;
#   3. écrire un `php.ini` minimal (extension_dir + les extensions utiles) ;
#   4. télécharger `composer.phar` et installer `api/vendor` ;
#   5. rejouer la commande exacte du CI :
#        cd api && vendor/bin/phpstan analyse --configuration=phpstan-strict.neon \
#          --memory-limit=1G --no-progress
#
# Usage :
#   bash dev-hub/tools/bootstrap-local-php.sh              # installe (idempotent)
#   bash dev-hub/tools/bootstrap-local-php.sh --check      # l'installation répond-elle ?
#   bash dev-hub/tools/bootstrap-local-php.sh --verify     # joue la commande du CI
#   PHP84_PREFIX=/tmp/php84 bash dev-hub/tools/bootstrap-local-php.sh
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PREFIX="${PHP84_PREFIX:-$HOME/.cache/leopardo/php84}"
PHP_TARGET_VERSION="8.4"
POOL_BASE="https://ppa.launchpadcontent.net/ondrej/php/ubuntu/pool/main/p"
COMPOSER_URL="https://getcomposer.org/composer-stable.phar"

# Extensions nécessaires : `phar` (composer), `mbstring`/`curl`/`xml`/`intl`
# (Laravel + PHPStan), **`tokenizer`** (php-parser, dont PHPStan dépend : sans
# elle, `Class "PhpToken" not found` — elle manquait à la recette du protocole
# §6.4, constaté en testant `--verify`), `ctype`/`fileinfo`/`iconv` (Laravel),
# `pdo` **puis** `pdo_sqlite` (l'ordre compte — pdo_sqlite dépend de pdo.so),
# `sqlite3` pour les suites qui utilisent SQLite.
PKG_NAMES=(cli common opcache readline mbstring curl xml intl sqlite3)
PHP_EXT_LOAD=(phar mbstring curl xml intl tokenizer ctype fileinfo iconv pdo pdo_sqlite sqlite3)

log()  { printf '%s\n' "$*"; }
fail() { printf '::error::%s\n' "$*" >&2; exit 1; }

# ── Détection de la distribution (le PPA sert jammy ET noble) ────────────────
detect_codename() {
    if [[ -r /etc/os-release ]]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        log "${VERSION_CODENAME:-}"
    fi
}

CODENAME="$(detect_codename)"
CODENAME="${CODENAME:-jammy}"

# Le PPA nomme ses paquets par version d'Ubuntu, pas par codename :
# jammy -> ubuntu22.04, noble -> ubuntu24.04. Exemple de fichier réel :
#   php8.4-cli_8.4.25-1+ubuntu22.04.1+deb.sury.org+1_amd64.deb
ubuntu_tag() {
    case "$1" in
        jammy) printf 'ubuntu22.04' ;;
        noble) printf 'ubuntu24.04' ;;
        focal) printf 'ubuntu20.04' ;;
        *)     printf 'ubuntu22.04' ;;
    esac
}

UBUNTU_TAG="$(ubuntu_tag "$CODENAME")"

php_bin() { printf '%s' "$PREFIX/usr/bin/php${PHP_TARGET_VERSION}"; }
ini_file() { printf '%s' "$PREFIX/php.ini"; }

# Exécute le PHP portable **sans** hériter du `conf.d` du système : sans ça,
# PHP lit aussi /etc/php/8.4/cli/conf.d/*.ini et tente de charger des
# extensions qui n'existent pas dans le préfixe (bruit + échecs trompeurs).
# `PHP_INI_SCAN_DIR=''` désactive entièrement le répertoire de scan.
php_run() { PHP_INI_SCAN_DIR='' "$(php_bin)" -c "$(ini_file)" "$@"; }

# ── L'installation est-elle déjà opérationnelle ? ───────────────────────────
is_operational() {
    local bin
    bin="$(php_bin)"
    [[ -x "$bin" ]] || return 1
    php_run -r 'exit(extension_loaded("mbstring") && extension_loaded("pdo") && extension_loaded("phar") ? 0 : 1);' 2>/dev/null || return 1
    return 0
}

# ── 1. Résoudre la version réellement servie ────────────────────────────────
resolve_version() {
    local index="/tmp/leopardo-php84-index.html"

    if [[ ! -s "$index" ]]; then
        log "→ lecture de l'index du PPA (version réellement servie)…"
        curl -sS "$POOL_BASE/php${PHP_TARGET_VERSION}/" -o "$index" \
            || fail "index PPA injoignable : $POOL_BASE/php${PHP_TARGET_VERSION}/"
    fi

    # La version de `php8.4-cli` fait foi : toutes les autres doivent être
    # alignées sur elle (ABI identique), sinon les extensions ne se chargent pas.
    local v
    v="$(grep -oE "php${PHP_TARGET_VERSION}-cli_[^\" ]*${UBUNTU_TAG}[^\" ]*_amd64\.deb" "$index" \
        | sed -E "s/^php${PHP_TARGET_VERSION}-cli_//; s/_amd64\.deb\$//" \
        | sort -V | tail -1)"

    [[ -n "$v" ]] || fail "aucun paquet php${PHP_TARGET_VERSION}-cli pour '${CODENAME}' (${UBUNTU_TAG}) dans l'index du PPA"
    printf '%s' "$v"
}

# ── 2. Télécharger + extraire dans le préfixe (sans root) ───────────────────
extract_packages() {
    local version="$1"
    local dest="$PREFIX/debs"

    mkdir -p "$dest" "$PREFIX"
    log "→ téléchargement des paquets php${PHP_TARGET_VERSION} ${version} (${CODENAME})…"

    local name deb
    for name in "${PKG_NAMES[@]}"; do
        deb="php${PHP_TARGET_VERSION}-${name}_${version}_amd64.deb"
        if [[ ! -s "$dest/$deb" ]]; then
            curl -sSf "$POOL_BASE/php${PHP_TARGET_VERSION}/${deb}" -o "$dest/$deb" \
                || fail "téléchargement impossible : $deb"
        fi
        dpkg -x "$dest/$deb" "$PREFIX" \
            || fail "extraction impossible : $deb"
    done

    [[ -x "$(php_bin)" ]] || fail "paquet extrait mais $(php_bin) absent"
}

# ── 3. php.ini minimal (extension_dir + extensions, ordre significatif) ─────
write_ini() {
    # Le répertoire d'extensions est celui qui **contient réellement des .so** :
    # sous usr/lib/php cohabitent `8.4/` (qui ne porte que php.ini-* et sapi) et
    # le dossier d'API `20240924/` (les 30 extensions). Trier par nom choisit le
    # mauvais (`8.4` > `20240924` lexicographiquement) — on compte les .so.
    local ext_dir
    ext_dir="$(find "$PREFIX/usr/lib/php" -maxdepth 1 -mindepth 1 -type d 2>/dev/null \
        | while read -r d; do
              printf '%s %s\n' "$(find "$d" -maxdepth 1 -name '*.so' 2>/dev/null | wc -l)" "$d"
          done \
        | sort -rn | head -1 | cut -d' ' -f2)"

    [[ -n "$ext_dir" ]] || fail "répertoire d'extensions introuvable sous $PREFIX/usr/lib/php"
    [[ "$(find "$ext_dir" -maxdepth 1 -name '*.so' | wc -l)" -gt 0 ]] \
        || fail "aucune extension .so dans $ext_dir — paquets php8.4-* mal extraits ?"

    {
        printf '; généré par dev-hub/tools/bootstrap-local-php.sh (issue #7585)\n'
        printf '; portable : ce fichier + PHP_INI_SCAN_DIR= vide (le conf.d système\n'
        printf '; chargerait des extensions absentes du préfixe).\n'
        printf 'extension_dir = %s\n' "$ext_dir"
        printf 'memory_limit = -1\n'
        local ext
        for ext in "${PHP_EXT_LOAD[@]}"; do
            printf 'extension = %s.so\n' "$ext"
        done
    } > "$(ini_file)"

    log "→ php.ini écrit ($(ini_file)), extension_dir=$ext_dir"
}

# ── 4. composer.phar + api/vendor ───────────────────────────────────────────
install_composer() {
    local phar="$PREFIX/composer.phar"

    if [[ ! -s "$phar" ]]; then
        log "→ téléchargement de composer.phar…"
        curl -sSf "$COMPOSER_URL" -o "$phar" || fail "téléchargement de composer impossible"
    fi

    mkdir -p "$HOME/.config/composer"

    log "→ composer install (api/) — peut prendre quelques minutes la première fois"
    ( cd "$REPO_ROOT/api" && php_run "$phar" install \
        --ignore-platform-reqs --no-scripts --no-interaction --prefer-dist --no-progress ) \
        || fail "composer install a échoué dans api/"
}

# ── 5. Vérifications ────────────────────────────────────────────────────────
run_check() {
    is_operational || fail "l'installation locale n'est pas opérationnelle ($PREFIX)"

    log "PHP      : $(php_run -r 'printf("%s\n", PHP_VERSION);')"
    log "binaire  : $(php_bin)"
    log "php.ini  : $(ini_file)"
    # shellcheck disable=SC2016  # le code entre quotes est du PHP, pas du shell
    log "extensions : $(php_run -r 'printf("%s\n", implode(",", array_filter(get_loaded_extensions(), fn($e) => in_array(strtolower($e), ["phar","mbstring","curl","xml","intl","pdo","pdo_sqlite","sqlite3"], true))));')"
    log "composer : $( [[ -s "$PREFIX/composer.phar" ]] && printf 'présent' || printf 'absent' )"
}

run_verify() {
    run_check
    [[ -x "$REPO_ROOT/api/vendor/bin/phpstan" ]] \
        || fail "api/vendor/bin/phpstan absent — lancer le script sans option (composer install)"

    log "→ commande exacte du check requis « PHPStan — Strict » :"
    ( cd "$REPO_ROOT/api" && php_run vendor/bin/phpstan analyse \
        --configuration=phpstan-strict.neon --memory-limit=1G --no-progress )
}

main() {
    case "${1:-}" in
        --check)  run_check ;;
        --verify) run_verify ;;
        "")
            if is_operational; then
                log "✓ PHP ${PHP_TARGET_VERSION} portable déjà opérationnel dans $PREFIX"
            else
                local version
                version="$(resolve_version)"
                extract_packages "$version"
            fi

            # Toujours réécrit : le php.ini doit refléter la liste d'extensions
            # COURANTE du script. Une recette qui évolue (ajout de `tokenizer`…)
            # ne doit pas laisser un ini périmé derrière elle, sans quoi le
            # prochain `--verify` échoue pour une raison déjà corrigée.
            write_ini
            install_composer
            run_check
            log ""
            log "Pour rejouer le check requis :  bash dev-hub/tools/bootstrap-local-php.sh --verify"
            log "Ou directement :"
            log "  cd api && PHP_INI_SCAN_DIR= $PREFIX/usr/bin/php${PHP_TARGET_VERSION} -c $(ini_file) vendor/bin/phpstan analyse --configuration=phpstan-strict.neon --memory-limit=1G --no-progress"
            ;;
        -h|--help) sed -n '2,30p' "${BASH_SOURCE[0]}" | sed 's/^# \?//' ;;
        *) fail "option inconnue : $1 (voir --help)" ;;
    esac
}

main "${1:-}"
