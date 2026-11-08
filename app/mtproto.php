<?php

ini_set('session.use_cookies', 0);

require __DIR__ . '/timezone.php';

require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';
if ($c['debug']) {
    require __DIR__ . '/debug.php';
}

$bot = new Bot($c['key'], $i);

$s = file_get_contents('/config/mtprotosecret');
if (empty($s)) {
    file_put_contents('/config/mtprotosecret', exec('head -c 16 /dev/urandom | xxd -ps'));
    $bot->restartTG();
}
$d = trim(file_get_contents('/config/mtprotodomain'));
if (empty($d)) {
    file_put_contents('/config/mtprotodomain', $bot->getDomain());
    $bot->restartTG();
}
$p = $bot->getPorts()['tg']['enable'];
if (empty($p)) {
    $bot->setPort(4443, 'tg');
    echo "need restart: make r\n";
}
// Вывод ссылки на MTProto-прокси
echo $bot->linkMtproto();
if (!empty($bot->getPacConf()['domain'])) {
    echo "\n" . $bot->linkMtprotoWeb();
}
