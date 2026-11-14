plugins {
    id("com.android.application")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.leopardo.leopardo_pos_retail"
    // compileSdk = 37 (et non flutter.compileSdkVersion, encore à 36) : les AAR
    // de flutter_local_notifications 23 et androidx.core 1.19 déclarent
    // `minCompileSdk 37`, et :app:checkDebugAarMetadata refuse de compiler sinon.
    // AGP 9.1 résout l'entier 37 vers le hash « android-37.0 » : la CI installe
    // donc platforms;android-37.0, et pas une autre mineure (#9465).
    compileSdk = 37
    ndkVersion = flutter.ndkVersion

    compileOptions {
        // flutter_local_notifications exige le core library desugaring : sans lui,
        // `:app:checkDebugAarMetadata` echoue et l'APK debug ne se construit plus.
        isCoreLibraryDesugaringEnabled = true
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.leopardo.leopardo_pos_retail"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        // #9471 — sentry_flutter exige minSdk 26 : le defaut Flutter (24) fait
        // echouer le manifest merger. Aligne sur les 9 autres apps.
        minSdk = 26
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            // TODO: Add your own signing config for the release build.
            // Signing with the debug keys for now, so `flutter run --release` works.
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}

dependencies {
    coreLibraryDesugaring("com.android.tools:desugar_jdk_libs:2.1.5")
}
