cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &

INTERFACE=$(route | grep '^default' | grep -o '[^ ]*$')
if [ "$HOSTNAME" = "wireguard1" ]
then
    if [ $(cat /etc/wireguard/wg0.conf | wc -c) -eq 0 ]
    then
        PRIVATEKEY=$(wg genkey | tee /etc/wireguard/privatekey)
        echo "[Interface]" > /etc/wireguard/wg0.conf
        echo "PrivateKey = $PRIVATEKEY" >> /etc/wireguard/wg0.conf
        echo "Address = $ADDRESS" >> /etc/wireguard/wg0.conf
        echo "ListenPort = $WG1PORT" >> /etc/wireguard/wg0.conf
    else
        sed "s/ListenPort = [0-9]\+/ListenPort = $WG1PORT/" /etc/wireguard/wg0.conf > change_port
        cat change_port > /etc/wireguard/wg0.conf
    fi
else
    if [ $(cat /etc/wireguard/wg0.conf | wc -c) -eq 0 ]
    then
        PRIVATEKEY=$(wg genkey | tee /etc/wireguard/privatekey)
        echo "[Interface]" > /etc/wireguard/wg0.conf
        echo "PrivateKey = $PRIVATEKEY" >> /etc/wireguard/wg0.conf
        echo "Address = $ADDRESS" >> /etc/wireguard/wg0.conf
        echo "ListenPort = $WGPORT" >> /etc/wireguard/wg0.conf
    else
        sed "s/ListenPort = [0-9]\+/ListenPort = $WGPORT/" /etc/wireguard/wg0.conf > change_port
        cat change_port > /etc/wireguard/wg0.conf
    fi
fi
iptables -t nat -A POSTROUTING --destination 10.10.0.5 -j ACCEPT
iptables -t nat -A POSTROUTING -o $INTERFACE -j MASQUERADE
ln -s /etc/wireguard/wg0.conf /etc/amnezia/amneziawg/wg0.conf
if [ "$HOSTNAME" = "wireguard1" ]
then
    if [ $(cat /pac.json | jq .wg1_amnezia) -eq 1 ]
    then
        awg-quick up wg0
    else
        wg-quick up wg0
    fi
    if [ $(cat /pac.json | jq .wg1_blocktorrent) -eq 1 ]
    then
        sh /block_torrent.sh
    fi
    if [ $(cat /pac.json | jq .wg1_exchange) -eq 1 ]
    then
        sh /block_exchange.sh
    fi
else
    if [ $(cat /pac.json | jq .amnezia) -eq 1 ]
    then
        awg-quick up wg0
    else
        wg-quick up wg0
    fi
    if [ $(cat /pac.json | jq .blocktorrent) -eq 1 ]
    then
        sh /block_torrent.sh
    fi
    if [ $(cat /pac.json | jq .exchange) -eq 1 ]
    then
        sh /block_exchange.sh
    fi
fi
tail -f /dev/null
