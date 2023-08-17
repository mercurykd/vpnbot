route add -net 10.0.1.0 netmask 255.255.255.0 gw wg
cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
/AdGuardHome/AdGuardHome --pidfile /AdGuardHome/pid -c /opt/adguardhome/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/
tail -f /dev/null
