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
    if (-not (Test-Path $fullPath)) {
        Fail "Missing required file: $Path"
    }

    return Get-Content -LiteralPath $fullPath -Raw
}

function Assert-Contains($Content, $Needle, $Label) {
    if ($Content -notlike "*$Needle*") {
        Fail "$Label is missing required marker: $Needle"
    }
}

function Assert-NoAwaitBeforeRunApp($Content, $Label) {
    $runAppIndex = $Content.IndexOf("runApp(")
    if ($runAppIndex -lt 0) {
        Fail "$Label does not call runApp()."
    }

    $mainIndex = $Content.IndexOf("main(")
    if ($mainIndex -lt 0) {
        Fail "$Label does not expose a main() function."
    }

    $beforeRunApp = $Content.Substring($mainIndex, $runAppIndex - $mainIndex)
    if ($beforeRunApp -match "\bawait\s+") {
        Fail "$Label awaits native/bootstrap work before runApp(); this can recreate a black screen or frozen splash."
    }
}

$apps = @(
    @{
        Name = "employee"
        Main = "front/mobile_apps/leopardo_employee/lib/main.dart"
        App = "front/mobile_apps/leopardo_employee/lib/app.dart"
        AppName = "Leopardo Employee"
        InitialRoute = "initialLocation: '/welcome'"
        ErrorText = "Erreur d affichage Leopardo Employee"
    },
    @{
        Name = "manager"
        Main = "front/mobile_apps/leopardo_manager/lib/main.dart"
        App = "front/mobile_apps/leopardo_manager/lib/app.dart"
        AppName = "Leopardo Manager"
        InitialRoute = "initialLocation: '/welcome'"
        ErrorText = "Erreur d affichage Leopardo Manager"
    },
    @{
        Name = "platform_admin"
        Main = "front/mobile_apps/leopardo_platform_admin/lib/main.dart"
        App = "front/mobile_apps/leopardo_platform_admin/lib/src/platform_admin_app.dart"
        AppName = "Leopardo Platform Admin"
        InitialRoute = "initialLocation: '/platform/login'"
        ErrorText = "Erreur d affichage Leopardo Platform Admin"
    }
)

foreach ($app in $apps) {
    $main = Read-RepoFile $app.Main
    $shell = Read-RepoFile $app.App

    Assert-NoAwaitBeforeRunApp $main $app.Name
    Assert-Contains $main "StartupGate(" $app.Name
    Assert-Contains $main "appName: '$($app.AppName)'" $app.Name
    Assert-Contains $main "criticalInitializer:" $app.Name
    Assert-Contains $main "optionalInitializer:" $app.Name
    Assert-Contains $main "ErrorWidget.builder" $app.Name
    Assert-Contains $main $app.ErrorText $app.Name
    Assert-Contains $shell $app.InitialRoute $app.Name

    $settingsGradle = Read-RepoFile "front/mobile_apps/leopardo_$($app.Name)/android/settings.gradle.kts"
    $appGradle = Read-RepoFile "front/mobile_apps/leopardo_$($app.Name)/android/app/build.gradle.kts"
    Assert-Contains $settingsGradle 'id("com.google.gms.google-services") version' "$($app.Name) Android settings"
    Assert-Contains $appGradle 'id("com.google.gms.google-services")' "$($app.Name) Android app Gradle"

    Write-Host "[mobile-runtime] $($app.Name): startup shell is guarded."
}

$startupGate = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/widgets/startup_gate.dart"
Assert-Contains $startupGate "addPostFrameCallback" "StartupGate"
Assert-Contains $startupGate "criticalTimeout" "StartupGate"
Assert-Contains $startupGate "TimeoutException" "StartupGate"
Assert-Contains $startupGate "_degradedAutoContinueDelay" "StartupGate"
Assert-Contains $startupGate "Continuer" "StartupGate"
Assert-Contains $startupGate "Ouverture de votre espace..." "StartupGate"

$startupGateTest = Read-RepoFile "front/mobile_apps/leopardo_core/test/core/widgets/startup_gate_test.dart"
Assert-Contains $startupGateTest "renders a visible startup guard" "StartupGate tests"
Assert-Contains $startupGateTest "auto-continues after a critical bootstrap timeout" "StartupGate tests"

$pushNotifications = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/services/push_notification_service.dart"
if ($pushNotifications -match "final\s+FirebaseMessaging\s+_\w+\s*=\s*FirebaseMessaging\.instance") {
    Fail "PushNotificationService must not instantiate FirebaseMessaging.instance eagerly before Firebase.initializeApp()."
}
Assert-Contains $pushNotifications "_ensureFirebaseInitialized" "PushNotificationService"
Assert-Contains $pushNotifications "_messaging ??= FirebaseMessaging.instance" "PushNotificationService"

Write-Host "[mobile-runtime] StartupGate anti-black-screen contract is valid."
