from nginx:stable
run apt update && \
apt install -y git net-tools lsof ssh && \
git clone https://github.com/TuxInvader/nginx-dns.git && \
cp -r ./nginx-dns/njs.d /etc/nginx/ && \
mkdir /root/.ssh
