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

$deployment = Read-RepoFile "DEPLOYMENT_GUIDE.md"
$operations = Read-RepoFile "docs/GESTION_PROJET/RUNBOOK_OPERATIONS.md"
$backup = Read-RepoFile "docs/GESTION_PROJET/RUNBOOK_BACKUP_RESTORE.md"
$firebase = Read-RepoFile "docs/validation/MOBILE_FIREBASE_DISTRIBUTION.md"
$notifications = Read-RepoFile "docs/validation/MOBILE_NOTIFICATIONS_PRODUCTION_PROOF_2026_06_01.md"
$deployMain = Read-RepoFile ".github/workflows/deploy-main.yml"
$databaseBackup = Read-RepoFile ".github/workflows/database-backup.yml"
$backendJobs = Read-RepoFile ".github/workflows/backend-jobs-ci.yml"

foreach ($marker in @(
    "QUEUE_CONNECTION=redis",
    "REDIS_URL",
    "queue:health-check",
    "Render Background Worker",
    "schedule:run",
    "rollback Render",
    "FIREBASE_EMPLOYEE_ANDROID_APP_ID",
    "FIREBASE_MANAGER_ANDROID_APP_ID",
    "FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID"
)) {
    Assert-Contains $deployment $marker "DEPLOYMENT_GUIDE.md"
}

foreach ($marker in @(
    "RUNBOOK_DEPLOY.md",
    "RUNBOOK_ROLLBACK.md",
    "RUNBOOK_INCIDENT_P1.md",
    "RUNBOOK_BACKUP_RESTORE.md"
)) {
    Assert-Contains $operations $marker "RUNBOOK_OPERATIONS.md"
}

foreach ($marker in @(
    "DATABASE_URL",
    "RESTORE_DB_URL",
    "BACKUP_S3_BUCKET",
    "Monthly restore drill"
)) {
    Assert-Contains $backup $marker "RUNBOOK_BACKUP_RESTORE.md"
}

foreach ($marker in @(
    "FIREBASE_EMPLOYEE_ANDROID_APP_ID",
    "FIREBASE_MANAGER_ANDROID_APP_ID",
    "FIREBASE_PLATFORM_ADMIN_ANDROID_APP_ID",
    "FIREBASE_SERVICE_ACCOUNT_JSON",
    "FIREBASE_READBACK_REQUIRED"
)) {
    Assert-Contains $firebase $marker "MOBILE_FIREBASE_DISTRIBUTION.md"
}

foreach ($marker in @(
    "Queue worker must listen to",
    "Redis must stay configured",
    "FIREBASE_PROJECT_ID"
)) {
    Assert-Contains $notifications $marker "MOBILE_NOTIFICATIONS_PRODUCTION_PROOF_2026_06_01.md"
}

foreach ($marker in @(
    "RENDER_DEPLOY_HOOK_URL",
    "RENDER_ROLLBACK_HOOK_URL",
    "Firebase App Distribution",
    "FIREBASE_SERVICE_ACCOUNT_JSON"
)) {
    Assert-Contains $deployMain $marker "deploy-main.yml"
}

Assert-Contains $databaseBackup "Daily PostgreSQL backup" "database-backup.yml"
Assert-Contains $databaseBackup "Monthly restore drill" "database-backup.yml"
Assert-Contains $backendJobs "PAYROLL_QUEUE_PDF_WARMUP" "backend-jobs-ci.yml"
Assert-Contains $backendJobs "REDIS_HOST" "backend-jobs-ci.yml"

Write-Host "[ops-readiness] Deploy, rollback, queues, Redis, Firebase, notifications and backup runbooks are linked."
