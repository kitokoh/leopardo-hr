param()

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Assert-Contains([string]$content, [string]$needle, [string]$label) {
    if (-not $content.Contains($needle)) {
        Add-Failure "$label must contain '$needle'"
    }
}

$appRoot = Join-Path $repoRoot "front/mobile_apps/leopardo_platform_admin"
$coreRoot = Join-Path $repoRoot "front/mobile_apps/leopardo_core"

foreach ($path in @($appRoot, $coreRoot)) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "Missing mobile path: $path"
    }
}

if (Test-Path -LiteralPath $appRoot) {
    $pubspec = Get-Content -LiteralPath (Join-Path $appRoot "pubspec.yaml") -Raw
    $androidGradle = Get-Content -LiteralPath (Join-Path $appRoot "android/app/build.gradle.kts") -Raw
    $androidManifest = Get-Content -LiteralPath (Join-Path $appRoot "android/app/src/main/AndroidManifest.xml") -Raw
    $iosProject = Get-Content -LiteralPath (Join-Path $appRoot "ios/Runner.xcodeproj/project.pbxproj") -Raw
    $appContent = (Get-ChildItem -LiteralPath (Join-Path $appRoot "lib") -Recurse -File -Filter *.dart | ForEach-Object {
        Get-Content -LiteralPath $_.FullName -Raw
    }) -join "`n"

    Assert-Contains $pubspec "name: leopardo_platform_admin" "Platform admin pubspec"
    Assert-Contains $pubspec "path: ../leopardo_core" "Platform admin core dependency"
    Assert-Contains $androidGradle 'applicationId = "com.leopardo.platformadmin"' "Platform admin Android id"
    Assert-Contains $androidGradle 'namespace = "com.leopardo.platformadmin"' "Platform admin Android namespace"
    Assert-Contains $androidManifest 'android:label="Leopardo Platform Admin"' "Platform admin Android label"
    Assert-Contains $iosProject "PRODUCT_BUNDLE_IDENTIFIER = com.leopardo.platformadmin;" "Platform admin iOS bundle"

    foreach ($endpoint in @(
        "/platform/auth/login",
        "/platform/auth/me",
        "/platform/auth/logout",
        "/platform/metrics/overview",
        "/platform/companies",
        "/platform/company-requests"
    )) {
        Assert-Contains $appContent $endpoint "Platform admin API contract"
    }

    foreach ($route in @(
        "/platform/login",
        "/platform/companies/new",
        "/platform/company-requests"
    )) {
        Assert-Contains $appContent $route "Platform admin router"
    }

    foreach ($forbidden in @(
        "/attendance/check-in",
        "/attendance/check-out",
        "/absences",
        "/salary-advances",
        "/team",
        "/approvals"
    )) {
        if ($appContent.Contains($forbidden)) {
            Add-Failure "Platform admin app must not contain tenant persona route or endpoint: $forbidden"
        }
    }

    $emptyHandlers = Select-String -Path (Join-Path $appRoot "lib/**/*.dart") -Pattern "onPressed:\s*\(\)\s*\{\s*\}|onTap:\s*\(\)\s*\{\s*\}" -AllMatches
    if ($emptyHandlers) {
        foreach ($match in $emptyHandlers) {
            Add-Failure "Platform admin contains empty UI handler in $($match.Path):$($match.LineNumber)"
        }
    }
}

Assert-Contains `
    (Get-Content -LiteralPath (Join-Path $repoRoot ".github/workflows/mobile-apps-ci.yml") -Raw) `
    "leopardo_platform_admin" `
    "Mobile Apps CI"

$deployMain = Get-Content -LiteralPath (Join-Path $repoRoot ".github/workflows/deploy-main.yml") -Raw
$mobileDistribute = Get-Content -LiteralPath (Join-Path $repoRoot ".github/workflows/mobile-distribute.yml") -Raw

Assert-Contains $deployMain "leopardo_platform_admin" "Deploy main mobile distribution"
Assert-Contains $deployMain "FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID" "Deploy main mobile distribution"
Assert-Contains $deployMain "leopardo-platform-admin-main" "Deploy main mobile distribution"

Assert-Contains $mobileDistribute "branches:" "Mobile distribute auto trigger"
Assert-Contains $mobileDistribute "front/mobile_apps/**" "Mobile distribute auto trigger"
Assert-Contains $mobileDistribute "type: string" "Mobile distribute dispatch schema"
Assert-Contains $mobileDistribute "platform_admin" "Mobile distribute app selector"
Assert-Contains $mobileDistribute "leopardo_platform_admin" "Mobile distribute matrix"
Assert-Contains $mobileDistribute "FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID" "Mobile distribute matrix"
Assert-Contains $mobileDistribute "FIREBASE_READBACK_REQUIRED" "Mobile distribute Firebase readback strict toggle"
Assert-Contains $deployMain "FIREBASE_READBACK_REQUIRED" "Deploy main Firebase readback strict toggle"

Assert-Contains `
    (Get-Content -LiteralPath (Join-Path $repoRoot "dev-hub/tools/install-mobile-firebase-configs.ps1") -Raw) `
    "com.leopardo.platformadmin" `
    "Firebase config installer"

if ($failures.Count -gt 0) {
    Write-Host "Mobile Plan 29 validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile Plan 29 validation passed."
