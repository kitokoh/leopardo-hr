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

$adr = Read-RepoFile "docs/architecture/adr/0004-open-core-marketplace-boundaries.md"
Assert-Contains $adr "open core cadree" "ADR 0004 decision"
Assert-Contains $adr "enterprise only" "ADR 0004 enterprise boundary"
Assert-Contains $adr "Aucun depot public" "ADR 0004 publication gate"
Assert-Contains $adr "webhooks doivent inclure signature" "ADR 0004 webhook security"

$guide = Read-RepoFile "docs/GUIDES/GUIDE_OPEN_CORE_MARKETPLACE.md"
foreach ($marker in @(
    "Ce qui peut devenir open source",
    "Ce qui reste enterprise",
    "Secrets et donnees a isoler",
    "Licence et support",
    "Scopes API initiaux proposes",
    "Webhooks initiaux proposes"
)) {
    Assert-Contains $guide $marker "Open core guide"
}

foreach ($forbidden in @(
    "-----BEGIN PRIVATE KEY-----",
    "firebase-adminsdk",
    "FIREBASE_SERVICE_ACCOUNT_JSON",
    "ghp_",
    "Bearer ey"
)) {
    if ($guide -like "*$forbidden*" -or $adr -like "*$forbidden*") {
        Fail "Open core documentation contains a forbidden secret-like marker: $forbidden"
    }
}

$report = Read-RepoFile "docs/validation/MARKETPLACE_OPEN_CORE_READINESS_2026_06_01.md"
Assert-Contains $report "Score global" "Open core readiness report"
Assert-Contains $report "Gates avant publication publique" "Open core readiness report"

Write-Host "[open-core] Boundaries, marketplace scopes and publication gates are documented."
