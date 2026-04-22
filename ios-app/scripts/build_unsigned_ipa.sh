#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

echo "[1/4] generate xcode project"
xcodegen generate

echo "[2/4] build app (iphoneos, no code signing)"
xcodebuild \
  -project KunlunStudentApp.xcodeproj \
  -scheme KunlunStudentApp \
  -configuration Release \
  -sdk iphoneos \
  -destination "generic/platform=iOS" \
  -derivedDataPath build/DerivedData \
  CODE_SIGNING_ALLOWED=NO \
  CODE_SIGNING_REQUIRED=NO \
  CODE_SIGN_IDENTITY="" \
  build

APP_PATH="build/DerivedData/Build/Products/Release-iphoneos/KunlunStudentApp.app"
if [[ ! -d "$APP_PATH" ]]; then
  echo "app not found: $APP_PATH"
  exit 1
fi

echo "[3/4] package unsigned ipa"
rm -rf build/Payload
mkdir -p build/Payload
cp -R "$APP_PATH" build/Payload/

cd build
rm -f KunlunStudentApp-unsigned.ipa
/usr/bin/zip -qry KunlunStudentApp-unsigned.ipa Payload

echo "[4/4] done"
echo "output: $PROJECT_DIR/build/KunlunStudentApp-unsigned.ipa"

