#!/usr/bin/env bash
#
# check-mobile-riverpod-legacy-imports.test.sh — tests du garde Riverpod legacy
# (issue #8754).
#
# Le garde prend la racine du dépôt en 1er argument. Chaque scénario construit
# son PROPRE arbre jetable (`<tmp>/sN/front/mobile_apps/...`) : sans isolation,
# les fichiers d'un scénario « rouge » polluent les scénarios suivants et les
# rendent rouges à tort (constaté à la première écriture de ces tests — 3 des
# scénarios « verts » échouaient à cause de `bad.dart` resté sur disque).
#
# Scénarios :
#   1. dépôt réel (après correctif) -> vert ;
#   2. StateNotifier SANS import legacy -> rouge (le bug #8754 exact) ;
#   3. StateNotifier AVEC import legacy -> vert ;
#   4. symbole legacy mentionné seulement en COMMENTAIRE -> vert (pas de faux positif) ;
#   5. fichier de ré-exportation `export ... legacy.dart` -> vert ;
#   6. `package:riverpod/legacy.dart` (variante non-flutter) -> vert ;
#   7. plusieurs violations dans un même fichier -> toutes rapportées ;
#   8. répertoire mobile absent -> rouge explicite.
#
# Usage : bash dev-hub/tools/tests/check-mobile-riverpod-legacy-imports.test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/../check-mobile-riverpod-legacy-imports.sh"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0
ok() { echo "✅ $1"; pass=$((pass + 1)); }
ko() { echo "❌ $1"; fail=$((fail + 1)); }

# Crée un arbre isolé pour un scénario et renvoie la racine.
new_tree() {
  local name="$1"
  local root="${TMP}/${name}"
  mkdir -p "${root}/front/mobile_apps/leopardo_core/lib"
  echo "${root}"
}

run_guard() {
  # $1 = racine ; renvoie 0 ou le code de sortie, sans faire échouer le script
  bash "${GUARD}" "$1" > /dev/null 2>&1 && echo 0 || echo $?
}

# --- 1. dépôt réel -----------------------------------------------------------
if [[ "$(run_guard "${REPO_ROOT}")" == "0" ]]; then
  ok "1. dépôt réel : imports Riverpod legacy cohérents"
else
  ko "1. dépôt réel : le garde échoue encore (correctif #8754 incomplet)"
fi

# --- 2. StateNotifier sans import (le bug) -----------------------------------
T2="$(new_tree s2)"
cat > "${T2}/front/mobile_apps/leopardo_core/lib/bad.dart" <<'EOF'
import 'package:flutter_riverpod/flutter_riverpod.dart';

class MyNotifier extends StateNotifier<int> {
  MyNotifier() : super(0);
}
EOF
if [[ "$(run_guard "${T2}")" == "1" ]]; then
  ok "2. StateNotifier sans import legacy : détecté"
else
  ko "2. StateNotifier sans import legacy : NON détecté"
fi

# --- 3. avec import legacy ---------------------------------------------------
T3="$(new_tree s3)"
cat > "${T3}/front/mobile_apps/leopardo_core/lib/good.dart" <<'EOF'
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_riverpod/legacy.dart';

class MyNotifier extends StateNotifier<int> {
  MyNotifier() : super(0);
}
EOF
if [[ "$(run_guard "${T3}")" == "0" ]]; then
  ok "3. avec import legacy : accepté"
else
  ko "3. avec import legacy : refusé à tort"
fi

# --- 4. mention en commentaire seulement -------------------------------------
T4="$(new_tree s4)"
cat > "${T4}/front/mobile_apps/leopardo_core/lib/comment.dart" <<'EOF'
import 'package:flutter_riverpod/flutter_riverpod.dart';

// StateNotifier a été retiré du point d'entrée principal en Riverpod 3.
/* StateNotifierProvider vit désormais dans legacy.dart */
final x = 1;
EOF
if [[ "$(run_guard "${T4}")" == "0" ]]; then
  ok "4. mention en commentaire : pas de faux positif"
else
  ko "4. mention en commentaire : faux positif"
fi

# --- 5. fichier de ré-exportation --------------------------------------------
T5="$(new_tree s5)"
cat > "${T5}/front/mobile_apps/leopardo_core/lib/reexport.dart" <<'EOF'
export 'package:flutter_riverpod/legacy.dart' show StateNotifier, StateNotifierProvider;
EOF
if [[ "$(run_guard "${T5}")" == "0" ]]; then
  ok "5. ré-exportation : légitime, acceptée"
else
  ko "5. ré-exportation : refusée à tort"
fi

# --- 6. variante package:riverpod/legacy.dart --------------------------------
T6="$(new_tree s6)"
cat > "${T6}/front/mobile_apps/leopardo_core/lib/pure.dart" <<'EOF'
import 'package:riverpod/legacy.dart';

class N extends StateNotifier<int> {
  N() : super(0);
}
EOF
if [[ "$(run_guard "${T6}")" == "0" ]]; then
  ok "6. package:riverpod/legacy.dart : accepté"
else
  ko "6. package:riverpod/legacy.dart : refusé à tort"
fi

# --- 7. plusieurs violations -------------------------------------------------
T7="$(new_tree s7)"
cat > "${T7}/front/mobile_apps/leopardo_core/lib/multi.dart" <<'EOF'
import 'package:flutter_riverpod/flutter_riverpod.dart';

class A extends StateNotifier<int> {
  A() : super(0);
}

final p = StateNotifierProvider<A, int>((ref) => A());
EOF
out="$(bash "${GUARD}" "${T7}" 2>&1 || true)"
n="$(grep -c "utilisé sans import legacy" <<< "${out}" || true)"
if [[ "${n}" -ge 2 ]]; then
  ok "7. plusieurs violations : ${n} rapportées"
else
  ko "7. plusieurs violations : seulement ${n} rapportée(s)"
fi

# --- 8. répertoire mobile absent ---------------------------------------------
T8="${TMP}/s8"
mkdir -p "${T8}"
if [[ "$(run_guard "${T8}")" == "1" ]]; then
  ok "8. répertoire mobile absent : échec explicite"
else
  ko "8. répertoire mobile absent : échec silencieux (toujours vert)"
fi

echo
echo "── ${pass} passés, ${fail} échecs ──"
[[ "${fail}" -eq 0 ]]
