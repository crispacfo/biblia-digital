#!/usr/bin/env bash
#
# Builds the distributable plugin ZIP from the working tree, honouring
# .distignore. Output: dist/estudobiblico-biblia-digital-<version>.zip containing
# a top-level estudobiblico-biblia-digital/ directory, and
# dist/estudobiblico-biblia-digital/ as the staged tree.
#
# The directory name is the WordPress.org slug; the main plugin file keeps its
# historical name (biblia-digital.php) because the slug is derived from the
# directory, not from the file.
#
# Usage: bash bin/build-zip.sh
set -euo pipefail

SLUG="estudobiblico-biblia-digital"
MAIN_FILE="biblia-digital.php"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

# Single source of truth for the packaged version: the plugin header.
VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9][^[:space:]]*\).*/\1/p' "${ROOT}/${MAIN_FILE}" | head -n1)"
if [ -z "${VERSION}" ]; then
	echo "Could not read Version from ${MAIN_FILE}" >&2
	exit 1
fi

rm -rf "${DIST}"
mkdir -p "${STAGE}"

# Build exclude patterns from .distignore (skip comments/blank lines).
PATTERNS=()
while IFS= read -r line; do
	line="${line%%#*}"
	line="$(printf '%s' "$line" | tr -d '\r' | sed 's/[[:space:]]*$//')"
	[ -z "$line" ] && continue
	PATTERNS+=( "$line" )
done < "${ROOT}/.distignore"

is_excluded() {
	local rel="$1" base p
	base="$(basename "$rel")"
	for p in "${PATTERNS[@]}"; do
		# match by basename, by top-level path, or by glob.
		case "$base" in $p) return 0;; esac
		case "$rel" in $p|$p/*|./$p|./$p/*) return 0;; esac
	done
	return 1
}

# Stage every tracked file that is not excluded.
( cd "${ROOT}" && find . -type f ! -path './dist/*' ) | sed 's|^\./||' | while IFS= read -r rel; do
	# Check the file and each of its parent directories against the patterns.
	skip=0
	probe="$rel"
	while [ "$probe" != "." ] && [ "$probe" != "/" ]; do
		if is_excluded "$probe"; then skip=1; break; fi
		probe="$(dirname "$probe")"
	done
	[ "$skip" -eq 1 ] && continue
	mkdir -p "${STAGE}/$(dirname "$rel")"
	cp "${ROOT}/${rel}" "${STAGE}/${rel}"
done

ZIP_NAME="${SLUG}-${VERSION}.zip"
( cd "${DIST}" && zip -rq "${ZIP_NAME}" "${SLUG}" )

# Fail loudly instead of shipping catalogs that WordPress.org asked us to drop.
if unzip -Z1 "${DIST}/${ZIP_NAME}" | grep -qE '\.(po|mo)$'; then
	echo "Refusing to ship: the archive contains .po/.mo files" >&2
	exit 1
fi

echo "Built ${DIST}/${ZIP_NAME}"
