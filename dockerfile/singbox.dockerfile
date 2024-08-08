ARG image
FROM $image
RUN apk add openssh openssl jq \
    && mkdir /root/.ssh \
    && wget https://github.com/SagerNet/sing-box/releases/download/v1.9.3/sing-box-1.9.3-linux-amd64.tar.gz \
    && tar -xf sing-box-1.9.3-linux-amd64.tar.gz \
    && mv sing-box-1.9.3-linux-amd64/sing-box /usr/bin \
    && rm sing-box-1.9.3-linux-amd64.tar.gz \
    && rm -rf /sing-box-1.9.3-linux-amd64
