#!/bin/bash
set -e

ANDROID_JAR="${ANDROID_JAR:-$ANDROID_HOME/platforms/android-23/android.jar}"
BUILD_DIR="build"
SRC_DIR="src"
RES_DIR="res"
MANIFEST="AndroidManifest.xml"
OUT_APK="meb-admin-java.apk"

TOOLS_DIR="/usr/lib/android-sdk/build-tools/27.0.1"
if [ -d "$TOOLS_DIR" ]; then
    export PATH="$TOOLS_DIR:$PATH"
fi

if [ ! -f "$ANDROID_JAR" ]; then
    echo "ERROR: android.jar not found at $ANDROID_JAR"
    echo "Set ANDROID_JAR environment variable to your android.jar path"
    exit 1
fi

echo "=== MEB Admin Java Build ==="
echo "android.jar: $ANDROID_JAR"
echo ""

rm -rf "$BUILD_DIR"
mkdir -p "$BUILD_DIR/gen" "$BUILD_DIR/obj" "$BUILD_DIR/dex"

echo "[1/5] aapt: generating R.java and packaging resources..."
aapt package -f -m \
    -S "$RES_DIR" \
    -J "$BUILD_DIR/gen" \
    -M "$MANIFEST" \
    -I "$ANDROID_JAR" \
    --auto-add-overlay

echo "[2/5] javac: compiling Java sources..."
find "$SRC_DIR" "$BUILD_DIR/gen" -name "*.java" > "$BUILD_DIR/sources.txt"
javac \
    -source 1.7 -target 1.7 \
    -bootclasspath "$ANDROID_JAR" \
    -d "$BUILD_DIR/obj" \
    @"$BUILD_DIR/sources.txt"

echo "[3/5] dx: creating DEX..."
find "$BUILD_DIR/obj" -name "*.class" > "$BUILD_DIR/classes.txt"
dx --dex --output="$BUILD_DIR/dex/classes.dex" "$BUILD_DIR/obj"

echo "[4/5] aapt: packaging APK..."
aapt package -f \
    -M "$MANIFEST" \
    -S "$RES_DIR" \
    -I "$ANDROID_JAR" \
    -F "$BUILD_DIR/$OUT_APK"

cd "$BUILD_DIR/dex"
aapt add "../$OUT_APK" classes.dex
cd ../..

echo "[5/5] signing APK..."
if [ ! -f "$BUILD_DIR/debug.keystore" ]; then
    keytool -genkeypair -v \
        -keystore "$BUILD_DIR/debug.keystore" \
        -storepass android \
        -alias androiddebugkey \
        -keypass android \
        -keyalg RSA \
        -keysize 2048 \
        -validity 10000 \
        -dname "CN=MEB Debug,O=MEB,C=RU" 2>/dev/null
fi

jarsigner -keystore "$BUILD_DIR/debug.keystore" -storepass android -keypass android "$BUILD_DIR/$OUT_APK" androiddebugkey

cp "$BUILD_DIR/$OUT_APK" "./$OUT_APK"

echo ""
echo "=== BUILD SUCCESS ==="
echo "APK: ./$OUT_APK"
echo "Size: $(du -h "./$OUT_APK" | cut -f1)"
