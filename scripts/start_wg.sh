if [ $(cat /etc/wireguard/wg0.conf | wc -c) -eq 0 ]
then
    PRIVATEKEY=$(wg genkey | tee /etc/wireguard/privatekey)
    INTERFACE=$(route | grep '^default' | grep -o '[^ ]*$')
    echo "[Interface]" > /etc/wireguard/wg0.conf
    echo "PrivateKey = $PRIVATEKEY" >> /etc/wireguard/wg0.conf
    echo "Address = $ADDRESS" >> /etc/wireguard/wg0.conf
    echo "ListenPort = $WGPORT" >> /etc/wireguard/wg0.conf
    echo "PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -t nat -A POSTROUTING -o $INTERFACE -j MASQUERADE" >> /etc/wireguard/wg0.conf
    echo "PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -t nat -D POSTROUTING -o $INTERFACE -j MASQUERADE" >> /etc/wireguard/wg0.conf
fi
sed "s/ListenPort = [0-9]\+/ListenPort = $WGPORT/" /etc/wireguard/wg0.conf > change_port
cat change_port > /etc/wireguard/wg0.conf
wg-quick up wg0
cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
tail -f /dev/null
