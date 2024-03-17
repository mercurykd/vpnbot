<?php

require __DIR__ . '/timezone.php';

// require __DIR__ . '/debug.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';

$bot = new Bot($c['key'], $i);

if (!empty($c['admin'])) {
    $ip = getenv('IP');
    $rm = explode(':', trim(file_get_contents('/update/reload_message')));
    $m  = file_get_contents('/update/message');
    foreach ($c['admin'] as $k => $v) {
        $r = $bot->send($v, "start $ip");
        $bot->input['chat']        = $v;
        $bot->input['message_id']  = $r['result']['message_id'];
        if (file_exists($bot->update)) {
            if (!empty($m)) {
                $bot->send($v, "<pre>$m</pre>", $v == $rm[0] ? $rm[1] : 0);
            }
            $r = $bot->send($v, "import settings");
            $bot->input['chat']        = $v;
            $bot->input['message_id']  = $r['result']['message_id'];
            $bot->input['callback_id'] = $r['result']['message_id'];
            if (empty($flag)) {
                $bot->importFile($bot->update);
                unlink($bot->update);
                $flag = true;
            }
        }
    }
}
file_put_contents('/update/message', '');
file_put_contents('/update/reload_message', '');
$bot->restartTG();
$bot->sslip();
