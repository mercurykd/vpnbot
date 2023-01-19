cat /ssh/key.pub > /root/.ssh/authorized_keys
service ssh start
/sslocal -v -d -c /config.json
tail -f /dev/null
