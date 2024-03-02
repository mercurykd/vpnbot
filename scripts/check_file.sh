if [[ -f "/start" && -f "/ssh/key.pub" && -s "/ssh/key.pub" ]]; then
    exit 0;
else
    exit 1;
fi
