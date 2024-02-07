ARG image
FROM $image
RUN apk add iproute2 linux-headers iptables xtables-addons openssh wireguard-tools jq alpine-sdk git go bash htop \
    && mkdir /root/.ssh
RUN git clone https://github.com/amnezia-vpn/amneziawg-go \
    && git clone https://github.com/amnezia-vpn/amneziawg-tools.git
RUN cd amneziawg-go \
    && make install
RUN cd amneziawg-tools/src \
    && make install WITH_WGQUICK=yes
ENV ENV="/root/.ashrc"
