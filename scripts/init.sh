TAG="${2:-master}"
apt update
apt install -y \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    make \
    git \
    iptables \
    iproute2 \
    xtables-addons-common \
    xtables-addons-dkms
curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh
git clone https://github.com/mercurykd/vpnbot.git
cd ./vpnbot
git checkout $TAG
echo "<?php

\$c = ['key' => '$1'];" > ./app/config.php
make u
