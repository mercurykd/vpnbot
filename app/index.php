<?php

// require __DIR__ . '/debug.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';

$url = trim($_SERVER['REQUEST_URI'], '/');
if (!('POST' == $_SERVER['REQUEST_METHOD'] && $url == $key)) {
    header('500', true, 500);
    die();
}

$bot = new Bot($key);
$bot->input();
