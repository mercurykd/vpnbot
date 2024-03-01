route add -net 10.0.1.0 netmask 255.255.255.0 gw wg
route add -net 10.0.3.0 netmask 255.255.255.0 gw wg1
route add -net 10.0.2.0 netmask 255.255.255.0 gw oc
cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
/opt/adguardhome/AdGuardHome --no-check-update --pidfile /opt/adguardhome/pid -c /config/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/work
tail -f /dev/null
