<?php

// require __DIR__ . '/debug.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';

$bot = new Bot($key);
$bot->setwebhook($key);
$bot->setcommands();
