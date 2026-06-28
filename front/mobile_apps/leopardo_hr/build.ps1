# build.ps1 — Alternative PowerShell au Makefile pour Windows
# Usage: .\build.ps1 [dev|staging|prod|clean]

param(
    [Parameter(Position=0)]
    [ValidateSet("dev", "staging", "prod", "clean", "help")]
    [string]$Target = "help"
)

$ErrorActionPreference = "Stop"

function Write-Header($text) {
    Write-Host ""
    Write-Host "  🐆 $text" -ForegroundColor Cyan
    Write-Host ""
}

function Invoke-FlutterCommand($args) {
    $process = Start-Process -FilePath "flutter" -ArgumentList $args -NoNewWindow -PassThru -Wait
    if ($process.ExitCode -ne 0) {
        Write-Host "❌ Erreur Flutter (code $($process.ExitCode))" -ForegroundColor Red
        exit $process.ExitCode
    }
}

switch ($Target) {

    "help" {
        Write-Host ""
        Write-Host "  🐆 LEOPARDO RH — Build Flutter (Windows)" -ForegroundColor Cyan
        Write-Host ""
        Write-Host "  Usage: .\build.ps1 [target]" -ForegroundColor White
        Write-Host ""
        Write-Host "  Targets:" -ForegroundColor Yellow
        Write-Host "    dev      → Lancer en développement (émulateur local)"
        Write-Host "    staging  → Compiler APK pour testeurs (backend Render)"
        Write-Host "    prod     → Compiler AAB pour le Play Store"
        Write-Host "    clean    → Nettoyer le projet"
        Write-Host ""
        Write-Host "  Exemples:" -ForegroundColor Green
        Write-Host "    .\build.ps1 staging"
        Write-Host "    .\build.ps1 dev"
        Write-Host ""
    }

    "dev" {
        Write-Header "Lancement en mode développement (émulateur Android)..."
        flutter run --dart-define-from-file=.env.local
    }

    "staging" {
        Write-Header "Compilation APK Staging → Backend Render..."
        flutter pub get
        flutter build apk --release `
            --dart-define-from-file=.env.staging `
            --build-name=1.0.0 `
            --build-number=1

        $apkPath = "build\app\outputs\flutter-apk\app-release.apk"
        if (Test-Path $apkPath) {
            Write-Host "  ✅ APK prêt !" -ForegroundColor Green
            Write-Host "  📦 Fichier : $apkPath" -ForegroundColor White
            Write-Host "  📤 Uploadez ce fichier sur Firebase App Distribution" -ForegroundColor Yellow
            Write-Host ""
            # Ouvrir le dossier automatiquement
            $folder = Split-Path -Parent $apkPath
            explorer $folder
        } else {
            Write-Host "  ❌ APK introuvable. Vérifiez les erreurs ci-dessus." -ForegroundColor Red
        }
    }

    "prod" {
        Write-Header "Compilation AAB Production → Play Store..."
        flutter pub get
        flutter build appbundle --release `
            --dart-define-from-file=.env.production `
            --build-name=1.0.0 `
            --build-number=1

        $aabPath = "build\app\outputs\bundle\release\app-release.aab"
        if (Test-Path $aabPath) {
            Write-Host "  ✅ AAB prêt !" -ForegroundColor Green
            Write-Host "  📦 Fichier : $aabPath" -ForegroundColor White
            Write-Host "  📤 Uploadez sur Google Play Console" -ForegroundColor Yellow
            Write-Host ""
            $folder = Split-Path -Parent $aabPath
            explorer $folder
        } else {
            Write-Host "  ❌ AAB introuvable. Vérifiez les erreurs ci-dessus." -ForegroundColor Red
        }
    }

    "clean" {
        Write-Header "Nettoyage du projet Flutter..."
        flutter clean
        flutter pub get
        Write-Host "  ✅ Projet nettoyé" -ForegroundColor Green
    }
}
