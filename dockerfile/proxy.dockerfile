from ubuntu:18.04
run apt update && \
apt install -y build-essential gcc make wget && \
wget https://www.inet.no/dante/files/dante-1.4.3.tar.gz && \
tar -xf dante-1.4.3.tar.gz && \
cd dante-1.4.3 && \
./configure --prefix=/usr --sysconfdir=/etc --localstatedir=/var --disable-client --without-libwrap --without-bsdauth --without-gssapi --without-krb5 --without-upnp --without-pam && \
make && \
make install
expose 1080
cmd ["sockd"]
