#!/usr/bin/env bash
# Installs the WordPress PHPUnit test suite and a test database.
# Based on the wp-cli scaffold script.
#
# Usage: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]
set -euo pipefail

DB_NAME=${1-wordpress_test}
DB_USER=${2-root}
DB_PASS=${3-root}
DB_HOST=${4-127.0.0.1}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo "$TMPDIR" | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

# Fails loudly on HTTP errors. Without --fail, curl happily writes a 404 body
# into the target file, which later surfaces as an unrelated tar/svn error and
# can look like a successful install of the wrong version.
download() {
	if command -v curl >/dev/null 2>&1; then
		curl --fail --silent --show-error --location "$1" --output "$2" \
			|| { echo "ERROR: download failed: $1" >&2; return 1; }
	else
		wget -nv -O "$2" "$1" \
			|| { echo "ERROR: download failed: $1" >&2; return 1; }
	fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	download http://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
	LATEST_VERSION=$(grep -o '"version":"[^"]*' "$TMPDIR/wp-latest.json" | sed 's/"version":"//' | head -1)
	[ -z "$LATEST_VERSION" ] && { echo "Latest WP version could not be found"; exit 1; }
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

install_wp() {
	[ -d "$WP_CORE_DIR" ] && return
	mkdir -p "$WP_CORE_DIR"
	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		download https://wordpress.org/nightly-builds/wordpress-latest.zip "$TMPDIR/wordpress-nightly.zip"
		unzip -q "$TMPDIR/wordpress-nightly.zip" -d "$TMPDIR/wordpress-nightly"
		mv "$TMPDIR/wordpress-nightly/wordpress"/* "$WP_CORE_DIR"
	else
		if [ "$WP_VERSION" == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			download https://api.wordpress.org/core/version-check/1.7/ "$TMPDIR/wp-latest.json"
			if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
				LATEST_VERSION=${WP_VERSION%??}
			else
				LATEST_VERSION=$(grep -o "\"version\":\"${WP_VERSION}[^\"]*" "$TMPDIR/wp-latest.json" | sed 's/"version":"//' | head -1)
			fi
			[ -z "$LATEST_VERSION" ] && local ARCHIVE_NAME="wordpress-$WP_VERSION" || local ARCHIVE_NAME="wordpress-$LATEST_VERSION"
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		# An explicitly requested version is never silently replaced by 'latest':
		# a missing archive aborts the run so the gap is visible in CI logs.
		echo "Installing WordPress core from ${ARCHIVE_NAME}.tar.gz (requested: ${WP_VERSION})."
		download "https://wordpress.org/${ARCHIVE_NAME}.tar.gz" "$TMPDIR/wordpress.tar.gz"
		tar --strip-components=1 -zxmf "$TMPDIR/wordpress.tar.gz" -C "$WP_CORE_DIR"
	fi
	download https://raw.githubusercontent.com/markoheijnen/wp-mysqli/master/db.php "$WP_CORE_DIR/wp-content/db.php"
}

install_test_suite() {
	if [ ! -d "$WP_TESTS_DIR" ]; then
		mkdir -p "$WP_TESTS_DIR"
		rm -rf "$WP_TESTS_DIR/includes" "$WP_TESTS_DIR/data"
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/" "$WP_TESTS_DIR/includes"
		svn export --quiet --ignore-externals "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/" "$WP_TESTS_DIR/data"
	fi
	if [ ! -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
		download "https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"
		WP_CORE_DIR_ESC=$(echo "$WP_CORE_DIR" | sed "s/\//\\\\\//g")
		sed -i "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR_ESC/':" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR/wp-tests-config.php"
		sed -i "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR/wp-tests-config.php"
	fi
}

create_db() {
	[ "$SKIP_DB_CREATE" = "true" ] && return
	local PARTS=(${DB_HOST//:/ })
	local H=${PARTS[0]}
	local P=${PARTS[1]-}
	local SOCK=""
	[ -n "$P" ] && { [[ "$P" =~ ^[0-9]+$ ]] && local EXTRA="--host=$H --port=$P --protocol=tcp" || local EXTRA="--socket=$P"; } || local EXTRA="--host=$H --protocol=tcp"
	mysqladmin create "$DB_NAME" --user="$DB_USER" --password="$DB_PASS" $EXTRA 2>/dev/null || true
}

install_wp
install_test_suite
create_db
echo "WordPress test suite installed (WP_TESTS_TAG=$WP_TESTS_TAG)."
