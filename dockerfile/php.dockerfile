ARG image
FROM $image
RUN apk add --no-cache --update php81 \
    php81-mbstring \
    php81-session \
    php81-phar \
    php81-zip \
    php81-curl \
    php81-opcache \
    php81-openssl \
    php81-iconv \
    php81-intl \
    php81-pecl-ssh2 \
    php81-pecl-yaml \
    unit \
    unit-php81 \
    xxd \
    certbot \
    libqrencode \
    openssh \
    openssl \
    curl \
    git \
    py3-qt5 \
    && wget https://github.com/ameshkov/dnslookup/releases/download/v1.9.1/dnslookup-linux-amd64-v1.9.1.tar.gz \
    && tar -xf dnslookup-linux-amd64-v1.9.1.tar.gz \
    && mv linux-amd64/dnslookup /usr/bin \
    && rm dnslookup-linux-amd64-v1.9.1.tar.gz \
    && rm -rf /linux-amd64 \
    && wget https://github.com/SagerNet/sing-box/releases/download/v1.8.11/sing-box-1.8.11-linux-amd64.tar.gz \
    && tar -xf sing-box-1.8.11-linux-amd64.tar.gz \
    && mv sing-box-1.8.11-linux-amd64/sing-box /usr/bin \
    && rm sing-box-1.8.11-linux-amd64.tar.gz \
    && rm -rf /sing-box-1.8.11-linux-amd64
ENV ENV="/root/.ashrc"
