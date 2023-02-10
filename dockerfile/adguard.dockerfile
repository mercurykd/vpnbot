from ubuntu:18.04
run apt update && \
apt install -y git net-tools lsof ssh wget && \
wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.23/AdGuardHome_linux_amd64.tar.gz && \
tar -xf AdGuardHome_linux_amd64.tar.gz && \
mkdir -p /opt/adguardhome && \
mkdir /root/.ssh
copy config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
run wget https://github.com/ameshkov/dnslookup/releases/download/v1.8.1/dnslookup-linux-amd64-v1.8.1.tar.gz && \
tar -xf dnslookup-linux-amd64-v1.8.1.tar.gz
env PATH="$PATH:/linux-amd64"
