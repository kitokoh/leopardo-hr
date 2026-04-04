param(
    [string]$BaseRef = "HEAD~1",
    [string]$HeadRef = "HEAD"
)

$ErrorActionPreference = "Stop"

function Fail([string]$Message) {
    Write-Host "FAIL: $Message" -ForegroundColor Red
    exit 1
}

function Pass([string]$Message) {
    Write-Host "OK: $Message" -ForegroundColor Green
}

Write-Host "Running governance checks on diff $BaseRef..$HeadRef"

$changed = git diff --name-only $BaseRef $HeadRef
if (-not $changed) {
    Pass "No changes detected."
    exit 0
}

$requiredFiles = @(
    "docs/GESTION_PROJET/INDEX_CANONIQUE.md",
    "docs/GESTION_PROJET/BACKLOG_PHASE1_UNIQUE.md",
    "docs/GESTION_PROJET/RUNBOOK_DEPLOY.md",
    "docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md",
    "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md",
    "docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md"
)

foreach ($f in $requiredFiles) {
    if (-not (Test-Path $f)) {
        Fail "Missing required governance file: $f"
    }
}
Pass "All required governance files exist."

$criticalPattern = '^(api/|docs/dossierdeConception/|ORCHESTRATION_MAITRE\.md|08_FEUILLE_DE_ROUTE\.md)'
$requiresChangelog = $false
foreach ($line in $changed) {
    if ($line -match $criticalPattern) {
        $requiresChangelog = $true
        break
    }
}

if ($requiresChangelog) {
    $hasChangelog = $false
    foreach ($line in $changed) {
        if ($line -eq "CHANGELOG.md") {
            $hasChangelog = $true
            break
        }
    }
    if (-not $hasChangelog) {
        Fail "Critical scope changed but CHANGELOG.md is not updated."
    }
    Pass "CHANGELOG updated for critical scope."
} else {
    Pass "No critical scope change requiring changelog."
}

Pass "Governance checks passed."
