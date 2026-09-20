#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
test_dir="$(mktemp -d "${TMPDIR:-/tmp}/basicrum-package-test.XXXXXX")"
trap 'rm -rf -- "$test_dir"' EXIT
export BASICRUM_RELEASE_TAG=

expect_failure() {
    if "$@" > "$test_dir/failure.log" 2>&1; then
        echo "Unexpected success: $*" >&2
        exit 1
    fi
}

write_checksum() {
    (
        cd "$(dirname "$1")"
        if command -v sha256sum >/dev/null 2>&1; then
            sha256sum "$(basename "$1")" > "$1.sha256"
        else
            shasum -a 256 "$(basename "$1")" > "$1.sha256"
        fi
    )
}

version="$(node "$repo_root/tools/release-version.js")"
archive_name="basicrum-magento-1-$version.zip"
bash "$repo_root/tools/build-release.sh" "$test_dir/output"
archive="$test_dir/output/$archive_name"
bash "$repo_root/tools/verify-release.sh" "$archive"
expect_failure bash "$repo_root/tools/build-release.sh" "$test_dir/output"

# Prerelease packaging must not require changing Magento's setup version.
BASICRUM_RELEASE_TAG="v$version-rc.1" bash "$repo_root/tools/build-release.sh" "$test_dir/prerelease"
test -f "$test_dir/prerelease/basicrum-magento-1-$version-rc.1.zip"
expect_failure env BASICRUM_RELEASE_TAG=v999.0.0 bash "$repo_root/tools/build-release.sh" "$test_dir/mismatch"
test ! -d "$test_dir/mismatch"

# Neither a bad checksum nor a modified ZIP with a fresh checksum may pass.
mkdir -p "$test_dir/invalid"
cp "$archive" "$archive.sha256" "$test_dir/invalid/"
invalid="$test_dir/invalid/$archive_name"
printf 'invalid checksum\n' > "$invalid.sha256"
expect_failure bash "$repo_root/tools/verify-release.sh" "$invalid"
write_checksum "$invalid"
bash "$repo_root/tools/verify-release.sh" "$invalid"
zip -qd "$invalid" basicrum-magento-1/js/basicrum/LICENSE.txt
write_checksum "$invalid"
expect_failure bash "$repo_root/tools/verify-release.sh" "$invalid"

cp "$archive" "$invalid"
printf 'development-only\n' > "$test_dir/unwanted.txt"
(cd "$test_dir" && zip -q "$invalid" unwanted.txt)
write_checksum "$invalid"
expect_failure bash "$repo_root/tools/verify-release.sh" "$invalid"

cp "$archive" "$invalid"
mkdir -p "$test_dir/basicrum-magento-1"
printf 'changed runtime bytes\n' > "$test_dir/basicrum-magento-1/README.md"
(cd "$test_dir" && zip -q "$invalid" basicrum-magento-1/README.md)
write_checksum "$invalid"
expect_failure bash "$repo_root/tools/verify-release.sh" "$invalid"

# The native runner must deploy the verified ZIP, and stop for a broken one.
mkdir -p "$test_dir/platform/app"
touch "$test_dir/platform/app/Mage.php"
BASICRUM_TEST_RELEASE_ZIP="$archive" bash "$repo_root/tests/platform/deploy-module.sh" openmage "$test_dir/platform"
while read -r source_path destination_path extra; do
    if [[ -n "${source_path:-}" && "${source_path:0:1}" != "#" ]]; then
        cmp "$repo_root/$source_path" "$test_dir/platform/$destination_path"
    fi
done < "$repo_root/modman"
expect_failure env BASICRUM_TEST_RELEASE_ZIP="$invalid" \
    bash "$repo_root/tests/platform/deploy-module.sh" openmage "$test_dir/platform"

echo "Release archive, checksum, rejection, and packaged deployment tests passed."
