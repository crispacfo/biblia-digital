#!/usr/bin/env bash
# Installs the WordPress PHPUnit test suite and a test database.
#
# The suite is fetched from the wordpress-develop tarball for an exact tag.
# Subversion is deliberately not used: the GitHub Ubuntu runner images no longer
# ship it, and `svn export` failed the install with exit 127 before any test ran.
#
# Only an exact X.Y.Z version is accepted. There is no 'latest' resolution, no
# branch checkout and no fallback to a different version: testing against
# something other than what was asked for is worse than failing.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> <db-host> <wp-version> [skip-db-create]
set -euo pipefail

DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-root}
DB_HOST=${4-127.0.0.1}
WP_VERSION=${5-}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

if [[ ! $WP_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	echo "ERROR: an exact WordPress version is required in X.Y.Z form, got '${WP_VERSION}'." >&2
	echo "       Branches, 'latest' and partial versions are not accepted." >&2
	exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
	echo "ERROR: curl is required but was not found in PATH." >&2
	exit 1
fi

CORE_TARBALL="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
SUITE_TARBALL="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${WP_VERSION}.tar.gz"

# --fail turns HTTP errors into a non-zero exit instead of writing the error
# body into the target file, which would later surface as a confusing tar error.
download() {
	curl --fail --location --show-error --silent "$1" --output "$2"
}

install_wp() {
	if [ -d "$WP_CORE_DIR" ]; then
		echo "WordPress core already present at ${WP_CORE_DIR}; reusing it."
		return
	fi

	local archive="${TMPDIR}/wordpress-${WP_VERSION}.tar.gz"
	rm -rf "$archive"
	mkdir -p "$WP_CORE_DIR"

	echo "Downloading WordPress core ${WP_VERSION} from ${CORE_TARBALL}"
	if ! download "$CORE_TARBALL" "$archive"; then
		echo "ERROR: WordPress core ${WP_VERSION} could not be downloaded." >&2
		echo "       URL: ${CORE_TARBALL}" >&2
		rm -rf "$WP_CORE_DIR"
		exit 1
	fi

	if ! tar --strip-components=1 -zxmf "$archive" -C "$WP_CORE_DIR"; then
		echo "ERROR: WordPress core ${WP_VERSION} archive could not be extracted." >&2
		echo "       URL: ${CORE_TARBALL}" >&2
		rm -rf "$WP_CORE_DIR"
		exit 1
	fi

	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php \
		"$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
	local archive="${TMPDIR}/wordpress-develop-${WP_VERSION}.tar.gz"
	local staging="${TMPDIR}/wordpress-develop-${WP_VERSION}"

	rm -rf "$staging" "$archive"
	mkdir -p "$staging"

	echo "Downloading WordPress test suite ${WP_VERSION} from ${SUITE_TARBALL}"
	if ! download "$SUITE_TARBALL" "$archive"; then
		echo "ERROR: WordPress test suite ${WP_VERSION} could not be downloaded." >&2
		echo "       URL: ${SUITE_TARBALL}" >&2
		rm -rf "$staging" "$archive"
		exit 1
	fi

	# Only the PHPUnit harness is extracted: the full tarball is not needed.
	if ! tar -zxmf "$archive" -C "$staging" --strip-components=1 --wildcards \
		'*/tests/phpunit/includes/*' \
		'*/tests/phpunit/data/*' \
		'*/wp-tests-config-sample.php'; then
		echo "ERROR: WordPress test suite ${WP_VERSION} could not be extracted." >&2
		echo "       URL: ${SUITE_TARBALL}" >&2
		rm -rf "$WP_TESTS_DIR" "$staging" "$archive"
		exit 1
	fi

	mkdir -p "$WP_TESTS_DIR"
	mv "$staging/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
	mv "$staging/tests/phpunit/data" "$WP_TESTS_DIR/data"
	cp "$staging/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

	verify_test_suite

	rm -rf "$staging" "$archive"
	configure_test_suite
}

# Guards against a download or extraction that succeeded only partially.
verify_test_suite() {
	local missing=0

	for required in "$WP_TESTS_DIR/includes/bootstrap.php" "$WP_TESTS_DIR/includes/functions.php"; do
		if [ ! -f "$required" ]; then
			echo "ERROR: missing required file after extraction: ${required}" >&2
			missing=1
		fi
	done

	if [ ! -d "$WP_TESTS_DIR/data" ]; then
		echo "ERROR: missing required directory after extraction: ${WP_TESTS_DIR}/data" >&2
		missing=1
	fi

	if [ "$missing" -ne 0 ]; then
		echo "ERROR: incomplete WordPress test suite for version ${WP_VERSION}." >&2
		echo "       URL: ${SUITE_TARBALL}" >&2
		rm -rf "$WP_TESTS_DIR"
		exit 1
	fi
}

configure_test_suite() {
	local core_dir_esc
	core_dir_esc=$(echo "$WP_CORE_DIR" | sed "s/\//\\\\\//g")
	sed -i "s:dirname( __FILE__ ) . '/src/':'${core_dir_esc}/':" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
	sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
}

create_db() {
	[ "$SKIP_DB_CREATE" = "true" ] && return 0

	local host port
	IFS=':' read -r host port <<<"$DB_HOST"

	# Built as an array so the flags stay separate words without word splitting.
	local extra=()
	if [ -n "${port-}" ]; then
		if [[ $port =~ ^[0-9]+$ ]]; then
			extra=( "--host=${host}" "--port=${port}" "--protocol=tcp" )
		else
			extra=( "--socket=${port}" )
		fi
	else
		extra=( "--host=${host}" "--protocol=tcp" )
	fi

	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" "${extra[@]}" 2>/dev/null || true
}

# Wiped up front, before anything can fail: leftovers from an earlier partial
# run must never survive a failed install and make it look complete. Doing this
# inside install_test_suite() would be too late, since an earlier failure (the
# core download, for instance) would exit before the cleanup was reached.
rm -rf "$WP_TESTS_DIR"

install_wp
install_test_suite
create_db

echo "WordPress ${WP_VERSION} installed."
echo "  core:  ${WP_CORE_DIR}"
echo "  suite: ${WP_TESTS_DIR} (from ${SUITE_TARBALL})"
