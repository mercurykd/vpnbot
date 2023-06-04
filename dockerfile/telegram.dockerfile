arg SYSTEM
arg RELEASE
from ${SYSTEM}:${RELEASE}
ENV DEBIAN_FRONTEND noninteractive
run apt update && \
apt install -y git curl build-essential libssl-dev zlib1g-dev xxd ssh && \
apt clean autoclean && \
apt autoremove -y && \
mkdir /root/.ssh
run git clone https://github.com/TelegramMessenger/MTProxy
copy config/Makefile /MTProxy/Makefile
run cd /MTProxy && make
env PATH="$PATH:/MTProxy/objs/bin"

