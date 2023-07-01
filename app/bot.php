<?php

class Bot
{
    public $input;

    public function __construct($key, $i18n)
    {
        $this->key      = $key;
        $this->api      = "https://api.telegram.org/bot$key/";
        $this->file     = "https://api.telegram.org/file/bot$key/";
        $this->clients  = '/config/clients.json';
        $this->pac      = '/config/pac.json';
        $this->ip       = getenv('IP');
        $this->i18n     = $i18n;
        $this->language = $this->getPacConf()['language'] ?: 'en';
        $this->dns      = '1.1.1.1, 8.8.8.8';
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
        $this->auth();
        $this->session();
        $this->action();
        $this->callbackCheck();
    }

    public function auth()
    {
        if (preg_match('~^/id$~', $this->input['message'])) {
            return;
        }
        $file = __DIR__ . '/config.php';
        require $file;
        if (empty($c['admin'])) {
            $c['admin'] = [$this->input['from']];
            file_put_contents($file, "<?php\n\n\$c = " . var_export($c, true) . ";\n");
        } elseif (!is_array($c['admin'])) {
            $c['admin'] = [$c['admin']];
            file_put_contents($file, "<?php\n\n\$c = " . var_export($c, true) . ";\n");
        } elseif (!in_array($this->input['from'], $c['admin'])) {
            // $this->send($this->input['chat'], 'you are not authorized', $this->input['message_id']);
            exit;
        }
    }

    public function callbackCheck()
    {
        if (empty($this->callback) && !empty($this->input['callback_id'])) {
            $this->answer($this->input['callback_id'], $GLOBALS['debug'] ? $this->input['callback'] : false);
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

    public function sd($var, $log = false, $json = false, $raw = false)
    {
        if ($log) {
            if ($json) {
                file_put_contents('/logs/debug', json_encode($var, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            } elseif ($raw) {
                file_put_contents('/logs/debug', $var);
            } else {
                file_put_contents('/logs/debug', var_export($var, true));
            }
        } else {
            $this->send($this->input['chat'], var_export($var, true), $this->input['message_id']);
        }
    }

    public function action()
    {
        switch (true) {
            // смена айпи сервера
            case preg_match('~^/menu$~', $this->input['message'], $m):
            case preg_match('~^/start$~', $this->input['message'], $m):
            case preg_match('~^/menu$~', $this->input['callback'], $m):
            case preg_match('~^/menu (?P<type>addpeer) (?P<arg>(?:-)?\d+)$~', $this->input['callback'], $m):
            case preg_match('~^/menu (?P<type>wg) (?P<arg>(?:-)?\d+)$~', $this->input['callback'], $m):
            case preg_match('~^/menu (?P<type>client) (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
            case preg_match('~^/menu (?P<type>pac|adguard|config|ss|lang)$~', $this->input['callback'], $m):
            case preg_match('~^/menu (?P<type>subzoneslist|reverselist|includelist|excludelist) (?P<arg>(?:-)?\d+)$~', $this->input['callback'], $m):
                $this->menu(type: $m['type'] ?? false, arg: $m['arg'] ?? false);
                break;
            case preg_match('~^/id$~', $this->input['message'], $m):
                $this->send($this->input['chat'], $this->input['from'], $this->input['message_id']);
                break;
            case preg_match('~^/mtproto$~', $this->input['callback'], $m):
                $this->mtproto();
                break;
            case preg_match('~^/logs$~', $this->input['callback'], $m):
                $this->logs();
                break;
            case preg_match('~^/getLog (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->getLog(...explode('_', $m['arg']));
                break;
            case preg_match('~^/clearLog (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->clearLog(...explode('_', $m['arg']));
                break;
            case preg_match('~^/debug$~', $this->input['callback'], $m):
                $this->debug();
                break;
            case preg_match('~^/backup$~', $this->input['callback'], $m):
                $this->backup();
                break;
            case preg_match('~^/generateSecret$~', $this->input['callback'], $m):
                $this->generateSecret();
                break;
            case preg_match('~^/setSecret$~', $this->input['callback'], $m):
                $this->setSecret();
                break;
            case preg_match('~^/defaultDNS (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->defaultDNS(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnet (?P<arg>-?\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->subnet(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnetAdd (?P<arg>-?\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->subnetAdd(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnetDelete (?P<arg>-?\d+(?:_-?\d+)?(?:_-?\d+)?)$~', $this->input['callback'], $m):
                $this->subnetDelete(...explode('_', $m['arg']));
                break;
            case preg_match('~^/addSubnets (?P<arg>-?\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->addSubnets(...explode('_', $m['arg']));
                break;
            case preg_match('~^/changeAllowedIps (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->changeAllowedIps(...explode('_', $m['arg']));
                break;
            case preg_match('~^/calc$~', $this->input['callback'], $m):
                $this->calc();
                break;
            case preg_match('~^/changeIps (?P<arg>\w+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->changeIps(...explode('_', $m['arg']));
                break;
            case preg_match('~^/selfssl$~', $this->input['callback'], $m):
                $this->selfssl();
                break;
            case preg_match('~^/sspswd$~', $this->input['callback'], $m):
                $this->sspswd();
                break;
            case preg_match('~^/v2ray$~', $this->input['callback'], $m):
                $this->v2ray();
                break;
            case preg_match('~^/checkdns$~', $this->input['callback'], $m):
                $this->checkdns();
                break;
            case preg_match('~^/resetnginx$~', $this->input['callback'], $m):
                $this->resetnginx();
                break;
            case preg_match('~^/adguardpsswd$~', $this->input['callback'], $m):
                $this->adguardpsswd();
                break;
            case preg_match('~^/addadmin$~', $this->input['callback'], $m):
                $this->enterAdmin();
                break;
            case preg_match('~^/geodb$~', $this->input['callback'], $m):
                $this->geodb();
                break;
            case preg_match('~^/adguardreset$~', $this->input['callback'], $m):
                $this->adguardreset();
                break;
            case preg_match('~^/addupstream$~', $this->input['callback'], $m):
                $this->addupstream();
                break;
            case preg_match('~^/checkurl$~', $this->input['callback'], $m):
                $this->checkurl();
                break;
            case preg_match('~^/setSSL (\w+)$~', $this->input['callback'], $m):
                $this->setSSL($m[1]);
                break;
            case preg_match('~^/lang (\w+)$~', $this->input['callback'], $m):
                $this->setLang($m[1]);
                break;
            case preg_match('~^/deletessl$~', $this->input['callback'], $m):
                $this->deleteSSL();
                break;
            case preg_match('~^/download (\d+)$~', $this->input['callback'], $m):
                $this->downloadPeer($m[1]);
                break;
            case preg_match('~^/switchTorrent (\d+)$~', $this->input['callback'], $m):
                $this->switchTorrent($m[1]);
                break;
            case preg_match('~^/blinkmenuswitch$~', $this->input['callback'], $m):
                $this->blinkmenuswitch();
                break;
            case preg_match('~^/switchClient (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->switchClient(...explode('_', $m['arg']));
                $this->menu('client', $m['arg']);
                break;
            case preg_match('~^/deladmin (\d+)$~', $this->input['callback'], $m):
                $this->delAdmin($m[1]);
                break;
            case preg_match('~^/qr (\d+)$~', $this->input['callback'], $m):
                $this->qrPeer($m[1]);
                break;
            case preg_match('~^/qrSS$~', $this->input['callback'], $m):
                $this->qrSS();
                break;
            case preg_match('~^/qrXray$~', $this->input['callback'], $m):
                $this->qrXray();
                break;
            case preg_match('~^/qrMtproto$~', $this->input['callback'], $m):
                $this->qrMtproto();
                break;
            case preg_match('~^/delupstream (\d+)$~', $this->input['callback'], $m):
                $this->delupstream($m[1]);
                break;
            case preg_match('~^/delete (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->deletePeer(...explode('_', $m['arg']));
                break;
            case preg_match('~^/dns (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->dnsPeer(...explode('_', $m['arg']));
                break;
            case preg_match('~^/deletedns (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->deletednsPeer(...explode('_', $m['arg']));
                break;
            case preg_match('~^/deldomain$~', $this->input['callback'], $m):
                $this->delDomain();
                break;
            case preg_match('~^/(?P<action>change|delete)(?P<typelist>subzoneslist|reverselist|includelist|excludelist) (?P<arg>[^\s]+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->listPacChange($m['typelist'], $m['action'], ...explode('_', $m['arg']));
                break;
            case preg_match('~^/paczapret$~', $this->input['callback'], $m):
                $this->pacZapret();
                break;
            case preg_match('~^/pacupdate$~', $this->input['callback'], $m):
                $this->pacUpdate();
                break;
            case preg_match('~^/add$~', $this->input['callback'], $m):
                $this->addPeer(); // добавление клиента "весь траффик"
                break;
            case preg_match('~^/add_ips$~', $this->input['callback'], $m):
                $this->addips(); // ответ с предложением ввести список подсетей
                break;
            case preg_match('~^/domain$~', $this->input['callback'], $m):
                $this->domain();
                break;
            case preg_match('~^/xray$~', $this->input['callback'], $m):
                $this->xray();
                break;
            case preg_match('~^/generateSecretXray$~', $this->input['callback'], $m):
                $this->generateSecretXray();
                break;
            case preg_match('~^/changeFakeDomain$~', $this->input['callback'], $m):
                $this->changeFakeDomain();
                break;
            case preg_match('~^/include (\d+)$~', $this->input['callback'], $m):
                $this->include($m[1]);
                break;
            case preg_match('~^/exclude (\d+)$~', $this->input['callback'], $m):
                $this->exclude($m[1]);
                break;
            case preg_match('~^/reverse (\d+)$~', $this->input['callback'], $m):
                $this->reverse($m[1]);
                break;
            case preg_match('~^/subzones (\d+)$~', $this->input['callback'], $m):
                $this->subzones($m[1]);
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
                $this->exportManual();
                break;
            case preg_match('~^/import$~', $this->input['callback'], $m):
                $this->import();
                break;
            case preg_match('~^/rename (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->rename(...explode('_', $m['arg']));
                break;
            case preg_match('~^/timer (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->timer(...explode('_', $m['arg']));
                break;
            case !empty($this->input['reply']):
                $this->reply();
                break;
        }
    }

    public function generateSecret()
    {
        $this->secretSet(exec('head -c 16 /dev/urandom | xxd -ps'));
    }

    public function setSecret()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter url",
            $this->input['message_id'],
            reply: 'enter url',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'secretSet',
            'args'           => [],
        ];
    }

    public function secretSet($secret)
    {
        $this->restartTG($secret);
        $this->mtproto();
    }

    public function restartTG($secret)
    {
        file_put_contents('/config/mtprotosecret', $secret ?: '');
        $this->ssh('pkill mtproto-proxy', 'tg');
        if (preg_match('~^\w{32}$~', $secret)) {
            $p = getenv('TGPORT');
            $this->ssh("mtproto-proxy -u nobody -H $p --nat-info 10.10.0.8:{$this->ip} -S $secret --aes-pwd /proxy-secret /proxy-multi.conf -M 1 >/dev/null 2>&1 &", 'tg');
        }
    }

    public function restartXray($c)
    {
        $this->ssh('pkill xray', 'xr');
        file_put_contents('/config/xray.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->ssh('xray run -config /xray.json > /dev/null 2>&1 &', 'xr');
    }

    public function linkMtproto()
    {
        $s  = file_get_contents('/config/mtprotosecret');
        $p  = getenv('TGPORT');
        $ip = $this->getPacConf()['domain'] ?: $this->ip;
        return "https://t.me/proxy?server=$ip&port=$p&secret=$s";
    }

    public function mtproto()
    {
        $s      = file_get_contents('/config/mtprotosecret');
        $p      = getenv('TGPORT');
        $ip     = $this->getPacConf()['domain'] ?: $this->ip;
        $st     = $this->ssh('pgrep mtproto-proxy', 'tg') ? 'on' : 'off';
        $text[] = "Menu -> MTProto\n";
        $text[] = "status: $st\n";
        if ($st == 'on') {
            $text[] = $this->linkMtproto();
        }
        $data[] = [
            [
                'text'          => $this->i18n('generateSecret'),
                'callback_data' => "/generateSecret",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('setSecret'),
                'callback_data' => "/setSecret",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('show QR'),
                'callback_data' => "/qrMtproto",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function setLang($lang)
    {
        $conf = $this->getPacConf();
        $this->language = $conf['language'] = $lang;
        $this->setPacConf($conf);
        $this->menu('config');
    }

    public function checkurl()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter url",
            $this->input['message_id'],
            reply: 'enter url',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'urlcheck',
            'args'           => [],
        ];
    }

    public function urlcheck($url)
    {
        if (file_exists(__DIR__ . '/zapretlists/mpac')) {
            $domains = explode("\n", file_get_contents(__DIR__ . '/zapretlists/mpac'));
            foreach ($domains as $k => $v) {
                if (preg_match("~$v~", $url)) {
                    $flag = 1;
                    break;
                }
            }
            if ($flag) {
                $text = "$url\nmatch";
            } else {
                $text = "$url\nnot match";
            }
        } else {
            $text = 'no file, update pac';
        }
        $this->update($this->input['chat'], $this->input['message_id'], $text);
        sleep(3);
        $this->menu('pac');
    }

    public function sspswd()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter password",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'sspwdch',
            'args'           => [],
        ];
    }

    public function sspwdch($pass)
    {
        $this->ssh('pkill sslocal', 'proxy');
        $this->ssh('pkill ssserver', 'ss');
        $c = $this->getSSConfig();
        $l = $this->getSSLocalConfig();
        $c['password'] = $l['password'] = $pass;
        file_put_contents('/config/ssserver.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents('/config/sslocal.json', json_encode($l, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->ssh('ssserver -v -d -c /config.json', 'ss');
        $this->ssh('sslocal -v -d -c /config.json', 'proxy');
        $this->menu('ss');
    }

    public function v2ray()
    {
        $this->ssh('pkill sslocal', 'proxy');
        $this->ssh('pkill ssserver', 'ss');
        $ssl = $this->nginxGetTypeCert();
        $c = $this->getSSConfig();
        $l = $this->getSSLocalConfig();
        $domain = $this->getPacConf()['domain'] ?: $this->ip;
        if ($c['plugin']) {
            unset($c['plugin']);
            unset($c['plugin_opts']);
            unset($l['plugin']);
            unset($l['plugin_opts']);
            $l['server']      = 'ss';
            $l['server_port'] = (int) getenv('SSPORT');
            $c['server_port'] = (int) getenv('SSPORT');
        } else {
            $c['plugin']      = 'v2ray-plugin';
            $c['plugin_opts'] = 'server;loglevel=none';
            $l['server']      = 'ng';
            $l['server_port'] = $ssl ? 443 : 80;
            $l['plugin']      = 'v2ray-plugin';
            $l['plugin_opts'] = ($ssl ? 'tls;' : '') . "fast-open;path=/v2ray;host=$domain";
        }
        file_put_contents('/config/ssserver.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents('/config/sslocal.json', json_encode($l, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->ssh('ssserver -v -d -c /config.json', 'ss');
        $this->ssh('sslocal -v -d -c /config.json', 'proxy');
        $this->menu('ss');
    }

    public function rename(int $client, $page)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter the title:",
            $this->input['message_id'],
            reply: 'enter the title:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'renameClient',
            'args'           => [$client, $page],
        ];
    }

    public function timer(int $client, $page)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter time like https://www.php.net/manual/ru/function.strtotime.php:",
            $this->input['message_id'],
            reply: 'enter time like https://www.php.net/manual/ru/function.strtotime.php:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'timerClient',
            'args'           => [$client, $page],
        ];
    }

    public function timerClient(string $time, int $client)
    {
        $clients = $this->readClients();
        if ($clients[$client]['# off']) {
            $this->switchClient($client);
            $clients = $this->readClients();
        }
        $server = $this->readConfig();
        switch (true) {
            case preg_match('~^0$~', $time):
                unset($clients[$client]['interface']['## time']);
                foreach ($server['peers'] as $k => $v) {
                    if ($v['AllowedIPs'] == $clients[$client]['interface']['Address']) {
                        unset($server['peers'][$k]['## time']);
                    }
                }
                break;
            default:
                $date = date('Y-m-d H:i:s', strtotime($time));
                $clients[$client]['interface']['## time'] = $date;
                foreach ($server['peers'] as $k => $v) {
                    if ($v['AllowedIPs'] == $clients[$client]['interface']['Address']) {
                        $server['peers'][$k]['## time'] = $date;
                    }
                }
                break;
        }
        $this->saveClients($clients);
        $this->restartWG($this->createConfig($server));
        $this->menu('client', implode('_', $_SESSION['reply'][$this->input['reply']]['args']));
    }

    public function cron()
    {
        while (true) {
            $this->shutdownClient();
            $this->checkVersion();
            $this->checkBackup();
            sleep(10);
        }
    }

    public function checkBackup()
    {
        $c    = time();
        $conf = $this->getPacConf();
        $time = $conf['backup'];
        if ($time) {
            preg_match('~(\d+\s\w+)(?:\s+)?/(?:\s+)?(\d{2}:\d{2})~', $time, $m);
            $period = strtotime($m[1]) - $c;
            $start  = strtotime($m[2]);
            $last   = $conf['pinbackup'];
            if ($last) {
                [$pin, $time] = explode('/', $last);
                if ($c - $time >= $period) {
                    $this->pinAdmin($pin, 1);
                    $this->pinBackup();
                    return;
                }
            } elseif ($c - $start > 0 && $c - $start < 30) {
                $this->pinBackup();
            }
        }
    }

    public function pinAdmin($pin, $unpin = false)
    {
        require __DIR__ . '/config.php';
        if ($unpin) {
            return $this->unpin($c['admin'][0], $pin);
        } else {
            return $this->pin($c['admin'][0], $pin);
        }
    }

    public function pinBackup()
    {
        require __DIR__ . '/config.php';
        $conf              = $this->getPacConf();
        $bot               = $this->request('getMyName', [])['result']['name'];
        $pin               = $this->upload("{$bot}_export_" . date('d_m_Y_H_i') . '.json', $this->export(), $c['admin'][0])['result']['message_id'];
        $conf['pinbackup'] = "$pin/" . time();
        $this->setPacConf($conf);
        $this->pinAdmin($pin);
    }

    public function checkVersion()
    {
        try {
            require __DIR__ . '/config.php';
            if (!empty($c['admin']) && (empty($this->time) || ((time() - $this->time) > 3600))) {
                $this->time = time();
                $current    = file_get_contents('/version');
                $last       = file_get_contents('https://raw.githubusercontent.com/mercurykd/vpnbot/master/version');
                if (!empty($last) && $last != $this->last && $last != $current) {
                    $this->last = $last;
                    $diff = implode("\n", array_slice(explode("\n", $last), 0, count(explode("\n", $last)) - count(explode("\n", $current))));
                    if (!empty($diff)) {
                        foreach ($c['admin'] as $k => $v) {
                            $this->send($v, "update:\n$diff");
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    public function getTime(int $seconds)
    {
        $seconds = ($seconds - time()) > 0 ? $seconds - time() : 0;
        $items   = [
            'Y' => [
                'diff' => 1970,
                'sign' => 'y',
            ],
            'm' => [
                'diff' => 1,
                'sign' => 'mon',
            ],
            'd' => [
                'diff' => 1,
                'sign' => 'd',
            ],
            'H' => [
                'diff' => 0,
                'sign' => 'h',
            ],
            'i' => [
                'diff' => 0,
                'sign' => 'min',
            ],
            's' => [
                'diff' => 0,
                'sign' => 's',
            ],
        ];
        foreach ($items as $k => $v) {
            if (($t = gmdate($k, $seconds) - $v['diff']) > 0) {
                $text .= " $t{$v['sign']}";
                break;
            }
        }
        return trim($text) ?: '♾';
    }

    public function shutdownClient()
    {
        try {
            $clients = $this->readClients();
            if ($clients) {
                foreach ($clients as $k => $v) {
                    if (!empty($v['interface']['## time'])) {
                        if (strtotime($v['interface']['## time']) < time()) {
                            $this->switchClient($k);
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
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
        $this->menu('client', implode('_', $_SESSION['reply'][$this->input['reply']]['args']));
    }

    public function readClients(): array
    {
        return json_decode(file_get_contents($this->clients), true) ?: [];
    }

    public function export()
    {
        $conf = [
            'wg' => [
                'server'  => $this->readConfig(),
                'clients' => json_decode(file_get_contents($this->clients), true) ?: [],
            ],
            'ss'  => $this->getSSConfig(),
            'sl'  => $this->getSSLocalConfig(),
            'ad'  => yaml_parse_file('/config/adguard/AdGuardHome.yaml'),
            'pac' => $this->getPacConf(),
            'ssl' => file_exists('/certs/cert_private') && preg_match('~BEGIN PRIVATE KEY~', file_get_contents('/certs/cert_private')) ? [
                'private' => file_get_contents('/certs/cert_private'),
                'public'  => file_get_contents('/certs/cert_public'),
            ] : false,
            'mtproto' => file_get_contents('/config/mtprotosecret'),
            'xray'    => json_decode(file_get_contents('/config/xray.json'), true),

        ];
        return json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function exportManual()
    {
        $bot = $this->request('getMyName', [])['result']['name'];
        return $this->upload("{$bot}_export_" . date('d_m_Y_H_i') . '.json', $this->export());
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
            // certs
            if (!empty($json['ssl'])) {
                $out[] = 'update certificates';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                file_put_contents('/certs/cert_private', $json['ssl']['private']);
                file_put_contents('/certs/cert_public', $json['ssl']['public']);
            }
            // wg
            if (!empty($json['wg'])) {
                $out[] = 'update wireguard';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->saveClients($json['wg']['clients']);
                $this->restartWG($this->createConfig($json['wg']['server']));
            }
            // pac
            if (!empty($json['pac'])) {
                $out[] = 'update pac';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->setPacConf($json['pac']);
                $this->pacUpdate('1');
            }
            // ad
            if (!empty($json['ad'])) {
                $out[] = 'update adguard';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->stopAd();
                yaml_emit_file('/config/adguard/AdGuardHome.yaml', $json['ad']);
                $this->startAd();
            }
            // ss
            if (!empty($json['ss'])) {
                $out[] = 'update shadowsocks server';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->ssh('pkill ssserver', 'ss');
                file_put_contents('/config/ssserver.json', json_encode($json['ss'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->ssh('ssserver -v -d -c /config.json', 'ss');
            }
            // sl
            if (!empty($json['sl'])) {
                $out[] = 'update shadowsocks proxy';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->ssh('pkill sslocal', 'proxy');
                file_put_contents('/config/sslocal.json', json_encode($json['sl'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->ssh('sslocal -v -d -c /config.json', 'proxy');
            }
            // mtproto
            if (!empty($json['mtproto'])) {
                $out[] = 'update mtproto';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->restartTG($json['mtproto']);
            }
            // xray
            if (!empty($json['xray'])) {
                $out[] = 'update xray';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->restartXray($json['xray']);
                $this->setUpstreamDomain($json['xray']['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]);
            }
            // nginx
            $out[] = 'reset nginx';
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));

            $t = file_get_contents('/config/nginx_default.conf');
            if (!empty($json['pac']['domain'])) {
                $t = preg_replace('/server_name([^\n]+)?/', "server_name {$json['pac']['domain']};", $t);
                preg_match_all('~#-domain.+?#-domain~s', $t, $m);
                foreach ($m[0] as $k => $v) {
                    $t = preg_replace('~#-domain.+?#-domain~s', $this->uncomment($v, 'domain'), $t, 1);
                }
            }
            if (!empty($json['ssl'])) {
                $name = $json['pac']['letsencrypt'] ? 'letsencrypt' : 'self';
                $t = preg_replace('/#~([^\n]+)?/', "#~$name", $t);
                preg_match_all('~#-ssl.+?#-ssl~s', $t, $m);
                foreach ($m[0] as $k => $v) {
                    $t = preg_replace('~#-ssl.+?#-ssl~s', $this->uncomment($v, 'ssl'), $t, 1);
                }
            }
            file_put_contents('/config/nginx.conf', $t);
            $out[] = $this->ssh("nginx -s reload 2>&1", 'ng');
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));

            $out[] = "end import";
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            sleep(3);
            $this->menu();
        }
    }

    public function downloadPeer($client)
    {
        $cl     = $client;
        $client = $this->readClients()[$client];
        $name   = $this->getName($client['interface']);
        $code   = $this->createConfig($client);
        $this->upload(preg_replace(['~\s+~', '~\(|\)~'], ['_', ''], $name) . ".conf", $code);
        if ($this->getPacConf()['blinkmenu']) {
            $this->delete($this->input['chat'], $this->input['message_id']);
            $this->input['message_id'] = $this->send($this->input['chat'], '.')['result']['message_id'];
            $this->menu('client', "{$cl}_0");
        }
    }

    public function switchClient($client)
    {
        $clients = $this->readClients();
        if ($clients[$client]['# off']) {
            unset($clients[$client]['# off']);
        } else {
            $clients[$client]['# off'] = 1;
        }
        unset($clients[$client]['interface']['## time']);
        $this->saveClients($clients);

        $server = $this->readConfig();
        if (array_key_exists('# PublicKey', $server['peers'][$client])) {
            foreach ($server['peers'][$client] as $k => $v) {
                $new[trim(preg_replace('~#~', '', $k, 1))] = $v;
            }
        } else {
            foreach ($server['peers'][$client] as $k => $v) {
                $new["# $k"] = $v;
            }
        }
        unset($new['## time']);
        unset($new['# ## time']);
        $server['peers'][$client] = $new;
        $this->restartWG($this->createConfig($server));
    }

    public function switchTorrent($page)
    {
        $c = $this->getPacConf();
        $c['blocktorrent'] = $c['blocktorrent'] ? 0 : 1;
        $this->setPacConf($c);
        if ($c['blocktorrent']) {
            $this->ssh('bash /block_torrent.sh');
        } else {
            $this->ssh('bash /unblock_torrent.sh');
        }
        $this->menu('wg', $page);
    }

    public function blinkmenuswitch()
    {
        $c = $this->getPacConf();
        $c['blinkmenu'] = $c['blinkmenu'] ? 0 : 1;
        $this->setPacConf($c);
        $this->menu('config');
    }

    public function qrPeer($client)
    {
        $cl      = $client;
        $client  = $this->readClients()[$client];
        $name    = $this->getName($client['interface']);
        $code    = $this->createConfig($client);
        $qr      = preg_replace(['~\s+~', '~\(~', '~\)~'], ['_'], $name);
        $qr_file = __DIR__ . "/qr/$qr.png";
        exec("qrencode -t png -o $qr_file '$code'");
        $r = $this->sendPhoto(
            $this->input['chat'],
            curl_file_create($qr_file),
            $name,
        );
        unlink($qr_file);
        if ($this->getPacConf()['blinkmenu']) {
            $this->delete($this->input['chat'], $this->input['message_id']);
            $this->input['message_id'] = $this->send($this->input['chat'], '.')['result']['message_id'];
            $this->menu('client', "{$cl}_0");
        }
    }

    public function qrSS()
    {
        $conf    = $this->getPacConf();
        $ip      = $this->ip;
        $domain  = $conf['domain'] ?: $ip;
        $scheme  = empty($ssl = $this->nginxGetTypeCert()) ? 'http' : 'https';
        $ss      = $this->getSSConfig();
        $port    = !empty($ss['plugin']) ? (!empty($ssl) ? 443 : 80) : getenv('SSPORT');
        $ss_link = preg_replace('~==~', '', 'ss://' . base64_encode("{$ss['method']}:{$ss['password']}")) . "@$domain:$port" . (!empty($ss['plugin']) ? '?plugin=' . urlencode("v2ray-plugin;path=/v2ray;host=$domain" . (!empty($ssl) ? ';tls' : '')) : '');
        $qr_file = __DIR__ . "/qr/shadowsocks.png";
        exec("qrencode -t png -o $qr_file '$ss_link'");
        $r = $this->sendPhoto(
            $this->input['chat'],
            curl_file_create($qr_file),
            "<code>$ss_link</code>"
        );
        unlink($qr_file);
        if ($this->getPacConf()['blinkmenu']) {
            $this->delete($this->input['chat'], $this->input['message_id']);
            $this->input['message_id'] = $this->send($this->input['chat'], '.')['result']['message_id'];
            $this->menu('ss');
        }
    }

    public function qrXray()
    {
        $link    = $this->linkXray();
        $qr_file = __DIR__ . "/qr/xray.png";
        exec("qrencode -t png -o $qr_file '$link'");
        $r = $this->sendPhoto(
            $this->input['chat'],
            curl_file_create($qr_file),
            "<code>$link</code>"
        );
        unlink($qr_file);
        if ($this->getPacConf()['blinkmenu']) {
            $this->delete($this->input['chat'], $this->input['message_id']);
            $this->input['message_id'] = $this->send($this->input['chat'], '.')['result']['message_id'];
            $this->xray();
        }
    }

    public function qrMtproto()
    {
        $link    = $this->linkMtproto();
        $qr_file = __DIR__ . "/qr/mtproto.png";
        exec("qrencode -t png -o $qr_file '$link'");
        $r = $this->sendPhoto(
            $this->input['chat'],
            curl_file_create($qr_file),
            "<code>$link</code>"
        );
        unlink($qr_file);
        if ($this->getPacConf()['blinkmenu']) {
            $this->delete($this->input['chat'], $this->input['message_id']);
            $this->input['message_id'] = $this->send($this->input['chat'], '.')['result']['message_id'];
            $this->mtproto();
        }
    }

    public function upload($name, $code, $chat = false)
    {
        $path = "/logs/$name";
        file_put_contents($path, $code);
        $r = $this->sendFile(
            $chat ?: $this->input['chat'],
            curl_file_create($path),
        );
        unlink($path);
        return $r;
    }

    public function proxy()
    {
        $proxy = trim($this->ssh("getent hosts proxy | awk '{ print $1 }'"));
        $this->createPeer("$proxy/32", 'proxy');
    }

    public function addSubnets($page = 0)
    {
        $this->createPeer(implode(',', $this->getPacConf()['subnets']), 'list');
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
            $this->delete($this->input['chat'], $this->input['message_id']);
            $callback = $_SESSION['reply'][$this->input['reply']]['callback'];
            $this->input['message_id']  = $this->input['callback_id'] = $_SESSION['reply'][$this->input['reply']]['start_message'];
            $this->{$callback}($this->input['message'], ...$_SESSION['reply'][$this->input['reply']]['args']);
            $this->answer($_SESSION['reply'][$this->input['reply']]['start_message']);
            unset($_SESSION['reply'][$this->input['reply']]);
        }
    }

    public function addDomain($domain)
    {
        $domain = trim($domain);
        if (!empty($domain)) {
            $conf = $this->getPacConf();
            $conf['domain'] = idn_to_ascii($domain);
            $nginx = file_get_contents('/config/nginx.conf');
            $t = preg_replace('/server_name([^\n]+)?/', "server_name {$conf['domain']};", $nginx);
            preg_match_all('~#-domain.+?#-domain~s', $t, $m);
            foreach ($m[0] as $k => $v) {
                $t = preg_replace('~#-domain.+?#-domain~s', $this->uncomment($v, 'domain'), $t, 1);
            }
            file_put_contents('/config/nginx.conf', $t);
            $u = $this->ssh("nginx -t 2>&1", 'ng');
            $out[] = $u;
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            if (preg_match('~test is successful~', $u)) {
                $out[] = $this->ssh("nginx -s reload 2>&1", 'ng');
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->setPacConf($conf);
            } else {
                file_put_contents('/config/nginx.conf', $nginx);
            }
        }
        sleep(3);
        $this->menu('config');
    }

    public function comment($text, $tag)
    {
        $text = explode("\n", $text);
        foreach ($text as $k => $v) {
            if (preg_match("~##$tag~", $v)) {
                $text[$k] = "#-$tag";
                continue;
            }
            $text[$k] = "#$v";
        }
        return implode("\n", $text);
    }

    public function uncomment($text, $tag)
    {
        $text = explode("\n", $text);
        foreach ($text as $k => $v) {
            if (preg_match("~#-$tag~", $v)) {
                $text[$k] = "##$tag";
                continue;
            }
            $text[$k] = preg_replace('~#~', '', $v, 1);
        }
        return implode("\n", $text);
    }

    public function deleteSSL($notmenu = false)
    {
        $nginx = file_get_contents('/config/nginx.conf');
        $t = preg_replace("/#~[^\s]+/", '#~', $nginx);
        preg_match_all('~##ssl.+?##ssl~s', $t, $m);
        foreach ($m[0] as $k => $v) {
            $t = preg_replace('~##ssl.+?##ssl~s', $this->comment($v, 'ssl'), $t, 1);
        }
        file_put_contents('/config/nginx.conf', $t);
        $u = $this->ssh("nginx -t 2>&1", 'ng');
        $this->update($this->input['chat'], $this->input['message_id'], $u);
        if (preg_match('~test is successful~', $u)) {
            $u .= $this->ssh("nginx -s reload 2>&1", 'ng');
            $this->update($this->input['chat'], $this->input['message_id'], $u);
            $u .= $this->stopAd();
            $this->update($this->input['chat'], $this->input['message_id'], $u);
            $c = yaml_parse_file('/config/adguard/AdGuardHome.yaml');
            $c['tls']['enabled'] = false;
            $c['tls']['server_name'] = '';
            yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
            $u .= $this->startAd();
            $this->update($this->input['chat'], $this->input['message_id'], $u);
            unlink('/certs/cert_private');
            unlink('/certs/cert_public');
            sleep(3);
        } else {
            file_put_contents('/config/nginx.conf', $nginx);
        }
        if (!$notmenu) {
            $this->menu('config');
        }
    }

    public function updateUnitInitConfig()
    {
        $unit = $this->controlUnit('config');
        file_put_contents('/config/unit.json', $unit);
    }

    public function setSSL($name)
    {
        $conf = $this->getPacConf();
        switch ($name) {
            case 'letsencrypt':
                $out[] = 'Install certificate:';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                exec("certbot certonly --force-renew --preferred-chain 'ISRG Root X1' -n --agree-tos --email mail@{$conf['domain']} -d {$conf['domain']} --webroot -w /certs/ --logs-dir /logs 2>&1", $out, $code);
                if ($code > 0) {
                    $this->send($this->input['chat'], "ERROR\n" . implode("\n", $out));
                    break;
                }
                $out[] = 'Generate bundle';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $bundle = file_get_contents("/etc/letsencrypt/live/{$conf['domain']}/privkey.pem") . file_get_contents("/etc/letsencrypt/live/{$conf['domain']}/fullchain.pem");
                $conf['letsencrypt'] = 1;
                $this->setPacConf($conf);
                break;
            case 'self':
                $r      = $this->request('getFile', ['file_id' => $this->input['file_id']]);
                $bundle = file_get_contents($this->file . $r['result']['file_path']);
                break;
        }
        if (preg_match('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', $bundle, $m)) {
            file_put_contents('/certs/cert_private', $m[0]);
            file_put_contents('/certs/cert_public', preg_replace('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', '', $bundle));
            $nginx = file_get_contents('/config/nginx.conf');
            $t = preg_replace('/#~([^\n]+)?/', "#~$name", $nginx);
            preg_match_all('~#-ssl.+?#-ssl~s', $t, $m);
            foreach ($m[0] as $k => $v) {
                $t = preg_replace('~#-ssl.+?#-ssl~s', $this->uncomment($v, 'ssl'), $t, 1);
            }
            file_put_contents('/config/nginx.conf', $t);
            $u = $this->ssh("nginx -t 2>&1", 'ng');
            $out[] = $u;
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            if (preg_match('~test is successful~', $u)) {
                $out[] = $this->ssh("nginx -s reload 2>&1", 'ng');
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $out[] = 'Restart Adguard Home';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $out[] = $this->stopAd();
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $c = yaml_parse_file('/config/adguard/AdGuardHome.yaml');
                $c['tls']['enabled'] = true;
                $c['tls']['server_name'] = $conf['domain'];
                yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
                $out[] = $this->startAd();
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            } else {
                file_put_contents('/config/nginx.conf', $nginx);
            }
        } else {
            $this->update($this->input['chat'], $this->input['message_id'], "wrong format key");
        }
        sleep(3);
        $this->menu('config');
    }

    public function controlUnit($url, $method = 'GET', $json = false, $bundle = false)
    {
        $ch = curl_init();
        $opt = [
            CURLOPT_CUSTOMREQUEST    => $method,
            CURLOPT_URL              => "http://localhost/$url",
            CURLOPT_RETURNTRANSFER   => 1,
            CURLOPT_UNIX_SOCKET_PATH => '/var/run/control.unit.sock',
            CURLOPT_TIMEOUT          => 10,
        ];
        if ($json) {
            $opt[CURLOPT_POSTFIELDS] = $json;
        }
        if ($bundle) {
            $opt[CURLOPT_POSTFIELDS] = ['file' => new CURLStringFile($bundle, 'bundle.pem', 'text/plain')];
        }
        curl_setopt_array($ch, $opt);
        $r = curl_exec($ch);
        curl_close($ch);
        return $r ?: 'lost connect to unit';
    }

    public function delDomain()
    {
        $this->deleteSSL(1);
        $conf = $this->getPacConf();
        unset($conf['domain']);
        $nginx = $t = file_get_contents('/config/nginx.conf');
        preg_match_all('~##domain.+?##domain~s', $t, $m);
        foreach ($m[0] as $k => $v) {
            $t = preg_replace('~##domain.+?##domain~s', $this->comment($v, 'domain'), $t, 1);
        }
        file_put_contents('/config/nginx.conf', $t);
        $u = $this->ssh("nginx -t 2>&1", 'ng');
        $out[] = $u;
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        if (preg_match('~test is successful~', $u)) {
            $out[] = $this->ssh("nginx -s reload 2>&1", 'ng');
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            $this->setPacConf($conf);
        } else {
            file_put_contents('/config/nginx.conf', $nginx);
        }
        $this->menu('config');
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
            'args'          => ['subnet'],
        ];
    }

    public function getPacConf()
    {
        return json_decode(file_get_contents($this->pac), true);
    }

    public function setPacConf(array $conf)
    {
        return file_put_contents($this->pac, json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function domain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter domain",
            $this->input['message_id'],
            reply: 'enter domain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addDomain',
            'args'          => [],
        ];
    }

    public function selfssl()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} send file with your certificate chain and private key <code>cat key.pem ca.pem cert.pem</code>",
            $this->input['message_id'],
            reply: 'send file with your certificate chain and private key',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'selfsslInstall',
            'args'          => [],
        ];
    }

    public function resetnginx()
    {
        $nginx   = file_get_contents('/config/nginx.conf');
        $default = file_get_contents('/config/nginx_default.conf');
        file_put_contents('/config/nginx.conf', $default);
        $u = $this->ssh("nginx -t 2>&1", 'ng');
        $out[] = $u;
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        if (preg_match('~test is successful~', $u)) {
            $out[] = $this->ssh("nginx -s reload 2>&1", 'ng');
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            $conf = $this->getPacConf();
            unset($conf['domain']);
            $this->setPacConf($conf);
        } else {
            file_put_contents('/config/nginx.conf', $nginx);
        }
        sleep(5);
        $this->menu('config');
    }

    public function adguardpsswd()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter password",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'chpsswd',
            'args'          => [],
        ];
    }

    public function enterAdmin()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter id",
            $this->input['message_id'],
            reply: 'enter id',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addAdmin',
            'args'          => [],
        ];
    }

    public function addAdmin($id)
    {
        $file = __DIR__ . '/config.php';
        require $file;
        $c['admin'][] = $id;
        file_put_contents($file, "<?php\n\n\$c = " . var_export($c, true) . ";\n");
        $this->menu('config');
    }

    public function delAdmin($id)
    {
        $file = __DIR__ . '/config.php';
        require $file;
        unset($c['admin'][array_search($id, $c['admin'])]);
        file_put_contents($file, "<?php\n\n\$c = " . var_export($c, true) . ";\n");
        $this->menu('config');
    }

    public function chpsswd($pass)
    {
        $out[] = 'Restart Adguard Home';
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $out[] = $this->stopAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $c = yaml_parse_file('/config/adguard/AdGuardHome.yaml');
        $c['users'][0]['password'] = password_hash($pass, PASSWORD_DEFAULT);
        yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
        $p = $this->getPacConf();
        $p['adpswd'] = $pass;
        $this->setPacConf($p);
        $out[] = $this->startAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        sleep(3);
        $this->menu('adguard');
    }

    public function adguardreset()
    {
        $out[] = 'Restart Adguard Home';
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $out[] = $this->stopAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $c = yaml_parse_file('/config/AdGuardHome.yaml');
        yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
        $out[] = $this->startAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        sleep(3);
        $this->menu('adguard');
    }

    public function checkdns()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter dns address
Plain DNS:
<code>example.org 94.140.14.14</code>
DNS-over-TLS:
<code>example.org tls://dns.adguard.com</code>
DNS-over-TLS with IP:
<code>example.org tls://dns.adguard.com 94.140.14.14</code>
DNS-over-HTTPS with HTTP/2:
<code>example.org https://dns.adguard.com/dns-query</code>
DNS-over-HTTPS forcing HTTP/3 only:
<code>example.org h3://dns.google/dns-query</code>
DNS-over-HTTPS with IP:
<code>example.org https://dns.adguard.com/dns-query 94.140.14.14</code>",
            $this->input['message_id'],
            reply: 'enter command',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'dnscheck',
            'args'          => [],
        ];
    }

    public function dnscheck($dns)
    {
        exec("JSON=1 dnslookup $dns", $out, $code);
        if ($code) {
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out), mode: false);
        } else {
            $this->send($this->input['chat'], "JSON=1 dnslookup $dns\n" . implode("\n", $out), mode: false);
        }
        sleep(3);
        $this->menu('adguard');
    }

    public function addupstream()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter address upstream",
            $this->input['message_id'],
            reply: 'enter address upstream',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'upstream',
            'args'          => [],
        ];
    }

    public function upstream($url)
    {
        $out[] = 'Restart Adguard Home';
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $out[] = $this->stopAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $c = yaml_parse_file('/config/adguard/AdGuardHome.yaml');
        $c['dns']['upstream_dns'][] = $url;
        yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
        $out[] = $this->startAd();
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        sleep(3);
        $this->menu('adguard');
    }

    public function delupstream($k)
    {
        $out[] = 'Restart Adguard Home';
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $this->stopAd();
        $c = yaml_parse_file('/config/adguard/AdGuardHome.yaml');
        unset($c['dns']['upstream_dns'][$k]);
        yaml_emit_file('/config/adguard/AdGuardHome.yaml', $c);
        $this->startAd();
        $this->menu('adguard');
    }

    public function startAd()
    {
        return $this->ssh('/AdGuardHome/AdGuardHome --pidfile /AdGuardHome/pid -c /opt/adguardhome/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/ > /dev/null 2>&1 &', 'ad', false);
    }

    public function stopAd()
    {
        return $this->ssh('kill -15 $(cat /AdGuardHome/pid)', 'ad');
    }

    public function selfsslInstall()
    {
        $this->setSSL('self');
    }

    public function include(int $count)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} list domains separated by commas",
            $this->input['message_id'],
            reply: 'list domains separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addInclude',
            'args'          => [$count],
        ];
    }

    public function addInclude(string $domains, int $count)
    {
        $domains = explode(',', $domains);
        $domains = array_filter($domains, fn($x) => !empty(trim($x)));
        if (!empty($domains)) {
            $conf = $this->getPacConf();
            foreach ($domains as $k => $v) {
                $conf['includelist'][idn_to_ascii(trim($v))] = true;
            }
            ksort($conf['includelist']);
            $this->setPacConf($conf);
            $page = (int) floor(array_search($v, array_keys($conf['includelist'])) / $count);
        }
        $page = $page ?: -2;
        $this->menu('includelist', $page);
    }

    public function reverse(int $count)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} list domains separated by commas",
            $this->input['message_id'],
            reply: 'list domains separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addReverse',
            'args'          => [$count],
        ];
    }

    public function addReverse(string $domains, int $count)
    {
        $domains = explode(',', $domains);
        $domains = array_filter($domains, fn($x) => !empty(trim($x)));
        if (!empty($domains)) {
            $conf = $this->getPacConf();
            foreach ($domains as $k => $v) {
                $conf['reverselist'][idn_to_ascii(trim($v))] = true;
            }
            ksort($conf['reverselist']);
            $this->setPacConf($conf);
            $page = (int) floor(array_search($v, array_keys($conf['reverselist'])) / $count);
        }
        $page = $page ?: -2;
        $this->menu('reverselist', $page);
    }

    public function subzones(int $count)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} list subdomains separated by commas",
            $this->input['message_id'],
            reply: 'list subdomains separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addSubzones',
            'args'          => [$count],
        ];
    }

    public function addSubzones(string $domains, int $count)
    {
        $domains = explode(',', $domains);
        $domains = array_filter($domains, fn($x) => !empty(trim($x)));
        if (!empty($domains)) {
            $conf = $this->getPacConf();
            foreach ($domains as $k => $v) {
                $conf['subzoneslist'][idn_to_ascii(trim($v))] = true;
            }
            ksort($conf['subzoneslist']);
            $this->setPacConf($conf);
            $page = (int) floor(array_search($v, array_keys($conf['subzoneslist'])) / $count);
        }
        $page = $page ?: -2;
        $this->menu('subzoneslist', $page);
    }

    public function exclude(int $count)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter regular expression",
            $this->input['message_id'],
            reply: 'enter regular expression',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addExclude',
            'args'          => [$count],
        ];
    }

    public function addExclude(string $reg, int $count)
    {
        $reg = trim($reg);
        if (!empty($reg)) {
            $conf = $this->getPacConf();
            $conf['excludelist'][$reg] = true;
            ksort($conf['excludelist']);
            $this->setPacConf($conf);
            $page = (int) floor(array_search($reg, array_keys($conf['excludelist'])) / $count);
        }
        $page = $page ?: -2;
        $this->menu('excludelist', $page);
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
                    'text'          => $this->i18n('back'),
                    'callback_data' => "/menu",
                ],
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            "Reset settings?",
            $data,
        );
    }

    public function reset()
    {
        $conf    = $this->readConfig();
        $address = getenv('ADDRESS');
        $port    = getenv('WGPORT');
        $r       = $this->ssh("/bin/sh /reset_wg.sh $address $port");
        file_put_contents($this->clients, '');
        $this->menu();
    }

    public function addPeer()
    {
        $this->createPeer(name: 'all');
    }

    public function config()
    {
        $conf = $this->createConfig($this->readConfig());
        $data = [
            [
                [
                    'text'          => $this->i18n('back'),
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

    public function deletePeer($client, $page, $menu = true)
    {
        $conf = $this->readConfig();
        $this->deleteClient($client);
        unset($conf['peers'][$client]);
        $this->restartWG($this->createConfig($conf));
        if ($menu) {
            $this->menu('wg', $page);
        }
    }

    public function dnsPeer($client, $page)
    {
        $clients = $this->readClients();
        $clients[$client]['interface']['DNS'] = '10.10.0.5';
        $this->saveClients($clients);
        $this->menu('client', "{$client}_$page");
    }

    public function deletednsPeer($client, $page)
    {
        $clients = $this->readClients();
        unset($clients[$client]['interface']['DNS']);
        $this->saveClients($clients);
        $this->menu('client', "{$client}_$page");
    }

    public function pad($text, $length, $symbol = ' ')
    {
        for ($i = 0; $i < $length; $i++) {
            $text .= $symbol;
        }
        return $text;
    }

    public function statusWg(int $page = 0)
    {
        $conf    = $this->readConfig();
        $status  = $this->readStatus();
        $clients = $this->getClients($page);
        $bt      = $this->getPacConf()['blocktorrent'];
        $dns     = $this->getPacConf()['dns'];
        $data    = [
            [
                [
                    'text'          =>  $this->i18n('update status'),
                    'callback_data' => "/menu wg $page",
                ],
                [
                    'text'          =>  $this->i18n(($bt ? 'block' : 'unblock') . 'torrent'),
                    'callback_data' => "/switchTorrent $page",
                ],
            ],
            [
                [
                    'text'          =>  $this->i18n('add peer'),
                    'callback_data' => "/menu addpeer $page",
                ],
                [
                    'text'          =>  $this->i18n('listSubnet'),
                    'callback_data' => "/subnet $page",
                ],
            ],
            [
                [
                    'text'          =>  $this->i18n('defaultDNS') . ': ' . ($dns ?: $this->dns),
                    'callback_data' => "/defaultDNS $page",
                ],
            ],
        ];
        if ($clients) {
            $data = array_merge($data, $clients);
        }
        if (!empty($conf['peers'])) {
            $all     = (int) ceil(count($conf['peers']) / 5);
            $page    = min($page, $all - 1);
            $page    = $page == -2 ? $all - 1 : $page;
            $conf['peers'] = array_slice($conf['peers'], $page * 5, 5, true);
            foreach ($conf['peers'] as $k => $v) {
                if (!empty($v['# PublicKey'])) {
                    $conf['peers'][$k]['online'] = 'off';
                } else {
                    $conf['peers'][$k]['status'] = $this->getStatusPeer($v['PublicKey'], $status['peers']);
                    $conf['peers'][$k]['online'] = preg_match('~^(\d+ seconds|[12] minute)~', $conf['peers'][$k]['status']['latest handshake']) ? 'online' : '';
                }
            }
            foreach ($conf['peers'] as $k => $v) {
                if (empty($v['# PublicKey'])) {
                    preg_match_all('~([0-9.]+\.?)\s(\w+)~', $v['status']['transfer'], $m);
                    $tr = $m[0] ? ceil($m[1][1]) . '↓' . substr($m[2][1], 0, 1) . '/' . ceil($m[1][0]) . '↑' . substr($m[2][0], 0, 1) : '';
                } else {
                    $tr = '';
                }
                $t = [
                    'name'    => $this->getName($v),
                    'time'    => $this->getTime(strtotime($v['## time'])),
                    'status'  => $v['online'] == 'off' ? '🚷' : ($v['online'] ? '🟢' : '🔴'),
                    'traffic' => $tr,
                ];
                $pad = [
                    'name'    => max(mb_strlen($t['name']), $pad['name']),
                    'time'    => max($t['time'] == '♾' ? 4 : mb_strlen($t['time']), $pad['time']),
                    'status'  => max(mb_strlen($t['status']), $pad['status']),
                    'traffic' => max(mb_strlen($t['traffic']), $pad['traffic']),
                ];
                $peers[] = $t;
            }
            foreach ($peers as $k => $v) {
                $text[] = implode('', [
                    $this->pad($v['name'], $pad['name'] - mb_strlen($v['name'])),
                    $this->pad(" {$v['time']}", $pad['time'] - mb_strlen($v['time'])),
                    $this->pad($v['status'], $pad['status'] - mb_strlen($v['status'])),
                    $this->pad(" {$v['traffic']}", $pad['traffic'] - mb_strlen($v['traffic'])),
                ]);
            }
        }
        $text = "Menu -> Wireguard\n\n<code>" . implode(PHP_EOL, $text ?: []) . '</code>';
        $data[] = [[
            'text'          => $this->i18n('back'),
            'callback_data' => "/menu",
        ]];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function defaultDNS($page = 0)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter dns separated by commas",
            $this->input['message_id'],
            reply: 'enter dns separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setDNS',
            'args'           => [$page],
        ];
    }

    public function setDNS($text, $page = 0)
    {
        $c = $this->getPacConf();
        if ($text) {
            $c['dns'] = $text;
        } else {
            unset($c['dns']);
        }
        $this->setPacConf($c);
        $this->menu('wg', $page);
    }

    public function subnetAdd($wgpage, $page)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter subnet separated by commas",
            $this->input['message_id'],
            reply: 'enter subnet separated by commas',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'subnetSave',
            'args'           => [$wgpage, $page],
        ];
    }

    public function subnetSave($text, $wgpage, $page)
    {
        $c = $this->getPacConf();
        $subnets = explode(',', $text);
        if ($subnets) {
            $c['subnets'] = array_merge($c['subnets'] ?: [], array_filter(array_map(fn ($e) => trim($e), $subnets)));
            $this->setPacConf($c);
            $page = floor(count($c['subnets']) / 5);
        }
        $this->subnet($wgpage, $page);
    }

    public function subnetDelete($wgpage, $k, $page = 0)
    {
        $c = $this->getPacConf();
        unset($c['subnets'][$k]);
        $this->setPacConf($c);
        $this->subnet($wgpage, $page);
    }

    public function calc()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter like '10.0.0.0/24, -10.0.0.5/32'",
            $this->input['message_id'],
            reply: 'enter like \'10.0.0.0/24, -10.0.0.5/32\'',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'calcSubnet',
            'args'           => [],
        ];
    }

    public function calcSubnet($text)
    {
        $text = explode(',', $text);
        $text = array_map(fn ($e) => trim($e), $text);
        if (!empty($text)) {
            foreach ($text as $k => $v) {
                if (preg_match('~^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}~', $v)) {
                    $include[] = $v;
                }
                if (preg_match('~^-(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2})~', $v, $m)) {
                    $exclude[] = $m[1];
                }
            }
        }
        if (!empty($include)) {
            foreach ($include as $k => $v) {
                $t = explode('/', $v);
                $include[$k] = [ip2long($t[0]), ip2long($t[0]) + (1 << (32 - $t[1])) - 1];
            }
            if (!empty($exclude)) {
                foreach ($exclude as $k => $v) {
                    $t = explode('/', $v);
                    $exclude[$k] = [ip2long($t[0]), ip2long($t[0]) + (1 << (32 - $t[1])) - 1];
                }
            }
            $c = new Calc();
            $r = $c->prepare($include, $exclude ?: []);
            if (!empty($r)) {
                $t = [];
                foreach ($r as $k => $v) {
                    $t = array_merge($t, $c->toCIDR($v[0], $v[1]));
                }
                $this->send($this->input['chat'], '<pre>' . implode(', ', $t) . '</pre>');
            }
        }
    }

    public function subnet($wgpage = 0, $page = 0, $count = 5)
    {
        $text = "Menu -> Wireguard -> " . $this->i18n('listSubnet') . "\n";
        $data[] = [
            [
                'text'          => $this->i18n('calc'),
                'callback_data' => "/calc",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/subnetAdd {$wgpage}_$page",
            ],
        ];
        $subnets = $this->getPacConf()['subnets'];
        if (!empty($subnets)) {
            $all     = (int) ceil(count($subnets) / $count);
            $page    = min($page, $all - 1);
            $page    = $page == -2 ? $all - 1 : $page;
            $subnets = $page != -1 ? array_slice($subnets, $page * $count, $count, true) : $subnets;
            foreach ($subnets as $k => $v) {
                $data[] = [
                    [
                        'text'          => $this->i18n('delete') . " $v",
                        'callback_data' => "/subnetDelete {$wgpage}_{$k}_$page",
                    ],
                ];
            }
            if ($page != -1 && $all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/subnet {$wgpage}_" . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                    ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/subnet {$wgpage}_" . ($page < $all - 1 ? $page + 1 : 0),
                    ]
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu wg $wgpage",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function changeAllowedIps($k, $page = 0)
    {
        $clients = $this->readClients();
        $name    = $this->getName($clients[$k]['interface']);
        $text    = "Menu -> Wireguard -> $name -> Change AllowedIPs\n\n";
        $data[]  = [
            [
                'text'          =>  $this->i18n('all traffic'),
                'callback_data' => "/changeIps all_{$k}_$page",
            ]
        ];
        $data[] = [
            [
                'text'          =>  $this->i18n('subnet'),
                'callback_data' => "/changeIps subnet_{$k}_$page",
            ]
        ];
        if ($this->getPacConf()['subnets']) {
            $data[] = [
                [
                    'text'          =>  $this->i18n('listSubnet'),
                    'callback_data' => "/changeIps list_{$k}_$page",
                ]
            ];
        }
        $data[] = [
            [
                'text'          =>  $this->i18n('proxy ip'),
                'callback_data' => "/changeIps proxy_{$k}_$page",
            ]
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu client {$k}_$page",
            ]
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function changeIps($type, $k, $page = 0)
    {
        switch ($type) {
            case 'all':
                $this->setIps('0.0.0.0/0', $k, $page);
                break;
            case 'subnet':
                $r = $this->send(
                    $this->input['chat'],
                    "@{$this->input['username']} list subnets separated by commas",
                    $this->input['message_id'],
                    reply: 'list subnets separated by commas',
                );
                $_SESSION['reply'][$r['result']['message_id']] = [
                    'start_message' => $this->input['message_id'],
                    'callback'      => 'setIps',
                    'args'          => [$k, $page],
                ];
                break;
            case 'list':
                $this->setIps(implode(',', $this->getPacConf()['subnets']), $k, $page);
                break;
            case 'proxy':
                $this->setIps(trim($this->ssh("getent hosts proxy | awk '{ print $1 }'")) . '/32', $k, $page);
                break;
        }
    }

    public function setIps($ips, $k, $page = 0)
    {
        $clients = $this->readClients();
        $clients[$k]['peers'][0]['AllowedIPs'] = $ips;
        $this->saveClients($clients);
        $this->menu('client', "{$k}_$page");
    }

    public function getClient($client, $page)
    {
        $clients = $this->readClients();
        if ($clients) {
            $name = $this->getName($clients[$client]['interface']);
            $conf = $this->createConfig($clients[$client]);
            return [
                'text' => "<code>$conf</code>\n\n<b>$name</b>",
                'data' => [
                    [
                        [
                            'text'          =>  $this->i18n('rename'),
                            'callback_data' => "/rename {$client}_$page",
                        ],
                        [
                            'text'          =>  $this->i18n('timer'),
                            'callback_data' => "/timer {$client}_$page",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('show QR'),
                            'callback_data' => "/qr $client",
                        ],
                        [
                            'text'          => $this->i18n('download config'),
                            'callback_data' => "/download $client",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n($clients[$client]['# off'] ? 'off' : 'on'),
                            'callback_data' => "/switchClient {$client}_$page",
                        ],
                        [
                            'text'          => $this->i18n($clients[$client]['interface']['DNS'] ? 'delete internal dns' : 'set internal dns'),
                            'callback_data' => "/" . ($clients[$client]['interface']['DNS'] ? 'delete' : '') . "dns {$client}_$page",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('AllowedIPs'),
                            'callback_data' => "/changeAllowedIps {$client}_$page",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('delete'),
                            'callback_data' => "/delete {$client}_$page",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('back'),
                            'callback_data' => "/menu wg $page",
                        ],
                    ],
                ],
            ];
        }
        return [
            'text' => "no clients",
            'data' => false
        ];
    }

    public function getClients(int $page, int $count = 5)
    {
        $clients = $this->readClients();
        if (!empty($clients)) {
            $all     = (int) ceil(count($clients) / $count);
            $page    = min($page, $all - 1);
            $page    = $page == -2 ? $all - 1 : $page;
            $clients = $page != -1 ? array_slice($clients, $page * $count, $count, true) : $clients;
            foreach ($clients as $k => $v) {
                $data[] = [[
                    'text'          => $this->getName($v['interface']),
                    'callback_data' => "/menu client {$k}_$page",
                ]];
            }
            if ($page != -1 && $all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/menu wg " . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                    ],
                    // [
                    //     'text'          => 'all',
                    //     'callback_data' => "/menu wg -1",
                    // ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/menu wg " . ($page < $all - 1 ? $page + 1 : 0),
                    ]
                ];
            }
        }
        return $data;
    }

    public function sizeFormat($bytes)
    {
        if (floor($bytes / 1024 ** 2) > 0) {
            $r = round($bytes / 1024 ** 2, 2) . 'MB';
        } elseif (floor($bytes / 1024) > 0) {
            $r = round($bytes / 1024, 2) . 'KB';
        } else {
            $r = $bytes . 'B';
        }
        return $r;
    }

    public function pacMenu()
    {
        $rmpac    = stat(__DIR__ . '/zapretlists/rmpac');
        $rpac     = stat(__DIR__ . '/zapretlists/rpac');
        $mpac     = stat(__DIR__ . '/zapretlists/mpac');
        $pac      = stat(__DIR__ . '/zapretlists/pac');
        $conf     = $this->getPacConf();
        $zapret   = $conf['zapret'] ? $this->i18n('on') : $this->i18n('off');
        $ip       = $conf['domain'] ?: $this->ip;
        $hash     = substr(md5($this->key), 0, 8);
        $scheme   = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $text     = <<<text
                Menu -> pac

                <code>RU blacklist:</code> <code>https://github.com/zapret-info/z-i</code>

                {$this->i18n('self list')}{$this->i18n('self list explain')}

                {$this->i18n('reverse list')}{$this->i18n('reverse list explain')}
                text;
        if ($pac) {
            $pac['time']  = date('d.m.Y H:i:s', $pac['mtime']);
            $pac['sz']    = $this->sizeFormat($pac['size']);
            $text .= <<<text


                    <b>PAC ({$pac['time']} / {$pac['sz']}):</b>
                    <code>$scheme://$ip/pac?h=$hash&a=127.0.0.1&p=1080</code>
                    text;
            $urls[0][] = [
                'text' => "PAC",
                'url'  => "$scheme://$ip/pac?h=$hash&a=127.0.0.1&p=1080",
            ];
            $urls[1][] = [
                'text' => "PAC Wireguard proxy",
                'url'  => "$scheme://$ip/pac?h=$hash&a=10.10.0.3&p=1080",
            ];
        }
        if ($mpac) {
            $mpac['time']  = date('d.m.Y H:i:s', $mpac['mtime']);
            $mpac['sz']    = $this->sizeFormat($mpac['size']);
            $text .= <<<text


                    <b>Shadowsocks-android PAC ({$mpac['time']} / {$mpac['sz']}):</b>
                    <code>$scheme://$ip/pac?h=$hash&t=mpac</code>
                    text;
            $urls[2][] = [
                'text' => "PAC ShadowSocks(Android)",
                'url'  => "$scheme://$ip/pac?h=$hash&t=mpac",
            ];
        }
        if ($rpac) {
            $rpac['time']  = date('d.m.Y H:i:s', $rpac['mtime']);
            $rpac['sz']    = $this->sizeFormat($rpac['size']);
            $text .= <<<text


                    <b>Reverse PAC ({$rpac['time']} / {$rpac['sz']}):</b>
                    <code>$scheme://$ip/pac?h=$hash&t=rpac&a=127.0.0.1&p=1080</code>
                    text;
            $urls[0][] = [
                'text' => "Reverse PAC",
                'url'  => "$scheme://$ip/pac?h=$hash&t=rpac",
            ];
            $urls[1][] = [
                'text' => "Reverse PAC Wireguard proxy",
                'url'  => "$scheme://$ip/pac?h=$hash&t=rpac&a=10.10.0.3",
            ];
        }
        if ($rmpac) {
            $rmpac['time']  = date('d.m.Y H:i:s', $rmpac['mtime']);
            $rmpac['sz']    = $this->sizeFormat($rmpac['size']);
            $text .= <<<text


                    <b>Reverse shadowsocks-android PAC ({$rmpac['time']} / {$rmpac['sz']}):</b>
                    <code>$scheme://$ip/pac?h=$hash&t=rmpac</code>
                    text;
            $urls[2][] = [
                'text' => "Reverse PAC SS(Android)",
                'url'  => "$scheme://$ip/pac?h=$hash&t=rmpac",
            ];
        }
        if ($urls) {
            $data = $urls;
        }
        if ($conf['zapret']) {
            $data[] = [
                [
                    'text'          => "RU blacklist : $zapret",
                    'callback_data' => "/paczapret",
                ],
                [
                    'text'          => $this->i18n('blacklist exclude'),
                    'callback_data' => "/menu excludelist 0",
                ],
            ];
        } else {
            $data[] = [
                [
                    'text'          => "RU blacklist : $zapret",
                    'callback_data' => "/paczapret",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('self list'),
                'callback_data' => "/menu includelist 0",
            ],
            [
                'text'          => $this->i18n('subzones'),
                'callback_data' => "/menu subzoneslist 0",
            ],
            [
                'text'          => $this->i18n('reverse list'),
                'callback_data' => "/menu reverselist 0",
            ],
        ];
        if ($conf['zapret'] || !empty($conf['includelist']) || !empty($conf['reverselist'])) {
            $data[] = [
                [
                    'text'          => "{$this->i18n('update')} PAC",
                    'callback_data' => "/pacupdate",
                ],
                [
                    'text'          => $this->i18n('check url'),
                    'callback_data' => "/checkurl",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function pacList($type, int $page, int $count = 5)
    {
        $name = str_replace('list', '', $type);
        $text = "Menu -> pac -> {$name}list\n\n";
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/$name $count",
            ],
        ];
        $domains = $this->getPacConf()[$type];
        if (!empty($domains)) {
            ksort($domains);
            $all     = (int) ceil(count($domains) / $count);
            $page    = min($page, $all - 1);
            $page    = $page == -2 ? $all - 1 : $page;
            $domains = $page != -1 ? array_slice($domains, $page * $count, $count, true) : $domains;
            foreach ($domains as $k => $v) {
                $data[] = [
                    [
                        'text'          => '(' . ($v ? 'ON' : 'OFF') . ') ' . ($type == 'includelist' ? idn_to_utf8($k) : $k),
                        'callback_data' => "/change{$name}list {$k}_$count",
                    ],
                    [
                        'text'          => 'delete',
                        'callback_data' => "/delete{$name}list {$k}_$count",
                    ],
                ];
            }
            if ($page != -1 && $all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/menu $type " . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                    ],
                    // [
                    //     'text'          => 'all',
                    //     'callback_data' => "/menu $type -1",
                    // ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/menu $type " . ($page < $all - 1 ? $page + 1 : 0),
                    ]
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu pac",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function listPacChange($type, $action, $key, int $count)
    {
        $conf = $this->getPacConf();
        ksort($conf[$type]);
        $page = (int) floor(array_search($key, array_keys($conf[$type])) / $count);
        switch ($action) {
            case 'change':
                $conf[$type][$key] = !$conf[$type][$key];
                $page = (int) floor(array_search($key, array_keys($conf[$type])) / $count);
                break;
            case 'delete':
                unset($conf[$type][$key]);
                break;
        }
        $this->setPacConf($conf);
        $this->menu($type, $page);
    }

    public function pacZapret()
    {
        $conf = $this->getPacConf();
        $conf['zapret'] = !$conf['zapret'];
        $this->setPacConf($conf);
        $this->menu('pac');
    }

    public function pacUpdate($import = '')
    {
        exec("php updatepac.php start {$this->input['chat']} {$this->input['message_id']} {$this->input['callback_id']} $import > /dev/null &");
    }

    public function getSSConfig()
    {
        return json_decode(file_get_contents('/config/ssserver.json'), true);
    }

    public function getSSLocalConfig()
    {
        return json_decode(file_get_contents('/config/sslocal.json'), true);
    }

    public function menuSS()
    {
        $conf    = $this->getPacConf();
        $ip      = $this->ip;
        $domain  = $conf['domain'] ?: $ip;
        $scheme  = empty($ssl = $this->nginxGetTypeCert()) ? 'http' : 'https';
        $ss      = $this->getSSConfig();
        $v2ray   = !empty($ss['plugin']) ? 'ON' : 'OFF';
        $port    = !empty($ss['plugin']) ? (!empty($ssl) ? 443 : 80) : getenv('SSPORT');
        $options = !empty($ssl) && !empty($ss['plugin']) ? "tls;fast-open;path=/v2ray;host=$domain" : "path=/v2ray;host=$domain";

        $text = "Menu -> ShadowSocks";
        $data[] = [
            [
                'text'          => $this->i18n('change password'),
                'callback_data' => "/sspswd",
            ],
        ];
        $ss_link = preg_replace('~==~', '', 'ss://' . base64_encode("{$ss['method']}:{$ss['password']}")) . "@$domain:$port" . (!empty($ss['plugin']) ? '?plugin=' . urlencode("v2ray-plugin;path=/v2ray;host=$domain" . (!empty($ssl) ? ';tls' : '')) : '');
        $text .= "\n\n<code>$ss_link</code>\n";
        $text .= "\n\nserver: <code>$domain:$port</code>";
        $text .= "\n\nmethod: <code>{$ss['method']}</code>";
        $text .= "\n\nnameserver: <code>10.10.0.5</code>";
        if ($ss['plugin']) {
            $text .= "\n\nplugin: <code>v2ray-plugin</code>";
            $text .= "\n\nv2ray options: <code>$options</code>";
        }
        $data[] = [
            [
                'text'          => "v2ray: $v2ray",
                'callback_data' => "/v2ray",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('show QR'),
                'callback_data' => "/qrSS",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function i18n(string $menu): string
    {
        return $this->i18n[$menu][$this->language] ?: $menu;
    }

    public function menu($type = false, $arg = false, $return = false)
    {
        $menu = [
            'main' => [
                'text' => $this->i18n('menu'),
                'data' => [
                    [
                        [
                            'text'          => $this->i18n('wg_title'),
                            'callback_data' => "/menu wg 0",
                        ],
                        [
                            'text'          => $this->i18n('ad_title'),
                            'callback_data' => "/menu adguard",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('sh_title'),
                            'callback_data' => "/menu ss",
                        ],
                        [
                            'text'          => $this->i18n('pac'),
                            'callback_data' => "/menu pac",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('xray'),
                            'callback_data' => "/xray",
                        ],
                        [
                            'text'          => $this->i18n('geodb'),
                            'callback_data' => "/geodb",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('mtproto'),
                            'callback_data' => "/mtproto",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('config'),
                            'callback_data' => "/menu config",
                        ]
                    ],
                    [
                        [
                            'text' => $this->i18n('chat'),
                            'url'  => "https://t.me/vpnbot_group",
                        ],
                        [
                            'text' => $this->i18n('donate'),
                            'url'  => "https://yoomoney.ru/to/410011827900450",
                        ],
                    ]
                ],
            ],
            'wg'      => $type == 'wg' ? $this->statusWg($arg) : false,
            'client'  => $type == 'client' ? $this->getClient(...explode('_', $arg)) : false,
            'addpeer' => $type == 'addpeer' ? $this->addWg(...explode('_', $arg)) : false,
            'pac'          => $type == 'pac'          ? $this->pacMenu() : false,
            'adguard'      => $type == 'adguard'      ? $this->adguardMenu() : false,
            'includelist'  => $type == 'includelist'  ? $this->pacList($type, $arg) : false,
            'excludelist'  => $type == 'excludelist'  ? $this->pacList($type, $arg) : false,
            'reverselist'  => $type == 'reverselist'  ? $this->pacList($type, $arg) : false,
            'subzoneslist' => $type == 'subzoneslist' ? $this->pacList($type, $arg) : false,
            'config'       => $type == 'config'       ? $this->configMenu() : false,
            'ss'           => $type == 'ss'           ? $this->menuSS() : false,
            'lang'         => $type == 'lang'         ? $this->menuLang() : false,
        ];

        $text = $menu[$type ?: 'main' ]['text'];
        $data = $menu[$type ?: 'main' ]['data'];

        if ($return) {
            return [$text, $data];
        }

        if (!empty($this->input['callback_id'])) {
            $this->update(
                $this->input['chat'],
                $this->input['message_id'],
                $text,
                $data ?: false,
            );
        } else {
            $this->send(
                $this->input['chat'],
                $text,
                $this->input['message_id'],
                $data ?: false,
            );
        }
    }

    public function geodb()
    {
        $this->answer($this->input['callback_id'], 'in developing...');
    }

    public function linkXray()
    {
        $c      = $this->getXray();
        $pac    = $this->getPacConf();
        $domain = $pac['domain'] ?: $this->ip;
        return "vless://{$c['inbounds'][0]['settings']['clients'][0]['id']}@$domain:443?security=reality&sni={$c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]}&fp=chrome&pbk={$pac['xray']}&sid={$c['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0]}&type=tcp&flow=xtls-rprx-vision#vpnbot";
    }

    public function xray()
    {
        $c      = $this->getXray();
        $pac    = $this->getPacConf();
        $domain = $pac['domain'] ?: $this->ip;
        $st     = $this->ssh('pgrep xray', 'xr') ? 'on' : 'off';
        $text[] = "Menu -> " . $this->i18n('xray') . "\n";
        $text[] = "uuid: <code>{$c['inbounds'][0]['settings']['clients'][0]['id']}</code>";
        $text[] = "shortId: <code>{$c['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0]}</code>";
        $text[] = "pubkey: <code>{$pac['xray']}</code>";
        $text[] = "fake domain: <code>{$c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]}</code>";
        if ($c['inbounds'][0]['settings']['clients'][0]['id']) {
            $text[] = "\n<code>{$this->linkXray()}</code>";
        }
        $text[] = "\nstatus: $st";

        $data[] = [
            [
                'text'          => $this->i18n('generateSecret'),
                'callback_data' => "/generateSecretXray",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('changeFakeDomain'),
                'callback_data' => "/changeFakeDomain",
            ],
        ];
        if ($c['inbounds'][0]['settings']['clients'][0]['id']) {
            $data[] = [
                [
                    'text'          => $this->i18n('show QR'),
                    'callback_data' => "/qrXray",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function getXray()
    {
        return json_decode(file_get_contents('/config/xray.json'), true);
    }

    public function generateSecretXray()
    {
        $c       = $this->getXray();
        $uuid    = trim($this->ssh('xray uuid', 'xr'));
        $shortId = trim($this->ssh('openssl rand -hex 8', 'xr'));
        $keys    = $this->ssh('xray x25519', 'xr');
        preg_match('~^Private key:\s([^\s]+)~m', $keys, $m);
        $private = trim($m[1]);
        preg_match('~^Public key:\s([^\s]+)~m', $keys, $m);
        $public = trim($m[1]);
        $c['inbounds'][0]['settings']['clients'][0]['id'] = $uuid;
        $c['inbounds'][0]['streamSettings']['realitySettings']['privateKey'] = $private;
        $c['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0] = $shortId;
        $pac         = $this->getPacConf();
        $pac['xray'] = $public;
        $this->setPacConf($pac);
        $this->restartXray($c);
        $this->xray();
    }

    public function setUpstreamDomain($domain)
    {
        $nginx = file_get_contents('/config/upstream.conf');
        $t = preg_replace('~#domain.+#domain~s', "#domain\n$domain reality;\n#domain", $nginx);
        file_put_contents('/config/upstream.conf', $t);
        $this->ssh("nginx -s reload 2>&1", 'up');
    }

    public function addWg($page)
    {
        $text = "Menu -> Wireguard -> Add peer\n\n";
        $data[] = [
            [
                'text'          =>  $this->i18n('all traffic'),
                'callback_data' => "/add",
            ]
        ];
        $data[] = [
            [
                'text'          =>  $this->i18n('subnet'),
                'callback_data' => "/add_ips",
            ]
        ];
        if ($this->getPacConf()['subnets']) {
            $data[] = [
                [
                    'text'          =>  $this->i18n('listSubnet'),
                    'callback_data' => "/addSubnets $page",
                ]
            ];
        }
        $data[] = [
            [
                'text'          =>  $this->i18n('proxy ip'),
                'callback_data' => "/proxy",
            ]
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu wg $page",
            ]
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function adguardMenu()
    {
        $conf   = $this->getPacConf();
        $ip     = $this->ip;
        $domain = $conf['domain'] ?: $ip;
        $scheme = empty($ssl = $this->nginxGetTypeCert()) ? 'http' : 'https';
        $text = "$scheme://$domain/adguard\nLogin: admin\nPass: <span class='tg-spoiler'>{$conf['adpswd']}</span>\n\n";
        if ($ssl) {
            $text .= "DNS over HTTPS:\n<code>$ip</code>\n<code>$scheme://$domain/dns-query</code>\n\n";
            $text .= "DNS over TLS:\n<code>tls://$domain</code>";
        }
        $data = [
            [
                [
                    'text'          => $this->i18n('change password'),
                    'callback_data' => "/adguardpsswd",
                ],
                [
                    'text'          => $this->i18n('reset settings'),
                    'callback_data' => "/adguardreset",
                ],
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add upstream'),
                'callback_data' => "/addupstream",
            ],
        ];
        $upstreams = yaml_parse_file('/config/adguard/AdGuardHome.yaml')['dns']['upstream_dns'];
        if (!empty($upstreams)) {
            foreach ($upstreams as $k => $v) {
                $data[] = [
                    [
                        'text'          => $v,
                        'callback_data' => "/menu adguard",
                    ],
                    [
                        'text'          => $this->i18n('delete'),
                        'callback_data' => "/delupstream $k",
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('check DNS'),
                'callback_data' => "/checkdns",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function menuLang()
    {
        $data = [];
        $lang = [];
        foreach ($this->i18n as $k => $v) {
            $lang = array_merge($lang, array_keys($v));
        }
        $lang = array_unique($lang);
        foreach ($lang as $v) {
            if ($v != $this->language) {
                $data[] = [
                    [
                        'text'          => $v,
                        'callback_data' => "/lang $v",
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu config",
            ],
        ];
        return [
            'text' => 'Language',
            'data' => $data,
        ];
    }

    public function configMenu()
    {
        $conf = $this->getPacConf();
        $text = $this->i18n('domain explain');
        $data = [
            [
                [
                    'text'          => $conf['domain'] ? "{$this->i18n('delete')} {$conf['domain']}" : $this->i18n('install domain'),
                    'callback_data' => $conf['domain'] ? '/deldomain' : '/domain',
                ],
            ],
        ];
        if ($conf['domain']) {
            if ($cert = $this->nginxGetTypeCert()) {
                switch ($cert) {
                    case 'letsencrypt':
                        $data[] = [
                            [
                                'text'          => $this->i18n('renew SSL'),
                                'callback_data' => "/setSSL letsencrypt",
                            ],
                            [
                                'text'          => $this->i18n('delete SSL'),
                                'callback_data' => "/deletessl",
                            ],
                        ];
                        break;
                    case 'self':
                        $data[] = [
                            [
                                'text'          => $this->i18n('delete SSL'),
                                'callback_data' => "/deletessl",
                            ],
                        ];
                        break;
                }
            } else {
                $data[] = [
                    [
                        'text'          => $this->i18n('Letsencrypt SSL'),
                        'callback_data' => "/setSSL letsencrypt",
                    ],
                    [
                        'text'          => $this->i18n('Self SSL'),
                        'callback_data' => "/selfssl",
                    ],
                ];
            }
        }
        /*$data[] = [
            [
                'text'          => $this->i18n('reset nginx'),
                'callback_data' => "/resetnginx",
            ],
        ];*/
        $data[] = [
            [
                'text'          => "{$this->i18n('add')} {$this->i18n('admin')}",
                'callback_data' => "/addadmin",
            ],
        ];
        $file = __DIR__ . '/config.php';
        opcache_invalidate($file);
        require $file;
        foreach ($c['admin'] as $k => $v) {
            $data[] = [
                [
                    'text'          => $this->i18n('delete') . " $v",
                    'callback_data' => "/deladmin $v",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('lang'),
                'callback_data' => "/menu lang",
            ],
            [
                'text'          => $this->i18n($conf['blinkmenu'] ? 'blinkmenuon' : 'blinkmenuoff'),
                'callback_data' => "/blinkmenuswitch",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('export'),
                'callback_data' => "/export",
            ],
            [
                'text'          => $this->i18n('backup') . ': ' . (implode(' / ', explode('/', $conf['backup'])) ?: $this->i18n('off')),
                'callback_data' => "/backup",
            ],
            [
                'text'          => $this->i18n('import'),
                'callback_data' => "/import",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('logs'),
                'callback_data' => "/logs",
            ],
            [
                'text'          => $this->i18n('debug') . ': ' . $this->i18n($c['debug'] ? 'on' : 'off'),
                'callback_data' => "/debug",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function logs()
    {
        foreach (scandir('/logs/') as $k => $v) {
            if (!preg_match('~^\.~', $v)) {
                $size   = filesize("/logs/$v");
                $data[] = [
                    [
                        'text'          => "$v ($size)",
                        'callback_data' => "/getLog $k",
                    ],
                    [
                        'text'          => $this->i18n('clear'),
                        'callback_data' => "/clearLog $k",
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu config",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function getLog($i)
    {
        foreach (scandir('/logs/') as $k => $v) {
            if (!preg_match('~^\.~', $v)) {
                $logs[$k] = $v;
            }
        }
        $this->sendFile(
            $this->input['chat'],
            curl_file_create("/logs/{$logs[$i]}"),
        );
    }

    public function clearLog($i)
    {
        foreach (scandir('/logs/') as $k => $v) {
            if ($i == $k) {
                file_put_contents("/logs/$v", '');
                break;
            }
        }
        $this->logs();
    }

    public function backup()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter like 1 day/00:00",
            $this->input['message_id'],
            reply: 'enter like 1 day/00:00',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setBackup',
            'args'           => [],
        ];
    }

    public function changeFakeDomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter domain",
            $this->input['message_id'],
            reply: 'enter domain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setFakeDomain',
            'args'           => [],
        ];
    }

    public function setFakeDomain($domain)
    {
        $c = $this->getXray();
        $c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0] = $domain;
        $c['inbounds'][0]['streamSettings']['realitySettings']['dest'] = "$domain:443";
        $this->restartXray($c);
        $this->setUpstreamDomain($domain);
        $this->xray();
    }

    public function setBackup($text)
    {
        $text = trim($text);
        $c = $this->getPacConf();
        if (empty($text)) {
            $c['backup'] = '';
        } elseif (preg_match('~(\d+\s\w+)(?:\s+)?/(?:\s+)?(\d{2}:\d{2})~', $text, $m)) {
            $period = $m[1];
            $start  = $m[2];
            $c['backup'] = $text;
        }
        if ($pin = explode('/', $c['pinbackup'])[0]) {
            $this->pinAdmin($pin, 1);
            $c['pinbackup'] = '';
        }
        $this->setPacConf($c);
        $this->menu('config');
    }

    public function debug()
    {
        $file = __DIR__ . '/config.php';
        require $file;
        $c['debug'] = !$c['debug'];
        file_put_contents($file, "<?php\n\n\$c = " . var_export($c, true) . ";\n");
        $this->menu('config');
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

    public function nginxGetTypeCert()
    {
        $conf = $this->ssh('cat /etc/nginx/nginx.conf', 'ng');
        preg_match("/#~([^\s]+)/", $conf, $m);
        return $m[1];
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

    public function getName(array $a): string
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
        if (empty($data['interface']['ListenPort'])) {
            if (empty($data['interface']['DNS'])) {
                $data['interface']['DNS'] = $this->getPacConf()['dns'] ?: $this->dns;
            }
        }
        foreach ($data['interface'] as $k => $v) {
            $conf[] = "$k = $v";
        }
        $domain = ($this->getPacConf()['domain'] ?: $this->ip) . ":" . getenv('WGPORT');
        if (!empty($data['peers'])) {
            foreach ($data['peers'] as $peer) {
                $conf[] = '';
                $conf[] = $peer['# PublicKey'] ? '# [Peer]' : '[Peer]';
                if (!empty($peer['Endpoint'])) {
                    $peer['Endpoint'] = $domain;
                }
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
                $ips[] = ip2long(explode('/', $v['AllowedIPs'] ?: $v['# AllowedIPs'])[0]);
            }
        }
        $ip_count = (1 << (32 - $bitmask)) - count($ips) - 1;
        for ($i = 1; $i < $ip_count; $i++) {
            $ip = $i + $server_ip;
            if (!in_array($ip, $ips)) {
                $client_ip = long2ip($ip);
                break;
            }
        }
        $public_server_key = trim($this->ssh("echo {$conf['interface']['PrivateKey']} | wg pubkey"));
        $private_peer_key  = trim($this->ssh("wg genkey"));
        $public_peer_key   = trim($this->ssh("echo $private_peer_key | wg pubkey"));

        $name = ($name ? "$name" : '') . time();

        $conf['peers'][] = [
            '## name'    => $name,
            'PublicKey'  => $public_peer_key,
            'AllowedIPs' => "$client_ip/32",
        ];
        $client_conf = [
            'interface' => [
                '## name'    => $name,
                'PrivateKey' => $private_peer_key,
                'Address'    => "$client_ip/32",
                'MTU'        => 1350,
            ],
            'peers' => [
                [
                    'PublicKey'           => $public_server_key,
                    'Endpoint'            => ($this->getPacConf()['domain'] ?: $this->ip) . ":" . getenv('WGPORT'),
                    'AllowedIPs'          => $ips_user ?: "0.0.0.0/0",
                    'PersistentKeepalive' => 20,
                ]
            ]
        ];
        $k = $this->saveClient($client_conf);
        $this->restartWG($this->createConfig($conf));
        $this->menu('client', "{$k}_-2");
    }

    public function deleteClient(int $client)
    {
        $clients = $this->readClients();
        unset($clients[$client]);
        $this->saveClients(array_values($clients));
    }

    public function saveClient(array $client)
    {
        $r = array_merge($this->readClients(), [$client]);
        $this->saveClients($r);
        return count($r) - 1;
    }

    public function syncPortClients()
    {
        $endpoint = $this->ip . ':' . getenv('WGPORT');
        $clients  = $this->readClients();
        foreach ($clients as $k => $v) {
            foreach ($v['peers'] as $i => $j) {
                $clients[$k]['peers'][$i]['Endpoint'] = $endpoint;
            }
        }
        $this->saveClients($clients);
    }

    public function saveClients(array $clients)
    {
        $domain = ($this->getPacConf()['domain'] ?: $this->ip) . ":" . getenv('WGPORT');
        foreach ($clients as $k => $v) {
            $clients[$k]['peers'][0]['Endpoint'] = $domain;
        }
        file_put_contents($this->clients, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function restartWG($conf_str)
    {
        $this->ssh("echo '$conf_str' > /etc/wireguard/wg0.conf");
        $this->ssh("wg syncconf wg0 <(wg-quick strip wg0)");
        return true;
    }

    public function disconnect(...$args)
    {
        $this->send($this->input['chat'], "disconnect: \n" . var_export($args, true) . "\n", $this->input['message_id']);
    }

    public function ssh($cmd, $service = 'wg', $wait = true)
    {
        try {
            $c = ssh2_connect($service, 22);
            if (empty($c)) {
                throw new Exception("no connection to $service: \n$cmd\n" . var_export($c, true));
            }
            $a = ssh2_auth_pubkey_file($c, 'root', '/ssh/key.pub', '/ssh/key');
            if (empty($a)) {
                throw new Exception("auth fail: \n$cmd\n" . var_export($a, true));
            }
            $s = ssh2_exec($c, $cmd);
            if (empty($s)) {
                throw new Exception("exec fail: \n$cmd\n" . var_export($s, true));
            }
            stream_set_blocking($s, $wait);
            $data = "";
            while ($buf = fread($s, 4096)) {
                $data .= $buf;
            }
            fclose($s);
            ssh2_disconnect($c);
        } catch (Exception | Error $e) {
            $this->send($this->input['chat'], $e->getMessage(), $this->input['message_id']);
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
            ] : [],
            CURLOPT_POSTFIELDS     => $data,
        ]);
        $res = curl_exec($ch);
        return json_decode($res, true);
    }

    public function setwebhook()
    {
        $ip = $this->ip;
        if (empty($ip)) {
            die('нет айпи');
        }
        echo "$ip\n";
        var_dump($this->request('setWebhook', [
            'url'         => "https://$ip/tlgrm?k={$this->key}",
            'certificate' => curl_file_create('/certs/self_public'),
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
                [
                    'command'     => 'id',
                    'description' => 'your id telegram',
                ],
            ]
        ];
        var_dump($this->request('setMyCommands', json_encode($data), 1));
    }

    public function send($chat, $text, ?int $to = 0, $button = false, $reply = false, $mode = 'HTML')
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
                    'parse_mode'               => $mode,
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
                'parse_mode'               => $mode,
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

    public function sendPhoto($chat, $id_url_cFile, $caption = false, $to = false)
    {
        return $this->request('sendPhoto', [
            'chat_id'             => $chat,
            'photo'               => $id_url_cFile,
            'caption'             => $caption,
            'reply_to_message_id' => $to,
            'parse_mode'          => 'html',
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

    public function update($chat, $message_id, $text, $button = false, $reply = false, $mode = 'HTML')
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
            'parse_mode'               => $mode,
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

    public function pin($chat, $message_id, $notnotify = true)
    {
        $data = [
            'chat_id'    => $chat,
            'message_id' => $message_id,
            'disable_notification' => $notnotify,
        ];
        return $this->request('pinChatMessage', $data);
    }

    public function unpin($chat, $message_id)
    {
        $data = [
            'chat_id'    => $chat,
            'message_id' => $message_id,
        ];
        return $this->request('unpinChatMessage', $data);
    }
}
