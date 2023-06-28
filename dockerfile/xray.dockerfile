from alpine:3.18.2
run apk add openssh openssl jq \
    && mkdir /root/.ssh \
    && wget https://github.com/XTLS/Xray-core/releases/download/v1.8.3/Xray-linux-64.zip \
    && unzip Xray-linux-64.zip \
    && mv xray /usr/bin/ \
    && rm Xray-linux-64.zip \
    && rm geoip.dat \
    && rm geosite.dat \
    && chmod +x /usr/bin/xray
env ENV="/root/.ashrc"