#!/bin/bash

# Leopardo RH Bootstrap Script
# Automates the setup of the API and Web environments.

set -e

echo "🐆 Starting Leopardo RH Bootstrap..."

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
    cd ..
fi

# 2. Frontend (Web) Setup
if [ -d "web" ]; then
    echo "📦 Setting up Web..."
    cd web
    if [ ! -f ".env.local" ]; then
        cp .env.example .env.local
        echo "✅ Created web/.env.local"
    fi
    # Check if npm is installed
    if command -v npm &> /dev/null; then
        npm install
    else
        echo "⚠️ NPM not found. Skipping Node dependencies."
    fi
    cd ..
fi

echo "🚀 Bootstrap complete! Check the documentation for next steps."
