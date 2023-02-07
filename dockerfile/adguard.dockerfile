from nginx:stable
run apt update && \
apt install -y git net-tools lsof ssh wget && \
wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.23/AdGuardHome_linux_amd64.tar.gz && \
tar -xf AdGuardHome_linux_amd64.tar.gz && \
mkdir -p /opt/adguardhome && \
mkdir /root/.ssh
copy config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
