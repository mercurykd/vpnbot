FROM alpine:3.18.2
RUN apk add --no-cache --update openssh \
    && wget https://github.com/AdguardTeam/AdGuardHome/releases/download/v0.107.39/AdGuardHome_linux_amd64.tar.gz \
    && tar -xf AdGuardHome_linux_amd64.tar.gz \
    && mkdir -p /opt/adguardhome \
    && mkdir /root/.ssh
COPY config/AdGuardHome.yaml /opt/adguardhome/AdGuardHome.yaml
ENV ENV="/root/.ashrc"
