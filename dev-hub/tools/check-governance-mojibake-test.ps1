# check-governance-mojibake-test.ps1 — Auto-test de la garde mojibake (#1612)
#
# Vérifie que la liste noire détecte les patrons NON standard (GÇö, +¬, â-¬, …)
# et accepte les caractères légitimes (é è ê à ç ô ù ï — – … ✓ ✗ → § • « »).
# Exécuté par le job Governance Gates (tests.yml) après check-governance.ps1.
#
# Usage : pwsh dev-hub/tools/check-governance-mojibake-test.ps1
$ErrorActionPreference = 'Stop'

function Assert-True([bool]$cond, [string]$msg) {
    if (-not $cond) { Write-Error "FAIL: $msg" }
    Write-Host "ok: $msg"
}

# Même expression que check-governance.ps1 (#1612).
$mojibakePattern = 'G\u00c7\u00f6|G\u00e5\u00c6|G\u00f9\u00ef|\+\u00ac|\+\u00a6|\+\u00bf|\+\u00ba|\+\u00e1|\u00e2-\u00ac|\u00e2-\u00a6|\u00e2-\u00bf|\u00e2-\u00e1|\u00f3\u2014{2}|\u00c3[\u00a0-\u00bf]|\u00e2\u20ac|\ufffd'

$bad = @(
    'module GÇö `post_publications`',
    'famille +¬ (é)',
    'contr+â-¦leur (ô)',
    'd+¿s (è)',
    'pending_countGåÆ0 (flèche)',
    'prerendered as static (Gùï)',
    'r+â-¬ellement (é double)',
    'd+¬j+á (déjà)',
    'flèche ó——',
    'employé Ã©'
)
foreach ($s in $bad) {
    Assert-True (($s -match $mojibakePattern)) "mojibake détecté : $s"
}

$good = @(
    'famille é, ô, è, ç, à',
    'em dash — et en dash –',
    'points … check ✓ croix ✗ flèche → paragraphe § puce •',
    'Génération PDF du bulletin',
    'réellement après contrôle'
)
foreach ($s in $good) {
    Assert-True (-not ($s -match $mojibakePattern)) "caractères légitimes acceptés : $s"
}

# Le CHANGELOG courant doit passer la garde (ré-encodé propre, #1589).
if (Test-Path "CHANGELOG.md") {
    $bytes = [System.IO.File]::ReadAllBytes((Resolve-Path "CHANGELOG.md"))
    $enc = New-Object System.Text.UTF8Encoding($false, $true)
    $text = $enc.GetString($bytes)
    Assert-True (-not ($text -match $mojibakePattern)) "CHANGELOG.md propre (aucun mojibake)"
}

Write-Host "check-governance-mojibake-test: all assertions passed."
