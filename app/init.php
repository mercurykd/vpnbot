<?php

// require __DIR__ . '/debug.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';

$bot = new Bot($c['key']);
$bot->setwebhook($c['key']);
$bot->setcommands();
