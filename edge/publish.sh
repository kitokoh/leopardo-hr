#!/bin/bash
# ============================================================
# Leopardo Edge — Build & Publish Docker Image
# Usage: bash edge/publish.sh [VERSION]
# Example: bash edge/publish.sh 1.0.0
# ============================================================
set -e

VERSION="${1:-1.0.0}"
IMAGE="leopardo/edge-api"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "🐆 Building Leopardo Edge Docker image..."
echo "   Version : $VERSION"
echo "   Image   : $IMAGE:$VERSION"
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

echo ""
echo "✅ Build successful!"
echo ""
echo "📦 Pushing to Docker Hub..."
docker push "$IMAGE:$VERSION"
docker push "$IMAGE:latest"

echo ""
echo "✅ Published: $IMAGE:$VERSION"
echo "   Pull: docker pull $IMAGE:$VERSION"
