plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.leopardo.leopardo_cameras"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_11
        targetCompatibility = JavaVersion.VERSION_11
        isCoreLibraryDesugaringEnabled = true
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_11.toString()
    }

    defaultConfig {
        // Convention des apps Leopardo (miroir com.leopardo.rh / .employee / .manager / .platformadmin) — #4520.
        applicationId = "com.leopardo.cameras"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // #8774 (D-1) — sentry_flutter ^10 exige minSdk 26 : le defaut Flutter
        // (24) fait echouer le manifest merger. Android 7.0/7.1 (API 24-25)
        // n'est donc plus supporte.
        minSdk = 26
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            // Parité avec les 4 autres apps : signature debug tant qu'aucun
            // keystore release n'est provisionné (résiduel #4520).
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")
}

flutter {
    source = "../.."
}
