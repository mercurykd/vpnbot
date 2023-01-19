php init.php
unitd --log /logs/unit_error
curl -X PUT --data-binary @/config/unit.json --unix-socket /var/run/control.unit.sock http://localhost/config
kill -TERM $(/bin/cat /var/run/unit.pid)
unitd --no-daemon --log /logs/unit_error
