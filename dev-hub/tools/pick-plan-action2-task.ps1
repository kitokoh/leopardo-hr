param(
    [string]$CsvPath = "docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv",
    [string]$Priority = "",
    [string]$Area = "",
    [string]$OutputPath = ""
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $CsvPath)) {
    throw "PLAN_ACTION2 CSV not found: $CsvPath"
}

$rows = Import-Csv $CsvPath

if ($Priority) {
    $rows = $rows | Where-Object { $_.Priority -eq $Priority }
}
if ($Area) {
    $rows = $rows | Where-Object { $_.Area -eq $Area }
}

$priorityOrder = @{ P0 = 0; P1 = 1; P2 = 2; P3 = 3 }
$task = $rows |
    Sort-Object @{ Expression = { if ($priorityOrder.ContainsKey($_.Priority)) { $priorityOrder[$_.Priority] } else { 9 } } }, Area, Title |
    Select-Object -First 1

if (-not $task) {
    throw "No PLAN_ACTION2 task matches Priority='$Priority' Area='$Area'."
}

$id = ($task.Title -split " ")[0]
$brief = @"
# $($task.Title)

Priority: $($task.Priority)
Area: $($task.Area)
Surface: $($task.Surface)
Dependencies: $($task.Dependencies)

## Acceptance Criteria

$($task."Acceptance Criteria")

## Agent Instructions

1. Run `git fetch origin main` and start from the latest `origin/main`.
2. Search the codebase for the surfaces listed above before editing.
3. Keep the change scoped to this ticket unless a dependency is genuinely missing.
4. Update `CHANGELOG.md`.
5. Update docs/API matrix/OpenAPI/i18n debt files when the implementation changes those contracts.
6. Open a PR whose title or body includes $id.
"@

if ($OutputPath) {
    $parent = Split-Path $OutputPath -Parent
    if ($parent -and -not (Test-Path $parent)) {
        New-Item -ItemType Directory -Path $parent | Out-Null
    }
    Set-Content -Path $OutputPath -Value $brief -Encoding UTF8
    Write-Host "Task brief written to $OutputPath"
} else {
    Write-Output $brief
}
