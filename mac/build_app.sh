#!/usr/bin/env bash
# Builds Flow.app (Apple silicon + Intel) into mac/build/ and zips it.
# Signs with FLOW_SIGN_IDENTITY (a "Developer ID Application: …" certificate) when set, otherwise ad-hoc.
set -euo pipefail
cd "$(dirname "$0")"

swift build -c release --arch arm64 --arch x86_64
bin="$(swift build -c release --arch arm64 --arch x86_64 --show-bin-path)/Flow"

app=build/Flow.app
rm -rf build
mkdir -p "$app/Contents/MacOS" "$app/Contents/Resources"
cp "$bin" "$app/Contents/MacOS/Flow"
cp Info.plist "$app/Contents/Info.plist"

iconset=build/Flow.iconset
mkdir -p "$iconset"
for s in 16 32 128 256 512; do
  sips -z "$s" "$s" ../static/icon-512.png --out "$iconset/icon_${s}x${s}.png" >/dev/null
  sips -z $((s * 2)) $((s * 2)) ../static/icon-512.png --out "$iconset/icon_${s}x${s}@2x.png" >/dev/null
done
iconutil -c icns "$iconset" -o "$app/Contents/Resources/Flow.icns"
rm -rf "$iconset"

codesign --force --options runtime --timestamp=none --entitlements Flow.entitlements \
  --sign "${FLOW_SIGN_IDENTITY:--}" "$app"
codesign --verify --strict "$app"
ditto -c -k --keepParent "$app" build/Flow.zip
echo "Built $app ($(lipo -archs "$app/Contents/MacOS/Flow"))"
