from alpine:latest
run apk add --no-cache --update php82 \
    php82-mbstring \
    php82-session \
    php82-curl \
    php82-opcache \
    php82-openssl \
    php82-iconv \
    php82-intl \
    php82-pecl-ssh2 \
    php82-pecl-yaml \
    unit \
    unit-php82 \
    xxd \
    certbot \
    libqrencode \
    openssh \
    curl \
    && wget https://github.com/ameshkov/dnslookup/releases/download/v1.8.1/dnslookup-linux-amd64-v1.8.1.tar.gz \
    && tar -xf dnslookup-linux-amd64-v1.8.1.tar.gz \
    && rm dnslookup-linux-amd64-v1.8.1.tar.gz \
    && ln -s /usr/bin/php82 /usr/bin/php
env PATH="$PATH:/linux-amd64"
env ENV="/root/.ashrc"