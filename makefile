-include .env
export

IP := $(or $(IP),$(shell hostname -I | awk '{print $$1}'))

b:
	docker compose build
preup:
	bash ./update/update.sh &
start: # запуск контейнеров
	touch ./override.env ./docker-compose.override.yml ./config/location.conf ./config/override.conf
	IP=$(IP) VER=$(shell git describe --tags) docker compose --env-file ./.env --env-file ./override.env up -d --force-recreate
u: preup start
d: # остановка контейнеров
	-kill -9 $(shell cat ./update/update_pid) > /dev/null
	docker compose down --remove-orphans
dv: # остановка контейнеров
	docker compose down -v
r: d u
ps: # список контейнеров
	docker compose ps -a
l: # логи из контейнеров
	docker compose logs
php: # консоль сервиса
	docker compose exec php /bin/sh
wg: # консоль сервиса
	docker compose exec wg /bin/sh
wg1: # консоль сервиса
	docker compose exec wg1 /bin/sh
ss: # консоль сервиса
	docker compose exec ss /bin/sh
ng: # консоль сервиса
	docker compose exec ng /bin/sh
np: # консоль сервиса
	docker compose exec np /bin/sh
up: # консоль сервиса
	docker compose exec up /bin/sh
ad: # консоль сервиса
	docker compose exec ad /bin/sh
wp: # консоль сервиса
	docker compose exec wp /bin/sh
proxy: # консоль сервиса
	docker compose exec proxy /bin/sh
tg: # консоль сервиса
	docker compose exec tg /bin/bash
dnstt: # консоль сервиса
	docker compose exec dnstt /bin/sh
hy: # консоль сервиса
	docker compose exec hy /bin/sh
xr: # консоль сервиса
	docker compose exec xr /bin/sh
oc: # консоль сервиса
	docker compose exec oc /bin/sh
service: # консоль сервиса
	docker compose exec service /bin/sh
delete:
	make d
	docker system prune -f -a
	docker volume prune -f -a
	rm -rf /root/vpnbot
push:
	docker compose push
s:
	git status -su
c:
	git add config/
	git checkout .
	git reset
webhook:
	docker compose exec php php checkwebhook.php
reset:
	make d
	git reset --hard
	git clean -fd
	docker volume rm vpnbot_adguard vpnbot_warp
	make u
backup: # сохранение настроек в backup.json
	docker compose exec php php backup.php > backup.json
restore: # восстановление из backup.json (make restore FILE=path/to/backup.json)
	@test -f "$(or $(FILE),backup.json)" || (echo "file not found: $(or $(FILE),backup.json)" && exit 1)
	docker compose exec -T php php restore.php < "$(or $(FILE),backup.json)"
restore-check: # проверка файла бэкапа без восстановления
	@test -f "$(or $(FILE),backup.json)" || (echo "file not found: $(or $(FILE),backup.json)" && exit 1)
	docker compose exec -T php php restore.php --check < "$(or $(FILE),backup.json)"
mtproto:
	docker compose exec php php mtproto.php
cron: # установка задачи в cron для автозапуска при перезагрузке
	@(crontab -l 2>/dev/null | grep -v "cd /root/vpnbot && make r"; echo "@reboot cd /root/vpnbot && make r") | crontab -
uncron: # удаление задачи из cron
	@crontab -l 2>/dev/null | grep -v "cd /root/vpnbot && make r" | crontab -