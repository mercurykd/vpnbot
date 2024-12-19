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
    if (file_exists(__DIR__ . '/override.php')) {
        include __DIR__ . '/override.php';
    }
    $bot = new Bot($c['key'], $i);
    $bot->input();
    exit;
}

// pac
if (!empty($t = unserialize(base64_decode(explode('/', $_SERVER['REQUEST_URI'])[2])))) { // fix sing-box import
    $_GET = array_merge($_GET, $t);
}
$type    = $_GET['t'] ?? 'pac';
$address = $_GET['a'] ?: '127.0.0.1';
$port    = $_GET['p'] ?: '1080';
$hash    = $_GET['h'];
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
        case 'cl':
            $bot->subscription();
            exit;

        case 'te':
            if (!empty($_GET['te'])) {
                $t = $bot->getPacConf()["{$_GET['ty']}templates"][$_GET['te']];
            } else {
                $t = json_decode(file_get_contents("/config/{$_GET['ty']}.json"), true);
            }
            if ($t) {
                header('Content-Type: text/html');
                $t = json_encode($t, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $name = $_GET['te'] ?: 'origin';
                $type = $_GET['ty'];
                echo <<<HTML
                    <!DOCTYPE HTML>
                    <html lang="en" style="height:100%">
                    <head>
                        <!-- when using the mode "code", it's important to specify charset utf-8 -->
                        <meta charset="utf-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">

                        <link href="jsoneditor.min.css" rel="stylesheet" type="text/css">
                        <script src="jsoneditor.min.js"></script>
                        <script src="jquery-3.7.1.min.js"></script>
                        <script src="https://telegram.org/js/telegram-web-app.js"></script>
                    </head>
                    <body style="height:100%">
                        <div id="jsoneditor" style="height:100%"></div>

                        <script>
                            jQuery(function($) {
                                var tg = window.Telegram.WebApp;
                                // create the editor
                                const container = document.getElementById("jsoneditor")
                                const options = {}
                                const editor = new JSONEditor(container, options)
                                editor.set({$t})
                                tg.MainButton.show().setText('{$bot->i18n('save')}').onClick(function (e) {
                                    var self = this;
                                    $.ajax({
                                        url: '/webapp/save?' + tg.initData,
                                        method: 'POST',
                                        data: {
                                            name: '$name',
                                            type: '$type',
                                            json: editor.getText()
                                        },
                                        dataType: 'json'
                                    }).done(function (r) {
                                        if (r.status == true) {
                                            tg.MainButton.setText('{$bot->i18n('success')}')
                                            setTimeout(() => {
                                                tg.close();
                                            }, 500);
                                        } else {
                                            tg.MainButton.setText(r.message);
                                        }
                                    }).fail(function (r) {
                                        tg.MainButton.setText('{$bot->i18n('error')}')
                                    });
                                });
                            });
                        </script>
                    </body>
                    </html>
                    HTML;
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
        if (!empty($_POST['json'])) {
            echo json_encode($bot->saveTemplate($_POST['name'], $_POST['type'], $_POST['json']));
            die();
        } else {
            setcookie('c', substr(hash('sha256', $c['key']), 0, 8), 0, '/');
            setcookie('a', $bot->adguardBasicAuth(), 0, '/');
        }
        die('ok');
    }
}

header('500', true, 500);
exit;
