param(
    [string]$Root = ".",
    [switch]$SkipFetch
)

$ErrorActionPreference = "Stop"

function Invoke-Git([string[]]$Arguments) {
    $output = & git @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed: $output"
    }

    return @($output)
}

Push-Location $Root
try {
    if (-not $SkipFetch) {
        Invoke-Git @("fetch", "--prune", "origin") | Out-Null
    }

    $current = (Invoke-Git @("branch", "--show-current") | Select-Object -First 1).Trim()
    $head = (Invoke-Git @("rev-parse", "--short", "HEAD") | Select-Object -First 1).Trim()
    $originMain = (Invoke-Git @("rev-parse", "--short", "origin/main") | Select-Object -First 1).Trim()

    $remoteMerged = Invoke-Git @("branch", "-r", "--merged", "origin/main") |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -and $_ -notin @("origin/main", "origin/HEAD") -and $_ -notlike "origin/HEAD ->*" } |
        Sort-Object

    $remoteUnmerged = Invoke-Git @("branch", "-r", "--no-merged", "origin/main") |
        ForEach-Object { $_.Trim() } |
        Where-Object { $_ -and $_ -notin @("origin/main", "origin/HEAD") -and $_ -notlike "origin/HEAD ->*" } |
        Sort-Object

    $localMerged = Invoke-Git @("branch", "--merged", "main") |
        ForEach-Object { $_.Trim().TrimStart("*").Trim() } |
        Where-Object { $_ -and $_ -ne "main" -and $_ -ne $current } |
        Sort-Object

    $localUnmerged = Invoke-Git @("branch", "--no-merged", "main") |
        ForEach-Object { $_.Trim().TrimStart("*").Trim() } |
        Where-Object { $_ -and $_ -ne $current } |
        Sort-Object

    $status = Invoke-Git @("status", "--short", "--branch")

    Write-Host "# Repository hygiene report"
    Write-Host ""
    Write-Host ("Current branch: {0}" -f $current)
    Write-Host ("HEAD: {0}" -f $head)
    Write-Host ("origin/main: {0}" -f $originMain)
    Write-Host ""
    Write-Host "## Status"
    $status | ForEach-Object { Write-Host $_ }
    Write-Host ""
    Write-Host ("Remote merged branches: {0}" -f @($remoteMerged).Count)
    $remoteMerged | ForEach-Object { Write-Host ("- {0}" -f $_) }
    Write-Host ""
    Write-Host ("Remote unmerged branches: {0}" -f @($remoteUnmerged).Count)
    $remoteUnmerged | ForEach-Object { Write-Host ("- {0}" -f $_) }
    Write-Host ""
    Write-Host ("Local merged branches: {0}" -f @($localMerged).Count)
    $localMerged | ForEach-Object { Write-Host ("- {0}" -f $_) }
    Write-Host ""
    Write-Host ("Local unmerged branches: {0}" -f @($localUnmerged).Count)
    $localUnmerged | ForEach-Object { Write-Host ("- {0}" -f $_) }

    if (@($remoteMerged).Count -gt 0 -or @($remoteUnmerged).Count -gt 0) {
        exit 2
    }

    exit 0
}
finally {
    Pop-Location
}
