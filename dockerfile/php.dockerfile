ARG image
FROM $image
RUN apk add --no-cache --update php85 \
    php85-mbstring \
    php85-session \
    php85-phar \
    php85-zip \
    php85-curl \
    php85-openssl \
    php85-iconv \
    php85-intl \
    php85-pecl-yaml \
    unit \
    unit-php85 \
    xxd \
    certbot \
    libqrencode \
    libqrencode-tools \
    openssl \
    curl \
    git \
    py3-qt5 \
    && ln -sf /usr/bin/php85 /usr/bin/php \
    && wget https://github.com/ameshkov/dnslookup/releases/download/v1.11.1/dnslookup-linux-amd64-v1.11.1.tar.gz \
    && tar -xf dnslookup-linux-amd64-v1.11.1.tar.gz \
    && mv linux-amd64/dnslookup /usr/bin \
    && rm dnslookup-linux-amd64-v1.11.1.tar.gz \
    && rm -rf /linux-amd64 \
    && wget https://github.com/SagerNet/sing-box/releases/download/v1.10.3/sing-box-1.10.3-linux-amd64.tar.gz \
    && tar -xf sing-box-1.10.3-linux-amd64.tar.gz \
    && mv sing-box-1.10.3-linux-amd64/sing-box /usr/bin \
    && rm sing-box-1.10.3-linux-amd64.tar.gz \
    && rm -rf /sing-box-1.10.3-linux-amd64 \
    && wget https://github.com/MetaCubeX/mihomo/releases/download/v1.18.10/mihomo-linux-amd64-v1.18.10.gz \
    && gunzip mihomo-linux-amd64-v1.18.10.gz \
    && mv mihomo-linux-amd64-v1.18.10 /usr/bin/mihomo