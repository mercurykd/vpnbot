#!/bin/sh

mkdir -p /config/tproxy

if [ "$(wc -c < /config/tproxy/token.key 2>/dev/null || echo 0)" -ne 32 ]; then
    head -c 32 /dev/urandom > /config/tproxy/token.key
    chmod 0400 /config/tproxy/token.key
fi

# tproxy-server держим под надзором: после pkill (смена конфига ботом) он поднимется сам
(
    while true; do
        if [ -f /config/tproxy/config.json ]; then
            tproxy-server -config /config/tproxy/config.json >>/logs/tproxy 2>&1
            sleep 10
        else
            sleep 3
        fi
    done
) &

nginx -g "daemon off;"

