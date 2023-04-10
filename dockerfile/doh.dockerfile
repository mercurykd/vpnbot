from nginx:stable
run apt update && \
apt install -y git net-tools lsof ssh && \
mkdir /root/.ssh
