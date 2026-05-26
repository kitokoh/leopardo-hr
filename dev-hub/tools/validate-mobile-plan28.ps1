param()

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Get-DartContent([string]$root) {
    return (Get-ChildItem -LiteralPath (Join-Path $root "lib") -Recurse -File -Filter *.dart | ForEach-Object {
        Get-Content -LiteralPath $_.FullName -Raw
    }) -join "`n"
}

function Get-PlistString([string]$content, [string]$key) {
    $pattern = "<key>$([regex]::Escape($key))</key>\s*<string>([^<]+)</string>"
    $match = [regex]::Match($content, $pattern)
    if ($match.Success) {
        return $match.Groups[1].Value
    }
    return $null
}

function Assert-FileContains([string]$path, [string]$needle, [string]$label) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "$label missing: $path"
        return
    }

    $content = Get-Content -LiteralPath $path -Raw
    if (-not $content.Contains($needle)) {
        Add-Failure "$label must contain '$needle'"
    }
}

function Assert-AndroidFirebasePackage([string]$path, [string]$packageName, [string]$label) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "$label Firebase Android config missing: $path"
        return
    }

    $json = Get-Content -LiteralPath $path -Raw | ConvertFrom-Json
    $packages = @($json.client | ForEach-Object { $_.client_info.android_client_info.package_name })
    if ($packages -notcontains $packageName) {
        Add-Failure "$label Firebase Android config must contain package $packageName. Found: $($packages -join ', ')"
    }
}

function Assert-IosFirebaseBundle([string]$path, [string]$bundleId, [string]$label) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "$label Firebase iOS config missing: $path"
        return
    }

    $content = Get-Content -LiteralPath $path -Raw
    $actual = Get-PlistString $content "BUNDLE_ID"
    if ($actual -ne $bundleId) {
        Add-Failure "$label Firebase iOS bundle must be $bundleId. Found: $actual"
    }
}

$appsRoot = Join-Path $repoRoot "front/mobile_apps"
$employeeRoot = Join-Path $appsRoot "leopardo_employee"
$managerRoot = Join-Path $appsRoot "leopardo_manager"
$coreRoot = Join-Path $appsRoot "leopardo_core"
$legacyRoot = Join-Path $appsRoot "leopardo_mobile_legacy"

foreach ($path in @($employeeRoot, $managerRoot, $coreRoot, $legacyRoot)) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "Mobile app root missing: $path"
    }
}

if (Test-Path -LiteralPath $employeeRoot) {
    $employeeContent = Get-DartContent $employeeRoot
    $employeeForbiddenTokens = @(
        "approveAbsence",
        "rejectAbsence",
        "approveAdvance",
        "rejectAdvance",
        "/approvals",
        "/team",
        "/manager/dashboard",
        "/manager/attendance",
        "/manager/anomalies",
        "/manager/corrections",
        "/approve'",
        "/reject'"
    )

    foreach ($token in $employeeForbiddenTokens) {
        if ($employeeContent.Contains($token)) {
            Add-Failure "Employee app must not contain manager decision token: $token"
        }
    }

    foreach ($path in @("lib/features/team", "lib/features/approvals", "lib/features/manager")) {
        if (Test-Path -LiteralPath (Join-Path $employeeRoot $path)) {
            Add-Failure "Employee app must not contain manager feature path: $path"
        }
    }
}

if (Test-Path -LiteralPath $managerRoot) {
    $managerContent = Get-DartContent $managerRoot
    $managerRequiredTokens = @(
        "approveAbsence",
        "rejectAbsence",
        "approveAdvance",
        "rejectAdvance",
        "/team",
        "/approvals",
        "/manager/dashboard",
        "/manager/attendance",
        "/manager/anomalies",
        "/manager/corrections"
    )

    foreach ($token in $managerRequiredTokens) {
        if (-not $managerContent.Contains($token)) {
            Add-Failure "Manager app must keep manager workflow token: $token"
        }
    }
}

if (Test-Path -LiteralPath $coreRoot) {
    $coreContent = Get-DartContent $coreRoot
    foreach ($token in @("package:leopardo_employee/", "package:leopardo_manager/")) {
        if ($coreContent.Contains($token)) {
            Add-Failure "Core package must not import app-specific package: $token"
        }
    }
}

Assert-AndroidFirebasePackage `
    (Join-Path $employeeRoot "android/app/google-services.json") `
    "com.leopardo.employee" `
    "Employee"
Assert-AndroidFirebasePackage `
    (Join-Path $managerRoot "android/app/google-services.json") `
    "com.leopardo.manager" `
    "Manager"
Assert-IosFirebaseBundle `
    (Join-Path $employeeRoot "ios/Runner/GoogleService-Info.plist") `
    "com.leopardo.employee" `
    "Employee"
Assert-IosFirebaseBundle `
    (Join-Path $managerRoot "ios/Runner/GoogleService-Info.plist") `
    "com.leopardo.manager" `
    "Manager"

Assert-FileContains `
    (Join-Path $repoRoot ".github/workflows/deploy-main.yml") `
    "appdistribution:releases:list" `
    "Main deploy Firebase read-after-write"
Assert-FileContains `
    (Join-Path $repoRoot ".github/workflows/mobile-distribute.yml") `
    "appdistribution:releases:list" `
    "Manual mobile distribution Firebase read-after-write"
Assert-FileContains `
    (Join-Path $repoRoot ".github/workflows/deploy-main.yml") `
    "FIREBASE_EMPLOYEE_ANDROID_APP_ID" `
    "Main deploy employee Firebase secret"
Assert-FileContains `
    (Join-Path $repoRoot ".github/workflows/deploy-main.yml") `
    "FIREBASE_MANAGER_ANDROID_APP_ID" `
    "Main deploy manager Firebase secret"

Assert-FileContains `
    (Join-Path $repoRoot "docs/validation/MOBILE_FIREBASE_DISTRIBUTION.md") `
    'iOS necessitera un workflow macOS signe produisant un `.ipa`' `
    "Firebase distribution documentation"

if ($failures.Count -gt 0) {
    Write-Host "Mobile Plan 28 validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile Plan 28 validation passed."
