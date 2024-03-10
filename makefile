b:
	docker compose build
u: # запуск контейнеров
	bash ./update/update.sh &
	touch ./override.env
	IP=$(shell curl https://ipinfo.io/ip) VER=$(shell git describe --tags) docker compose --env-file ./.env --env-file ./override.env up -d --force-recreate
d: # остановка контейнеров
	-kill -9 $(shell cat ./update/update_pid) > /dev/null
	docker compose down --remove-orphans
dv: # остановка контейнеров
	docker compose down -v
r: d cleanf u cleanf
ps: # список контейнеров
	docker compose ps
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
	docker compose exec wp bash
proxy: # консоль сервиса
	docker compose exec proxy /bin/sh
tg: # консоль сервиса
	docker compose exec tg /bin/sh
xr: # консоль сервиса
	docker compose exec xr /bin/sh
oc: # консоль сервиса
	docker compose exec oc /bin/sh
clean:
	docker image prune
	docker builder prune
cleanf:
	docker image prune -f > /dev/null
	docker builder prune -f > /dev/null
cleanall:
	docker image prune -a -f
	docker builder prune -a -f
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