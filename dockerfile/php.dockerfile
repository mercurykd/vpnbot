from php:8.1-fpm
run apt update && apt install -y libssh2-1-dev && pecl install https://pecl.php.net/get/ssh2-1.3.1.tgz
ARG IP
run mkdir /cert && \
openssl req -newkey rsa:2048 -sha256 -nodes \
-keyout /cert/nginx_private.key -x509 -days 365 -out /cert/nginx_public.pem \
-subj "/C=US/ST=New York/L=Brooklyn/O=Example Brooklyn Company/CN=$IP"
workdir /app
