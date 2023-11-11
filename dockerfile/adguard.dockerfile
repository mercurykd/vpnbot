FROM adguard/adguardhome
RUN apk add --no-cache --update openssh \
    && mkdir /root/.ssh
ENV ENV="/root/.ashrc"
