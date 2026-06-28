param(
    [string]$BaseRef = $env:GITHUB_BASE_REF
)

$ErrorActionPreference = "Stop"

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot "..\..")
Set-Location $repoRoot

$appsRoot = Join-Path $repoRoot "front/mobile_apps"
$coreRoot = Join-Path $appsRoot "leopardo_core"
$employeeRoot = Join-Path $appsRoot "leopardo_employee"
$managerRoot = Join-Path $appsRoot "leopardo_manager"
$platformAdminRoot = Join-Path $appsRoot "leopardo_platform_admin"
$legacyRoot = Join-Path $appsRoot "leopardo_mobile_legacy"

$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

function Assert-Path([string]$path, [string]$label) {
    if (-not (Test-Path -LiteralPath $path)) {
        Add-Failure "$label missing at $path"
    }
}

Assert-Path $coreRoot "leopardo_core"
Assert-Path $employeeRoot "leopardo_employee"
Assert-Path $managerRoot "leopardo_manager"
Assert-Path $platformAdminRoot "leopardo_platform_admin"
# leopardo_mobile_legacy: ARCHIVED (2026-06-28) — new multi-app architecture is complete.
# The legacy app has been removed from git tracking. Do not restore this assertion.
# Assert-Path $legacyRoot "leopardo_mobile_legacy"

if ($failures.Count -eq 0) {
    # Legacy CI/release artifact checks removed (2026-06-28)
    # leopardo_mobile_legacy is archived — its CI workflow and release artifacts are no longer required.

    $employeeForbiddenPaths = @(
        "lib/features/team",
        "lib/features/approvals",
        "lib/features/organigramme",
        "lib/features/modules"
    )

    foreach ($relativePath in $employeeForbiddenPaths) {
        $fullPath = Join-Path $employeeRoot $relativePath
        if (Test-Path -LiteralPath $fullPath) {
            Add-Failure "Employee app must not contain manager feature path: $relativePath"
        }
    }

    $employeeForbiddenPatterns = @(
        "canManageTeam",
        "isManager",
        "isPrincipal",
        "isHr",
        "managerRole",
        "/team",
        "/approvals",
        "/organigramme",
        "/manager/"
    )

    $employeeDartFiles = Get-ChildItem -LiteralPath (Join-Path $employeeRoot "lib") -Recurse -File -Filter *.dart
    foreach ($file in $employeeDartFiles) {
        $content = Get-Content -LiteralPath $file.FullName -Raw
        foreach ($pattern in $employeeForbiddenPatterns) {
            if ($content.Contains($pattern)) {
                $relative = Resolve-Path -LiteralPath $file.FullName -Relative
                Add-Failure "Employee app contains forbidden manager marker '$pattern' in $relative"
            }
        }
    }

    $sharedRoots = @($coreRoot, $employeeRoot, $managerRoot, $platformAdminRoot)
    foreach ($root in $sharedRoots) {
        $dartFiles = Get-ChildItem -LiteralPath (Join-Path $root "lib") -Recurse -File -Filter *.dart
        foreach ($file in $dartFiles) {
            $content = Get-Content -LiteralPath $file.FullName -Raw
            if ($content.Contains("package:leopardo_rh/")) {
                $relative = Resolve-Path -LiteralPath $file.FullName -Relative
                Add-Failure "New mobile app imports legacy package name in $relative"
            }
        }
    }

    $coreFiles = Get-ChildItem -LiteralPath (Join-Path $coreRoot "lib") -Recurse -File -Filter *.dart
    foreach ($file in $coreFiles) {
        $content = Get-Content -LiteralPath $file.FullName -Raw
        if ($content.Contains("package:leopardo_employee/") -or $content.Contains("package:leopardo_manager/") -or $content.Contains("package:leopardo_platform_admin/")) {
            $relative = Resolve-Path -LiteralPath $file.FullName -Relative
            Add-Failure "Core package must not import app-specific package in $relative"
        }
    }

    foreach ($app in @("leopardo_employee", "leopardo_manager", "leopardo_platform_admin")) {
        $pubspec = Join-Path $appsRoot "$app/pubspec.yaml"
        $content = Get-Content -LiteralPath $pubspec -Raw
        if (-not $content.Contains("leopardo_core:")) {
            Add-Failure "$app pubspec must depend on leopardo_core"
        }
        if (-not $content.Contains("path: ../leopardo_core")) {
            Add-Failure "$app pubspec must use path dependency ../leopardo_core"
        }
    }

    $managerApp = Get-Content -LiteralPath (Join-Path $managerRoot "lib/app.dart") -Raw
    foreach ($route in @("/manager/dashboard", "/manager/attendance", "/manager/anomalies", "/manager/corrections")) {
        if (-not $managerApp.Contains($route)) {
            Add-Failure "Manager app is missing prepared route $route"
        }
    }
}

# Legacy immutability check removed (2026-06-28) — leopardo_mobile_legacy is archived.
# Changes to front/mobile_apps/leopardo_mobile_legacy/* are intentionally allowed (archiving work).
if ($BaseRef) {
    git fetch origin $BaseRef --depth=1 | Out-Null
    # No legacy immutability check needed
}

if ($failures.Count -gt 0) {
    Write-Host "Mobile apps split validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile apps split validation passed."
