param(
    [string]$CsvPath = "docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv",
    [string]$BacklogPath = "docs/PLAN_ACTION2/02_BACKLOG_ATOMIQUE.md"
)

$ErrorActionPreference = "Stop"

if (-not (Test-Path $CsvPath)) {
    throw "PLAN_ACTION2 CSV not found: $CsvPath"
}

$rows = Import-Csv $CsvPath
if (-not $rows -or $rows.Count -eq 0) {
    throw "PLAN_ACTION2 CSV is empty."
}

$requiredColumns = @("Title", "Priority", "Area", "Surface", "Dependencies", "Acceptance Criteria")
$actualColumns = @($rows[0].PSObject.Properties.Name)
foreach ($column in $requiredColumns) {
    if ($actualColumns -notcontains $column) {
        throw "Missing CSV column: $column"
    }
}

$ids = @()
foreach ($row in $rows) {
    $id = ($row.Title -split " ")[0]
    if ($id -notmatch "^PA2-[A-Z0-9]+-\d{3}$") {
        throw "Invalid ticket id in title: $($row.Title)"
    }
    if ([string]::IsNullOrWhiteSpace($row.Priority) -or [string]::IsNullOrWhiteSpace($row.Area) -or [string]::IsNullOrWhiteSpace($row."Acceptance Criteria")) {
        throw "Ticket has missing required values: $($row.Title)"
    }
    $ids += $id
}

$duplicates = $ids | Group-Object | Where-Object { $_.Count -gt 1 }
if ($duplicates) {
    throw "Duplicate ticket IDs: $(($duplicates | ForEach-Object { $_.Name }) -join ', ')"
}

$idSet = @{}
foreach ($id in $ids) {
    $idSet[$id] = $true
}

$missing = New-Object System.Collections.Generic.List[string]
foreach ($row in $rows) {
    if ([string]::IsNullOrWhiteSpace($row.Dependencies)) {
        continue
    }
    foreach ($dependency in ($row.Dependencies -split ";")) {
        $dep = $dependency.Trim()
        if ($dep -and -not $idSet.ContainsKey($dep)) {
            $missing.Add("$($row.Title) -> $dep")
        }
    }
}

if ($missing.Count -gt 0) {
    throw "Missing PLAN_ACTION2 dependencies:`n$($missing -join "`n")"
}

if (Test-Path $BacklogPath) {
    $backlog = Get-Content $BacklogPath -Raw
    $notDocumented = $ids | Where-Object { $backlog -notmatch [regex]::Escape($_) }
    if ($notDocumented) {
        throw "Tickets present in CSV but missing from backlog markdown: $($notDocumented -join ', ')"
    }
}

Write-Host "PLAN_ACTION2 OK: $($rows.Count) tickets, unique IDs, dependencies resolved."

