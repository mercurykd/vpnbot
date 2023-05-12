arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && \
apt install -y git net-tools lsof ssh wget && \
apt clean autoclean && \
apt autoremove -y && \
wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.29/AdGuardHome_linux_amd64.tar.gz && \
tar -xf AdGuardHome_linux_amd64.tar.gz && \
mkdir -p /opt/adguardhome && \
mkdir /root/.ssh && \
wget https://github.com/ameshkov/dnslookup/releases/download/v1.8.1/dnslookup-linux-amd64-v1.8.1.tar.gz && \
tar -xf dnslookup-linux-amd64-v1.8.1.tar.gz
copy config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
env PATH="$PATH:/linux-amd64"
