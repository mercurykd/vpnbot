#!/bin/bash
set -e

WGCF_DIR="/etc/warp"
WGCF_CONF="$WGCF_DIR/wgcf-profile.conf"
WGCF_TOML="$WGCF_DIR/wgcf-account.toml"
SOCKS_PORT="${SOCKS_PORT:-1080}"

WARP_LICENSE_KEY=$(cat /config/pac.json | jq -r '.warp // empty')

mkdir -p "$WGCF_DIR"
cd "$WGCF_DIR"

if [ ! -f "$WGCF_CONF" ]; then
    echo "[warp] Registering WARP account..."
    rm -f "$WGCF_TOML"
    wgcf register --accept-tos
    if [ -n "$WARP_LICENSE_KEY" ]; then
        echo "[warp] Applying license key..."
        # Заменяем license_key в wgcf-account.toml на WARP_LICENSE_KEY
        if grep -q '^license_key' "$WGCF_TOML"; then
            sed -i "s/^license_key.*/license_key = \"$WARP_LICENSE_KEY\"/" "$WGCF_TOML"
        fi
        wgcf update
    fi
    echo "[warp] Generating WireGuard profile..."
    wgcf generate
fi

# wg-quick в контейнере не может управлять /etc/resolv.conf: Docker монтирует его сам,
# resolvconf падает с "signature mismatch" и wg-quick откатывает интерфейс (exit 1).
# поэтому выкидываем IPv6 и DNS из профиля — microsocks и socks5 не требуют DNS на интерфейсе.
echo "[warp] Stripping IPv6/DNS from profile..."
sed -i '/^Address.*:/d' "$WGCF_CONF"
sed -i '/^AllowedIPs.*::/d' "$WGCF_CONF"
sed -i '/^DNS.*=/d' "$WGCF_CONF"

echo "[warp] Starting WireGuard (WARP)..."
wg-quick up "$WGCF_CONF"

echo "[warp] Starting microsocks on port $SOCKS_PORT..."
exec microsocks -p "$SOCKS_PORT"
