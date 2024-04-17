cat /ssh/key.pub > /root/.ssh/authorized_keys
echo 'HostKeyAlgorithms +ssh-rsa' >> /etc/ssh/sshd_config
echo 'PubkeyAcceptedKeyTypes +ssh-rsa' >> /etc/ssh/sshd_config
service ssh start
warp-svc > /dev/null &
sleep 3
warp-cli --accept-tos registration new
key=$(cat /config/pac.json | jq -r .warp)
if [ ! -z "$key" ]
then
    warp-cli --accept-tos registration license $key
fi
warp-cli --accept-tos mode proxy
warp-cli --accept-tos connect
socat TCP-LISTEN:4000,fork TCP:127.0.0.1:40000
