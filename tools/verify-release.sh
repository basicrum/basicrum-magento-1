#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 || ! -f "$1" || ! -f "$1.sha256" ]]; then
    echo "Usage: $0 <release.zip> (matching .sha256 file required)" >&2
    exit 2
fi
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
archive_path="$1"
archive_name="$(basename "$archive_path")"
module_version="$(xmllint --xpath 'string(/config/modules/BasicRum_Analytics/version)' \
    "$repo_root/app/code/community/BasicRum/Analytics/etc/config.xml")"
release_version="${archive_name#basicrum-magento-1-}"
release_version="${release_version%.zip}"
if [[ "$archive_name" != "basicrum-magento-1-$release_version.zip" \
    || ! "$release_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-(alpha|beta|rc)\.[1-9][0-9]*)?$ \
    || "${release_version%%-*}" != "$module_version" ]]; then
    echo "Archive name does not match the module version: $archive_name" >&2
    exit 1
fi
if command -v sha256sum >/dev/null 2>&1; then
    checksum="$(sha256sum "$archive_path" | awk '{print $1}')"
else
    checksum="$(shasum -a 256 "$archive_path" | awk '{print $1}')"
fi
if [[ "$(cat "$archive_path.sha256")" != "$checksum  $archive_name" ]]; then
    echo "Release checksum does not match the archive." >&2
    exit 1
fi

expected_files="$(bash "$repo_root/tools/package-files.sh")"
unzip -tqq "$archive_path"
diff -u \
    <(printf '%s\n' "$expected_files" | sed 's|^|basicrum-magento-1/|') \
    <(zipinfo -1 "$archive_path" | LC_ALL=C sort)
while IFS= read -r package_file; do
    # Check the actual packaged bytes against this checkout, not just filenames.
    unzip -p "$archive_path" "basicrum-magento-1/$package_file" | cmp - "$repo_root/$package_file"
done <<< "$expected_files"
echo "Verified release contents and checksum: $archive_name"
