cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
off=$(cat /config/pac.json | jq -r .warpoff)
key=$(cat /config/pac.json | jq -r .warp)
if [ "$off" == 'null' ]
then
    warp-svc > /dev/null &
    sleep 3
    warp-cli --accept-tos registration new
    if [ ! -z "$key" ]
    then
        warp-cli --accept-tos registration license $key
    fi
    warp-cli --accept-tos mode proxy
    warp-cli --accept-tos connect
fi
socat TCP-LISTEN:4000,fork TCP:127.0.0.1:40000
