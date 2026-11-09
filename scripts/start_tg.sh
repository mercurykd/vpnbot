#!/bin/sh

mkdir -p /config/telemt/tlsfront

# telemt держим под надзором: после pkill (бот перегенерировал config.toml) он поднимется сам.
# Один процесс обслуживает ee (tls), dd (secure), plain (classic) и WEB-листенер для web-mtproto.
(
    while true; do
        if [ -f /config/telemt/config.toml ]; then
            telemt /config/telemt/config.toml >>/logs/telemt 2>&1
            sleep 5
        else
            sleep 3
        fi
    done
) &

tail -f /dev/null
