<?php

require __DIR__ . '/timezone.php';

require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';

if ($c['debug']) {
    require __DIR__ . '/debug.php';
}

$bot = new Bot($c['key'], $i);

$bot->selfUpdate();
$bot->restartTG();
if (!empty($bot->selfupdate)) {
    $bot->offWarp();
}
$bot->dontshowcron = 1;
$bot->sslip();
$bot->adguardSync();
$bot->syncDeny();
$bot->cleanDocker();
