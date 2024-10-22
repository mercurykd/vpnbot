<?php

require __DIR__ . '/timezone.php';

require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';

if ($c['debug']) {
    require __DIR__ . '/debug.php';
}

$bot = new Bot($c['key'], $i);

$bot->restartTG();
$bot->dontshowcron = 1;
$bot->sslip();
$bot->cleanDocker();
