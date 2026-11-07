#!/bin/sh
# sshd обязателен: dnstt-server форвардит DNS-туннель на 127.0.0.1:22 (см. Bot::dnsttStart)
ssh-keygen -A
/usr/sbin/sshd -D -e >> /logs/dnstt 2>&1 &
tail -f /dev/null
