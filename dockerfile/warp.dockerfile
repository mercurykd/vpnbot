FROM ubuntu:22.04 AS build
RUN apt update && apt install -y curl gpg lsb-release \
    && curl -fsSL https://pkg.cloudflareclient.com/pubkey.gpg | gpg --yes --dearmor --output /usr/share/keyrings/cloudflare-warp-archive-keyring.gpg \
    && echo "deb [signed-by=/usr/share/keyrings/cloudflare-warp-archive-keyring.gpg] https://pkg.cloudflareclient.com/ $(lsb_release -cs) main" | tee /etc/apt/sources.list.d/cloudflare-client.list \
    && apt update && apt install -y cloudflare-warp

FROM alpine:3.17
ARG GLIBC_VERSION=2.34-r0
COPY --from=build /usr/bin/warp-cli /usr/bin/warp-svc /usr/local/bin/
RUN apk add --no-cache dbus-libs wget socat openssh-server jq curl \
    && mkdir /tmp/glibc-pkgs \
    && for PKG in glibc-$GLIBC_VERSION.apk glibc-bin-$GLIBC_VERSION.apk; do wget -q --directory-prefix /tmp/glibc-pkgs https://github.com/sgerrand/alpine-pkg-glibc/releases/download/$GLIBC_VERSION/$PKG; done \
    && apk add --no-cache --allow-untrusted --force-overwrite /tmp/glibc-pkgs/* \
    && rm -rf /tmp/glibc-pkgs \
    && /usr/glibc-compat/sbin/ldconfig /lib /usr/glibc-compat/lib \
    && mkdir /root/.ssh
