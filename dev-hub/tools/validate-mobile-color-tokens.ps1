param(
    [string]$Root = "."
)

$ErrorActionPreference = "Stop"

# PA2-MOB-011 anti-regression guard: hardcoded hex color literals
# (`Color(0x...)`) must never reappear in mobile app screens/widgets.
# All colors must be centralized in leopardo_core's AppColors palette
# (front/mobile_apps/leopardo_core/lib/core/theme/app_colors.dart).
#
# Rationale: duplicated hex literals across the manager/hr/employee
# attendance and smart_attendance screens made theming and dark-mode
# consistency hard to maintain. This script fails CI if a new
# `Color(0x...)` literal is introduced outside the single source of
# truth file.

$appsRoot = Join-Path $Root "front/mobile_apps"
$allowedFile = Resolve-Path -LiteralPath (Join-Path $appsRoot "leopardo_core/lib/core/theme/app_colors.dart")

$failures = New-Object System.Collections.Generic.List[string]

function Add-Failure([string]$message) {
    $failures.Add($message) | Out-Null
}

$dartFiles = Get-ChildItem -LiteralPath $appsRoot -Recurse -File -Filter *.dart

foreach ($file in $dartFiles) {
    if ($file.FullName -eq $allowedFile.Path) {
        continue
    }

    $lineNumber = 0
    foreach ($line in Get-Content -LiteralPath $file.FullName) {
        $lineNumber++
        if ($line -match "Color\(0x[0-9A-Fa-f]{6,8}\)") {
            $relative = Resolve-Path -LiteralPath $file.FullName -Relative
            Add-Failure "Hardcoded hex color literal found in ${relative}:${lineNumber} - use AppColors instead"
        }
    }
}

if ($failures.Count -gt 0) {
    Write-Host "Mobile color tokens validation failed:" -ForegroundColor Red
    foreach ($failure in $failures) {
        Write-Host "- $failure" -ForegroundColor Red
    }
    exit 1
}

Write-Host "Mobile color tokens validation passed: no hardcoded Color(0x...) literals outside AppColors."
