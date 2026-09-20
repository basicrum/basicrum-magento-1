#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <platform> <platform-root>" >&2
    exit 2
fi

platform="$1"
platform_root="$(cd "$2" && pwd)"
plugin_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

document_root="$platform_root"
case "$platform" in
    magento-ce|openmage)
        ;;
    *)
        echo "Unsupported platform: $platform" >&2
        exit 2
        ;;
esac

server_log="$platform_root/var/log/basicrum-platform-server.log"
mkdir -p "$(dirname "$server_log")"
(
    cd "$platform_root"
    php -d memory_limit=-1 -S 127.0.0.1:8080 -t "$document_root"
) >"$server_log" 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

storefront_html=''
for attempt in {1..30}; do
    if storefront_html="$(curl --fail --silent http://127.0.0.1:8080/)"; then
        break
    fi
    sleep 1
done

if [[ -z "$storefront_html" ]]; then
    echo "Storefront did not become ready. Server log:" >&2
    cat "$server_log" >&2
    exit 1
fi

consent_count="$( (grep -o 'consent-boomerang-loader-v1-15.min.js' <<<"$storefront_html" || true) \
    | wc -l | tr -d ' ')"
if [[ "$consent_count" != "1" ]]; then
    echo "Expected one consent loader in the live storefront; found $consent_count" >&2
    exit 1
fi

loader_js="$(curl --fail --silent --show-error \
    http://127.0.0.1:8080/js/basicrum/loaders/consent-boomerang-loader-v1-15.min.js)"
for callback in OPT_IN_BASICRUM_LOADER_WRAPPER OPT_OUT_BASICRUM_LOADER_WRAPPER OPT_IN_BASIC_RUM OPT_OUT_BASIC_RUM; do
    if [[ "$loader_js" != *"$callback"* ]]; then
        echo "Live consent loader is missing callback: $callback" >&2
        exit 1
    fi
done

php "$plugin_root/tests/platform/configure.php" "$platform_root" "$platform" disabled
disabled_html="$(curl --fail --silent --show-error http://127.0.0.1:8080/)"
if [[ "$disabled_html" == *"basicRumBoomerangConfig"* ]]; then
    echo "Disabled live storefront emitted monitoring configuration" >&2
    exit 1
fi

php "$plugin_root/tests/platform/configure.php" "$platform_root" "$platform" immediate
immediate_html="$(curl --fail --silent --show-error http://127.0.0.1:8080/)"
immediate_count="$( (grep -o 'boomerang-loader-v15.min.js' <<<"$immediate_html" || true) \
    | wc -l | tr -d ' ')"
if [[ "$immediate_count" != "1" ]]; then
    echo "Expected one immediate loader in the live storefront; found $immediate_count" >&2
    exit 1
fi

echo "Live ${platform} storefront checks passed."
