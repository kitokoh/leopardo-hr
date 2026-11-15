#!/usr/bin/env bash
# Auto-test de dev-hub/tools/generate-feature-mvp-split.py (issue #9098).
#
# Ce que ce test verrouille :
#   1. le classement MVP / canonique (dont la forme FQCN et la forme groupee) ;
#   2. l'ANCRAGE sur le mot-cle `use` : une simple mention dans un commentaire
#      ne doit PAS envoyer le fichier dans la suite MVP ;
#   3. l'invariant "chaque fichier une seule fois" (aucun test perdu au decoupage) ;
#   4. l'idempotence et la detection de peremption (--check).
set -uo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
gen="${racine}/dev-hub/tools/generate-feature-mvp-split.py"
tmp="$(mktemp -d)"
trap 'rm -rf "${tmp}"' EXIT

fail() { echo "KO: $*" >&2; exit 1; }

mkdir -p "${tmp}/api/tests/Feature/Sub"
cat > "${tmp}/api/phpunit.xml" <<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<phpunit>
    <testsuites>
        <testsuite name="Feature">
            <directory>tests/Feature</directory>
        </testsuite>
    </testsuites>
</phpunit>
XML

cat > "${tmp}/api/tests/Feature/MvpAlphaTest.php" <<'PHP'
<?php
namespace Tests\Feature;
use Tests\Support\CreatesMvpSchema;
class MvpAlphaTest { use CreatesMvpSchema; }
PHP

cat > "${tmp}/api/tests/Feature/Sub/SubGammaTest.php" <<'PHP'
<?php
namespace Tests\Feature\Sub;
use Tests\Support\CreatesMvpSchema;
use Tests\RefreshTenantDatabase;
class SubGammaTest { use CreatesMvpSchema, RefreshTenantDatabase; }
PHP

cat > "${tmp}/api/tests/Feature/CanonBetaTest.php" <<'PHP'
<?php
namespace Tests\Feature;
use Tests\RefreshTenantDatabase;
class CanonBetaTest { use RefreshTenantDatabase; }
PHP

# Mention en commentaire uniquement : doit rester dans la suite canonique.
cat > "${tmp}/api/tests/Feature/MentionOnlyTest.php" <<'PHP'
<?php
namespace Tests\Feature;
// N'utilise PAS use CreatesMvpSchema; (mention seulement)
class MentionOnlyTest {}
PHP

python3 "${gen}" --api-dir "${tmp}/api" >/dev/null || fail "generation en echec"

conf="${tmp}/api/phpunit.xml"
mvp_block="$(sed -n '/name="FeatureMvpFixture"/,/<\/testsuite>/p' "${conf}")"
rest_block="$(sed -n '/name="FeatureRest"/,/<\/testsuite>/p' "${conf}")"

[[ "${mvp_block}" == *"tests/Feature/MvpAlphaTest.php"* ]] || fail "MvpAlphaTest absent de FeatureMvpFixture"
[[ "${mvp_block}" == *"tests/Feature/Sub/SubGammaTest.php"* ]] || fail "SubGammaTest (forme groupee) absent de FeatureMvpFixture"
[[ "${mvp_block}" != *"MentionOnlyTest"* ]] || fail "une mention en commentaire ne doit pas classer en MVP"
[[ "${rest_block}" == *"tests/Feature/CanonBetaTest.php"* ]] || fail "CanonBetaTest absent de FeatureRest"
[[ "${rest_block}" == *"tests/Feature/MentionOnlyTest.php"* ]] || fail "MentionOnlyTest absent de FeatureRest"

nb_mvp="$(printf '%s\n' "${mvp_block}" | grep -c '<file>')"
nb_rest="$(printf '%s\n' "${rest_block}" | grep -c '<file>')"
total_fichiers="$(find "${tmp}/api/tests/Feature" -name '*Test.php' | wc -l | tr -d ' ')"
[[ $((nb_mvp + nb_rest)) -eq ${total_fichiers} ]] || fail "tous les fichiers ne sont pas couverts (${nb_mvp}+${nb_rest} pour ${total_fichiers})"
[[ ${nb_mvp} -eq 2 ]] || fail "FeatureMvpFixture devrait contenir 2 fichiers, en a ${nb_mvp}"

# Idempotence : une seconde generation ne change pas le fichier.
avant="$(cat "${conf}")"
python3 "${gen}" --api-dir "${tmp}/api" >/dev/null || fail "seconde generation en echec"
[[ "$(cat "${conf}")" == "${avant}" ]] || fail "la generation n'est pas idempotente"

# Peremption : un nouveau fichier de test non integre doit faire echouer --check.
python3 "${gen}" --api-dir "${tmp}/api" --check >/dev/null || fail "--check devrait passer juste apres generation"
cat > "${tmp}/api/tests/Feature/NouveauTest.php" <<'PHP'
<?php
namespace Tests\Feature;
class NouveauTest {}
PHP
if python3 "${gen}" --api-dir "${tmp}/api" --check >/dev/null 2>&1; then
  fail "--check devrait echouer quand un fichier de test n'est pas dans la zone"
fi

# Le reste de phpunit.xml ne doit pas etre touche.
[[ "${avant}" == *"<directory>tests/Feature</directory>"* ]] || fail "la testsuite Feature d'origine a ete alteree"

echo "GENERATE_FEATURE_MVP_SPLIT_SELF_TEST_OK: 4 fichiers, 2 suites, idempotent, --check detecte la peremption."
