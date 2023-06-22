from alpine:3.6
run apk add --no-cache --virtual .build-deps alpine-sdk linux-headers openssl-dev \
    && git clone --single-branch --depth 1 https://github.com/TelegramMessenger/MTProxy.git /mtproxy/sources \
    && mkdir /mtproxy/patches && wget -P /mtproxy/patches https://raw.githubusercontent.com/alexdoesh/mtproxy/master/patches/randr_compat.patch \
    && cd /mtproxy/sources && patch -p0 -i /mtproxy/patches/randr_compat.patch \
    && make \
    && mkdir /root/.ssh \
    && cp /mtproxy/sources/objs/bin/mtproto-proxy /usr/bin \
    && rm -rf /mtproxy \
    && apk del .build-deps\
    && apk add --no-cache --update curl openssh \
    && ln -s /usr/lib/libcrypto.so.41 /usr/lib/libcrypto.so.1.0.0
env ENV="/root/.ashrc"
