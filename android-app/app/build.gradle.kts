plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

val apiBaseUrl = (project.findProperty("APP_API_BASE_URL") as String?) ?: "http://10.0.2.2/"

android {
    namespace = "com.kunlun.studentapp"
    compileSdk = 34

    defaultConfig {
        applicationId = "com.kunlun.studentapp"
        minSdk = 24
        targetSdk = 34
        versionCode = 2
        versionName = "1.0.1"

        testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
        buildConfigField("String", "API_BASE_URL", "\"${apiBaseUrl.trim()}\"")
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = "17"
    }

    buildFeatures {
        buildConfig = true
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.appcompat:appcompat:1.7.0")
    implementation("com.google.android.material:material:1.12.0")
    implementation("androidx.constraintlayout:constraintlayout:2.2.0")
    implementation("androidx.recyclerview:recyclerview:1.3.2")
    implementation("androidx.swiperefreshlayout:swiperefreshlayout:1.1.0")
    implementation("androidx.lifecycle:lifecycle-runtime-ktx:2.8.7")

    implementation("org.jetbrains.kotlinx:kotlinx-coroutines-android:1.9.0")

    implementation("com.squareup.okhttp3:okhttp:4.12.0")
    implementation("com.squareup.okhttp3:logging-interceptor:4.12.0")
    implementation("com.squareup.retrofit2:retrofit:2.11.0")
    implementation("com.squareup.retrofit2:converter-gson:2.11.0")

    implementation("io.coil-kt:coil:2.7.0")
}

// Compatibility tasks for IDE build actions that still invoke legacy task names.
tasks.register("unitTestClasses") {
    group = "verification"
    description = "Compiles unit-test classes for debug variant (IDE compatibility)."
    val candidates = listOf(
        "compileDebugUnitTestKotlin",
        "compileDebugUnitTestJavaWithJavac",
        "compileReleaseUnitTestKotlin",
        "compileReleaseUnitTestJavaWithJavac"
    )
    dependsOn(candidates.mapNotNull { tasks.findByName(it) })
}

tasks.register("androidTestClasses") {
    group = "verification"
    description = "Compiles androidTest classes for debug variant (IDE compatibility)."
    val candidates = listOf(
        "compileDebugAndroidTestKotlin",
        "compileDebugAndroidTestJavaWithJavac"
    )
    dependsOn(candidates.mapNotNull { tasks.findByName(it) })
}
