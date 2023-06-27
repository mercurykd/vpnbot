cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
if [ $(cat /xray.json | jq -r '.inbounds[0].settings.clients[0].id' | wc -c) -gt 1 ]
then
    xray run -config /xray.json &
fi
tail -f /dev/null
