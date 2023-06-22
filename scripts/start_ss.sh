cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
sed "s/\"server_port\": [0-9]\+/\"server_port\": $SSPORT/" /config.json > change_port
cat change_port > /config.json
ssserver -v -d -c /config.json
tail -f /dev/null
