# Build stage
FROM ubuntu:22.04 AS builder

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        build-essential \
        libssl-dev \
        zlib1g-dev \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /src
RUN git clone --single-branch --depth 1 https://github.com/GetPageSpeed/MTProxy . \
    && make -j$(nproc) \
    && strip objs/bin/mtproto-proxy

# Runtime stage - minimal Ubuntu
FROM ubuntu:22.04

RUN apt-get update && apt-get install -y --no-install-recommends \
        libssl3 \
        zlib1g \
        curl \
        ca-certificates \
        vim-common \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /src/objs/bin/mtproto-proxy /usr/local/bin/mtproto-proxy

ENV PATH="/usr/local/bin:$PATH"
