#!/bin/bash
# ============================================================
# Leopardo Edge — Build & Publish Docker Image
# Usage: bash edge/publish.sh [VERSION]
# Example: bash edge/publish.sh 1.0.0
#
# Garde Trivy optionnelle (#7997) : TRIVY_ENFORCE=1 bash edge/publish.sh 1.0.0
#   refuse le push si une vulnérabilité CRITICAL/HIGH corrigeable non
#   acquittée (.trivyignore.yaml à la racine) subsiste dans une image.
#   Défaut : 0 (comportement historique — le scan CI hebdo image-scan.yml
#   reste la garde systématique). Exige le binaire `trivy` dans le PATH.
# ============================================================
set -e

VERSION="${1:-1.0.0}"
IMAGE="leopardo/edge-api"
UI_IMAGE="leopardo/edge-ui"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "🐆 Building Leopardo Edge Docker images..."
echo "   Version : $VERSION"
echo "   Images  : $IMAGE:$VERSION + $UI_IMAGE:$VERSION"
echo ""

cd "$REPO_ROOT"

# Build
docker build \
  -f edge/Dockerfile.publish \
  -t "$IMAGE:$VERSION" \
  -t "$IMAGE:latest" \
  --label "build.version=$VERSION" \
  --label "build.date=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --label "build.commit=$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')" \
  .

# #6595 (audit Vague 2) : l'image `leopardo/edge-ui` (PWA web-offline) était
# référencée par le compose Edge (`image: leopardo/edge-ui:${EDGE_VERSION}`)
# sans jamais être construite ni publiée — une install locale tirait une
# image introuvable. Construite depuis front/web-offline/Dockerfile.
docker build \
  -f front/web-offline/Dockerfile \
  -t "$UI_IMAGE:$VERSION" \
  -t "$UI_IMAGE:latest" \
  --label "build.version=$VERSION" \
  --label "build.date=$(date -u +%Y-%m-%dT%H:%M:%SZ)" \
  --label "build.commit=$(git rev-parse --short HEAD 2>/dev/null || echo 'unknown')" \
  front/web-offline

echo ""
echo "✅ Build successful!"

# #7997 : refus de push sur CRITICAL/HIGH non acquitté — opt-in documenté
# (TRIVY_ENFORCE=1, défaut 0). Fail-closed : si la garde est demandée sans
# binaire trivy disponible, on s'arrête AVANT tout push (jamais de skip
# silencieux). `--ignore-unfixed` : seules les CVE corrigeables bloquent,
# comme dans le workflow image-scan.yml ; les acquittements sont partagés
# via .trivyignore.yaml (statement + expired_at obligatoires).
TRIVY_ENFORCE="${TRIVY_ENFORCE:-0}"
if [[ "${TRIVY_ENFORCE}" == "1" ]]; then
  if ! command -v trivy >/dev/null 2>&1; then
    echo "❌ TRIVY_ENFORCE=1 mais le binaire 'trivy' est introuvable dans le PATH." >&2
    echo "   Installez Trivy (https://trivy.dev) ou relancez sans TRIVY_ENFORCE." >&2
    exit 1
  fi
  echo ""
  echo "🔍 TRIVY_ENFORCE=1 — scan Trivy CRITICAL/HIGH avant push (acquittements : .trivyignore.yaml)..."
  for image in "${IMAGE}:${VERSION}" "${UI_IMAGE}:${VERSION}"; do
    trivy image \
      --severity CRITICAL,HIGH \
      --ignore-unfixed \
      --ignorefile "${REPO_ROOT}/.trivyignore.yaml" \
      --exit-code 1 \
      "${image}"
    echo "   ✅ ${image} : aucune CRITICAL/HIGH corrigeable non acquittée."
  done
fi

echo ""
echo "📦 Pushing to Docker Hub..."
docker push "$IMAGE:$VERSION"
docker push "$IMAGE:latest"
docker push "$UI_IMAGE:$VERSION"
docker push "$UI_IMAGE:latest"

echo ""
echo "✅ Published: $IMAGE:$VERSION + $UI_IMAGE:$VERSION"
echo "   Pull: docker pull $IMAGE:$VERSION / docker pull $UI_IMAGE:$VERSION"
