ARG image
FROM $image
COPY --from=golang:1.25.12-alpine /usr/local/go/ /usr/local/go/
ENV PATH="/usr/local/go/bin:${PATH}"
RUN apk add --no-cache --virtual .build-deps alpine-sdk git \
    && apk add iproute2 linux-headers iptables xtables-addons wireguard-tools jq bash htop \
    && git clone --depth 1 --branch v3.1.20260828 https://github.com/amnezia-vpn/amneziawg-go \
    && git clone --depth 1 --branch v3.1.20260812 https://github.com/amnezia-vpn/amneziawg-tools.git \
    && git clone https://github.com/rofl0r/microsocks \
    && cd /amneziawg-go \
    && make install \
    && cd /amneziawg-tools/src \
    && make install WITH_WGQUICK=yes \
    && cd /microsocks \
    && make && mv microsocks /usr/bin/microsocks \
    && apk del .build-deps \
    && rm -rf /amneziawg-go \
    && rm -rf /amneziawg-tools \
    && rm -rf /microsocks \
    && cd /tmp \
    && wget -O tun2socks.zip https://github.com/xjasonlyu/tun2socks/releases/download/v2.6.0/tun2socks-linux-amd64.zip \
    && unzip tun2socks.zip \
    && mv tun2socks-linux-amd64 /usr/bin/tun2socks \
    && chmod +x /usr/bin/tun2socks \
    && wget -O /usr/bin/wgcf https://github.com/ViRb3/wgcf/releases/download/v2.2.22/wgcf_2.2.22_linux_amd64 \
    && chmod +x /usr/bin/wgcf \
    && sed -i 's/sysctl -q net\.ipv4\.conf\.all\.src_valid_mark=1/echo "skip sysctl src_valid_mark"/' $(which wg-quick)
