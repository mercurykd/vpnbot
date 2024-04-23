cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
sing-box run -c /config/singbox.json > /dev/null &
tail -f /dev/null
