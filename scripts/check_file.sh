if [[ -f "/start" ]] && curl -fsS --max-time 2 http://127.0.0.1:8080/status >/dev/null; then
    exit 0;
else
    exit 1;
fi
