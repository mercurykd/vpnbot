ARG image
FROM $image
RUN apk add --update \
    openssh \
    iptables \
    curl \
    g++ \
    gnutls-dev \
    gpgme \
    libev-dev \
    libnl3-dev \
    libseccomp-dev \
    linux-headers \
    linux-pam-dev \
    lz4-dev \
    make \
    readline-dev \
    tar \
    xz \
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
    && mkdir /root/.ssh
