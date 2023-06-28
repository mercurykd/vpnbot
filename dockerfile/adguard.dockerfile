from alpine:3.18.2
run apk add --no-cache --update openssh \
    && wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.32/AdGuardHome_linux_amd64.tar.gz \
    && tar -xf AdGuardHome_linux_amd64.tar.gz \
    && mkdir -p /opt/adguardhome \
    && mkdir /root/.ssh
copy config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
env ENV="/root/.ashrc"
