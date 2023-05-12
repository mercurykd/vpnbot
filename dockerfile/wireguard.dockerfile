arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && \
apt install -y linux-headers-$(uname -r) iproute2 net-tools iptables xtables-addons-common xtables-addons-dkms wireguard ssh git && \
apt clean autoclean && \
apt autoremove -y && \
mkdir /root/.ssh
