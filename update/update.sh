#!/bin/bash
pwd=`pwd`
> $pwd/update/pipe
echo "$$" > $pwd/update/update_pid

while true
do
    cmd=$(cat $pwd/update/pipe)
    if [[ -n "$cmd" ]]
    then
        docker compose down --remove-orphans
        git reset --hard
        git pull > ./update/message
        IP=$(curl https://ipinfo.io/ip) VER=$(git describe --tags) docker compose up -d --force-recreate
        bash $pwd/update/update.sh &
        exit 0
    fi
    sleep 1
done