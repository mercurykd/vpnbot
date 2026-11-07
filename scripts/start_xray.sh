#!/bin/sh
# запускаем ядро только если клиент настроен, дальше его перезапускает бот после изменения конфига
uuid=$(jq -r '.inbounds[0].settings.clients[0].id // empty' /xray.json 2>/dev/null)
if [ -n "$uuid" ]; then
    xray run -config /xray.json >> /logs/xray 2>&1 &
fi
tail -f /dev/null
