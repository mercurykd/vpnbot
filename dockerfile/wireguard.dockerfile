arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && apt install -y linux-headers-$(uname -r) iproute2 iptables xtables-addons-common xtables-addons-dkms wireguard ssh && mkdir /root/.ssh
