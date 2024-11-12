rm /ssh/key*
ssh-keygen -m PEM -t rsa -f /ssh/key -N ''
openssl req -newkey rsa:2048 -sha256 -nodes -x509 -days 365 -keyout /certs/self_private -out /certs/self_public  -subj "/C=NN/ST=N/L=N/O=N/CN=$IP"
php init.php
if [[ -f "/start" && -f "/ssh/key.pub" && -s "/ssh/key.pub" ]]; then
    unitd --log /logs/unit_error
    curl -X PUT --data-binary @/config/unit.json --unix-socket /var/run/control.unit.sock http://localhost/config
    pkill unitd
    unitd --no-daemon --control 0.0.0.0:8080 --log /logs/unit_error
else
    exit 1;
fi
