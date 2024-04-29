socat TCP-LISTEN:80,fork TCP:{ip}:80 &
socat TCP-LISTEN:443,fork TCP:{ip}:443 &
socat TCP-LISTEN:853,fork TCP:{ip}:853 &
socat TCP-LISTEN:{tg},fork TCP:{ip}:{tg} &
socat TCP-LISTEN:{ss},fork TCP:{ip}:{ss} &
socat UDP-LISTEN:{ss},fork UDP:{ip}:{ss} &
socat UDP-LISTEN:{wg},fork UDP:{ip}:{wg} &
tail -f /dev/null