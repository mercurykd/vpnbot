ARG image
FROM $image
RUN apk add nginx-mod-stream \
    && mkdir /var/cache/nginx
ENV ENV="/root/.ashrc"
