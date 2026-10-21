import java.util.Properties

plugins {
    id("com.android.application")
    id("com.google.gms.google-services")
    id("com.google.firebase.crashlytics")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

// Real release signing credentials, recovered from a leftover Gradle build
// artifact in the original bs-contract-android project (2026-09-11) and
// verified against the actual bs-unit-release-key.keystore file from the
// handover package. Kept in android/key.properties, which is gitignored —
// never commit it or put the password inline here.
val keystorePropertiesFile = rootProject.file("key.properties")
val keystoreProperties = Properties()
if (keystorePropertiesFile.exists()) {
    keystoreProperties.load(keystorePropertiesFile.inputStream())
}

android {
    namespace = "com.bslandandhome.bsunits.dev"
    // permission_handler_android requires compileSdk 37 (flutter.compileSdkVersion is 36).
    compileSdk = 37
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    // Two flavors so UAT and production never get mixed up (both registered in
    // google-services.json, Firebase project: contract-register):
    //   prod -> com.bslandandhome.bsunits       (lib/main_prod.dart, live server)
    //   uat  -> com.bslandandhome.bsunits.dev   (lib/main_uat.dart, 10.3.0.99) - installs next to prod
    flavorDimensions += "env"
    productFlavors {
        create("prod") {
            dimension = "env"
            applicationId = "com.bslandandhome.bsunits"
        }
        create("uat") {
            dimension = "env"
            applicationId = "com.bslandandhome.bsunits.dev"
            versionNameSuffix = "-uat"
        }
    }

    defaultConfig {
        // applicationId is set per flavor above; this is just a fallback.
        applicationId = "com.bslandandhome.bsunits"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        // Uses the version code from pubspec.yaml. When using split APKs, 1000 * ABI_VERSION
        // is added automatically by Flutter. (https://developer.android.com/studio/build/configure-apk-splits#configure-APK-versions)
        // You can force using the value of versionCode by specifying the `-P force-version-code-ignoring-abi=true`
        // flag during build.
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    signingConfigs {
        if (keystorePropertiesFile.exists()) {
            create("release") {
                keyAlias = keystoreProperties["keyAlias"] as String
                keyPassword = keystoreProperties["keyPassword"] as String
                storeFile = file(keystoreProperties["storeFile"] as String)
                storePassword = keystoreProperties["storePassword"] as String
                storeType = keystoreProperties["storeType"] as String
            }
        }
    }

    buildTypes {
        release {
            // Falls back to the debug key if key.properties/the keystore
            // aren't present (e.g. a fresh checkout without them), so
            // `flutter build apk --release` still works for local testing.
            signingConfig = if (keystorePropertiesFile.exists()) {
                signingConfigs.getByName("release")
            } else {
                signingConfigs.getByName("debug")
            }
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
