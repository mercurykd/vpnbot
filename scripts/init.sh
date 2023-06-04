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
mkdir -p /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/$(lsb_release -is | tr '[:upper:]' '[:lower:]')/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$(lsb_release -is | tr '[:upper:]' '[:lower:]') \
  $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null
apt update
apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin
git clone https://github.com/mercurykd/vpnbot.git
cd ./vpnbot
echo "<?php

\$c = ['key' => '$1'];" > ./app/config.php
make u
