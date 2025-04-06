cat /ssh/key.pub > /root/.ssh/authorized_keys
echo 'HostKeyAlgorithms +ssh-rsa' >> /etc/ssh/sshd_config
echo 'PubkeyAcceptedKeyTypes +ssh-rsa' >> /etc/ssh/sshd_config
service ssh start
off=$(cat /config/pac.json | jq -r .warpoff)
key=$(cat /config/pac.json | jq -r .warp)
if [ "$off" = 'null' ]
then
    warp-svc > /dev/null &
    sleep 3
    if [ ! -f /var/lib/cloudflare-warp/conf.json ]
    then
        warp-cli --accept-tos registration new
        if [ ! -z "$key" ]
        then
            warp-cli --accept-tos registration license $key
        fi
    fi
    warp-cli --accept-tos mode proxy
    warp-cli --accept-tos connect
fi
socat TCP-LISTEN:4000,fork TCP:127.0.0.1:40000
