from ubuntu:18.04
run apt update && \
apt install -y wireguard \
iproute2 \
net-tools \
iptables \
linux-headers-$(uname -r) \
ssh
run mkdir /root/.ssh && \
mkdir /ssh && \
touch /root/.ssh/authorized_keys && \
ssh-keygen -t rsa -f /ssh/key -N '' && \
chmod 644 /ssh/key && \
wg genkey > /etc/wireguard/privatekey
copy ./scripts/start_wg.sh /start_wg.sh
copy ./scripts/reset_wg.sh /reset_wg.sh
cmd ["/bin/sh", "/start_wg.sh"]
