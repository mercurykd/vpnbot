# telemt (MTProxy на Rust) — релизный статический бинарник вместо сборки MTProxy из исходников.
# Один процесс закрывает classic (plain), secure (dd) и FakeTLS (ee) + нативный WEB-листенер
# для web-mtproto, поэтому tproxy-server и второй инстанс прокси больше не нужны.
ARG TELEMT_VERSION=3.5.17

FROM ubuntu:22.04 AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

ARG TELEMT_VERSION
ARG TARGETARCH

RUN set -eux; \
    case "${TARGETARCH:-amd64}" in \
        amd64) asset="telemt-x86_64-linux-musl" ;; \
        arm64) asset="telemt-aarch64-linux-musl" ;; \
        *) echo "unsupported arch: ${TARGETARCH}" && exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/telemt.tar.gz \
        "https://github.com/telemt/telemt/releases/download/${TELEMT_VERSION}/${asset}.tar.gz"; \
    tar -xzf /tmp/telemt.tar.gz -C /tmp; \
    install -m 0755 /tmp/telemt /usr/local/bin/telemt

# Runtime stage - minimal Ubuntu
FROM ubuntu:22.04

RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl3 \
        zlib1g \
        curl \
        ca-certificates \
        vim-common \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /usr/local/bin/telemt /usr/local/bin/telemt

ENV PATH="/usr/local/bin:$PATH"
