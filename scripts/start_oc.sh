INTERFACE=$(route | grep '^default' | grep -o '[^ ]*$')
cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
iptables -t nat -A POSTROUTING --destination 10.10.0.5 -j ACCEPT
iptables -t nat -A POSTROUTING -o $INTERFACE -j MASQUERADE
ocserv -c /etc/ocserv/ocserv.conf
tail -f /dev/null
