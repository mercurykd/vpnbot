make d
rm /etc/systemd/resolved.conf.d/adguardhome.conf
mv /etc/resolv.conf.backup /etc/resolv.conf
systemctl reload-or-restart systemd-resolved
cat docker-compose-init.yml > docker-compose.yml
make u
