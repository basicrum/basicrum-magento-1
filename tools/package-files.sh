#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."
package_files=(README.md LICENSE.md THIRD-PARTY-NOTICES.txt modman)

while read -r source_path destination_path extra; do
    if [[ -z "${source_path:-}" || "${source_path:0:1}" == "#" ]]; then
        continue
    fi
    # The ZIP is copied directly into Magento's directory layout. Reject mapping
    # changes that would make that layout disagree with a Modman installation.
    if [[ -n "${extra:-}" || "$source_path" != "$destination_path" \
        || ! "$source_path" =~ ^(app/|js/basicrum/)[a-zA-Z0-9_./-]+$ \
        || "/$source_path/" == *"/../"* || "/$source_path/" == *"/./"* \
        || "$source_path" == *"//"* ]]; then
        echo "Invalid or non-identity modman mapping: $source_path $destination_path" >&2
        exit 1
    fi
    package_files+=("$source_path")
done < modman

if [[ -n "$(find app js -type l -print)" ]]; then
    echo "Runtime symlinks cannot be included in a release." >&2
    exit 1
fi
for package_file in "${package_files[@]}"; do
    if [[ ! -f "$package_file" || -L "$package_file" ]]; then
        echo "Package source is missing or is a symlink: $package_file" >&2
        exit 1
    fi
done
if [[ -n "$(printf '%s\n' "${package_files[@]}" | LC_ALL=C sort | uniq -d)" ]]; then
    echo "Duplicate package file in modman." >&2
    exit 1
fi
while IFS= read -r runtime_file; do
    if ! printf '%s\n' "${package_files[@]}" | awk -v file="$runtime_file" \
        '$0 == file { found = 1 } END { exit found ? 0 : 1 }'; then
        echo "Runtime file is missing from modman: $runtime_file" >&2
        exit 1
    fi
done < <(find app js -type f -print)

printf '%s\n' "${package_files[@]}" | LC_ALL=C sort
