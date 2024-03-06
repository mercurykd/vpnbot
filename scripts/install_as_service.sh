path=`pwd`
sed "s|path|$path|g" "$path/scripts/vpnbot.service" > /etc/systemd/system/vpnbot.service
systemctl daemon-reload
systemctl enable vpnbot
