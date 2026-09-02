#!/bin/bash
# ============================================================
# Leopardo Edge — Build & Publish Docker Image
# Usage: bash edge/publish.sh [VERSION]
# Example: bash edge/publish.sh 1.0.0
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
echo ""
echo "📦 Pushing to Docker Hub..."
docker push "$IMAGE:$VERSION"
docker push "$IMAGE:latest"
docker push "$UI_IMAGE:$VERSION"
docker push "$UI_IMAGE:latest"

echo ""
echo "✅ Published: $IMAGE:$VERSION + $UI_IMAGE:$VERSION"
echo "   Pull: docker pull $IMAGE:$VERSION / docker pull $UI_IMAGE:$VERSION"
