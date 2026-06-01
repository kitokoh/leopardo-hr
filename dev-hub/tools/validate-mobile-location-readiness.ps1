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

$corePubspec = Read-RepoFile "front/mobile_apps/leopardo_core/pubspec.yaml"
Assert-Contains $corePubspec "geolocator:" "leopardo_core pubspec"

$locationService = Read-RepoFile "front/mobile_apps/leopardo_core/lib/core/location/attendance_location_service.dart"
Assert-Contains $locationService "Geolocator.isLocationServiceEnabled" "AttendanceLocationService"
Assert-Contains $locationService "Geolocator.requestPermission" "AttendanceLocationService"
Assert-Contains $locationService "getCurrentPosition" "AttendanceLocationService"
Assert-Contains $locationService "TimeoutException" "AttendanceLocationService"

$apps = @("employee", "manager")

foreach ($app in $apps) {
    $androidManifest = Read-RepoFile "front/mobile_apps/leopardo_$app/android/app/src/main/AndroidManifest.xml"
    Assert-Contains $androidManifest "android.permission.ACCESS_FINE_LOCATION" "$app AndroidManifest"
    Assert-Contains $androidManifest "android.permission.ACCESS_COARSE_LOCATION" "$app AndroidManifest"

    $iosPlist = Read-RepoFile "front/mobile_apps/leopardo_$app/ios/Runner/Info.plist"
    Assert-Contains $iosPlist "NSLocationWhenInUseUsageDescription" "$app Info.plist"

    $providers = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/core/providers/core_providers.dart"
    Assert-Contains $providers "attendanceLocationServiceProvider" "$app providers"

    $attendanceProvider = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/attendance/providers/attendance_provider.dart"
    Assert-Contains $attendanceProvider "currentForAttendance" "$app attendance provider"
    Assert-Contains $attendanceProvider "gpsAccuracy" "$app attendance provider"

    $repository = Read-RepoFile "front/mobile_apps/leopardo_$app/lib/features/attendance/data/attendance_repository.dart"
    Assert-Contains $repository "gps_lat" "$app attendance repository"
    Assert-Contains $repository "gps_lng" "$app attendance repository"
    Assert-Contains $repository "gps_accuracy" "$app attendance repository"

    Write-Host "[mobile-location] ${app}: native permissions and attendance payload are ready."
}

$checkInRequest = Read-RepoFile "api/app/Http/Requests/Api/V1/Attendance/CheckInRequest.php"
$checkOutRequest = Read-RepoFile "api/app/Http/Requests/Api/V1/Attendance/CheckOutRequest.php"
$dto = Read-RepoFile "api/app/DTOs/CheckInDTO.php"
$resource = Read-RepoFile "api/app/Http/Resources/Api/V1/AttendanceLogResource.php"

Assert-Contains $checkInRequest "gps_accuracy" "CheckInRequest"
Assert-Contains $checkOutRequest "gps_accuracy" "CheckOutRequest"
Assert-Contains $dto "gps_accuracy" "CheckInDTO"
Assert-Contains $resource "accuracy_m" "AttendanceLogResource"

Write-Host "[mobile-location] GPS/geofence readiness contract is valid."
