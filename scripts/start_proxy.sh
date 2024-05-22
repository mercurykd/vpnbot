cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
sed -n "s/\"server_port\": \([0-9]\+\),/\1/p" /config.json > current_port
CURRENT_PORT=$(cat current_port | tr -d " ")
if [ "$CURRENT_PORT" -ne "443" ]
then
    sed "s/\"server_port\": [0-9]\+/\"server_port\": $SSPORT/" /config.json > change_port
    cat change_port > /config.json
fi
sslocal -v -d -c /config.json
tail -f /dev/null
