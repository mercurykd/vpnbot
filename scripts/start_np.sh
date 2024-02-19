cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
caddy run -c /config/Caddyfile > /dev/null 2>&1 &
tail -f /dev/null
