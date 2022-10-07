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
            case preg_match('~^/rename (.+)$~', $this->input['callback'], $m):
                $this->rename($m[1]);
                break;
            case !empty($this->input['reply']):
                $this->reply();
                break;
        }
    }

    public function rename(int $client)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} введите название:",
            $this->input['message_id'],
            reply: 'введите название:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'renameClient',
            'args'           => [$client],
        ];
    }

    public function renameClient(string $name, int $client)
    {
        $clients = $this->readClients();
        $clients[$client]['interface']['## name'] = $name;
        $this->saveClients($clients);
        $server = $this->readConfig();
        foreach ($server['peers'] as $k => $v) {
            if ($v['AllowedIPs'] == $clients[$client]['interface']['Address']) {
                $server['peers'][$k]['## name'] = $name;
            }
        }
        $this->restartWG($this->createConfig($server));
    }

    public function readClients():array
    {
        return json_decode(file_get_contents($this->clients), true) ?: [];
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
            "@{$this->input['username']} send the export file:",
            $this->input['message_id'],
            reply: 'send the export file:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'importFile',
            'args'           => [],
        ];
    }

    public function importFile()
    {
        $r    = $this->request('getFile', ['file_id' => $this->input['file_id']]);
        $json = json_decode(file_get_contents($this->file . $r['result']['file_path']), true);
        if (empty($json) || !is_array($json)) {
            $this->answer($this->input['callback_id'], 'error', true);
        } else {
            $this->saveClients($json['clients']);
            $this->restartWG($this->createConfig($json['server']));
        }
    }

    public function downloadPeer($client)
    {
        $client = $this->readClients()[$client];
        $name   = $this->getName($client['interface']);
        $code   = $this->createConfig($client);
        $this->upload(preg_replace(['~\s+~', '~\(|\)~'], ['_', ''], $name) . ".conf", $code);
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
            "@{$this->input['username']} list the domains (each domain on a new line, you can part of the domain):",
            $this->input['message_id'],
            reply: 'list the domains (each domain on a new line, you can part of the domain):',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'pac',
            'args'           => [],
        ];
    }

    public function proxy()
    {
        $proxy = trim($this->ssh("getent hosts proxy | awk '{ print $1 }'"));
        $this->createPeer("$proxy/32", 'proxy');
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
            $this->{$callback}($this->input['message'], ...$_SESSION['reply'][$this->input['reply']]['args']);
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
                case 'renameClient':
                    $this->delete($this->input['chat'], $this->input['message_id']);
                    $this->input['message_id']  = $this->input['callback_id'] = $_SESSION['reply'][$this->input['reply']]['start_message'];
                    $this->menu();
                    $this->answer($_SESSION['reply'][$this->input['reply']]['start_message']);
                    break;
            }
            unset($_SESSION['reply'][$this->input['reply']]);
        }
    }

    public function addips()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} list subnets separated by commas",
            $this->input['message_id'],
            reply: 'list subnets separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'createPeer',
            'args'          => ['подсеть'],
        ];
    }

    public function showaddclient()
    {
        [$text, $data] = $this->menu(return: true);
        $data = [
            [
                [
                    'text'          => "all traffic",
                    'callback_data' => "/add",
                ],
            ],
            [
                [
                    'text'          => "subnet",
                    'callback_data' => "/add_ips",
                ],
            ],
            [
                [
                    'text'          => "proxy",
                    'callback_data' => "/proxy",
                ],
            ],
            [
                [
                    'text'          => "back",
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
                    'text'          => "confirm",
                    'callback_data' => "/reset",
                ],
                [
                    'text'          => "back",
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
        $this->createPeer(name: 'all traffic');
        $this->menu();
    }

    public function config()
    {
        $conf = $this->createConfig($this->readConfig());
        $data = [
            [
                [
                    'text'          => "back",
                    'callback_data' => "/menu",
                ],
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            "Server config:\n\n<code>$conf</code>",
            $data,
        );
    }

    public function deletePeer($client)
    {
        $conf = $this->readConfig();
        $this->deleteClient($client);
        unset($conf['peers'][$client]);
        $this->restartWG($this->createConfig($conf));
        $this->menu();
    }

    public function menu($client = false, $return = false)
    {
        $clients = $this->readClients();
        if ($client !== false) {
            $name = $this->getName($clients[$client]['interface']);
            $text = "<code>{$this->createConfig($clients[$client])}</code>\n\n<b>$name</b>";
            $data = [
                [
                    [
                        'text'          => "rename",
                        'callback_data' => "/rename $client",
                    ],
                ],
                [
                    [
                        'text'          => "download",
                        'callback_data' => "/download $client",
                    ],
                ],
                [
                    [
                        'text'          => "delete",
                        'callback_data' => "/delete $client",
                    ],
                ],
                [
                    [
                        'text'          => "back",
                        'callback_data' => "/menu",
                    ],
                ],
            ];
        } else {
            $data[] = [
                [
                    'text'          => "update status",
                    'callback_data' => "/menu",
                ],
            ];
            if (!empty($clients)) {
                foreach ($clients as $k => $v) {
                    $data[] = [[
                        'text'          => $this->getName($v['interface']),
                        'callback_data' => "/client $k",
                    ]];
                }
            }
            $data[] = [
                [
                    'text'          => "add peer",
                    'callback_data' => "/showadd",
                ],
                [
                    'text'          => "PAC script",
                    'callback_data' => "/showpac",
                ],
            ];
            $data[] = [
                [
                    'text'          => "export",
                    'callback_data' => "/export",
                ],
                [
                    'text'          => "import",
                    'callback_data' => "/import",
                ],
                [
                    'text'          => "reset",
                    'callback_data' => "/showreset",
                ],
            ];
            $conf   = $this->readConfig();
            $status = $this->readStatus();
            $text[] = 'Server:';
            $text[] = "  address: {$conf['interface']['Address']}";
            $text[] = "  port: {$status['interface']['listening port']}";
            $text[] = "  publickey: {$status['interface']['public key']}";
            $text[] = "\nPeers:";
            foreach ($conf['peers'] as $k => $v) {
                foreach ($clients as $cl) {
                    if ($cl['interface']['Address'] == $v['AllowedIPs']) {
                        $allowed_ips = $cl['peers'][0]['AllowedIPs'];
                    }
                }
                $peer   = $this->getStatusPeer($v['PublicKey'], $status['peers']);
                $text[] = "  {$this->getName($v)}: " . (preg_match('~^(\d+ seconds|[12] minute)~', $peer['latest handshake']) ? 'ONLINE' : 'OFFLINE') . ($peer['transfer'] ? "  {$peer['transfer']}": '');
                $text[] = "    address: {$peer['allowed ips']}";
                $text[] = "    allowed ips: $allowed_ips";
                $text[] = "    publickey: {$peer['peer']}";
                if ($peer['latest handshake']) {
                    $text[] = "    endpoint: {$peer['endpoint']}";
                    $text[] = "    handshake: {$peer['latest handshake']}";
                }
                $text[] = '';
            }
            $text = '<code>' . implode(PHP_EOL, $text) . '</code>';
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

    public function getStatusPeer(string $publickey, array $peers)
    {
        foreach ($peers as $k => $v) {
            if ($v['peer'] == $publickey) {
                return $v;
            }
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

    public function readStatus()
    {
        $r = $this->ssh('wg');
        $r = explode(PHP_EOL, $r);
        $r = array_filter($r);
        $i = 0;
        foreach ($r as $k => $v) {
            if (preg_match('~^(interface|peer):~', $v, $m)) {
                $i++;
                if ($m[1] == 'interface') {
                    $data[$i]['type'] = 'interface';
                } else {
                    $data[$i]['type'] = 'peer';
                }
            }
            $t = explode(':', $v, 2);
            $data[$i][trim($t[0])] = trim($t[1]);
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

    public function getName(array $a):string
    {
        $name = '';
        foreach ($a as $k => $v) {
            if (preg_match('~^#.*name$~', $k)) {
                $name = $v;
            }
        }
        $name = $name ?: $a['AllowedIPs'] ?: $a['Address'];
        return $name;
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

    public function createPeer($ips_user = false, $name = false)
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
            '## name'    => $client_ip . ($name ? " ($name)" : ''),
            'PublicKey'  => $public_peer_key,
            'AllowedIPs' => "$client_ip/32",
        ];
        $client_conf = [
            'interface' => [
                '## name'    => $client_ip . ($name ? " ($name)" : ''),
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

    public function deleteClient(int $client)
    {
        $clients = $this->readClients();
        unset($clients[$client]);
        $this->saveClients($clients);
    }

    public function saveClient(array $client)
    {
        $this->saveClients(array_merge($this->readClients(), [$client]));
    }

    public function saveClients(array $clients)
    {
        file_put_contents($this->clients, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
            $this->send($this->input['chat'], 'no connection to wg', $this->input['message_id']);
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
