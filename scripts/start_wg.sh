#!/bin/bash
INTERFACE_VPN="tun0"
TABLE_ID=100

INTERFACE=$(route | grep '^default' | grep -o '[^ ]*$')
if [ $(cat /etc/wireguard/wg0.conf | wc -c) -eq 0 ]
then
    PRIVATEKEY=$(wg genkey | tee /etc/wireguard/privatekey)
    echo "[Interface]" > /etc/wireguard/wg0.conf
    echo "PrivateKey = $PRIVATEKEY" >> /etc/wireguard/wg0.conf
    echo "Address = $ADDRESS" >> /etc/wireguard/wg0.conf
    echo "ListenPort = 51820" >> /etc/wireguard/wg0.conf
else
    sed "s/ListenPort = [0-9]\+/ListenPort = 51820/" /etc/wireguard/wg0.conf > change_port
    sed "s|Address = [0-9\.\/ ]\+|Address = $ADDRESS|" change_port > change_address
    cat change_address > /etc/wireguard/wg0.conf
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

ip tuntap add mode tun dev $INTERFACE_VPN || true
ip addr add 10.255.0.1/24 dev $INTERFACE_VPN || true
ip link set up dev $INTERFACE_VPN
ip rule add from $ADDRESS table $TABLE_ID || true
ip route add default dev $INTERFACE_VPN table $TABLE_ID || true
iptables -A FORWARD -i $INTERFACE_VPN -j ACCEPT
iptables -A FORWARD -o $INTERFACE_VPN -j ACCEPT
iptables -t nat -I POSTROUTING 1 -s $ADDRESS -j RETURN
tun2socks -device $INTERFACE_VPN -proxy socks5://xr:10808 &

tail -f /dev/null
