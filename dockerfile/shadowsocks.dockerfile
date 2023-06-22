from alpine:latest
run apk add openssh git xz \
    && mkdir /root/.ssh \
    && mkdir /ss \
    && wget https://github.com/shadowsocks/shadowsocks-rust/releases/download/v1.15.2/shadowsocks-v1.15.2.x86_64-unknown-linux-musl.tar.xz \
    && tar -xf shadowsocks-v1.15.2.x86_64-unknown-linux-musl.tar.xz \
    && wget https://github.com/teddysun/v2ray-plugin/releases/download/v5.2.0/v2ray-plugin-linux-amd64-v5.2.0.tar.gz \
    && tar -xf v2ray-plugin-linux-amd64-v5.2.0.tar.gz \
    && mv sslocal /ss/ \
    && mv ssserver /ss/ \
    && mv ssmanager /ss/ \
    && mv ssservice /ss/ \
    && mv ssurl /ss/ \
    && mv v2ray-plugin_linux_amd64 /ss/v2ray-plugin \
    && rm v2ray-plugin-linux-amd64-v5.2.0.tar.gz\
    && rm shadowsocks-v1.15.2.x86_64-unknown-linux-musl.tar.xz
env ENV="/root/.ashrc"
env PATH="$PATH:/ss"
