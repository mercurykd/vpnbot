FROM alpine:3.22
RUN apk add iodine openssh iptables\
    && mkdir /root/.ssh
ENV ENV="/root/.ashrc"
