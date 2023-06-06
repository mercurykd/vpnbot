cat /ssh/key.pub > /root/.ssh/authorized_keys
echo 'HostKeyAlgorithms +ssh-rsa' >> /etc/ssh/sshd_config
echo 'PubkeyAcceptedKeyTypes +ssh-rsa' >> /etc/ssh/sshd_config
service ssh start
curl -s https://core.telegram.org/getProxySecret -o proxy-secret
curl -s https://core.telegram.org/getProxyConfig -o proxy-multi.conf
if [ $(cat /mtprotosecret | wc -c) -eq 0 ]
then
    head -c 16 /dev/urandom | xxd -ps > /mtprotosecret
fi
SECRET=$(cat /mtprotosecret)
mtproto-proxy -u nobody -H $TGPORT --nat-info 10.10.0.8:$IP -S $SECRET --aes-pwd /proxy-secret /proxy-multi.conf -M 1
tail -f /dev/null
