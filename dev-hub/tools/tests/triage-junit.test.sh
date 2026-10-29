#!/usr/bin/env bash
#
# triage-junit.test.sh — tests de dev-hub/tools/triage-junit.py (issue #8396).
#
# L'outil transforme un artefact JUnit en clusters par cause. Un triage faux ne
# « échoue » pas visiblement : il produit un tableau crédible et envoie une
# équipe sur la mauvaise cause. Ces tests pincent donc les quatre erreurs qui
# rendraient le tableau plausible mais faux :
#
#   1. total de tests gonflé par l'imbrication des <testsuite> PHPUnit ;
#   2. module déduit du NOM DE FICHIER de test au lieu de son dossier ;
#   3. messages non normalisés → un UUID différent par exécution = un cluster
#      par test, donc zéro regroupement (le seul intérêt de l'outil) ;
#   4. frame vendor citée au lieu de la première frame du dépôt.
#
# Usage : bash dev-hub/tools/tests/triage-junit.test.sh
set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TOOL="${SCRIPT_DIR}/../triage-junit.py"

TMP="$(mktemp -d)"
trap 'rm -rf "${TMP}"' EXIT

pass=0
fail=0
ok() { pass=$((pass + 1)); printf '  ✅ %s\n' "$*"; }
ko() { fail=$((fail + 1)); printf '  ❌ %s\n' "$*"; }

# ---------------------------------------------------------------------------
# Fixture : deux fichiers JUnit, suites imbriquées comme les produit PHPUnit
# (l'ensemble rédéclare tests="99", chaque suite de classe rédéclare son total).
# ---------------------------------------------------------------------------
cat > "${TMP}/feature.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<testsuites name="PHPUnit tests">
  <testsuite name="" tests="99" assertions="3" errors="1" failures="3" skipped="1">
    <testsuite name="Tests\Feature\Marketing\MarketingOsPlatformTest" tests="3">
      <testcase name="asset creation audits defaults" class="Tests\Feature\Marketing\MarketingOsPlatformTest">
        <failure type="ErrorException">asset creation audits defaultsAttempt to read property "value" on null
at app/Modules/Marketing/Infrastructure/Services/MarketingOsAudit.php:110
at tests/Feature/Marketing/MarketingOsPlatformTest.php:88</failure>
      </testcase>
      <testcase name="campaign creation audits defaults" class="Tests\Feature\Marketing\MarketingOsPlatformTest">
        <failure type="ErrorException">campaign creation audits defaultsAttempt to read property "value" on null
at app/Modules/Marketing/Infrastructure/Services/MarketingOsAudit.php:110
at tests/Feature/Marketing/MarketingOsPlatformTest.php:140</failure>
      </testcase>
      <testcase name="publication schedule is scoped" class="Tests\Feature\Marketing\MarketingOsPlatformTest">
        <error type="Illuminate\Database\QueryException">publication schedule is scopedSQLSTATE[42P01]: relation "publications" does not exist (company 3f2a1b9c-1111-4222-8333-444455556666)
at vendor/laravel/framework/src/Illuminate/Database/Connection.php:407
at app/Modules/Marketing/Domain/Models/MarketingPublication.php:52
at tests/Feature/Marketing/MarketingOsPlatformTest.php:210</error>
      </testcase>
    </testsuite>
    <testsuite name="Tests\Feature\HealthEndpointTest" tests="2">
      <testcase name="health exposes queue depth" class="Tests\Feature\HealthEndpointTest">
        <failure type="PHPUnit\Framework\ExpectationFailedException">health exposes queue depthExpected response status code [200] but received 503.
at vendor/laravel/framework/src/Illuminate/Testing/TestResponseAssert.php:45
at tests/Feature/HealthEndpointTest.php:31</failure>
      </testcase>
      <testcase name="health skips optional deps" class="Tests\Feature\HealthEndpointTest">
        <skipped/>
      </testcase>
    </testsuite>
  </testsuite>
</testsuites>
XML

echo "== triage-junit.py (#8396) =="

# 1. Totaux : les <testcase> réels, pas l'attribut `tests` des suites imbriquées.
JSON="$("${TOOL}" "${TMP}" --format json 2>/dev/null)"
total_tests="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["totals"]["tests"])' <<<"${JSON}")"
# 5 <testcase> réels (3 Marketing + 2 Health) ; la suite extérieure déclare 99.
[ "${total_tests}" = "5" ] \
  && ok "imbrication des <testsuite> : 5 tests réels (et non 99 déclarés)" \
  || ko "total de tests faux : ${total_tests} (attendu 5)"

# 2. Un <skipped> n'est pas un échec.
echecs="$(python3 -c 'import json,sys; print(json.load(sys.stdin)["totals"]["failures"])' <<<"${JSON}")"
# 4 échecs sur 5 tests : le 5e porte <skipped/> et sort du décompte.
[ "${echecs}" = "4" ] \
  && ok "les tests ignorés ne comptent pas comme échecs (4 échecs / 5 tests)" \
  || ko "décompte des échecs faux : ${echecs} (attendu 4)"

# 3. Les deux ErrorException identiques ne font QU'UN cluster (c'est le sujet).
marketing="$(python3 -c '
import json, sys
data = json.load(sys.stdin)
clusters = [c for c in data["clusters"] if c["module"] == "Marketing"]
print(len(clusters), clusters[0]["count"] if clusters else 0)
' <<<"${JSON}")"
[ "${marketing}" = "2 2" ] \
  && ok "deux échecs de même cause = 1 cluster de 2 (module Marketing)" \
  || ko "regroupement Marketing faux : '${marketing}' (attendu '2 2')"

# 4. Le module vient du DOSSIER de test ; un test de premier niveau = Racine.
module_racine="$(python3 -c '
import json, sys
data = json.load(sys.stdin)
clusters = [c for c in data["clusters"] if "HealthEndpointTest" in c["location"]]
print(clusters[0]["module"] if clusters else "ABSENT")
' <<<"${JSON}")"
[ "${module_racine}" = "Racine" ] \
  && ok "tests/Feature/XTest.php → « Racine » (pas un module nommé XTest)" \
  || ko "module déduit du nom de fichier : ${module_racine} (attendu Racine)"

# 5. La frame citée est la première frame du DÉPÔT, pas celle du vendor.
location="$(python3 -c '
import json, sys
data = json.load(sys.stdin)
clusters = [c for c in data["clusters"] if c["exception"] == "ErrorException"]
print(clusters[0]["location"] if clusters else "ABSENT")
' <<<"${JSON}")"
[ "${location}" = "app/Modules/Marketing/Infrastructure/Services/MarketingOsAudit.php:110" ] \
  && ok "localisation = première frame projet (pas vendor/…)" \
  || ko "localisation fausse : ${location}"

# 6. Un UUID dans le message ne doit pas séparer deux occurrences de la même cause.
uuid_clusters="$(python3 -c '
import json, sys
data = json.load(sys.stdin)
print(len([c for c in data["clusters"] if c["exception"] == "QueryException"]))
' <<<"${JSON}")"
[ "${uuid_clusters}" = "1" ] \
  && ok "UUID normalisé dans le message (une seule signature QueryException)" \
  || ko "normalisation du message insuffisante : ${uuid_clusters} signature(s)"

# 7. --list-tests expose les tests concernés (pour ouvrir la bonne classe).
"${TOOL}" "${TMP}" --list-tests --top 1 2>/dev/null | grep -q 'MarketingOsPlatformTest::asset creation audits defaults' \
  && ok "--list-tests liste les noms de tests du cluster" \
  || ko "--list-tests ne liste pas les tests"

# 8. --fail-over transforme le triage en garde optionnelle (code 1).
"${TOOL}" "${TMP}" --fail-over 1 >/dev/null 2>&1
status=$?
[ "${status}" -eq 1 ] \
  && ok "--fail-over 1 échoue quand le total dépasse le seuil" \
  || ko "--fail-over ignoré (code ${status})"

# 9. Sans --fail-over, un triage plein d'échecs reste un RAPPORT (code 0) :
#    brancher l'outil dans le résumé du job ne doit pas changer le verdict du job.
"${TOOL}" "${TMP}" >/dev/null 2>&1
status=$?
[ "${status}" -eq 0 ] \
  && ok "sans --fail-over, code 0 (rapport, pas garde)" \
  || ko "code de sortie inattendu : ${status}"

# 10. Entrée absente = erreur explicite, pas un tableau vide trompeur.
"${TOOL}" "${TMP}/inexistant.xml" >/dev/null 2>&1
status=$?
[ "${status}" -ne 0 ] \
  && ok "entrée introuvable → échec explicite" \
  || ko "entrée introuvable silencieusement acceptée"

echo
echo "Résultat : ${pass} ok, ${fail} ko"
[ "${fail}" -eq 0 ]
