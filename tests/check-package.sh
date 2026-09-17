#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repo_root"

while IFS= read -r xml_file; do
    xmllint --noout "$xml_file"
done < <(find app -type f -name '*.xml' -print | sort)

module_version="$(xmllint --xpath 'string(/config/modules/BasicRum_Analytics/version)' app/code/community/BasicRum/Analytics/etc/config.xml)"
privacy_default="$(xmllint --xpath 'string(/config/default/basicrum_analytics/privacy/opt_in_required)' app/code/community/BasicRum/Analytics/etc/config.xml)"

if [[ "$module_version" != "1.1.0" || "$privacy_default" != "1" ]]; then
    echo "Unexpected module version or privacy default: version=$module_version opt_in_required=$privacy_default" >&2
    exit 1
fi

package_files=(README.md LICENSE.md THIRD-PARTY-NOTICES.txt modman package.json)

while read -r source_path destination_path extra; do
    if [[ -z "${source_path:-}" || "${source_path:0:1}" == "#" ]]; then
        continue
    fi

    if [[ -n "${extra:-}" ]]; then
        echo "Invalid modman row: $source_path $destination_path $extra" >&2
        exit 1
    fi

    if [[ ! -f "$source_path" ]]; then
        echo "modman source does not exist: $source_path" >&2
        exit 1
    fi

    package_files+=("$source_path")
done < modman

while IFS= read -r runtime_file; do
    if ! awk -v file="$runtime_file" '$1 == file { found = 1 } END { exit found ? 0 : 1 }' modman; then
        echo "Runtime file is missing from modman: $runtime_file" >&2
        exit 1
    fi
done < <(find app js -type f -print | sort)

expected_boomerang_sha="90e8a1c85949b10d43e441efc3f0545f95e4384e26ee3042344a8b2b4110589c"
if command -v shasum >/dev/null 2>&1; then
    actual_boomerang_sha="$(shasum -a 256 js/basicrum/boomerangs/boomerang-1.815.60.cutting-edge.min.js | awk '{print $1}')"
else
    actual_boomerang_sha="$(sha256sum js/basicrum/boomerangs/boomerang-1.815.60.cutting-edge.min.js | awk '{print $1}')"
fi

if [[ "$actual_boomerang_sha" != "$expected_boomerang_sha" ]]; then
    echo "Bundled Boomerang checksum changed: $actual_boomerang_sha" >&2
    exit 1
fi

if grep -R --line-number '<ms>' app/code/community/BasicRum/Analytics/etc; then
    echo "Legacy wait-after-onload default key found" >&2
    exit 1
fi

package_tmp_dir="$(mktemp -d -t basicrum-magento-1.XXXXXX)"
archive_path="$package_tmp_dir/basicrum-magento-1.zip"
trap 'rm -f "$archive_path"; rmdir "$package_tmp_dir"' EXIT
zip -q "$archive_path" "${package_files[@]}"
unzip -tqq "$archive_path"
diff -u \
    <(printf '%s\n' "${package_files[@]}" | sort -u) \
    <(zipinfo -1 "$archive_path" | sort -u)

echo "XML, modman, provenance, and package archive checks passed."
