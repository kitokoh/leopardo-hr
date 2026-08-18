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
    "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md",
    "docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md",
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

$changedList = @($changed | Where-Object { $_ -and $_.Trim() -ne "" })

$apiFeatureChanged = $false
$mobileFeatureChanged = $false
$webFeatureChanged = $false

foreach ($line in $changedList) {
    if ($line -match '^(api/app/Http/Controllers/|api/routes/|api/app/Services/|api/app/Policies/|api/app/Http/Requests/Api/)') {
        $apiFeatureChanged = $true
    }
    if ($line -match '^(mobile/lib/features/|mobile/integration_test/|mobile/test/features/)') {
        $mobileFeatureChanged = $true
    }
    if ($line -match '^(admin-dashboard/src/|admin-dashboard/e2e/|admin-dashboard/playwright\.config\.js)') {
        $webFeatureChanged = $true
    }
}

function Assert-ScenarioUpdate([string]$ScenarioFile, [string]$Label) {
    $updated = $false
    foreach ($line in $changedList) {
        if ($line -eq $ScenarioFile -or $line -eq "docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md") {
            $updated = $true
            break
        }
    }

    if (-not $updated) {
        Fail "$Label changed but neither $ScenarioFile nor docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md was updated."
    }
}

if ($apiFeatureChanged) {
    Assert-ScenarioUpdate "docs/GESTION_PROJET/SCENARIOS_TEST_API_GITHUB_ACTIONS.md" "API feature surface"
    Pass "API scenario governance updated."
}

if ($mobileFeatureChanged) {
    Assert-ScenarioUpdate "docs/GESTION_PROJET/SCENARIOS_TEST_MOBILE_FLUTTER.md" "Mobile feature surface"
    Pass "Mobile scenario governance updated."
}

if ($webFeatureChanged) {
    Assert-ScenarioUpdate "docs/GESTION_PROJET/SCENARIOS_TEST_WEB_ADMIN_GITHUB_ACTIONS.md" "Web admin feature surface"
    Pass "Web scenario governance updated."
}

# Issue #1589 + #1612 : le CHANGELOG a un historique d'encodage mixte
# latin-1/UTF-8 (mojibake). Garde : le fichier doit rester un UTF-8 valide et
# ne doit pas contenir de séquences de ré-encodage.
#
# #1589 couvrait les patrons classiques (Ã©, â€, U+FFFD). #1612 ajoute la liste
# noire des patrons NON standard observés dans l'historique (outillage
# défectueux, ~830 occurrences réparées le 2026-08-09) :
#   GÇö (em dash) · GåÆ (flèche) · Gùï (✓) · +¬/+¦/+¿/+º/+á (é/ô/è/ç/à) ·
#   â-¬/â-¦/â-¿/â-á (é/ô/è/à doublement ré-encodés) · ó—— (flèche).
# Tout ré-encodage de l'un de ces patrons fait échouer la garde ; les
# caractères légitimes (é è ê à ç ô ù ï — – … ✓ ✗ → § • « ») restent admis.
if (Test-Path "CHANGELOG.md") {
    $bytes = [System.IO.File]::ReadAllBytes((Resolve-Path "CHANGELOG.md"))
    try {
        $enc = New-Object System.Text.UTF8Encoding($false, $true)
        $text = $enc.GetString($bytes)
        $mojibakePattern = 'G\u00c7\u00f6|G\u00e5\u00c6|G\u00f9\u00ef|\+\u00ac|\+\u00a6|\+\u00bf|\+\u00ba|\+\u00e1|\u00e2-\u00ac|\u00e2-\u00a6|\u00e2-\u00bf|\u00e2-\u00e1|\u00f3\u2014{2}|\u00c3[\u00a0-\u00bf]|\u00e2\u20ac|\ufffd'
        if ($text -match $mojibakePattern) {
            Fail "CHANGELOG.md contient des séquences mojibake (ré-encodage latin-1/UTF-8) — ré-encoder en UTF-8 propre (issues #1589/#1612)."
        }
    } catch [System.Text.DecoderFallbackException] {
        Fail "CHANGELOG.md n'est pas un UTF-8 valide (issue #1589) : $($_.Exception.Message)"
    }
    Pass "CHANGELOG.md encoding is valid UTF-8 without mojibake."
}

# Session 2026-08-15 — garde structure CHANGELOG : les merges parallèles
# (swarm d'agents) ont dupliqué l'en-tête "### Fixed" à plusieurs reprises
# (#2480, #2495, #2503…). Un en-tête de section ne doit jamais apparaître
# deux fois de suite.
if (Test-Path "CHANGELOG.md") {
    $chg = Get-Content "CHANGELOG.md" -Raw
    $dupHeaders = @("### Fixed`n### Fixed", "### Added`n### Added", "### Changed`n### Changed", "### Removed`n### Removed")
    foreach ($h in $dupHeaders) {
        if ($chg.Contains($h)) {
            Fail "CHANGELOG.md contient un en-tête de section dupliqué (merge parallèle) — fusionner les sections avant merge."
        }
    }
    Pass "CHANGELOG.md section headers are not duplicated."
}

# Session 2026-08-17 — consolidation CHANGELOG (audit doc chef de projet).
# Garde DIFF-AWARE : on compare la structure de CHANGELOG.md entre la base et
# la tête de la PR. Une PR ne doit JAMAIS AJOUTER un header "## [Unreleased]"
# (il en existe déjà un) ni dupliquer un header de version. L'état absolu de
# main (dette préexistante) ne bloque aucune PR — seul le diff compte.
# Plafond dur de taille anti-régression (CONVENTIONS §4.3).
$changelogTouched = $false
foreach ($line in $changed) {
    if ($line -eq "CHANGELOG.md") { $changelogTouched = $true; break }
}
if ($changelogTouched -and (Test-Path "CHANGELOG.md")) {
    $chg = Get-Content "CHANGELOG.md" -Raw
    $baseChg = ""
    try {
        $baseChg = git show "${BaseRef}:CHANGELOG.md"
    } catch {
        $baseChg = ""
    }

    $unrelRegex = '(?m)^## \[Unreleased\]$'
    $headUnrel = ([regex]::Matches($chg, $unrelRegex)).Count
    $baseUnrel = if ($baseChg) { ([regex]::Matches($baseChg, $unrelRegex)).Count } else { 1 }
    if ($headUnrel -gt $baseUnrel) {
        Fail "CHANGELOG.md ajoute $($headUnrel - $baseUnrel) header(s) '## [Unreleased]' par rapport à la base ($baseUnrel → $headUnrel) — une PR ne doit jamais en ajouter (fusionner les blocs avant merge)."
    }
    Pass "CHANGELOG.md [Unreleased] headers not increased by this PR."

    $verRegex = '(?m)^## \[(4\.[0-9]+\.[0-9]+[a-z0-9-]*)\](?: - .*)?$'
    $headVers = [regex]::Matches($chg, $verRegex) | ForEach-Object { $_.Groups[1].Value }
    $baseVers = if ($baseChg) { [regex]::Matches($baseChg, $verRegex) | ForEach-Object { $_.Groups[1].Value } } else { @() }
    $headDups = $headVers | Group-Object | ForEach-Object {
        $g = $_
        [pscustomobject]@{
            Name      = $g.Name
            Count     = $g.Count
            BaseCount = @($baseVers | Where-Object { $_ -eq $g.Name }).Count
        }
    }
    $addedDups = $headDups | Where-Object { $_.Count -gt $_.BaseCount }
    if ($addedDups) {
        Fail "CHANGELOG.md duplique des headers de version par rapport à la base: $(($addedDups | ForEach-Object { $_.Name + 'x' + $_.Count + ' (base: ' + $_.BaseCount + ')' }) -join ', ') — fusionner avant merge."
    }
    Pass "CHANGELOG.md version headers not duplicated by this PR."

    $size = (Get-Item "CHANGELOG.md").Length
    if ($size -gt 1.2MB) {
        Fail "CHANGELOG.md dépasse le plafond dur 1,2 Mo ($([math]::Round($size/1MB,2)) Mo) — consolider/archiver (règle CONVENTIONS §4.3)."
    }
    if ($size -gt 300KB) {
        Write-Host "WARN: CHANGELOG.md = $([math]::Round($size/1KB,0)) Ko (> 300 Ko) — prévoir une release pour condenser [Unreleased] actif." -ForegroundColor Yellow
    }
    Pass "CHANGELOG.md size within hard ceiling."
}

# Garde .claude/ : le scratch de planification des agents ne doit jamais
# être commité (fichiers locaux, cf. issue #2494 / session 2026-08-15).
foreach ($line in $changedList) {
    if ($line -match '^\.claude/') {
        Fail "Fichier de scratch agent committé : $line — retirer .claude/ du repo (git rm --cached + .gitignore)."
    }
}
Pass "No agent scratch (.claude/) committed."

Pass "Governance checks passed."
