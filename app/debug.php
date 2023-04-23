<?php

// ini_set('display_errors', 'On');
// ini_set("log_errors", 1);
// ini_set("error_log", '/logs/php_error');

$debug = [
    'raw'  => file_get_contents('php://input'),
    'json' => json_decode(file_get_contents('php://input'), true),
    'post' => $_POST,
    'file' => $_FILES,
];
register_shutdown_function('exit_log', $debug);

function exit_log($debug)
{
    $output            = ob_get_contents();
    $debug['response'] = json_decode($output, true) ?: $output;
    file_put_contents('/logs/requests', "\n" . date('Y-m-d H:i:s') . "\n" . var_export($debug, true) . "\n", FILE_APPEND);
}
