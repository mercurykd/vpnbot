#!/bin/bash
set -e

WGCF_DIR="/etc/warp"
WGCF_CONF="$WGCF_DIR/wgcf-profile.conf"
WGCF_V4="$WGCF_DIR/wgcf-profile-v4.conf"
WGCF_NEW="$WGCF_DIR/wgcf-profile.new"
WGCF_TOML="$WGCF_DIR/wgcf-account.toml"
SOCKS_PORT="${SOCKS_PORT:-1080}"

# IPv6 есть не в каждом окружении (ядро, ip6tables, docker) — тогда поднимаем туннель только по IPv4
ipv6_available() {
    [ -f /proc/sys/net/ipv6/conf/all/disable_ipv6 ] || return 1
    [ "$(cat /proc/sys/net/ipv6/conf/all/disable_ipv6)" = "0" ] || return 1
    ip -6 route show >/dev/null 2>&1 || return 1
    ip6tables -L -n >/dev/null 2>&1 || return 1
    return 0
}

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

# профиль на томе мог остаться от старой версии скрипта, где IPv6 вырезался — восстанавливаем из того же аккаунта
if ipv6_available && [ -f "$WGCF_TOML" ] && ! grep -q '^Address[[:space:]]*=.*:' "$WGCF_CONF"; then
    echo "[warp] Restoring IPv6 in the profile..."
    if (umask 077; wgcf generate -p "$WGCF_NEW") >/dev/null 2>&1 && grep -q '^\[Interface\]' "$WGCF_NEW"; then
        mv "$WGCF_NEW" "$WGCF_CONF"
    else
        echo "[warp] profile regeneration failed, staying on IPv4 only"
    fi
    rm -f "$WGCF_NEW"
fi

# wg-quick в контейнере не может управлять /etc/resolv.conf: Docker монтирует его сам,
# resolvconf падает с "signature mismatch" и wg-quick откатывает интерфейс (exit 1).
# поэтому выкидываем DNS из профиля — microsocks и socks5 не требуют DNS на интерфейсе.
echo "[warp] Stripping DNS from profile..."
sed -i '/^DNS.*=/d' "$WGCF_CONF"

echo "[warp] Starting WireGuard (WARP)..."
if ipv6_available && wg-quick up "$WGCF_CONF"; then
    echo "[warp] tunnel is up (IPv4 + IPv6)"
else
    # запасной профиль без IPv6 собираем на каждом старте, чтобы IPv6 включился сам, когда станет доступен
    echo "[warp] IPv6 is unavailable in the tunnel, falling back to IPv4 only"
    wg-quick down "$WGCF_CONF" >/dev/null 2>&1 || true
    (umask 077; grep -v -e '^Address[[:space:]]*=.*:' -e '^AllowedIPs[[:space:]]*=.*::' "$WGCF_CONF" > "$WGCF_V4") || true
    wg-quick up "$WGCF_V4"
fi

echo "[warp] Starting microsocks on port $SOCKS_PORT..."
exec microsocks -p "$SOCKS_PORT"
