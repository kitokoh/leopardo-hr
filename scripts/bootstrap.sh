#!/bin/bash
set -e

echo "🚀 Bootstrapping Leopardo RH Environment..."

# Check dependencies
command -v docker >/dev/null 2>&1 || { echo >&2 "Docker is required but not installed. Aborting."; exit 1; }
command -v docker-compose >/dev/null 2>&1 || { echo >&2 "docker-compose is required but not installed. Aborting."; exit 1; }

# Initialize .env for API
if [ ! -f api/.env ]; then
    echo "Creating .env for API..."
    cp api/.env.example api/.env
fi

# Start infrastructure
echo "Starting infrastructure..."
docker-compose up -d

# Wait for DB to be ready
echo "Waiting for database..."
sleep 5

# Run migrations and seeders
echo "Running migrations and seeds..."
docker exec leopardo-api php artisan key:generate --force
docker exec leopardo-api php artisan migrate:fresh --seed --force

echo "✅ Leopardo RH is ready!"
echo "API: http://localhost:8000"
echo "Admin Dashboard: http://localhost:3000"
