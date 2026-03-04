#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/kunden-link-tracker"
DIST_DIR="$ROOT_DIR/dist"
ZIP_PATH="$DIST_DIR/kunden-link-tracker.zip"

mkdir -p "$DIST_DIR"
rm -f "$ZIP_PATH"

(
  cd "$ROOT_DIR"
  zip -rq "$ZIP_PATH" "kunden-link-tracker"
)

echo "ZIP erstellt: $ZIP_PATH"
