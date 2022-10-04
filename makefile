IP ?= 127.0.0.1
SERVICE ?= fpm
hosts: unhosts # маппинг доменов на локалку
	echo "$(IP) test.ru" >> /mnt/c/Windows/System32/drivers/etc/hosts
unhosts:
	sed -i '/test.ru/d' /mnt/c/Windows/System32/drivers/etc/hosts
u: # запуск контейнеров
	IP=$(shell curl https://ipinfo.io/ip) docker compose up -d --build --force-recreate
	sleep 1
	docker compose logs wg fpm proxy
d: # остановка контейнеров
	docker compose down -v
ps: # список контейнеров
	docker compose ps
l: # логи из контейнеров
	docker compose logs $(SERVICE)
nginx: # консоль сервиса
	docker compose exec nginx bash
fpm: # консоль сервиса
	docker compose exec fpm bash
proxy: # консоль сервиса
	docker compose exec proxy bash
wg: # консоль сервиса
	docker compose exec wg bash
r-fpm: # рестарт сервиса
	docker compose restart fpm
r-nginx: # рестарт сервиса
	docker compose restart nginx
r-wg: # рестарт сервиса
	docker compose restart wg
