from alpine:latest
run apk add --no-cache --update openssh && \
wget https://github.com/ameshkov/dnslookup/releases/download/v1.8.1/dnslookup-linux-amd64-v1.8.1.tar.gz && \
tar -xf dnslookup-linux-amd64-v1.8.1.tar.gz && \
wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.32/AdGuardHome_linux_amd64.tar.gz && \
tar -xf AdGuardHome_linux_amd64.tar.gz && \
mkdir -p /opt/adguardhome && \
mkdir /root/.ssh
copy config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
env PATH="$PATH:/linux-amd64"
env ENV="/root/.ashrc"
