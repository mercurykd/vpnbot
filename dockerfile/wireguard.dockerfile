ARG image
FROM $image
COPY --from=golang:1.22.3-alpine /usr/local/go/ /usr/local/go/
ENV PATH="/usr/local/go/bin:${PATH}"
RUN apk add --no-cache --virtual .build-deps alpine-sdk git \
    && apk add iproute2 linux-headers iptables xtables-addons openssh wireguard-tools jq bash htop \
    && git clone https://github.com/amnezia-vpn/amneziawg-go \
    && git clone https://github.com/amnezia-vpn/amneziawg-tools.git \
    && cd /amneziawg-go \
    && make install \
    && cd /amneziawg-tools/src \
    && make install WITH_WGQUICK=yes \
    && apk del .build-deps \
    && rm -rf /amneziawg-go \
    && rm -rf /amneziawg-tools \
    && mkdir /root/.ssh
