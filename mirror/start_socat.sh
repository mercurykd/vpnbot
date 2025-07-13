#!/bin/bash

# Параметры (можно менять)
PORTS=(80 443 853 ~tg~ ~ss~ ~wg1~ ~wg2~)          # Прослушиваемые порты
TARGET="~ip~"               # Куда перенаправляем трафик
TCP_CMD="socat TCP-LISTEN:{PORT},fork,reuseaddr TCP:$TARGET:{PORT}"
UDP_CMD="socat UDP-LISTEN:{PORT},fork,reuseaddr UDP:$TARGET:{PORT}"

# Проверяем, установлен ли socat
if ! command -v socat &> /dev/null; then
    echo "socat не установлен. Устанавливаем..."
    if [[ -f /etc/debian_version ]]; then
        sudo apt update && sudo apt install -y socat
    elif [[ -f /etc/redhat-release ]]; then
        sudo yum install -y socat
    else
        echo "Ошибка: Неизвестный дистрибутив. Установите socat вручную."
        exit 1
    fi
fi

# Проверяем, заняты ли порты (TCP и UDP)
for PORT in "${PORTS[@]}"; do
    # Проверка TCP
    if ss -tulnp | grep -q ":$PORT "; then
        PROCESS=$(ss -tulnp | grep ":$PORT " | awk '{print $7}')
        echo "Ошибка: Порт $PORT (TCP) занят процессом: $PROCESS"
        exit 1
    fi
    # Проверка UDP
    if ss -ulnp | grep -q ":$PORT "; then
        PROCESS=$(ss -ulnp | grep ":$PORT " | awk '{print $6}')
        echo "Ошибка: Порт $PORT (UDP) занят процессом: $PROCESS"
        exit 1
    fi
done

# Запускаем socat для каждого порта (TCP и UDP)
for PORT in "${PORTS[@]}"; do
    # TCP
    CMD_TCP=$(echo "$TCP_CMD" | sed "s/{PORT}/$PORT/g")
    echo "Запуск (TCP): $CMD_TCP"
    eval "$CMD_TCP &"  # Запускаем в фоне

    # UDP
    CMD_UDP=$(echo "$UDP_CMD" | sed "s/{PORT}/$PORT/g")
    echo "Запуск (UDP): $CMD_UDP"
    eval "$CMD_UDP &"  # Запускаем в фоне
done

echo "socat запущен для портов: ${PORTS[*]} (TCP и UDP)"
echo "Трафик перенаправляется на: $TARGET"
echo "Для остановки выполните: pkill socat"