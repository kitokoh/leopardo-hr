#!/usr/bin/env bash
# Garde #7982 — le dépôt est PUBLIC : aucune donnée nominative dans docs/.
#
# Décision propriétaire tracée ici (2026-10-26, #7982) : **transparence assumée**
# plutôt que dépôt privé. Le contenu commercial (scripts, stratégie, pricing,
# pipeline) reste publié — c'est un choix produit cohérent avec la licence MIT.
#
# Mais « transparence assumée » ne veut PAS dire « publier des personnes ».
# Deux catégories n'ont rien à faire dans un dépôt public, quelle que soit la
# décision commerciale :
#   1. des coordonnées réelles (e-mail, téléphone) dans la documentation ;
#   2. des lignes de CRM nominatives non marquées comme fictives.
#
# Cette garde rend la décision VÉRIFIABLE : elle échoue si un fichier de docs/
# introduit l'une de ces deux choses. Le contenu commercial lui-même n'est PAS
# contrôlé — il est publié volontairement.
#
# Usage : bash dev-hub/tools/check-public-docs-no-pii.sh [base-ref]
#   base-ref (défaut origin/main) : seuls les fichiers AJOUTÉS/MODIFIÉS depuis
#   cette ref sont contrôlés, pour ne pas bloquer sur de l'historique.
set -euo pipefail

BASE_REF="${1:-origin/main}"

if ! git rev-parse --verify --quiet "$BASE_REF" >/dev/null; then
    echo "check-public-docs-no-pii: ref '$BASE_REF' introuvable — contrôle de tout docs/." >&2
    BASE_REF=""
fi

if [ -n "$BASE_REF" ]; then
    mapfile -t FILES < <(git diff --name-only --diff-filter=ACMR "$BASE_REF"...HEAD -- 'docs/' || true)
else
    mapfile -t FILES < <(git ls-files 'docs/')
fi

if [ "${#FILES[@]}" -eq 0 ]; then
    echo "check-public-docs-no-pii: aucun fichier docs/ modifié — OK"
    exit 0
fi

FAIL=0

# ── 1. Coordonnées réelles dans la documentation ────────────────────────────
# On n'interdit pas tout e-mail : les adresses de contact PUBLIQUES du produit
# (support@, contact@leopardo-rh.com, noreply@, les adresses d'exemple des
# domaines réservés) sont légitimes. On cible les adresses personnelles.
EMAIL_RE='[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}'
ALLOWED_EMAIL_RE='@(leopardo-rh\.com|leopardo\.com|example\.(com|org|net)|example\.invalid|localhost)$'

for f in "${FILES[@]}"; do
    [ -f "$f" ] || continue
    case "$f" in
        *.md|*.txt|*.csv|*.json|*.yml|*.yaml) ;;
        *) continue ;;
    esac

    while IFS= read -r match; do
        [ -z "$match" ] && continue
        addr="${match##*:}"
        if echo "$addr" | grep -qiE "$ALLOWED_EMAIL_RE"; then
            continue
        fi
        echo "✗ $f — adresse e-mail personnelle dans un dépôt public : $addr"
        FAIL=1
    done < <(grep -nEo "$EMAIL_RE" "$f" 2>/dev/null || true)
done

# ── 2. Lignes de CRM nominatives non marquées fictives ──────────────────────
# Un CSV de pipeline doit porter la marque explicite `DONNEES FICTIVES` sur
# chaque ligne de données, sinon un lecteur (ou un prospect nommé) ne peut pas
# distinguer un exemple d'une fuite.
for f in "${FILES[@]}"; do
    case "$f" in
        *vente*CRM*.csv|*CRM*.csv) ;;
        *) continue ;;
    esac
    [ -f "$f" ] || continue

    # 1re ligne = en-tête, on la saute.
    lineno=0
    while IFS= read -r line; do
        lineno=$((lineno + 1))
        [ "$lineno" -eq 1 ] && continue
        [ -z "${line// /}" ] && continue
        if ! echo "$line" | grep -q 'DONNEES FICTIVES'; then
            echo "✗ $f:$lineno — ligne de CRM sans la marque « DONNEES FICTIVES » (#7982)"
            echo "    $line" | cut -c1-160
            FAIL=1
        fi
    done < "$f"
done

if [ "$FAIL" -ne 0 ]; then
    echo ""
    echo "check-public-docs-no-pii: ÉCHEC — le dépôt est public (licence MIT)."
    echo "Décision #7982 : le contenu commercial est publié volontairement, mais"
    echo "aucune coordonnée personnelle ni ligne de CRM nominative ne doit entrer."
    exit 1
fi

echo "check-public-docs-no-pii: OK (${#FILES[@]} fichier(s) docs/ contrôlé(s))"
