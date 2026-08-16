#!/bin/bash
set -e

echo "🚀 Bootstrapping Leopardo RH Environment..."

# Check dependencies — Docker v2 (plugin compose) avec fallback v1
command -v docker >/dev/null 2>&1 || { echo >&2 "Docker is required but not installed. Aborting."; exit 1; }
if docker compose version >/dev/null 2>&1; then
    DC="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    DC="docker-compose"
else
    echo >&2 "Docker Compose (v2 plugin or standalone) is required but not installed. Aborting."
    exit 1
fi

# Initialize .env for API
if [ ! -f api/.env ]; then
    echo "Creating .env for API..."
    cp api/.env.example api/.env
fi

# Start infrastructure
echo "Starting infrastructure..."
$DC up -d

# Wait for DB to be ready
echo "Waiting for database..."
sleep 5

# Run migrations and seeders
echo "Running migrations and seeds..."
docker exec leopardo-api php artisan key:generate --force
# NB: leopardo:migrate ne définit pas d'option --force (voir routes/console.php) — #4413
docker exec leopardo-api php artisan leopardo:migrate --fresh --seed

echo "✅ Leopardo RH is ready!"
echo "API: http://localhost:8000"
echo "Admin Dashboard: http://localhost:3000"
