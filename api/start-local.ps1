param(
    [switch]$Rebuild,
    [switch]$SeedDemo,
    [switch]$RunTests
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

function Set-EnvValue {
    param(
        [string]$Path,
        [string]$Key,
        [string]$Value
    )

    $content = Get-Content $Path -Raw
    if ($content -match "(?m)^$Key=") {
        $content = [regex]::Replace($content, "(?m)^$Key=.*$", "$Key=$Value")
    } else {
        $content = $content.TrimEnd() + "`r`n$Key=$Value`r`n"
    }
    Set-Content -Path $Path -Value $content -Encoding UTF8
}

Push-Location $PSScriptRoot
try {
    if (-not (Test-Path ".env")) {
        Copy-Item ".env.example" ".env"
    }

    Set-EnvValue -Path ".env" -Key "APP_ENV" -Value "local"
    Set-EnvValue -Path ".env" -Key "DB_CONNECTION" -Value "pgsql"
    Set-EnvValue -Path ".env" -Key "DB_HOST" -Value "pgsql"
    Set-EnvValue -Path ".env" -Key "DB_PORT" -Value "5432"
    Set-EnvValue -Path ".env" -Key "DB_DATABASE" -Value "leopardo"
    Set-EnvValue -Path ".env" -Key "DB_USERNAME" -Value "sail"
    Set-EnvValue -Path ".env" -Key "DB_PASSWORD" -Value "password"
    Set-EnvValue -Path ".env" -Key "SESSION_DRIVER" -Value "file"
    Set-EnvValue -Path ".env" -Key "CACHE_STORE" -Value "file"
    Set-EnvValue -Path ".env" -Key "QUEUE_CONNECTION" -Value "sync"

    if ($Rebuild) {
        docker compose down
        docker compose up --build -d
    } else {
        docker compose up -d
    }

    docker compose exec app composer install --no-interaction --prefer-dist
    docker compose exec app php artisan key:generate
    docker compose exec app php artisan config:clear
    docker compose exec app php artisan migrate --path=database/migrations/public --force
    docker compose exec app php artisan migrate --path=database/migrations/tenant --force
    docker compose exec app php artisan db:seed --class=DatabaseSeeder

    if ($SeedDemo) {
        docker compose exec app php artisan db:seed --class=DemoCompanySeeder
    }

    if ($RunTests) {
        docker compose exec -e APP_ENV=testing app php artisan test
    }

    Write-Host ""
    Write-Host "✅ Environnement local prêt"
    Write-Host "URL API/Web: http://localhost:8000"
    Write-Host ""
    Write-Host "Identifiants démo manager (si -SeedDemo exécuté):"
    Write-Host "Email: ahmed.benali@techcorp-algerie.dz"
    Write-Host "Mot de passe: password123"
}
finally {
    Pop-Location
}
