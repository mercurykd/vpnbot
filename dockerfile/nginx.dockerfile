ARG image

# официальный WEB-релей Telegram Desktop (tproxy-server) для web-mtproto
FROM golang:1.23-alpine AS tproxy
RUN apk add --no-cache git \
    && git clone --depth 1 https://github.com/telegramdesktop/tproxy-server /src \
    && cd /src \
    && CGO_ENABLED=0 go build -trimpath -ldflags "-s -w" -o /tproxy-server ./cmd/tproxy-server

FROM $image
COPY --from=tproxy /tproxy-server /usr/local/bin/tproxy-server
RUN apk add nginx-mod-stream \
    && mkdir /var/cache/nginx
ENV ENV="/root/.ashrc"

