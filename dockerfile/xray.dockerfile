ARG image
FROM $image
RUN apk add openssh openssl jq \
    && mkdir /root/.ssh \
    && wget https://github.com/XTLS/Xray-core/releases/download/v1.8.10/Xray-linux-64.zip \
    && unzip Xray-linux-64.zip \
    && mv xray /usr/bin/ \
    && rm Xray-linux-64.zip \
    && rm geoip.dat \
    && rm geosite.dat \
    && chmod +x /usr/bin/xray
ENV ENV="/root/.ashrc"
