<?php

require './config.php';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => "https://api.telegram.org/bot{$c['key']}/getWebhookInfo",
    CURLOPT_RETURNTRANSFER => true,
]);
$res = curl_exec($ch);
die(var_dump($res));
