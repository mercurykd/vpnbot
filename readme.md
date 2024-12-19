telegram bot to manage servers (inside the bot)

<img src="https://github.com/user-attachments/assets/ec5faa51-987c-461a-a973-d94c7edf7115" width="300">

### VLESS (Reality OR Websocket)
- change secret
- qr/config
- change fake domain
- multiple users
- subscriptions with routing (xray, sing-box, mihomo)
- routing templates per user
- routing via rulesets (sing-box, mihomo)
- steal from yourself
- add domains to warp

### Main menu VLESS:
<img src="https://github.com/user-attachments/assets/80d2f671-fade-4819-a96a-efa253741ffd" width="300">

### User menu VLESS:
<img src="https://github.com/user-attachments/assets/5c3c5b5e-1931-4e98-a472-d303bac61a4e" width="300">

### NaiveProxy
- change login
- change password
<img src="https://github.com/mercurykd/vpnbot/assets/30900414/2127a4af-0436-452c-bfe2-c750dd5dbc06" width="300">

### OpenConnect
- change secret
- change password
- change dns
- add user
- add ip subnet
- expose-iroutes (lan between users)
<img src="https://github.com/user-attachments/assets/c3a8a78e-fe99-423c-a626-610b333a2a56" width="300">

### Wireguard / Amnezia
- create
- delete
- rename
- timer
- torrent blocking
- qr/config
- AmneziaVPN vpn:// link
- statistics
<img src="https://github.com/mercurykd/vpnbot/assets/30900414/51a79c93-8083-40ba-a14b-a6ef19f00531" width="300">
<img src="https://github.com/mercurykd/vpnbot/assets/30900414/fd6ffd9f-bd75-479c-8dca-ea6c0b938b6c" width="300">

### Shadowsocks + v2ray
- change password
- on/off v2ray
- qr
- short link
<img src="https://github.com/mercurykd/vpnbot/assets/30900414/fbe39617-63ae-4536-8ab0-e4269ed8784a" width="300">

### AdguardHome
- change password
- change upstream dns
- check dns
- check safesearch
- add custom clientID for DNSoverTLS (DOT)
- fill allowed clients (WG/AWG + OpenConnect + VLESS)
- custom ID for each user VLESS
<img src="https://github.com/user-attachments/assets/1dcdd9f9-f781-4ec2-8e32-01ce6095e0a3" width="300">

### PAC
- the ability to create your own PAC available by url with the ability to substitute the final ip and port
- Shadowsocks Android PAC
- add [antifilter-community](https://community.antifilter.download/) or [ru-bundle](https://github.com/legiz-ru/sb-rule-sets/blob/main/ru-bundle.lst) domain lists
<img src="https://github.com/user-attachments/assets/8d039dbb-6478-43a8-811a-4279e766c884" width="300">

### MTProto
- change secret
- qr/config
<img src="https://github.com/user-attachments/assets/d37b2e1c-f991-4e1c-8060-1b547eea8fe2" width="300">

### Settings
- add/change admin
- change language (en/ru)
- import/export all settings
- domain binding
- obtain ssl for domain
- fake html for domain
- ports block
<img src="https://github.com/user-attachments/assets/82b78014-eaa2-4b4c-bc90-77f58d03d57c" width="300">

---
environment: ubuntu 22.04/24.04, debian 11/12

### Install:

```shell
wget -O- https://raw.githubusercontent.com/mercurykd/vpnbot/master/scripts/init.sh | sh -s YOUR_TELEGRAM_BOT_KEY
```

### Install as service (autoload on start):

```shell
cd /root/vpnbot
bash scripts/install_as_service.sh
```
