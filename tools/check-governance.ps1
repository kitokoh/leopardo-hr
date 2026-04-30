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

function Assert-Contains([string]$Path, [string]$Pattern, [string]$FailureMessage) {
    $content = Get-Content $Path -Raw
    if ($content -notmatch $Pattern) {
        Fail $FailureMessage
    }
}

function Assert-NotContains([string]$Path, [string]$Pattern, [string]$FailureMessage) {
    $content = Get-Content $Path -Raw
    if ($content -match $Pattern) {
        Fail $FailureMessage
    }
}

Write-Host "Running governance checks on diff $BaseRef..$HeadRef"

$changed = git diff --name-only $BaseRef $HeadRef
if (-not $changed) {
    Pass "No changes detected."
    exit 0
}

$requiredFiles = @(
    "PILOTAGE.md",
    ".github/PULL_REQUEST_TEMPLATE.md",
    ".github/BRANCH_PROTECTION_REQUIRED.md",
    ".github/workflows/phpstan-baseline.yml",
    "api/phpstan.neon",
    "api/phpstan-baseline.neon",
    "docs/notes/archive/INDEX_CANONIQUE.md",
    "docs/notes/archive/BACKLOG_PHASE1_UNIQUE.md",
    "docs/GESTION_PROJET/GARDE_FOUS.md",
    "docs/GESTION_PROJET/CORRECTIONS.md",
    "docs/GESTION_PROJET/RUNBOOK_DEPLOY.md",
    "docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md",
    "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md",
    "docs/GESTION_PROJET/RUNBOOK_INCIDENT_P1.md",
    "docs/GESTION_PROJET/RUNBOOK_DRILLS_LOG.md",
    "docs/notes/archive/EXECUTION_BLOCKERS_AND_NEXT.md",
    "docs/notes/archive/08_FEUILLE_DE_ROUTE.md",
    "docs/notes/archive/CU-01_ET_AGENTS.md",
    "docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md"
)

foreach ($f in $requiredFiles) {
    if (-not (Test-Path $f)) {
        Fail "Missing required governance file: $f"
    }
}
Pass "All required governance files exist."

$historicalFiles = @{
    "docs/notes/archive/BACKLOG_PHASE1_UNIQUE.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/INDEX_CANONIQUE.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/CONTEXTE_SESSION_IA.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/JOURNAL_DE_BORD.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/SUIVI_PROMPTS.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/08_FEUILLE_DE_ROUTE.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/CU-01_ET_AGENTS.md" = "REMPLACE PAR PILOTAGE\.md"
    "docs/notes/archive/ARBORESCENCE_PROJET_COMPLET.md" = "REMPLACE PAR PILOTAGE\.md"
}

foreach ($entry in $historicalFiles.GetEnumerator()) {
    Assert-Contains $entry.Key $entry.Value "Historical marker missing in $($entry.Key)"
}
Pass "Historical canonical redirects are intact."

Assert-Contains ".github/BRANCH_PROTECTION_REQUIRED.md" 'CodeQL \(Actions\)' 'Branch protection doc must reference CodeQL (Actions).'
Assert-NotContains ".github/BRANCH_PROTECTION_REQUIRED.md" 'CodeQL \(Backend\)' 'Branch protection doc still references the obsolete CodeQL (Backend) label.'
Assert-Contains ".github/BRANCH_PROTECTION_REQUIRED.md" 'Backend Quality \(Pint \+ PHP Syntax \+ PHPStan/Larastan\)' 'Branch protection doc must reference the PHPStan/Larastan quality gate.'
Pass "Branch protection guidance matches the active checks."

$criticalPattern = '^(api/|mobile/|docs/dossierdeConception/|docs/GESTION_PROJET/|docs/REFERENTIEL_PRODUIT/|docs/notes/archive/|PILOTAGE\.md|\.github/|tools/check-governance\.ps1)'
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
