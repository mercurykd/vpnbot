from alpine:3.18.2
run apk add iproute2 linux-headers iptables xtables-addons wireguard-tools openssh jq \
    && mkdir /root/.ssh
env ENV="/root/.ashrc"
