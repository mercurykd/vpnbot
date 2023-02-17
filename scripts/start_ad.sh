cat /ssh/key.pub > /root/.ssh/authorized_keys
echo 'HostKeyAlgorithms +ssh-rsa' >> /etc/ssh/sshd_config
echo 'PubkeyAcceptedKeyTypes +ssh-rsa' >> /etc/ssh/sshd_config
service ssh start
/AdGuardHome/AdGuardHome -s install -c /opt/adguardhome/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/
tail -f /dev/null
