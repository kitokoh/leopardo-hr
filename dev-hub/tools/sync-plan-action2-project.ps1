param(
    [Parameter(Mandatory = $true)]
    [string]$Owner,

    [Parameter(Mandatory = $true)]
    [int]$ProjectNumber,

    [string]$OwnerType = "user",
    [string]$CsvPath = "docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv",
    [string]$Token = $env:PLAN_ACTION2_PROJECT_TOKEN,
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
    throw "GitHub CLI 'gh' is required."
}
if (-not (Test-Path $CsvPath)) {
    throw "PLAN_ACTION2 CSV not found: $CsvPath"
}
if (-not $Token -and -not $env:GH_TOKEN) {
    throw "Set PLAN_ACTION2_PROJECT_TOKEN or GH_TOKEN with project scope."
}
if ($Token) {
    $env:GH_TOKEN = $Token
}

$rows = Import-Csv $CsvPath

function Invoke-GraphQL {
    param(
        [string]$Query,
        [hashtable]$Variables
    )

    $inputObject = @{
        query = $Query
        variables = $Variables
    }
    $payload = $inputObject | ConvertTo-Json -Depth 20 -Compress
    $result = $payload | gh api graphql --input - | ConvertFrom-Json
    if ($result.errors) {
        throw (($result.errors | ConvertTo-Json -Depth 10))
    }
    return $result.data
}

$ownerQuery = if ($OwnerType -eq "organization") {
    'query($login:String!,$number:Int!){ organization(login:$login){ projectV2(number:$number){ id items(first:200){ nodes{ id content{ ... on DraftIssue{ title } ... on Issue{ title } } } } } } }'
} else {
    'query($login:String!,$number:Int!){ user(login:$login){ projectV2(number:$number){ id items(first:200){ nodes{ id content{ ... on DraftIssue{ title } ... on Issue{ title } } } } } } }'
}

$projectData = Invoke-GraphQL -Query $ownerQuery -Variables @{ login = $Owner; number = $ProjectNumber }
$project = if ($OwnerType -eq "organization") { $projectData.organization.projectV2 } else { $projectData.user.projectV2 }
if (-not $project) {
    throw "Project not found for $OwnerType '$Owner' number $ProjectNumber."
}

$existing = @{}
foreach ($item in $project.items.nodes) {
    if ($item.content -and $item.content.title) {
        $existing[$item.content.title] = $true
    }
}

$mutation = 'mutation($project:ID!,$title:String!,$body:String!){ addProjectV2DraftIssue(input:{projectId:$project,title:$title,body:$body}){ projectItem{ id } } }'
$created = 0
$skipped = 0

foreach ($row in $rows) {
    $title = $row.Title
    if ($existing.ContainsKey($title)) {
        $skipped++
        continue
    }

    $body = @"
Priority: $($row.Priority)
Area: $($row.Area)
Surface: $($row.Surface)
Dependencies: $($row.Dependencies)

Acceptance Criteria:
$($row."Acceptance Criteria")

Source: docs/PLAN_ACTION2/03_GITHUB_PROJECT_IMPORT.csv
"@

    if ($DryRun) {
        Write-Host "[dry-run] create draft item: $title"
        $created++
        continue
    }

    Invoke-GraphQL -Query $mutation -Variables @{ project = $project.id; title = $title; body = $body } | Out-Null
    $created++
}

Write-Host "PLAN_ACTION2 project sync complete: created=$created skipped=$skipped total=$($rows.Count)"

