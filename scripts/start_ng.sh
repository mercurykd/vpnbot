openssl req -newkey rsa:2048 -sha256 -nodes -x509 -days 365 -keyout /certs/self_private -out /certs/self_public  -subj "/C=NN/ST=N/L=N/O=N/CN=$(curl https://ipinfo.io/ip)"
ssh-keygen -m PEM -t rsa -f /ssh/key -N ''
cat /ssh/key.pub > /root/.ssh/authorized_keys
service ssh start
nginx -g "daemon off;"
