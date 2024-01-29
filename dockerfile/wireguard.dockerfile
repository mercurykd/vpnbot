ARG image
FROM $image
RUN apk add iproute2 linux-headers iptables xtables-addons wireguard-tools openssh jq \
    && mkdir /root/.ssh
ENV ENV="/root/.ashrc"
