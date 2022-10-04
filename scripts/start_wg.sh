INTERFACE=$(route | grep '^default' | grep -o '[^ ]*$')
PRIVATEKEY=$(cat /etc/wireguard/privatekey)
if [ $(cat /etc/wireguard/wg0.conf | wc -c) -eq 0 ]
then
    echo "[Interface]" > /etc/wireguard/wg0.conf
    echo "PrivateKey = $PRIVATEKEY" >> /etc/wireguard/wg0.conf
    echo "Address = $ADDRESS" >> /etc/wireguard/wg0.conf
    echo "ListenPort = $PORT_WG" >> /etc/wireguard/wg0.conf
    echo "PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -t nat -A POSTROUTING -o $INTERFACE -j MASQUERADE" >> /etc/wireguard/wg0.conf
    echo "PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -t nat -D POSTROUTING -o $INTERFACE -j MASQUERADE" >> /etc/wireguard/wg0.conf
fi
wg-quick up wg0
cat /ssh/key.pub > /root/.ssh/authorized_keys
service ssh start
tail -f /dev/null
