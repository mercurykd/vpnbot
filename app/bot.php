<?php

class Bot
{
    public $input;

    public function __construct($key)
    {
        $this->key     = $key;
        $this->api     = "https://api.telegram.org/bot$key/";
        $this->file    = "https://api.telegram.org/file/bot$key/";
        $this->clients = __DIR__ . '/clients.json';
    }

    public function input()
    {
        $this->input_raw = $input = json_decode(file_get_contents('php://input'), true);
        $this->input     = [
            'message'           => $input['callback_query']['message']['text'] ?? $input['message']['text'] ?? $input['channel_post']['text'] ?? '',
            'message_id'        => $input['callback_query']['message']['message_id'] ?? $input['message']['message_id'] ?? $input['channel_post']['message_id'],
            'chat'              => $input['message']['chat']['id'] ?? $input['callback_query']['message']['chat']['id'] ?? $input['channel_post']['chat']['id'] ?? $input['my_chat_member']['chat']['id'],
            'from'              => $input['message']['from']['id'] ?? $input['inline_query']['from']['id'] ?? $input['callback_query']['from']['id'] ?? $input['channel_post']['chat']['id'] ?? $input['my_chat_member']['from']['id'],
            'username'          => $input['message']['from']['username'] ?? $input['inline_query']['from']['username'] ?? $input['callback_query']['from']['username'],
            'query'             => $input['inline_query']['query'] ?? '',
            'inlid'             => $input['inline_query']['id'] ?? '',
            'group'             => 'group' == $input['message']['chat']['type'],
            'sticker_id'        => $input['message']['sticker']['file_id'] ?? false,
            'channel'           => !empty($input['channel_post']['message_id']),
            'callback'          => $input['callback_query']['data'] ?? false,
            'callback_id'       => $input['callback_query']['id'] ?? false,
            'photo'             => $input['message']['photo'] ?? false,
            'file_name'         => $input['message']['document']['file_name'] ?? false,
            'file_id'           => $input['message']['document']['file_id'] ?? false,
            'caption'           => $input['message']['caption'] ?? false,
            'reply'             => $input['message']['reply_to_message']['message_id'] ?? false,
            'reply_from'        => $input['message']['reply_to_message']['from']['id'] ??  $input['callback_query']['message']['reply_to_message']['from']['id'] ?? false,
            'reply_text'        => $input['message']['reply_to_message']['text'] ?? false,
            'new_member_id'     => $input['my_chat_member']['new_chat_member']['user']['id'] ?? false,
            'new_member_status' => $input['my_chat_member']['new_chat_member']['status'] ?? false,
        ];
        $this->session();
        $this->action();
        $this->callbackCheck();
    }

    public function callbackCheck()
    {
        if (empty($this->callback) && !empty($this->input['callback_id'])) {
            $this->answer($this->input['callback_id']);
        }
    }

    public function session()
    {
        session_id($this->input['from']);
        session_start();
        if (!empty($_SESSION['reply'])) {
            if (empty($this->input['reply'])) {
                foreach ($_SESSION['reply'] as $k => $v) {
                    $this->delete($this->input['chat'], $k);
                }
                unset($_SESSION['reply']);
            }
        }
    }

    public function sd($var, $log = false, $json = false)
    {
        if ($log) {
            if ($json) {
                file_put_contents(__DIR__ . '/logs/input', json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } else {
                file_put_contents(__DIR__ . '/logs/input', var_export($var, true));
            }
        }
        $this->send($this->input['chat'], var_export($var, true), $this->input['message_id']);
    }

    public function action()
    {
        switch (true) {
            // смена айпи сервера
            case preg_match('~^/menu$~', $this->input['message'], $m):
            case preg_match('~^/menu$~', $this->input['callback'], $m):
            case preg_match('~^/client (\d+)$~', $this->input['callback'], $m):
                $this->menu($m[1] ?? false);
                break;
            case preg_match('~^/download (\d+)$~', $this->input['callback'], $m):
                $this->downloadPeer($m[1]);
                break;
            case preg_match('~^/delete (\d+)$~', $this->input['callback'], $m):
                $this->deletePeer($m[1]);
                break;
            case preg_match('~^/add$~', $this->input['callback'], $m):
                $this->addPeer(); // добавление клиента "весь траффик"
                break;
            case preg_match('~^/showadd$~', $this->input['callback'], $m):
                $this->showaddclient(); // меню добавления клиента
                break;
            case preg_match('~^/add_ips$~', $this->input['callback'], $m):
                $this->addips(); // ответ с предложением ввести список подсетей
                break;
            case preg_match('~^/showreset$~', $this->input['callback'], $m):
                $this->showreset();
                break;
            case preg_match('~^/reset$~', $this->input['callback'], $m):
                $this->reset();
                break;
            case preg_match('~^/proxy$~', $this->input['callback'], $m):
                $this->proxy();
                break;
            case preg_match('~^/showpac$~', $this->input['callback'], $m):
                $this->showpac();
                break;
            case preg_match('~^/export$~', $this->input['callback'], $m):
                $this->export();
                break;
            case preg_match('~^/import$~', $this->input['callback'], $m):
                $this->import();
                break;
            case !empty($this->input['reply']):
                $this->reply();
                break;
        }
    }

    public function export()
    {
        $conf    = $this->readConfig();
        $export  = [
            'server'  => $this->readConfig(),
            'clients' => json_decode(file_get_contents($this->clients), true) ?: [],
        ];
        $this->upload(date('d_m_Y_H_i') . '.json', json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function import()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} перешлите файл экспорта:",
            $this->input['message_id'],
            reply: 'перешлите файл экспорта:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'importFile',
        ];
    }

    public function importFile()
    {
        $r    = $this->request('getFile', ['file_id' => $this->input['file_id']]);
        $json = json_decode(file_get_contents($this->file . $r['result']['file_path']), true);
        if (empty($json) || !is_array($json)) {
            $this->answer($this->input['callback_id'], 'ошибка', true);
        } else {
            file_put_contents($this->clients, json_encode($json['clients'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->restartWG($this->createConfig($json['server']));
        }
    }

    public function downloadPeer($client)
    {
        $conf    = $this->readConfig();
        $peer    = $conf['peers'][$client];
        $clients = json_decode(file_get_contents($this->clients), true);
        foreach ($clients as $k => $v) {
            if ($v['interface']['Address'] == $peer['AllowedIPs']) {
                $client_conf = $v;
                break;
            }
        }
        $name = explode('/', $peer['AllowedIPs'])[0];
        $code = $this->createConfig($client_conf);
        $this->upload("$name.conf", $code);
    }

    public function upload($name, $code)
    {
        $path = __DIR__ . "/logs/$name";
        file_put_contents($path, $code);
        $this->sendFile(
            $this->input['chat'],
            curl_file_create($path),
        );
        unlink($path);
    }

    public function pac($domains)
    {
        $domains = implode(' || ', array_map(fn($el) => 'shExpMatch(url, "*' . trim($el) . '*")', explode(PHP_EOL, $domains)));
        $proxy   = trim($this->ssh("getent hosts proxy | awk '{ print $1 }'"));
        $pac     = <<<PAC
        function FindProxyForURL(url, host)
        {
          if ($domains) {
            return "SOCKS $proxy:1080";
          } else {
            return "DIRECT";
          }
        }
        PAC;
        $this->send(
            $this->input['chat'],
            "<code>$pac</code>",
            $this->input['message_id'],
        );
    }

    public function showpac()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} перечислите домены (каждый домен на новой строке, можно часть домена):",
            $this->input['message_id'],
            reply: 'перечислите домены (каждый домен на новой строке, можно часть домена):',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'pac',
        ];
    }

    public function proxy()
    {
        $proxy = trim($this->ssh("getent hosts proxy | awk '{ print $1 }'"));
        $this->createPeer("$proxy/32");
        $this->menu();
    }

    public function change_server_ip($ip)
    {
        $conf = $this->readConfig();
        $conf['interface']['Address'] = $ip;
        $this->restartWG($this->createConfig($conf));
    }

    public function reply()
    {
        if (!empty($_SESSION['reply'][$this->input['reply']])) {
            $this->delete($this->input['chat'], $this->input['reply']);
            $callback = $_SESSION['reply'][$this->input['reply']]['callback'];
            $this->{$callback}($this->input['message']);
            switch ($callback) {
                case 'createPeer':
                case 'importFile':
                    $this->delete($this->input['chat'], $this->input['message_id']);
                    $this->input['message_id']  = $this->input['callback_id'] = $_SESSION['reply'][$this->input['reply']]['start_message'];
                    $this->menu();
                    $this->answer($_SESSION['reply'][$this->input['reply']]['start_message']);
                    break;
                case 'pac':
                    $this->answer($_SESSION['reply'][$this->input['reply']]['start_callback']);
                    break;
            }
            unset($_SESSION['reply'][$this->input['reply']]);
        }
    }

    public function addips()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} перечислите через запятую подсети",
            $this->input['message_id'],
            reply: 'перечислите через запятую подсети',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'createPeer',
        ];
    }

    public function showaddclient()
    {
        [$text, $data] = $this->menu(return: true);
        $data = [
            [
                [
                    'text'          => "весь трафик",
                    'callback_data' => "/add",
                ],
            ],
            [
                [
                    'text'          => "подсеть",
                    'callback_data' => "/add_ips",
                ],
            ],
            [
                [
                    'text'          => "прокси",
                    'callback_data' => "/proxy",
                ],
            ],
            [
                [
                    'text'          => "назад",
                    'callback_data' => "/menu",
                ],
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data,
        );
    }

    public function showreset()
    {
        $data = [
            [
                [
                    'text'          => "подтвердить",
                    'callback_data' => "/reset",
                ],
                [
                    'text'          => "назад",
                    'callback_data' => "/menu",
                ],
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            "Сбросить?",
            $data,
        );
    }

    public function reset()
    {
        $conf = $this->readConfig();
        $r    = $this->ssh("/bin/sh /reset_wg.sh {$_SERVER['ADDRESS']} {$_SERVER['PORT_WG']}");
        file_put_contents($this->clients, '');
        $this->menu();
    }

    public function addPeer()
    {
        $this->createPeer();
        $this->menu();
    }

    public function config()
    {
        $conf = $this->createConfig($this->readConfig());
        $data = [
            [
                [
                    'text'          => "назад",
                    'callback_data' => "/menu",
                ],
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            "Конфиг сервера:\n\n<code>$conf</code>",
            $data,
        );
    }

    public function deletePeer($client)
    {
        $conf = $this->readConfig();
        $this->deleteClient($conf['peers'][$client]);
        unset($conf['peers'][$client]);
        $this->restartWG($this->createConfig($conf));
        $this->menu();
    }

    public function menu($client = false, $return = false)
    {
        $status = $this->ssh('wg');
        $conf   = $this->readConfig();
        $text   = "<b>Статус</b>\n\nСеть: {$conf['interface']['Address']}:{$conf['interface']['ListenPort']}\n\n<code>$status</code>\n$text";
        if ($client !== false) {
            $clients = json_decode(file_get_contents($this->clients), true);
            foreach ($clients as $k => $v) {
                if ($v['interface']['Address'] == $conf['peers'][$client]['AllowedIPs']) {
                    $client_conf = $v;
                    break;
                }
            }
            $client_ip       = explode('/', $conf['peers'][$client]['AllowedIPs'])[0];
            $client_conf_str = $this->createConfig($client_conf);
            $text = "<b>$client_ip</b>\n\n<code>$client_conf_str</code>";
            $data = [
                [
                    [
                        'text'          => "скачать {$conf['peers'][$client]['AllowedIPs']}",
                        'callback_data' => "/download $client",
                    ],
                ],
                [
                    [
                        'text'          => "удалить {$conf['peers'][$client]['AllowedIPs']}",
                        'callback_data' => "/delete $client",
                    ],
                ],
                [
                    [
                        'text'          => "назад",
                        'callback_data' => "/menu",
                    ],
                ],
            ];
        } else {
            if (!empty($conf['peers'])) {
                foreach ($conf['peers'] as $k => $v) {
                    $peers[] = [[
                        'text'          => "{$v['AllowedIPs']}",
                        'callback_data' => "/client $k",
                    ]];
                }
            }
            $data = [
                [
                    [
                        'text'          => "добавить клиента",
                        'callback_data' => "/showadd",
                    ],
                    [
                        'text'          => "PAC скрипт",
                        'callback_data' => "/showpac",
                    ],
                ],
                [
                    [
                        'text'          => "экспорт",
                        'callback_data' => "/export",
                    ],
                    [
                        'text'          => "импорт",
                        'callback_data' => "/import",
                    ],
                    [
                        'text'          => "сброс",
                        'callback_data' => "/showreset",
                    ],
                ],
            ];
            if ($peers) {
                $data = array_merge($peers, $data);
            }
            array_unshift($data, [
                [
                    'text'          => "обновить статус",
                    'callback_data' => "/menu",
                ],
            ]);
        }

        if ($return) {
            return [$text, $data];
        }

        if (!empty($this->input['callback_id'])) {
            $this->update(
                $this->input['chat'],
                $this->input['message_id'],
                $text,
                $data,
            );
        } else {
            $this->send(
                $this->input['chat'],
                $text,
                $this->input['message_id'],
                $data,
            );
        }
    }

    public function readConfig()
    {
        $r = $this->ssh('cat /etc/wireguard/wg0.conf');
        $r = explode(PHP_EOL, $r);
        $r = array_filter($r);
        $i = 0;
        foreach ($r as $k => $v) {
            if (preg_match('~\[(.+)\]~', $v, $m)) {
                $i++;
                if ($m[1] == 'Interface') {
                    $data[$i]['type'] = 'interface';
                } else {
                    $data[$i]['type'] = 'peer';
                }
            } else {
                $t = explode('=', $v, 2);
                $data[$i][trim($t[0])] = trim($t[1]);
            }
        }
        foreach ($data as $v) {
            $type = $v['type'];
            unset($v['type']);
            if ($type == 'interface') {
                $d['interface'] = $v;
            } else {
                $d['peers'][] = $v;
            }
        }
        return $d;
    }

    public function createConfig($data)
    {
        $conf[] = "[Interface]";
        foreach ($data['interface'] as $k => $v) {
            $conf[] = "$k = $v";
        }
        if (!empty($data['peers'])) {
            foreach ($data['peers'] as $peer) {
                $conf[] = '';
                $conf[] = '[Peer]';
                foreach ($peer as $k => $v) {
                    $conf[] = "$k = $v";
                }
            }
        }
        return implode(PHP_EOL, $conf);
    }

    public function createPeer($ips_user = false)
    {
        $conf      = $this->readConfig();
        $ipnet     = explode('/', $conf['interface']['Address']);
        $server_ip = ip2long($ipnet[0]);
        $ips       = [$server_ip];
        $bitmask   = $ipnet[1];
        if (!empty($conf['peers'])) {
            foreach ($conf['peers'] as $k => $v) {
                $ips[] = ip2long(explode('/', $v['AllowedIPs'])[0]);
            }
        }
        $ip_count = (1 << (32 - $bitmask)) - count($ips) - 1;
        for ($i=1; $i < $ip_count; $i++) {
            $ip = $i + $server_ip;
            if (!in_array($ip, $ips)) {
                $client_ip = long2ip($ip);
                break;
            }
        }
        $public_server_key = trim($this->ssh("echo {$conf['interface']['PrivateKey']} | wg pubkey"));
        $private_peer_key  = trim($this->ssh("wg genkey"));
        $public_peer_key   = trim($this->ssh("echo $private_peer_key | wg pubkey"));

        $conf['peers'][] = [
            'PublicKey'  => $public_peer_key,
            'AllowedIPs' => "$client_ip/32",
        ];
        $client_conf = [
            'interface' => [
                'PrivateKey' => $private_peer_key,
                'Address'    => "$client_ip/32",
                'MTU'        => 1350,
            ],
            'peers' => [
                [
                    'PublicKey'           => $public_server_key,
                    'Endpoint'            => "{$_SERVER['HTTP_HOST']}:{$_SERVER['PORT_WG']}",
                    'AllowedIPs'          => $ips_user ?: "0.0.0.0/0",
                    'PersistentKeepalive' => 20,
                ]
            ]
        ];
        $this->saveClient($client_conf);
        $this->restartWG($this->createConfig($conf));
    }

    public function deleteClient($conf)
    {
        $clients = json_decode(file_get_contents($this->clients), true);
        foreach ($clients as $k => $v) {
            if ($v['interface']['Address'] == $conf['AllowedIPs']) {
                unset($clients[$k]);
            }
        }
        file_put_contents($this->clients, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function saveClient($client)
    {
        if (file_exists($this->clients)) {
            $conf = json_decode(file_get_contents($this->clients), true) ?: [];
        } else {
            $conf = [];
        }
        $conf = array_merge($conf, [$client]);
        file_put_contents($this->clients, json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function restartWG($conf_str)
    {
        $this->ssh("wg-quick down wg0");
        $this->ssh("echo '$conf_str' > /etc/wireguard/wg0.conf");
        $this->ssh("wg-quick up wg0");
        return true;
    }

    public function ssh($cmd)
    {
        try {
            $c = ssh2_connect('wg', 22);
            ssh2_auth_pubkey_file($c, 'root', '/ssh/key.pub', '/ssh/key');
            $s = ssh2_exec($c, $cmd);
            stream_set_blocking($s, true);
            $data = "";
            while ($buf = fread($s, 4096)) {
                $data .= $buf;
            }
            fclose($s);
            ssh2_disconnect($c);
        } catch (Exception | Error $e) {
            $this->send($this->input['chat'], 'нет подключения к wg', $this->input['message_id']);
            die();
        }
        return $data;
    }

    public function request($method, $data, $json_header = 0)
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->api . $method,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $json_header ? [
                'Content-Type: application/json'
            ]: [],
            CURLOPT_POSTFIELDS     => $data,
        ]);
        $res = curl_exec($ch);
        return json_decode($res, true);
    }

    public function setwebhook()
    {
        $ip = file_get_contents('https://ipinfo.io/ip');
        if (empty($ip)) {
            die('нет айпи');
        }
        echo "$ip\n";
        var_dump($this->request('setWebhook', [
            'url'         => "https://$ip/{$this->key}",
            'certificate' => curl_file_create('/cert/nginx_public.pem'),
        ]));
    }

    public function setcommands()
    {
        $data = [
            'commands' => [
                [
                    'command'     => 'menu',
                    'description' => '...',
                ],
            ]
        ];
        var_dump($this->request('setMyCommands', json_encode($data), 1));
    }

    public function send($chat, $text, ?int $to = 0, $button = false, $reply = false)
    {
        if ($button) {
            $extra = ['inline_keyboard' => $button];
        }
        if (false !== $reply) {
            $extra = [
                'force_reply'             => true,
                'input_field_placeholder' => $reply,
                'selective'               => true,
            ];
        }
        $length = 3096;
        if (mb_strlen($text, 'utf-8') > $length) {
            $tails = $this->splitText($text, $length);
            foreach ($tails as $k => $v) {
                $data = [
                    'chat_id'                  => $chat,
                    'text'                     => "$v\n",
                    'parse_mode'               => 'HTML',
                    // 'disable_web_page_preview' => true,
                    // 'disable_notification'     => !empty($to) && 0 == $k,
                    'reply_to_message_id'      => 0 == $k && $to > 0 ? $to : false,
                ];
                if ($k == array_key_last($tails)) {
                    if ($extra) {
                        $data['reply_markup'] = json_encode($extra);
                    }
                }
                $r = $this->request('sendMessage', $data);
            }
        } else {
            $data = [
                'chat_id'                  => $chat,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                // 'disable_web_page_preview' => true,
                // 'disable_notification'     => !empty($to),
                'reply_to_message_id'      => $to,
            ];
            if (!empty($extra)) {
                $data['reply_markup'] = json_encode($extra);
            }
            $r = $this->request('sendMessage', $data);
        }
        return $r;
    }

    public function splitText($text, $size = 4096)
    {
        $tails = preg_split('~\n~', $text);
        if (!empty($tails)) {
            foreach ($tails as $v) {
                $lines[] = [
                    'length' => mb_strlen($v, 'utf-8'),
                    'text'   => $v,
                ];
            }
            $i = 0;
            foreach ($lines as $v) {
                $i += $v['length'];
                $output[ceil($i / $size)] .= $v['text'] . "\n";
            }
            return array_values($output);
        } else {
            return [$text];
        }
    }

    public function image($chat, $id_url_cFile, $caption = false, $to = false)
    {
        return $this->request('sendPhoto', [
            'chat_id'             => $chat,
            'photo'               => $id_url_cFile,
            'caption'             => $caption,
            'reply_to_message_id' => $to,
        ]);
    }

    public function sendFile($chat, $id_url_cFile, $caption = false, $to = false)
    {
        return $this->request('sendDocument', [
            'chat_id'             => $chat,
            'document'            => $id_url_cFile,
            'caption'             => $caption,
            'reply_to_message_id' => $to,
            'parse_mode'          => 'html',
        ]);
    }

    public function update($chat, $message_id, $text, $button = false, $reply = false)
    {
        if ($button) {
            $extra = ['inline_keyboard' => $button];
        }
        if ($reply !== false) {
            $extra = [
                'force_reply'             => true,
                'input_field_placeholder' => $reply
            ];
        }
        $data = [
            'chat_id'                  => $chat,
            'message_id'               => $message_id,
            'text'                     => $text,
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if (!empty($extra)) {
            $data['reply_markup'] = json_encode($extra);
        }
        return $this->request('editMessageText', $data);
    }

    public function answer($callback_id, $textNotify = false, $notify = false)
    {
        return $this->callback = $this->request('answerCallbackQuery', [
            'callback_query_id' => $callback_id,
            'show_alert'        => $notify,
            'text'              => $textNotify,
        ]);
    }

    public function delete($chat, $message_id)
    {
        $data = [
            'chat_id'    => $chat,
            'message_id' => $message_id,
        ];
        return $this->request('deleteMessage', $data);
    }
}
