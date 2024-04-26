cat /ssh/key.pub > /root/.ssh/authorized_keys
ssh-keygen -A
exec /usr/sbin/sshd -D -e "$@" &
sed "s/ss:[0-9]\+/ss:$SSPORT/" /config/nginx_default.conf > change_port
cat change_port > /config/nginx_default.conf
sed "s/ss:[0-9]\+/ss:$SSPORT/" /config/nginx.conf > change_port
cat change_port > /config/nginx.conf
nginx -g "daemon off;" -c /config/nginx.conf
