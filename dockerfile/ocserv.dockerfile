ARG image
FROM $image
RUN apk add --update openssh \
    iptables \
    gnutls-dev \
    libev-dev \
    linux-pam-dev \
    lz4-dev \
    libseccomp-dev \
    && apk add --no-cache --virtual .build-deps \
    xz \
    linux-headers \
    libnl3-dev \
    g++ \
    gpgme \
    curl \
    make \
    readline-dev \
    tar \
    autoconf \
    automake \
    gperf \
    protobuf-c-compiler \
    git \
    && git clone https://gitlab.com/openconnect/ocserv.git \
    && cd /ocserv \
    && autoreconf -fvi \
    && ./configure \
    && make \
    && make install \
    && apk del .build-deps \
    && mkdir /root/.ssh
