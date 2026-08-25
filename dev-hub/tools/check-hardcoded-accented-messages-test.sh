#!/usr/bin/env bash
# Tests de la garde i18n étendue (issue #5432 — surfaces Services/Exceptions/Commandes).
#
# Cas couverts :
#   T1 — message accented ajouté dans un Service (Modules/*/Application) → FAIL
#   T2 — message accented ajouté dans une exception de domaine → FAIL
#   T3 — message accented ajouté dans une commande Console → FAIL
#   T4 — ligne ajoutée avec __('catalogue.cle') → OK (jamais flaggée)
#   T5 — message accented ajouté dans un Controller → FAIL (PA2-I18N-007 inchangé)
#   T6 — diff sans fichier à risque → OK (nothing to check)
#
# Usage : bash dev-hub/tools/check-hardcoded-accented-messages-test.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="${SCRIPT_DIR}/check-hardcoded-accented-messages.sh"
WORK="$(mktemp -d)"
trap 'rm -rf "${WORK}"' EXIT

PASS=0
FAIL_N=0

init_repo() {
  local dir="$1"
  mkdir -p "${dir}"
  git -C "${dir}" init -q
  git -C "${dir}" config user.email test@test
  git -C "${dir}" config user.name test
  mkdir -p "${dir}/api/app"
}

# run_case <name> <expected ok|fail> <repo_dir> <file_relpath> <file_content>
run_case() {
  local name="$1" expected="$2" dir="$3" rel="$4" content="$5"
  local base head out rc
  # commit de base (vide)
  git -C "${dir}" add -A >/dev/null 2>&1 || true
  git -C "${dir}" commit -q -m base --allow-empty
  base="$(git -C "${dir}" rev-parse HEAD)"
  # ajout du fichier à risque
  mkdir -p "${dir}/$(dirname "${rel}")"
  printf '%s\n' "${content}" > "${dir}/${rel}"
  git -C "${dir}" add -A
  git -C "${dir}" commit -q -m head
  head="$(git -C "${dir}" rev-parse HEAD)"

  out="$(cd "${dir}" && bash "${GUARD}" "${base}" "${head}" 2>&1)" && rc=0 || rc=1
  if [[ "${expected}" == "fail" && "${rc}" -eq 1 ]]; then
    echo "✅ ${name} (échec attendu)"
    PASS=$((PASS + 1))
  elif [[ "${expected}" == "ok" && "${rc}" -eq 0 ]]; then
    echo "✅ ${name}"
    PASS=$((PASS + 1))
  else
    echo "❌ ${name} : attendu ${expected}, obtenu rc=${rc}"
    echo "${out}" | sed 's/^/    /' | head -5
    FAIL_N=$((FAIL_N + 1))
  fi
}

# T1 : Service avec message accented
run_case "T1 Service (Application) accented refusé" fail "$(init_repo "${WORK}/t1" >/dev/null; echo "${WORK}/t1")" \
  "api/app/Modules/Accounting/Application/Services/MonService.php" \
  "<?php
final class MonService {
    public function go(): string { return 'Paiement refusé.'; }
}"

# T2 : exception de domaine accented
run_case "T2 Exception de domaine accented refusée" fail "$(init_repo "${WORK}/t2" >/dev/null; echo "${WORK}/t2")" \
  "api/app/Modules/Accounting/Domain/Exceptions/MonException.php" \
  "<?php
final class MonException extends \\Exception {
    public function __construct() { parent::__construct('Document déjà payé.'); }
}"

# T3 : commande Console accented
run_case "T3 Commande Console accented refusée" fail "$(init_repo "${WORK}/t3" >/dev/null; echo "${WORK}/t3")" \
  "api/app/Modules/Accounting/Console/Commands/MaCommande.php" \
  "<?php
final class MaCommande {
    protected \$description = 'Envoie les relances déjà dues';
}"

# T4 : ligne __('...') jamais flaggée
run_case "T4 ligne __('catalogue.cle') OK" ok "$(init_repo "${WORK}/t4" >/dev/null; echo "${WORK}/t4")" \
  "api/app/Modules/Accounting/Application/Services/MonService.php" \
  "<?php
final class MonService {
    public function go(): string { return __('accounting.montant_invalide'); }
}"

# T5 : Controller accented (comportement historique)
run_case "T5 Controller accented refusé (PA2-I18N-007)" fail "$(init_repo "${WORK}/t5" >/dev/null; echo "${WORK}/t5")" \
  "api/app/Modules/Accounting/Interfaces/Api/V1/MonController.php" \
  "<?php
final class MonController {
    public function go() { return response()->json(['message' => 'Paiement refusé.']); }
}"

# T6 : diff sans fichier à risque → OK
run_case "T6 aucun fichier à risque → OK" ok "$(init_repo "${WORK}/t6" >/dev/null; echo "${WORK}/t6")" \
  "api/app/Modules/Accounting/Domain/Models/Modele.php" \
  "<?php
final class Modele {
    public int \$x = 1;
}"

echo ""
echo "Résultat : ${PASS} cas OK, ${FAIL_N} échec(s)."
[[ "${FAIL_N}" -eq 0 ]]
