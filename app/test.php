<?php

$c = ssh2_connect('wg', 22);
ssh2_auth_pubkey_file($c, 'root', '/ssh/key.pub', '/ssh/key');

function ssh($c, $cmd)
{
    $stream = ssh2_exec($c, $cmd);
    stream_set_blocking($stream, true);
    $data = "";
    while ($buf = fread($stream, 4096)) {
        $data .= $buf;
    }
    fclose($stream);
    return $data;
}

$data[] = ssh($c, 'wg');
// $data[] = ssh($c, 'wg-quick up wg0');
var_dump($data);
