#!/usr/bin/env bash
#
# Builds the distributable plugin ZIP from the working tree, honouring
# .distignore. Output: dist/biblia-digital.zip containing a top-level
# biblia-digital/ directory, and dist/biblia-digital/ as the staged tree.
#
# Usage: bash bin/build-zip.sh
set -euo pipefail

SLUG="biblia-digital"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DIST="${ROOT}/dist"
STAGE="${DIST}/${SLUG}"

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

( cd "${DIST}" && zip -rq "${SLUG}.zip" "${SLUG}" )

echo "Built ${DIST}/${SLUG}.zip"
