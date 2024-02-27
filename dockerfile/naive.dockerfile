ARG image
FROM golang:alpine as go
RUN go install github.com/caddyserver/xcaddy/cmd/xcaddy@latest \
    && /go/bin/xcaddy build --with github.com/caddyserver/forwardproxy@caddy2=github.com/klzgrad/forwardproxy@naive
FROM $image
COPY --from=go /go/caddy /usr/local/bin/
RUN apk add openssh \
    && mkdir /root/.ssh