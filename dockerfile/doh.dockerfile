from nginx:stable
run apt update && \
apt install -y git net-tools lsof ssh && \
apt clean autoclean && \
apt autoremove -y && \
mkdir /root/.ssh
