#!/usr/bin/env bash
# Exécute la suite Feature shard par shard et écrit un rapport JUnit par shard
# dans api/storage/qa/junit/. Objectif : un rapport JUnit RÉEL et incrémental
# (chaque shard terminé laisse son XML), là où un run paratest global ne
# produit rien tant qu'il n'a pas fini — impossible dans une fenêtre bornée
# sur ce sandbox (2 Go de cgroup, ~1,4 test/s).
#
# Usage : bash dev-tools/qa-junit-shards.sh
set -u
cd "$(dirname "$0")/.." || exit 1
OUT=storage/qa/junit
mkdir -p "$OUT"
export APP_ENV=testing
export DB_DATABASE=leopardo_test
PER_SHARD_TIMEOUT=${PER_SHARD_TIMEOUT:-420}

# Liste des shards Feature (hors Unit / Arch / suite « Feature » agrégée).
mapfile -t SUITES < <(grep -oE '<testsuite name="[^"]+"' phpunit.xml \
  | sed -E 's/.*name="([^"]+)"/\1/' | grep -vE '^(Unit|Arch|Feature)$')

echo "shards: ${#SUITES[@]}"
echo "start $(date -u +%FT%TZ)"

for s in "${SUITES[@]}"; do
  xml="$OUT/$s.xml"
  [ -s "$xml" ] && { echo "SKIP $s (déjà présent)"; continue; }
  start=$(date +%s)
  timeout "$PER_SHARD_TIMEOUT" ./vendor/bin/phpunit --no-coverage \
    --testsuite "$s" --log-junit "$xml" >"$OUT/$s.log" 2>&1
  rc=$?
  dur=$(( $(date +%s) - start ))
  if [ -s "$xml" ]; then
    echo "OK   $s rc=$rc ${dur}s $(grep -oE 'tests="[0-9]+"' "$xml" | head -1)"
  else
    echo "PART $s rc=$rc ${dur}s (aucun XML — shard trop long ou en erreur)"
  fi
done

echo "done $(date -u +%FT%TZ)"
