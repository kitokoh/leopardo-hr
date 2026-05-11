#!/bin/bash

# Leopardo RH Bootstrap Script
# Automates the setup of the API, Web and Admin environments.

set -e

echo "🐆 Starting Leopardo RH Bootstrap..."

# Determine the root directory relative to the script's location
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

# 1. Backend (API) Setup
if [ -d "api" ]; then
    echo "📦 Setting up API..."
    cd api
    if [ ! -f ".env" ]; then
        cp .env.example .env
        echo "✅ Created api/.env"
    fi
    # Check if composer is installed
    if command -v composer &> /dev/null; then
        composer install --no-interaction --prefer-dist
    else
        echo "⚠️ Composer not found. Skipping PHP dependencies."
    fi
    cd "$ROOT_DIR"
fi

# 2. Frontend (Web) Setup
if [ -d "front/web" ]; then
    echo "📦 Setting up Web..."
    cd front/web
    if [ ! -f ".env.local" ]; then
        if [ -f ".env.example" ]; then
            cp .env.example .env.local
            echo "✅ Created front/web/.env.local"
        fi
    fi
    # Check if npm is installed
    if command -v npm &> /dev/null; then
        npm install
    else
        echo "⚠️ NPM not found. Skipping Node dependencies."
    fi
    cd "$ROOT_DIR"
fi

# 3. Admin Dashboard Setup
if [ -d "front/admin-dashboard" ]; then
    echo "📦 Setting up Admin Dashboard..."
    cd front/admin-dashboard
    # Check if npm is installed
    if command -v npm &> /dev/null; then
        npm install
    else
        echo "⚠️ NPM not found. Skipping Node dependencies."
    fi
    cd "$ROOT_DIR"
fi

echo "🚀 Bootstrap complete! Check the documentation for next steps."
