warp-svc > /dev/null &
sleep 3
warp-cli --accept-tos registration new
warp-cli --accept-tos mode proxy
warp-cli --accept-tos connect
socat TCP-LISTEN:4000,fork TCP:127.0.0.1:40000
