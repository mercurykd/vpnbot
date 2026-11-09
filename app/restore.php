<?php

// восстановление настроек из резервной копии (backup.php) из командной строки
// usage: php restore.php [file] [--check]
//   file    - путь к json-бэкапу, если не указан или '-' — читается stdin
//   --check - только проверить файл и показать секции, без восстановления
//
// примеры:
//   php restore.php backup.json
//   docker compose exec -T php php restore.php < backup.json
//   make restore FILE=backup.json

$args  = array_slice($argv, 1);
$check = false;
$file  = '';
foreach ($args as $arg) {
    if ($arg == '--check' || $arg == '-n') {
        $check = true;
    } elseif ($arg == '--help' || $arg == '-h') {
        echo "usage: php restore.php [file] [--check]\n";
        exit(0);
    } elseif ($file === '') {
        $file = $arg;
    }
}

if ($file === '' || $file == '-') {
    $raw = stream_get_contents(STDIN);
} else {
    $raw = @file_get_contents($file);
}

if (empty($raw)) {
    fwrite(STDERR, 'restore: empty input' . ($file ? " ({$file})" : '') . "\n");
    exit(1);
}

$json = json_decode($raw, true);
if (empty($json) || !is_array($json)) {
    fwrite(STDERR, 'restore: invalid json' . (json_last_error() ? ': ' . json_last_error_msg() : '') . "\n");
    exit(1);
}

// секции, которые восстанавливает Bot::importFile()
$sections = [
    'ssl',
    'pac',
    'wg',
    'wg1',
    'ad',
    'ss',
    'sl',
    'mtproto',
    'mtprotodomain',
    'mtprotoadtag',
    'hwid',
    'xray',
    'xraystats',
    'oc',
    'ocu',
    'hy',
    'dnstt',
];
$found = array_values(array_intersect($sections, array_keys($json)));
if (empty($found)) {
    fwrite(STDERR, "restore: no known sections in backup\n");
    exit(1);
}

if ($check) {
    echo 'backup sections: ' . implode(', ', $found) . "\n";
    exit(0);
}

require __DIR__ . '/timezone.php';

require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';
// debug.php не подключаем: он читает php://input (stdin) и ломает восстановление из пайпа

// прогресс восстановления печатаем в stdout: в CLI нет чата telegram,
// поэтому update()/send() не ходят в api.telegram.org
class RestoreBot extends Bot
{
    public function update($chat, $message_id, $text, $button = false, $reply = false, $mode = 'HTML')
    {
        echo $text . "\n";
        return null;
    }

    public function send($chat, $text, ?int $to = 0, $button = false, $reply = false, $mode = 'HTML', $disable_notification = false)
    {
        if (!is_null($text)) {
            echo $text . "\n";
        }
        return null;
    }
}

$bot = new RestoreBot($c['key'], $i);
// часть методов (pacUpdate и т.п.) читает input напрямую — без него были бы warning'и
$bot->input = [
    'chat'        => 0,
    'message_id'  => 0,
    'callback_id' => 0,
    'username'    => '',
    'from'        => 0,
];

echo 'restore: ' . implode(', ', $found) . "\n";

$tmp = tempnam(sys_get_temp_dir(), 'vpnbot-restore');
if ($tmp === false) {
    fwrite(STDERR, "restore: can not create temp file\n");
    exit(1);
}
file_put_contents($tmp, $raw);

try {
    $bot->importFile($tmp);
} catch (Throwable $e) {
    @unlink($tmp);
    fwrite(STDERR, 'restore failed: ' . $e->getMessage() . "\n");
    exit(1);
}
@unlink($tmp);

echo "restore complete\n";
