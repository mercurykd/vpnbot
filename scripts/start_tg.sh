echo 'root:dummy_passwd'|chpasswd
cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
curl -s https://core.telegram.org/getProxySecret -o proxy-secret
curl -s https://core.telegram.org/getProxyConfig -o proxy-multi.conf
if [ $(cat /mtprotosecret | wc -c) -gt 0 ]
then
    SECRET=$(cat /mtprotosecret)
    mtproto-proxy -u nobody -H $TGPORT --nat-info 10.10.0.8:$IP -S $SECRET --aes-pwd /proxy-secret /proxy-multi.conf -M 1
fi
tail -f /dev/null
