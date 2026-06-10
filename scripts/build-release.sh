#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="overcustomise"
DIST_DIR="$ROOT_DIR/dist"
BUILD_DIR="$DIST_DIR/$SLUG"
ZIP_FILE="$DIST_DIR/$SLUG.zip"

cd "$ROOT_DIR"

command -v composer >/dev/null 2>&1 || { printf 'composer is required\n' >&2; exit 1; }
command -v npm >/dev/null 2>&1 || { printf 'npm is required\n' >&2; exit 1; }
command -v rsync >/dev/null 2>&1 || { printf 'rsync is required\n' >&2; exit 1; }
command -v zip >/dev/null 2>&1 || { printf 'zip is required\n' >&2; exit 1; }

npm run build
composer install --no-dev --optimize-autoloader

rm -rf "$BUILD_DIR" "$ZIP_FILE"
mkdir -p "$BUILD_DIR"

rsync -a ./ "$BUILD_DIR/" \
	--exclude='.git/' \
	--exclude='.github/' \
	--exclude='.claude/' \
	--exclude='node_modules/' \
	--exclude='src/' \
	--exclude='tests/' \
	--exclude='dist/' \
	--exclude='coverage/' \
	--exclude='.phpunit.result.cache' \
	--exclude='package-lock.json' \
	--exclude='webpack.config.js'

cd "$DIST_DIR"
zip -qr "$ZIP_FILE" "$SLUG"

printf 'Built %s\n' "$ZIP_FILE"
