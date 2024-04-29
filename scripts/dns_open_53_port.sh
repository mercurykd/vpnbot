mkdir /etc/systemd/resolved.conf.d
echo "[Resolve]
DNS=127.0.0.1
DNSStubListener=no" > /etc/systemd/resolved.conf.d/adguardhome.conf
mv /etc/resolv.conf /etc/resolv.conf.backup
ln -s /run/systemd/resolve/resolv.conf /etc/resolv.conf
systemctl reload-or-restart systemd-resolved
make d u
