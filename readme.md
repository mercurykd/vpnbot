telegram bot to manage servers (inside the bot)

- VLESS (Reality OR Websocket)
- NaiveProxy
- OpenConnect
- Wireguard
- Amnezia
- AdguardHome
- MTProto
- PAC
- automatic ssl

---
environment: ubuntu 22.04/24.04, debian 11/12

## Install:

```shell
wget -O- https://raw.githubusercontent.com/mercurykd/vpnbot/master/scripts/init.sh | sh -s YOUR_TELEGRAM_BOT_KEY master
```
#### Restart:
```shell
make r
```
#### autoload:
```shell
crontab -e
```
add `@reboot cd /root/vpnbot && make r` and save
