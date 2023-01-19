from ubuntu:18.04
run apt update && \
apt install -y wireguard \
iproute2 \
net-tools \
lsof \
iptables \
linux-headers-$(uname -r) \
ssh && \
mkdir /root/.ssh
