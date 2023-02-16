arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && \
apt install -y wireguard \
iproute2 \
net-tools \
lsof \
iptables \
linux-headers-$(uname -r) \
ssh && \
mkdir /root/.ssh
