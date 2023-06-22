from alpine:latest
run apk add iproute2 linux-headers iptables xtables-addons wireguard-tools openssh \
    && mkdir /root/.ssh
env ENV="/root/.ashrc"