#!/usr/bin/env bash
set -euo pipefail

if [[ $# -gt 1 ]]; then
    echo "Usage: $0 [output-directory]" >&2
    exit 2
fi
repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
release_dir="${1:-$repo_root/release}"
version="$(node "$repo_root/tools/release-version.js")"
archive_name="basicrum-magento-1-$version.zip"
stage_dir="$(mktemp -d "${TMPDIR:-/tmp}/basicrum-release.XXXXXX")"
trap 'rm -rf -- "$stage_dir"' EXIT

bash "$repo_root/tools/package-files.sh" > "$stage_dir/files.txt"
while IFS= read -r package_file; do
    mkdir -p "$stage_dir/basicrum-magento-1/$(dirname "$package_file")"
    cp -p "$repo_root/$package_file" "$stage_dir/basicrum-magento-1/$package_file"
done < "$stage_dir/files.txt"
(
    cd "$stage_dir"
    sed 's|^|basicrum-magento-1/|' files.txt | zip -X -q "$archive_name" -@
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$archive_name" > "$archive_name.sha256"
    else
        shasum -a 256 "$archive_name" > "$archive_name.sha256"
    fi
)
bash "$repo_root/tools/verify-release.sh" "$stage_dir/$archive_name"

mkdir -p "$release_dir"
if [[ -e "$release_dir/$archive_name" || -e "$release_dir/$archive_name.sha256" ]]; then
    echo "Release output already exists; choose an empty output directory: $release_dir" >&2
    exit 1
fi
cp "$stage_dir/$archive_name" "$stage_dir/$archive_name.sha256" "$release_dir/"
echo "Built $release_dir/$archive_name and its SHA-256 checksum."
