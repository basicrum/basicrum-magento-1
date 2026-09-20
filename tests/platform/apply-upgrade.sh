#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 2 ]]; then
    echo "Usage: $0 <platform> <platform-root>" >&2
    exit 2
fi

platform="$1"
platform_root="$(cd "$2" && pwd)"

case "$platform" in
    magento-ce|openmage)
        document_root="$platform_root"
        ;;
    *)
        echo "Unsupported platform: $platform" >&2
        exit 2
        ;;
esac

server_log="$platform_root/var/log/basicrum-upgrade-server.log"
mkdir -p "$(dirname "$server_log")"
(
    cd "$platform_root"
    php -d memory_limit=-1 -S 127.0.0.1:8080 -t "$document_root"
) >"$server_log" 2>&1 &
server_pid=$!
trap 'kill "$server_pid" 2>/dev/null || true' EXIT

upgrade_applied=0
for attempt in {1..30}; do
    if curl --fail --silent --output /dev/null http://127.0.0.1:8080/; then
        upgrade_applied=1
        break
    fi
    sleep 1
done

if [[ "$upgrade_applied" != "1" ]]; then
    echo "Storefront did not apply the pending module upgrade. Server log:" >&2
    cat "$server_log" >&2
    exit 1
fi

kill "$server_pid" 2>/dev/null || true
wait "$server_pid" 2>/dev/null || true
trap - EXIT

echo "Applied pending ${platform} module upgrade through the storefront."
