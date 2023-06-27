b:
	docker compose build
u: # запуск контейнеров
	IP=$(shell ip -4 addr | sed -ne 's|^.* inet \([^/]*\)/.* scope global.*$$|\1|p' | awk '{print $1}' | head -1) docker compose up -d --build --force-recreate
d: # остановка контейнеров
	docker compose down
dv: # остановка контейнеров
	docker compose down -v
r: d u
ps: # список контейнеров
	docker compose ps
l: # логи из контейнеров
	docker compose logs
php: # консоль сервиса
	docker compose exec php /bin/sh
wg: # консоль сервиса
	docker compose exec wg /bin/sh
ss: # консоль сервиса
	docker compose exec ss /bin/sh
ng: # консоль сервиса
	docker compose exec ng /bin/sh
ad: # консоль сервиса
	docker compose exec ad /bin/sh
proxy: # консоль сервиса
	docker compose exec proxy /bin/sh
tg: # консоль сервиса
	docker compose exec tg /bin/sh
xr: # консоль сервиса
	docker compose exec xr /bin/sh
clean:
	docker image prune
	docker builder prune
cleanall:
	docker image prune -a
	docker builder prune -a
