cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
mkdir -p /dev/net
mknod /dev/net/tun c 10 200
chmod 600 /dev/net/tun
iptables -t nat -A POSTROUTING -o eth0 -j MASQUERADE
ocserv -c /etc/ocserv/ocserv.conf
tail -f /dev/null
