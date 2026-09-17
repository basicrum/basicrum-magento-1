#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <platform> <platform-root>" >&2
    exit 2
fi

platform="$1"
platform_root="$(cd "$2" && pwd)"
plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
database_host="${BASICRUM_TEST_DB_HOST:-127.0.0.1}"
database_name="${BASICRUM_TEST_DB_NAME:-basicrum}"
database_user="${BASICRUM_TEST_DB_USER:-basicrum}"
database_password="${BASICRUM_TEST_DB_PASSWORD:-basicrum}"

bash "$plugin_root/tests/platform/deploy-module.sh" "$platform" "$platform_root"

case "$platform" in
    magento-ce)
        ;;
    openmage)
        composer install \
            --working-dir="$platform_root" \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --no-progress
        ;;
    maho)
        # Verify that the extension does not depend on Maho's optional global
        # aliases for legacy Varien form-element classes.
        export MAHO_ENABLE_VARIEN_ALIASES=0
        composer install \
            --working-dir="$platform_root" \
            --no-dev \
            --prefer-dist \
            --no-interaction \
            --no-progress
        ;;
    *)
        echo "Unsupported platform: $platform" >&2
        exit 2
        ;;
esac

common_install_args=(
    --license_agreement_accepted yes
    --locale en_US
    --timezone Europe/Sofia
    --default_currency USD
    --db_host "$database_host"
    --db_name "$database_name"
    --db_user "$database_user"
    --db_pass "$database_password"
    --db_prefix br_
    --url http://127.0.0.1:8080/
    --secure_base_url http://127.0.0.1:8080/
    --use_secure no
    --use_secure_admin no
    --admin_lastname Admin
    --admin_firstname Basicrum
    --admin_email admin@example.com
    --admin_username basicrum_admin
    --admin_password BasicrumAdmin123
)

if [[ "$platform" == "maho" ]]; then
    (
        cd "$platform_root"
        ./maho install "${common_install_args[@]}"
    )
else
    (
        cd "$platform_root"
        php -d memory_limit=-1 install.php -- \
            "${common_install_args[@]}" \
            --use_rewrites no \
            --skip_url_validation yes
    )
fi

php "$plugin_root/tests/platform/verify.php" "$platform_root" "$platform"
bash "$plugin_root/tests/platform/verify-live.sh" "$platform" "$platform_root"

php "$plugin_root/tests/platform/prepare-upgrade.php" "$platform_root" "$platform" legacy
bash "$plugin_root/tests/platform/apply-upgrade.sh" "$platform" "$platform_root"
php "$plugin_root/tests/platform/verify-upgrade.php" "$platform_root" "$platform" legacy 0 absent

php "$plugin_root/tests/platform/prepare-upgrade.php" "$platform_root" "$platform" explicit
bash "$plugin_root/tests/platform/apply-upgrade.sh" "$platform" "$platform_root"
php "$plugin_root/tests/platform/verify-upgrade.php" "$platform_root" "$platform" explicit 1 absent

php "$plugin_root/tests/platform/prepare-upgrade.php" "$platform_root" "$platform" http
bash "$plugin_root/tests/platform/apply-upgrade.sh" "$platform" "$platform_root"
php "$plugin_root/tests/platform/verify-upgrade.php" "$platform_root" "$platform" http 0 1
