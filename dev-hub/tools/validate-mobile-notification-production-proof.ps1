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

$pushService = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/services/push_notification_service.dart"
Assert-Contains $pushService "FirebaseMessaging.instance" "PushNotificationService"
Assert-Contains $pushService "requestPermission" "PushNotificationService"
Assert-Contains $pushService "/device-tokens" "PushNotificationService"
Assert-Contains $pushService "method: 'POST'" "PushNotificationService register"
Assert-Contains $pushService "method: 'DELETE'" "PushNotificationService unregister"
Assert-Contains $pushService "onTokenRefresh" "PushNotificationService token refresh"
Assert-Contains $pushService "requestWithRetry" "PushNotificationService backend sync"

foreach ($app in @("employee", "manager")) {
    $appFile = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/app.dart"
    Assert-Contains $appFile "ref.listen<AuthState>" "$app auth listener"
    Assert-Contains $appFile "pushNotificationServiceProvider" "$app push init"
    Assert-Contains $appFile ".initialize(apiClient: ref.read(apiClientProvider))" "$app push init api client"

    $auth = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/auth/providers/auth_provider.dart"
    Assert-Contains $auth "unregisterCurrentToken(apiClient: _apiClient)" "$app logout push cleanup"
    Assert-Contains $auth "await _repository.logout()" "$app logout order"

    $repository = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/notifications/data/notification_repository.dart"
    Assert-Contains $repository "/notifications" "$app notifications repository"
    Assert-Contains $repository "/notifications/read-all" "$app notifications read all"
    Assert-Contains $repository "/notifications/`$id/read" "$app notifications mark read"
    Assert-Contains $repository "method: 'DELETE'" "$app notifications delete"
    Assert-Contains $repository "requestWithRetry" "$app notifications retry"

    $screen = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/notifications/screens/notification_list_screen.dart"
    Assert-Contains $screen "RefreshIndicator" "$app notifications refresh"
    Assert-Contains $screen "Dismissible" "$app notifications delete gesture"
    Assert-Contains $screen "markAsRead" "$app notifications mark read"
    Assert-Contains $screen "delete" "$app notifications delete action"

    Write-Host "[mobile-notifications] ${app}: push token lifecycle and notification actions are wired."
}

$platformMain = Read-RepoFile "front/mobile_apps/leopardo_platform_admin/lib/main.dart"
Assert-Contains $platformMain "FirebaseMessaging.onBackgroundMessage" "platform admin Firebase background handler"
Assert-Contains $platformMain "_initFirebase" "platform admin Firebase optional init"

$routes = Read-RepoFile "api/routes/modules/integrations.php"
Assert-Contains $routes "Route::post('/device-tokens'" "device token register route"
Assert-Contains $routes "Route::delete('/device-tokens'" "device token unregister route"
Assert-Contains $routes "Route::get('/device-tokens'" "device token list route"
Assert-Contains $routes "Route::post('/push-notifications/send'" "manager push test route"

$controller = Read-RepoFile "api/app/Http/Controllers/Api/V1/DeviceTokenController.php"
Assert-Contains $controller "CommunicationService" "DeviceTokenController"
Assert-Contains $controller "notifyEmployee" "DeviceTokenController send test"
Assert-Contains $controller "abort_unless(`$user->isManager()" "DeviceTokenController manager guard"

$backendPush = Read-RepoFile "api/app/Services/PushNotificationService.php"
Assert-Contains $backendPush "firebase.messaging" "Backend PushNotificationService"
Assert-Contains $backendPush "handleFailedTokens" "Backend PushNotificationService failed token cleanup"
Assert-Contains $backendPush "Cache::put(`$cacheKey" "Backend PushNotificationService OAuth cache"

$openApi = Read-RepoFile "api/openapi.yaml"
Assert-Contains $openApi "/device-tokens:" "OpenAPI device tokens"
Assert-Contains $openApi "/push-notifications/send:" "OpenAPI push send test"

Write-Host "[mobile-notifications] Production notification proof contract is valid."
