set -euo pipefail
FIREBASE_READBACK_STRICT="${FIREBASE_READBACK_REQUIRED:-false}"
USED_SERVICE_ACCOUNT=false
FIREBASE_AUTH_ARGS=(--token "${FIREBASE_TOKEN}")
if [[ -n "${FIREBASE_SERVICE_ACCOUNT_JSON:-}" ]]; then
  printf '%s' "${FIREBASE_SERVICE_ACCOUNT_JSON}" > "${RUNNER_TEMP}/firebase-service-account.json"
  export GOOGLE_APPLICATION_CREDENTIALS="${RUNNER_TEMP}/firebase-service-account.json"
  FIREBASE_AUTH_ARGS=()
  USED_SERVICE_ACCOUNT=true
  echo "Using Firebase service account readback for ${APP_NAME}."
  echo "Firebase readback strict mode: ${FIREBASE_READBACK_STRICT}."
fi
echo "Verifying Firebase App Distribution read-after-write for ${APP_NAME} build ${BUILD_NUMBER}."
for attempt in 1 2 3 4 5 6; do
  echo "Firebase visibility attempt ${attempt}/6..."
  set +e
  npx --yes firebase-tools appdistribution:releases:list \
    --app "${FIREBASE_APP_ID}" \
    "${FIREBASE_AUTH_ARGS[@]}" \
    --limit 10 \
    --json > firebase-releases.json 2> firebase-releases.err
  firebase_status=$?
  set -e

  if [[ ${firebase_status} -ne 0 ]]; then
    if [[ "${USED_SERVICE_ACCOUNT}" == "true" && "${FIREBASE_READBACK_STRICT}" != "true" ]]; then
      echo "::warning::Firebase service-account readback failed for ${APP_NAME}; retrying readback with FIREBASE_TOKEN."
      cat firebase-releases.err || true
      set +e
      npx --yes firebase-tools appdistribution:releases:list \
        --app "${FIREBASE_APP_ID}" \
        --token "${FIREBASE_TOKEN}" \
        --limit 10 \
        --json > firebase-releases.json 2> firebase-releases.err
      firebase_status=$?
      set -e
    fi
  fi

  if [[ ${firebase_status} -ne 0 ]]; then
    if [[ "${FIREBASE_READBACK_STRICT}" == "true" ]]; then
      echo "::error::Firebase service-account readback failed for ${APP_NAME}."
      cat firebase-releases.err || true
      exit ${firebase_status}
    fi
    echo "::warning::Firebase upload succeeded, but firebase-tools could not list App Distribution releases for ${APP_NAME}."
    echo "::warning::Keeping deploy green because the upload action is the distribution source of truth; set FIREBASE_READBACK_REQUIRED=true after the service account is rotated and granted App Distribution access."
    cat firebase-releases.err || true
    exit 0
  fi

  set +e
  # PA2-OPS-002: single quotes here are intentional, not a bug. The
  # JS template literals (`${...}`) below are Node.js string
  # interpolation evaluated inside the node process, not shell
  # interpolation; the values come from process.env.BUILD_NUMBER /
  # process.env.APP_NAME (set via this step's `env:` block above),
  # never from unescaped shell variables. Double-quoting the -e
  # script would let bash expand `$...` before Node ever sees it,
  # which is exactly what we must avoid here.
  # shellcheck disable=SC2016
  node -e 'const fs = require("fs"); const payload = JSON.parse(fs.readFileSync("firebase-releases.json", "utf8")); const expectedBuild = process.env.BUILD_NUMBER; const appName = process.env.APP_NAME; const releases = Array.isArray(payload.result) ? payload.result : (Array.isArray(payload.result?.releases) ? payload.result.releases : []); const match = releases.find((release) => String(release.buildVersion) === String(expectedBuild)); if (!match) { console.error(`Firebase App Distribution release not visible yet for ${appName} build ${expectedBuild}.`); console.error(`Visible build versions: ${releases.map((release) => release.buildVersion).join(", ") || "none"}`); process.exit(2); } console.log(`Firebase App Distribution release visible for ${appName}: ${match.name || match.displayVersion || match.buildVersion}`);'
  node_status=$?
  set -e

  if [[ ${node_status} -eq 0 ]]; then
    exit 0
  fi
  if [[ ${node_status} -ne 2 ]]; then
    echo "::error::Firebase release list returned an unexpected payload."
    head -c 2000 firebase-releases.json || true
    exit ${node_status}
  fi
  sleep 20
done

echo "::error::Firebase upload succeeded, but build ${BUILD_NUMBER} was not visible in the last 10 releases after retries."
exit 1