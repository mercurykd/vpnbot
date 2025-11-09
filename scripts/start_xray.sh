cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
uuid=$(cat /xray.json | jq -r '.inbounds[0].settings.clients[0].id // empty')
if [ -n "$uuid" ]; then
    xray run -config /xray.json > /dev/null 2>&1 &
fi
tail -f /dev/null
