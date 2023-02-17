arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && \
apt install -y ssh git net-tools xz-utils wget
run mkdir /root/.ssh && \
mkdir /ssh && \
touch /root/.ssh/authorized_keys && \
wget https://github.com/shadowsocks/shadowsocks-rust/releases/download/v1.15.2/shadowsocks-v1.15.2.x86_64-unknown-linux-gnu.tar.xz && \
tar -xf shadowsocks-v1.15.2.x86_64-unknown-linux-gnu.tar.xz && \
wget https://github.com/teddysun/v2ray-plugin/releases/download/v5.2.0/v2ray-plugin-linux-amd64-v5.2.0.tar.gz && \
tar -xf v2ray-plugin-linux-amd64-v5.2.0.tar.gz && \
mv v2ray-plugin_linux_amd64 /usr/local/bin/v2ray-plugin
