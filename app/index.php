<?php

require __DIR__ . '/timezone.php';

// bot
require __DIR__ . '/config.php';
if ('POST' == $_SERVER['REQUEST_METHOD'] && $_GET['k'] == $c['key']) {
    if ($c['debug']) {
        require __DIR__ . '/debug.php';
    }
    require __DIR__ . '/calc.php';
    require __DIR__ . '/bot.php';
    require __DIR__ . '/i18n.php';
    $bot = new Bot($c['key'], $i);
    $bot->input();
    exit;
}

// pac
$type = $_GET['t'] ?? 'pac';
$address = $_GET['a'] ?: '127.0.0.1';
$port = $_GET['p'] ?: '1080';
$hash = $_GET['h'];
if ($hash == substr(md5($c['key']), 0, 8)) {
    if (file_exists($file = __DIR__ . "/zapretlists/$type")) {
        $pac = file_get_contents($file);
        header('Content-Type: text/plain');
        echo str_replace([
            '~address~',
            '~port~',
        ], [
            $address,
            $port,
        ], $pac);
        exit;
    }
}

header('500', true, 500);
exit;
