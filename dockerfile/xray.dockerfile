ARG image
FROM $image
RUN apk add openssh openssl jq \
    && mkdir /root/.ssh \
    && wget -O Xray-linux-64.zip $(wget -q -O - https://api.github.com/repos/XTLS/Xray-core/releases/latest | grep browser_download_url | cut -d\" -f4 | egrep 'Xray-linux-64.zip$') \
    && unzip Xray-linux-64.zip \
    && mv xray /usr/bin/ \
    && rm Xray-linux-64.zip \
    && rm geoip.dat \
    && rm geosite.dat \
    && chmod +x /usr/bin/xray
ENV ENV="/root/.ashrc"
