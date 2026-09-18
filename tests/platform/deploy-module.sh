#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <platform> <platform-root>" >&2
    exit 2
fi

platform="$1"
platform_root="$2"
plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

case "$platform" in
    magento-ce|openmage|maho)
        ;;
    *)
        echo "Unsupported platform: $platform" >&2
        exit 2
        ;;
esac

if [[ ! -f "$platform_root/app/Mage.php" ]]; then
    echo "Platform root does not contain app/Mage.php: $platform_root" >&2
    exit 1
fi

while read -r source_path destination_path extra; do
    if [[ -z "${source_path:-}" || "${source_path:0:1}" == "#" ]]; then
        continue
    fi

    if [[ -n "${extra:-}" ]]; then
        echo "Invalid modman row: $source_path $destination_path $extra" >&2
        exit 1
    fi

    # Maho's public document root contains static assets. PHP module files
    # retain their Magento 1 paths, while root-level js/ maps to public/js/.
    if [[ "$platform" == "maho" && "$destination_path" == js/* ]]; then
        destination_path="public/$destination_path"
    fi

    mkdir -p "$platform_root/$(dirname "$destination_path")"
    cp "$plugin_root/$source_path" "$platform_root/$destination_path"
done < "$plugin_root/modman"

echo "Deployed Basicrum into $platform."
