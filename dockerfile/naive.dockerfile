ARG image
FROM $image
RUN apk add openssh \
    && mkdir /root/.ssh \
    && wget https://github.com/klzgrad/forwardproxy/releases/download/v2.7.6-naive/caddy-forwardproxy-naive.tar.xz -O naive.tar.xz \
    && tar -xf naive.tar.xz \
    && mv caddy-forwardproxy-naive/caddy /usr/local/bin \
    && rm naive.tar.xz \
    && rm -rf caddy-forwardproxy-naive
