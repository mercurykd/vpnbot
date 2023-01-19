cat /ssh/key.pub > /root/.ssh/authorized_keys
service ssh start
/ssserver -v -d -c /config.json
tail -f /dev/null
