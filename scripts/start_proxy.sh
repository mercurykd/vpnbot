cat /ssh/key.pub > /root/.ssh/authorized_keys
echo 'HostKeyAlgorithms +ssh-rsa' >> /etc/ssh/sshd_config
echo 'PubkeyAcceptedKeyTypes +ssh-rsa' >> /etc/ssh/sshd_config
service ssh start
sed -n "s/\"server_port\": \([0-9]\+\),/\1/p" /config.json > current_port
CURRENT_PORT=$(cat current_port | tr -d " ")
if [ "$CURRENT_PORT" -ne "443" ]
then
    sed "s/\"server_port\": [0-9]\+/\"server_port\": $SSPORT/" /config.json > change_port
    cat change_port > /config.json
fi
/sslocal -v -d -c /config.json
tail -f /dev/null
