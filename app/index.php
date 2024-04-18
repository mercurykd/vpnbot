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
    require __DIR__ . '/bot.php';
    require __DIR__ . '/i18n.php';
    $bot = new Bot($c['key'], $i);
    switch ($type) {
        case 'mirror':
            $bot->getMirror();
            break;
        case 's':
        case 'si':
            $bot->subscription();
            exit;

        case 'te':
            if (!empty($_GET['te'])) {
                $t = $bot->getPacConf()["{$_GET['ty']}templates"][$_GET['te']];
            } else {
                $t = json_decode(file_get_contents("/config/{$_GET['ty']}.json"), true);
            }
            if ($t) {
                header('Content-Type: application/json');
                echo json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }

        default:
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
            break;
    }
}
if (!empty($_GET['hash'])) {
    $t = $_GET;
    unset($t['hash']);
    ksort($t);
    foreach ($t as $k => $v) {
        $s[] = "$k=$v";
    }
    $s  = implode("\n", $s);
    $sk = hash_hmac('sha256', $c['key'], "WebAppData", true);
    if (hash_hmac('sha256', $s, $sk) == $_GET['hash']) {
        require __DIR__ . '/bot.php';
        require __DIR__ . '/i18n.php';
        $bot = new Bot($c['key'], $i);
        setcookie('c', substr(hash('sha256', $c['key']), 0, 8), 0, '/');
        setcookie('a', $bot->adguardBasicAuth(), 0, '/');
        die('ok');
    }
}

header('500', true, 500);
exit;
