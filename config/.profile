export LANG=ru_RU.utf8

alias ls='ls $LS_OPTIONS'
alias ll='ls -lah'


PS1='[\[\e[0;94m\]\u\[\e[0;37m\]@\[\e[0;31m\]\h\[\e[0;36m\] $PWD\[\e[0;37m\]]\$\[\e[0m\] '

export LS_OPTIONS='--color=auto'

case "$TERM" in
xterm*|rxvt*)
    PS1="\[\e]0;${debian_chroot:+($debian_chroot)}\u@\h: \w\a\]$PS1"
    ;;
*)
    ;;
esac
