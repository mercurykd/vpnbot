from alpine:latest
run apk add nginx-mod-stream openssh \
    && mkdir /root/.ssh \
    && mkdir /var/cache/nginx
env ENV="/root/.ashrc"
