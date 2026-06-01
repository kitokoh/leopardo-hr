param(
    [string]$Root = "."
)

$ErrorActionPreference = "Stop"

function Fail($Message) {
    Write-Error $Message
    exit 1
}

function Read-RepoFile($Path) {
    $fullPath = Join-Path $Root $Path
    if (-not (Test-Path -LiteralPath $fullPath)) {
        Fail "Missing required file: $Path"
    }

    return Get-Content -LiteralPath $fullPath -Raw
}

function Assert-Contains($Content, $Needle, $Label) {
    if ($Content -notlike "*$Needle*") {
        Fail "$Label is missing required marker: $Needle"
    }
}

function Assert-NotContains($Content, $Needle, $Label) {
    if ($Content -like "*$Needle*") {
        Fail "$Label contains obsolete marker: $Needle"
    }
}

$apiReadme = Read-RepoFile "docs/api/README.md"
$planSummary = Read-RepoFile "docs/PLAN_ACTION/00_SOMMAIRE.md"
$releaseGate = Read-RepoFile "docs/validation/RELEASE_READINESS_GATE.md"
$plan68 = Read-RepoFile "docs/PLAN_ACTION/68_PLAN_AUDIT_POST_67_QUALITE_CODE_LANCEMENT.md"

Assert-Contains $apiReadme "/docs/openapi.yaml" "API README"
Assert-Contains $apiReadme "/docs" "API README"
Assert-NotContains $apiReadme "/openapi/v1.yaml" "API README"
Assert-NotContains $planSummary "openapi/v1.yaml" "Plan summary"

foreach ($path in @(
    "dev-hub/tools/repository-hygiene-report.ps1",
    "dev-hub/tools/validate-frontend-api-contract-governance.ps1",
    "dev-hub/tools/validate-open-core-boundaries.ps1",
    "docs/validation/REPOSITORY_HYGIENE_REPORT_2026_06_01.md",
    "docs/validation/FRONTEND_API_CONTRACT_GOVERNANCE_REPORT_2026_06_01.md",
    "docs/validation/MARKETPLACE_OPEN_CORE_READINESS_2026_06_01.md"
)) {
    if (-not (Test-Path -LiteralPath (Join-Path $Root $path))) {
        Fail "Missing post-67 governance artifact: $path"
    }
}

Assert-Contains $releaseGate "25/25" "Release readiness gate"
Assert-Contains $plan68 "Lot 68.3" "Plan 68"
Assert-Contains $plan68 "validate-code-quality-governance.ps1" "Plan 68"

Write-Host "[code-quality] Canonical docs and post-67 quality governance artifacts are aligned."
