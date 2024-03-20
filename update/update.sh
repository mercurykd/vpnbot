#!/bin/bash
pwd=`pwd`
> $pwd/update/pipe
echo "$$" > $pwd/update/update_pid

while true
do
    cmd=$(cat $pwd/update/pipe)
    branch=$(cat $pwd/update/branch 2>/dev/null)
    if [[ -n "$cmd" ]]
    then
        docker compose down --remove-orphans
        git reset --hard && git clean -fd
        git fetch
        if [[ -n "$branch" ]]
        then
            git checkout -t origin/$branch || git checkout $branch
        fi
        git pull > ./update/message
        IP=$(curl https://ipinfo.io/ip) VER=$(git describe --tags) docker compose up -d --force-recreate
        bash $pwd/update/update.sh &
        exit 0
    fi
    sleep 1
done