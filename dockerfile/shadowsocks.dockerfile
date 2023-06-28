from alpine:3.18.2
run apk add openssh git xz \
    && mkdir /root/.ssh \
    && wget https://github.com/shadowsocks/shadowsocks-rust/releases/download/v1.15.3/shadowsocks-v1.15.3.x86_64-unknown-linux-musl.tar.xz \
    && tar -xf shadowsocks-v1.15.3.x86_64-unknown-linux-musl.tar.xz \
    && wget https://github.com/teddysun/v2ray-plugin/releases/download/v5.7.0/v2ray-plugin-linux-amd64-v5.7.0.tar.gz \
    && tar -xf v2ray-plugin-linux-amd64-v5.7.0.tar.gz \
    && mv sslocal /usr/bin/ \
    && mv ssserver /usr/bin/ \
    && mv ssmanager /usr/bin/ \
    && mv ssservice /usr/bin/ \
    && mv ssurl /usr/bin/ \
    && mv v2ray-plugin_linux_amd64 /usr/bin/v2ray-plugin \
    && rm v2ray-plugin-linux-amd64-v5.7.0.tar.gz\
    && rm shadowsocks-v1.15.3.x86_64-unknown-linux-musl.tar.xz
env ENV="/root/.ashrc"
