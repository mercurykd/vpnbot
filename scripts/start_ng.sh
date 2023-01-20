cat /ssh/key.pub > /root/.ssh/authorized_keys
service ssh start
nginx -g "daemon off;"
