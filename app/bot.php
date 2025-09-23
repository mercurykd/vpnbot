<?php

class Bot
{
    public $input;
    public $adguard;
    public $update;
    public $ip;
    public $limit;
    public $key;
    public $file;
    public $dns;
    public $mtu;
    public $logs;
    public $reg;
    public $pool;
    public $hwid;

    public function __construct($key, $i18n)
    {
        $this->key      = $key;
        $this->api      = "https://api.telegram.org/bot$key/";
        $this->file     = "https://api.telegram.org/file/bot$key/";
        $this->clients  = '/config/clients.json';
        $this->clients1 = '/config/clients1.json';
        $this->pac      = '/config/pac.json';
        $this->ip       = getenv('IP');
        $this->i18n     = $i18n;
        $this->language = $this->getPacConf()['language'] ?: 'en';
        $this->dns      = '1.1.1.1, 8.8.8.8';
        $this->mtu      = 1350;
        $this->limit    = $this->getPacConf()['limitpage'] ?: 5;
        $this->adguard  = '/config/AdGuardHome.yaml';
        $this->update   = '/update/json';
        $this->hwid     = '/config/hwid.json';
        $this->logs = [
            'nginx_default_access',
            'nginx_domain_access',
            'upstream_access',
            'xray',
        ];
        $this->reg = '~' . implode('|', [
            'GET / HTTP',
            'GET /favicon.ico HTTP',
            preg_quote($this->getHashBot(1))
        ]) . '~';
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
            case preg_match('~^/menu (?P<type>pac|adguard|config|ss|lang|oc|naive|mirror|update)$~', $this->input['callback'], $m):
                $this->menu(type: $m['type'] ?? false, arg: $m['arg'] ?? false);
                break;
            case preg_match('~^/changeWG (\d+)$~', $this->input['callback'], $m):
                $this->changeWG($m[1]);
                break;
            case preg_match('~^/changeTransport(?: (\d+))?$~', $this->input['callback'], $m):
                $this->changeTransport($m[1] ?: false);
                break;
            case preg_match('~^/mirror$~', $this->input['message'], $m):
                $this->menu('mirror');
                break;
            case preg_match('~^/mainOutbound$~', $this->input['callback'], $m):
                $this->mainOutbound();
                break;
            case preg_match('~^/importIps (.+)$~', $this->input['callback'], $m):
                $this->importIps($m[1]);
                break;
            case preg_match('~^/switchBanIp$~', $this->input['callback'], $m):
                $this->switchBanIp();
                break;
            case preg_match('~^/switchMonthlyStats$~', $this->input['callback'], $m):
                $this->switchMonthlyStats();
                break;
            case preg_match('~^/setIpLimit$~', $this->input['callback'], $m):
                $this->setIpLimit();
                break;
            case preg_match('~^/hwidLimit$~', $this->input['callback'], $m):
                $this->hwidLimit();
                break;
            case preg_match('~^/toggleHwidLimit(?: (\w+))?$~', $this->input['callback'], $m):
                $this->toggleHwidLimit($m[1] ?? null);
                break;
            case preg_match('~^/setHwidDevices(?: (\w+))?$~', $this->input['callback'], $m):
                $this->setHwidDevices($m[1] ?? null);
                break;
            case preg_match('~^/hwidUser (\d+)(?:_(\d+))?$~', $this->input['callback'], $m):
                $this->hwidUser($m[1], $m[2] ?? 0);
                break;
            case preg_match('~^/hwidUserToggle (\d+)$~', $this->input['callback'], $m):
                $this->hwidUserToggle($m[1]);
                break;
            case preg_match('~^/hwidUserDefault (\d+)$~', $this->input['callback'], $m):
                $this->hwidUserDefault($m[1]);
                break;
            case preg_match('~^/setHwidUserLimit (\d+)$~', $this->input['callback'], $m):
                $this->setHwidUserLimit($m[1]);
                break;
            case preg_match('~^/hwidUserDel (\d+)_(\d+) (.+)$~', $this->input['callback'], $m):
                $this->hwidUserDel($m[1], $m[2], $m[3]);
                break;
            case preg_match('~^/searchLogs (.+)$~', $this->input['message'], $m):
                $this->searchLogs($m[1]);
                break;
            case preg_match('~^/searchLogs (.+?)(?:\s(.+?))?(?:\s(.+?))?(?:\s(.+?))?$~', $this->input['callback'], $m):
                $this->searchLogs($m[1], $m[2], $m[3], $m[4]);
                break;
            case preg_match('~^/switchSilence$~', $this->input['callback'], $m):
                $this->switchSilence();
                break;
            case preg_match('~^/switchScanIp$~', $this->input['callback'], $m):
                $this->switchScanIp();
                break;
            case preg_match('~^/autoScanTimeout$~', $this->input['callback'], $m):
                $this->autoScanTimeout();
                break;
            case preg_match('~^/autoupdate$~', $this->input['callback'], $m):
                $this->autoupdate();
                break;
            case preg_match('~^/ports$~', $this->input['callback'], $m):
                $this->ports();
                break;
            case preg_match('~^/analysisIp(?:\s(\d+))?$~', $this->input['callback'], $m):
                $this->analysisIp($m[1] ?: 0);
                break;
            case preg_match('~^/ipMenu$~', $this->input['callback'], $m):
                $this->ipMenu();
                break;
            case preg_match('~^/cleanDeny(?:\s(\d))?$~', $this->input['callback'], $m):
                $this->cleanDeny($m[1]);
                break;
            case preg_match('~^/denyList (.+?)(?:\s(\d))?$~', $this->input['callback'], $m):
                $this->denyList($m[1], $m[2] ?: 0);
                break;
            case preg_match('~^/cleanLogs (.+?)(?:\s(1))?$~', $this->input['callback'], $m):
                $this->cleanLogs($m[1], $m[2]);
                break;
            case preg_match('~^/allowIp (.+?) (\d+)(?:\s(\d+))?$~', $this->input['callback'], $m):
                $this->allowIp($m[1], $m[2], $m[3]);
                break;
            case preg_match('~^/searchIp (.+)$~', $this->input['callback'], $m):
                $this->searchIp($m[1]);
                break;
            case preg_match('~^/searchSuspiciousIp (.+)$~', $this->input['callback'], $m):
                $this->searchSuspiciousIp($m[1]);
                break;
            case preg_match('~^/denyIp (.+?)(?:\s(.+?)\s(\d+?)\s(\d))?$~', $this->input['callback'], $m):
                $this->denyIp($m[1], $m[2], $m[3], $m[4]);
                break;
            case preg_match('~^/whiteIp (.+?)(?:\s(.+?)\s(\d+?)\s(\d))?$~', $this->input['callback'], $m):
                $this->whiteIp($m[1], $m[2], $m[3], $m[4]);
                break;
            case preg_match('~^/adgFillAllowedClients(?: (\d+))?$~', $this->input['callback'], $m):
                $this->adgFillAllowedClients($m[1] ?: false);
                break;
            case preg_match('~^/appOutbound$~', $this->input['callback'], $m):
                $this->appOutbound();
                break;
            case preg_match('~^/domainsOutbound$~', $this->input['callback'], $m):
                $this->domainsOutbound();
                break;
            case preg_match('~^/finalOutbound$~', $this->input['callback'], $m):
                $this->finalOutbound();
                break;
            case preg_match('~^/processOutbound$~', $this->input['callback'], $m):
                $this->processOutbound();
                break;
            case preg_match('~^/offWarp$~', $this->input['callback'], $m):
                $this->offWarp();
                break;
            case preg_match('~^/addSubdomain$~', $this->input['callback'], $m):
                $this->addSubdomain();
                break;
            case preg_match('~^/addLinkDomain$~', $this->input['callback'], $m):
                $this->addLinkDomain();
                break;
            case preg_match('~^/id$~', $this->input['message'], $m):
                $this->send($this->input['chat'], "your id: {$this->input['from']}\nchat id: {$this->input['chat']}", $this->input['message_id']);
                break;
            case preg_match('~^/adguardChBr$~', $this->input['callback'], $m):
                $this->adguardChBr();
                break;
            case preg_match('~^/mtproto$~', $this->input['callback'], $m):
                $this->mtproto();
                break;
            case preg_match('~^/deleteAll (\w+)$~', $this->input['callback'], $m):
                $this->deleteAll($m[1]);
                break;
            case preg_match('~^/exportList (\w+)$~', $this->input['callback'], $m):
                $this->exportList($m[1]);
                break;
            case preg_match('~^/hidePort (\w+)$~', $this->input['callback'], $m):
                $this->hidePort($m[1]);
                break;
            case preg_match('~^/deleteYes (\w+)$~', $this->input['callback'], $m):
                $this->deleteYes($m[1]);
                break;
            case preg_match('~^/addCommunityFilter$~', $this->input['callback'], $m):
                $this->addCommunityFilter();
                break;
            case preg_match('~^/addLegizFilter$~', $this->input['callback'], $m):
                $this->addLegizFilter();
                break;
            case preg_match('~^/pacMenu (\d+)$~', $this->input['callback'], $m):
                $this->pacMenu($m[1]);
                break;
            case preg_match('~^/applyupdatebot$~', $this->input['callback'], $m):
                $this->applyupdatebot();
                break;
            case preg_match('~^/restart$~', $this->input['callback'], $m):
                $this->restart();
                break;
            case preg_match('~^/branches$~', $this->input['callback'], $m):
                $this->branches();
                break;
            case preg_match('~^/changeBranch (\d+)$~', $this->input['callback'], $m):
                $this->changeBranch($m[1]);
                break;
            case preg_match('~^/getMirror$~', $this->input['callback'], $m):
                $this->getMirror();
                break;
            case preg_match('~^/logs$~', $this->input['callback'], $m):
                $this->logs();
                break;
            case preg_match('~^/iodine$~', $this->input['callback'], $m):
                $this->iodine();
                break;
            case preg_match('~^/iodineDomain$~', $this->input['callback'], $m):
                $this->iodineDomain();
                break;
            case preg_match('~^/iodinePassword$~', $this->input['callback'], $m):
                $this->iodinePassword();
                break;
            case preg_match('~^/setIodineDomain (\w+)$~', $this->input['callback'], $m):
                $this->setIodineDomain($m[1]);
                break;
            case preg_match('~^/setIodinePassword (\w+)$~', $this->input['callback'], $m):
                $this->setIodinePassword($m[1]);
                break;
            case preg_match('~^/getLog (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->getLog(...explode('_', $m['arg']));
                break;
            case preg_match('~^/clearLog (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->clearLog(...explode('_', $m['arg']));
                break;
            case preg_match('~^/cleanLog$~', $this->input['callback'], $m):
                $this->cleanLog();
                break;
            case preg_match('~^/delLog (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->delLog(...explode('_', $m['arg']));
                break;
            case preg_match('~^/debug$~', $this->input['message'], $m):
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
            case preg_match('~^/defaultMTU (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->defaultMTU(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnet (?P<arg>-?\d+(?:_-?\d+)?(?:_\d)?)$~', $this->input['callback'], $m):
                $this->subnet(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnetAdd (?P<arg>-?\d+(?:_-?\d+)?(?:_-?\d+)?)$~', $this->input['callback'], $m):
                $this->subnetAdd(...explode('_', $m['arg']));
                break;
            case preg_match('~^/subnetDelete (?P<arg>-?\d+(?:_-?\d+)?(?:_-?\d+)?(?:_-?\d+)?)$~', $this->input['callback'], $m):
                $this->subnetDelete(...explode('_', $m['arg']));
                break;
            case preg_match('~^/addSubnets (?P<arg>-?\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->addSubnets(...explode('_', $m['arg']));
                break;
            case preg_match('~^/changeAllowedIps (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->changeAllowedIps(...explode('_', $m['arg']));
                break;
            case preg_match('~^/changeMTU (?P<arg>\d+(?:_(?:-)?\d+)?)$~', $this->input['callback'], $m):
                $this->changeMTU(...explode('_', $m['arg']));
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
            case preg_match('~^/changeCamouflage$~', $this->input['callback'], $m):
                $this->changeCamouflage();
                break;
            case preg_match('~^/changeOcDomain$~', $this->input['callback'], $m):
                $this->changeOcDomain();
                break;
            case preg_match('~^/changeOcPass$~', $this->input['callback'], $m):
                $this->changeOcPass();
                break;
            case preg_match('~^/changeNaiveUser$~', $this->input['callback'], $m):
                $this->changeNaiveUser();
                break;
            case preg_match('~^/changeNaiveSubdomain$~', $this->input['callback'], $m):
                $this->changeNaiveSubdomain();
                break;
            case preg_match('~^/changeNaivePass$~', $this->input['callback'], $m):
                $this->changeNaivePass();
                break;
            case preg_match('~^/changeOcDns$~', $this->input['callback'], $m):
                $this->changeOcDns();
                break;
            case preg_match('~^/addOcUser$~', $this->input['callback'], $m):
                $this->addOcUser();
                break;
            case preg_match('~^/changeOcExpose$~', $this->input['callback'], $m):
                $this->changeOcExpose();
                break;
            case preg_match('~^/addXrUser$~', $this->input['callback'], $m):
                $this->addXrUser();
                break;
            case preg_match('~^/renameXrUser (\d+)$~', $this->input['callback'], $m):
                $this->renameXrUser($m[1]);
                break;
            case preg_match('~^/resetXrUser (\d+)$~', $this->input['callback'], $m):
                $this->resetXrUser($m[1]);
                break;
            case preg_match('~^/resetXrStats$~', $this->input['callback'], $m):
                $this->resetXrStats();
                break;
            case preg_match('~^/v2ray$~', $this->input['callback'], $m):
                $this->v2ray();
                break;
            case preg_match('~^/checkdns$~', $this->input['callback'], $m):
                $this->checkdns();
                break;
            case preg_match('~^/adguardpsswd$~', $this->input['callback'], $m):
                $this->adguardpsswd();
                break;
            case preg_match('~^/setAdguardKey$~', $this->input['callback'], $m):
                $this->setAdguardKey();
                break;
            case preg_match('~^/addadmin$~', $this->input['callback'], $m):
                $this->enterAdmin();
                break;
            case preg_match('~^/enterPage$~', $this->input['callback'], $m):
                $this->enterPage();
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
            case preg_match('~^/deloc (\d+)$~', $this->input['callback'], $m):
                $this->deloc($m[1]);
                break;
            case preg_match('~^/userXr (\d+)$~', $this->input['callback'], $m):
                $this->userXr($m[1]);
                break;
            case preg_match('~^/choiceTemplate (.+)$~', $this->input['callback'], $m):
                $this->choiceTemplate($m[1]);
                break;
            case preg_match('~^/templateUser (\w+) (\d+)$~', $this->input['callback'], $m):
                $this->templateUser($m[1], $m[2]);
                break;
            case preg_match('~^/timerXr (\d+)$~', $this->input['callback'], $m):
                $this->timerXr($m[1]);
                break;
            case preg_match('~^/switchXr (\d+)$~', $this->input['callback'], $m):
                $this->switchXr($m[1]);
                break;
            case preg_match('~^/delxr (\d+)$~', $this->input['callback'], $m):
                $this->delxr($m[1]);
                break;
            case preg_match('~^/listXr (\d+)$~', $this->input['callback'], $m):
                $this->listXr($m[1]);
                break;
            case preg_match('~^/switchTorrent (\d+)$~', $this->input['callback'], $m):
                $this->switchTorrent($m[1]);
                break;
            case preg_match('~^/switchEndpoint (\d+)$~', $this->input['callback'], $m):
                $this->switchEndpoint($m[1]);
                break;
            case preg_match('~^/switchAmnezia (-?\d+)$~', $this->input['callback'], $m):
                $this->switchAmnezia($m[1]);
                break;
            case preg_match('~^/switchExchange (\d+)$~', $this->input['callback'], $m):
                $this->switchExchange($m[1]);
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
            case preg_match('~^/qrXray (\d+)(?:_(\d+))?$~', $this->input['callback'], $m):
                $this->qrXray($m[1], $m[2] ?: false);
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
            case preg_match('~^/addNipdomain$~', $this->input['callback'], $m):
                $this->addNipdomain();
                break;
            case preg_match('~^/(?P<action>change|delete)(?P<typelist>\w+) (?P<arg>\d+)$~', $this->input['callback'], $m):
                $this->listPacChange($m['typelist'], $m['action'], $m['arg']);
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
            case preg_match('~^/warp$~', $this->input['callback'], $m):
                $this->warp();
                break;
            case preg_match('~^/warpPlus$~', $this->input['callback'], $m):
                $this->warpPlus();
                break;
            case preg_match('~^/xray(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xray($m[1] ?: 0);
                break;
            case preg_match('~^/xtlsblock(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlsblock($m[1] ?: 0);
                break;
            case preg_match('~^/routes(?: (\d+))?$~', $this->input['callback'], $m):
                $this->routes($m[1] ?: 0);
                break;
            case preg_match('~^/xtlswarp(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlswarp($m[1] ?: 0);
                break;
            case preg_match('~^/xtlsproxy(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlsproxy($m[1] ?: 0);
                break;
            case preg_match('~^/xtlsapp(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlsapp($m[1] ?: 0);
                break;
            case preg_match('~^/xtlsprocess(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlsprocess($m[1] ?: 0);
                break;
            case preg_match('~^/xtlssubnet(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlssubnet($m[1] ?: 0);
                break;
            case preg_match('~^/xtlsrulesset(?: (\d+))?$~', $this->input['callback'], $m):
                $this->xtlsrulesset($m[1] ?: 0);
                break;
            case preg_match('~^/templateCopy (\w+)(?: (.+))?$~', $this->input['callback'], $m):
                $this->templateCopy($m[1], $m[2]);
                break;
            case preg_match('~^/delTemplate (\w+)(?: (.+))?$~', $this->input['callback'], $m):
                $this->delTemplate($m[1], $m[2]);
                break;
            case preg_match('~^/downloadOrigin (\w+)$~', $this->input['callback'], $m):
                $this->downloadOrigin($m[1]);
                break;
            case preg_match('~^/downloadTemplate (\w+)(?: (.+))?$~', $this->input['callback'], $m):
                $this->downloadTemplate($m[1], $m[2]);
                break;
            case preg_match('~^/defaultTemplate (\w+)(?: (.+))?$~', $this->input['callback'], $m):
                $this->defaultTemplate($m[1], $m[2]);
                break;
            case preg_match('~^/templates (\w+)$~', $this->input['callback'], $m):
                $this->templates($m[1]);
                break;
            case preg_match('~^/templateAdd (\w+)$~', $this->input['callback'], $m):
                $this->templateAdd($m[1]);
                break;
            case preg_match('~^/generateSecretXray$~', $this->input['callback'], $m):
                $this->generateSecretXray();
                break;
            case preg_match('~^/changeFakeDomain$~', $this->input['callback'], $m):
                $this->changeFakeDomain();
                break;
            case preg_match('~^/autoCleanLogs$~', $this->input['callback'], $m):
                $this->autoCleanLogs();
                break;
            case preg_match('~^/selfFakeDomain$~', $this->input['callback'], $m):
                $this->selfFakeDomain();
                break;
            case preg_match('~^/changeTGDomain$~', $this->input['callback'], $m):
                $this->changeTGDomain();
                break;
            case preg_match('~^/include (\w+)$~', $this->input['callback'], $m):
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
            case preg_match('~^/addOverrideHtml$~', $this->input['callback'], $m):
                $this->addOverrideHtml();
                break;
            case preg_match('~^/export$~', $this->input['callback'], $m):
                $this->pinBackup();
                break;
            case preg_match('~^/import$~', $this->input['callback'], $m):
                $this->import();
                break;
            case preg_match('~^/importList (\w+)$~', $this->input['callback'], $m):
                $this->importList($m[1]);
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
            "@{$this->input['username']} enter key or 0 for stop mtproto",
            $this->input['message_id'],
            reply: 'enter key or 0 for stop mtproto',
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
        file_put_contents('/config/mtprotosecret', $secret);
        $this->restartTG();
        $this->mtproto();
    }

    public function setTelegramDomain($domain)
    {
        file_put_contents('/config/mtprotodomain', $domain);
        $this->restartTG();
        $this->mtproto();
    }

    public function restartTG()
    {
        $secret     = file_get_contents('/config/mtprotosecret');
        $fakedomain = file_get_contents('/config/mtprotodomain') ?: 'vk.com';
        $this->ssh('pkill mtproto-proxy', 'tg');
        if (preg_match('~^\w{32}$~', $secret)) {
            $p = getenv('TGPORT');
            $this->ssh("mtproto-proxy --domain $fakedomain -u nobody -H $p --nat-info 10.10.0.8:{$this->ip} -S $secret --aes-pwd /proxy-secret /proxy-multi.conf -M 1 >/dev/null 2>&1 &", 'tg');
        }
    }

    public function restartXray($c, $norestart = false)
    {
        $c['inbounds'][0]['settings']['clients'] = array_values($c['inbounds'][0]['settings']['clients']);
        $c['log']['access'] = '/logs/xray';
        foreach ($c['inbounds'] as $v) {
            if ($v['tag'] == 'api') {
                $inbound = true;
                break;
            }
        }
        if (empty($inbound)) {
            $c['inbounds'][] = [
                "listen"   => "127.0.0.1",
                "port"     => 8080,
                "protocol" => "dokodemo-door",
                "settings" => [
                    "address" => "127.0.0.1"
                ],
                "tag" => "api"
            ];
        }
        foreach ($c['routing']['rules'] as $v) {
            if ($v['outboundTag'] == 'api') {
                $rule = true;
                break;
            }
        }
        if (empty($rule)) {
            $c['routing']['rules'][] = [
                "inboundTag"  => ["api"],
                "outboundTag" => "api",
                "type"        => "field"
            ];
        }
        $c['stats'] = new stdClass();
        $c['api'] = [
            'services' => ['StatsService'],
            'tag'      => 'api'
        ];
        $l = new stdClass();
        $l->{'0'} = [
            "statsUserUplink"   => true,
            "statsUserDownlink" => true
        ];
        $c['policy']['levels'] = $l;
        $c['policy']['system'] = [
            "statsInboundUplink"    => true,
            "statsInboundDownlink"  => true,
            "statsOutboundUplink"   => true,
            "statsOutboundDownlink" => true
        ];
        if (empty($norestart)) {
            $this->collectSession();
            file_put_contents('/config/xray.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->ssh('pkill xray', 'xr');
            $this->ssh('xray run -config /xray.json > /dev/null 2>&1 &', 'xr');
        } else {
            file_put_contents('/config/xray.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
    }

    public function collectSession() {
        $p = $this->getXrayStats();
        $p['global'] = [
            'download' => $p['global']['download'] + $p['session']['download'],
            'upload'   => $p['global']['upload'] + $p['session']['upload'],
        ];
        $p['session'] = [
            'download' => 0,
            'upload'   => 0,
        ];
        foreach ($p['users'] as $k => $v) {
            $p['users'][$k]['global']['download']  += $v['session']['download'];
            $p['users'][$k]['session']['download']  = 0;
            $p['users'][$k]['global']['upload']    += $v['session']['upload'];
            $p['users'][$k]['session']['upload']    = 0;
        }
        $this->setXrayStats($p);
    }

    public function linkMtproto()
    {
        $s  = file_get_contents('/config/mtprotosecret');
        $p  = getenv('TGPORT');
        $d  = trim(file_get_contents('/config/mtprotodomain') ?: 'vk.com');
        $d  = exec("echo $d | tr -d '\\n' | xxd -ps -c 200");
        $ip = $this->getPacConf()['domain'] ?: $this->ip;
        return "https://t.me/proxy?server=$ip&port=$p&secret=ee$s$d";
    }

    public function mtproto()
    {
        $d      = file_get_contents('/config/mtprotodomain') ?: 'vk.com';
        $st     = $this->ssh('pgrep mtproto-proxy', 'tg') ? 'on' : 'off';
        $text[] = "Menu -> MTProto\n";
        $text[] = "status: $st\n";
        $text[] = "fake domain: <code>$d</code>\n";
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
                'text'          => $this->i18n('changeFakeDomain'),
                'callback_data' => "/changeTGDomain",
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

    public function ssPswdCheck()
    {
        $c = $this->getSSConfig();
        if (empty($c['password']) || ($c['password'] == 'test')) {
            $this->sspwdch(password_hash(time(), PASSWORD_DEFAULT), 1);
        }
    }

    public function changeCamouflage()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter camouflage key",
            $this->input['message_id'],
            reply: 'enter camouflage key',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chockey',
            'args'           => [],
        ];
    }

    public function changeOcDomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter subdomain",
            $this->input['message_id'],
            reply: 'enter subdomain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chOcSubdomain',
            'args'           => [],
        ];
    }

    public function changeOcDns()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter dns",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chocdns',
            'args'           => [],
        ];
    }

    public function changeOcPass()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter pass",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chocpass',
            'args'           => [],
        ];
    }

    public function changeNaiveUser()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter login",
            $this->input['message_id'],
            reply: 'enter login',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chnplogin',
            'args'           => [],
        ];
    }

    public function changeNaiveSubdomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter subdomain",
            $this->input['message_id'],
            reply: 'enter subdomain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'chNpSubdomain',
            'args'           => [],
        ];
    }

    public function changeNaivePass()
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
            'callback'       => 'chnppass',
            'args'           => [],
        ];
    }

    public function addOcUser()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter name",
            $this->input['message_id'],
            reply: 'enter name',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'addocus',
            'args'           => [],
        ];
    }

    public function addXrUser()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter name",
            $this->input['message_id'],
            reply: 'enter name:uuid [,name:uuid]',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'addxrus',
            'args'           => [],
        ];
    }

    public function renameXrUser($i)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter name",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'renXrUs',
            'args'           => [$i],
        ];
    }

    public function addOverrideHtml()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} attach html",
            $this->input['message_id'],
            reply: 'attach html',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setOverrideHtml',
            'args'           => [],
        ];
    }

    public function setOverrideHtml()
    {
        $r = $this->request('getFile', ['file_id' => $this->input['file_id']]);
        if (!empty($f = file_get_contents($this->file . $r['result']['file_path']))) {
            file_put_contents('/app/webapp/override.html', $f);
        }
    }

    public function restartOcserv($conf)
    {
        file_put_contents('/config/ocserv.conf', $conf);
        $this->ssh('pkill ocserv', 'oc');
        $this->ssh('ocserv -c /etc/ocserv/ocserv.conf', 'oc');
    }

    public function restartNaive()
    {
        $pac = $this->getPacConf();
        $this->ssh('pkill caddy', 'np');
        $c = file_get_contents('/config/Caddyfile');
        $t = preg_replace('~^(\t+)?basic_auth[^\n]+~sm', '$1basic_auth ' . ($pac['naive']['user'] ?? '_') . ' ' . ($pac['naive']['pass'] ?? '__'), $c);
        file_put_contents('/config/Caddyfile', $t);
        $this->ssh('caddy run -c /config/Caddyfile > /dev/null 2>&1 &', 'np', false);
    }

    public function chocdns($dns)
    {
        $c = file_get_contents('/config/ocserv.conf');
        $t = preg_replace('~^dns[^\n]+~sm', "dns = $dns", $c);
        $this->restartOcserv($t);
        $this->menu('oc');
    }

    public function chOcSubdomain($domain)
    {
        $pac = $this->getPacConf();
        if (empty($domain)) {
            unset($pac["oc_domain"]);
        } else {
            $pac["oc_domain"] = $domain;
        }
        $this->setPacConf($pac);
        $this->chocdomain($pac['domain']);
        $this->setUpstreamDomainOcserv($pac['domain']);
        $this->menu('oc');
    }

    public function chNpSubdomain($domain)
    {
        $pac = $this->getPacConf();
        if (!empty($data)) {
            unset($pac['np_domain']);
        } else {
            $pac['np_domain'] = $domain;
        }
        $this->setPacConf($pac);
        $this->restartNaive();
        $this->setUpstreamDomainNaive($pac['domain']);
        $this->menu('naive');
    }

    public function chnplogin($user)
    {
        $pac = $this->getPacConf();
        $pac['naive']['user'] = $user;
        $this->setPacConf($pac);
        $this->restartNaive();
        $this->menu('naive');
    }

    public function chnppass($pass)
    {
        $pac = $this->getPacConf();
        $pac['naive']['pass'] = $pass;
        $this->setPacConf($pac);
        $this->restartNaive();
        $this->menu('naive');
    }

    public function chockey($pass)
    {
        $c = file_get_contents('/config/ocserv.conf');
        $t = preg_replace('~^camouflage_secret[^\n]+~sm', "camouflage_secret = \"$pass\"", $c);
        $this->restartOcserv($t);
        $this->menu('oc');
    }

    public function chocdomain($domain)
    {
        $oc = $this->getHashSubdomain('oc');
        $c  = file_get_contents('/config/ocserv.conf');
        $t  = preg_replace('~^default-domain[^\n]+~sm', "default-domain = $oc.$domain", $c);
        $this->restartOcserv($t);
    }

    public function chocpass($pass)
    {
        $pac = $this->getPacConf();
        $pac['ocserv'] = $pass;
        $this->setPacConf($pac);
        $clients = $this->getClientsOc();
        foreach ($clients as $k => $v) {
            $this->ssh("echo '$pass' | ocpasswd -c /etc/ocserv/ocserv.passwd $v", 'oc');
        }
        $this->menu('oc');
    }

    public function sspwdch($pass, $nomenu = false)
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

        if (empty($nomenu)) {
            $this->menu('ss');
        }
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
            $l['server']      = 'up';
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

    public function importList($type)
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
            'callback'       => 'importListFile',
            'args'           => [$type],
        ];
    }

    public function importListFile($text = '', $type)
    {
        $r = $this->request('getFile', ['file_id' => $this->input['file_id']]);
        $f = file_get_contents($this->file . $r['result']['file_path']);
        if (!empty($f)) {
            foreach (explode("\n", $f) as $v) {
                if (!empty($s = trim($v))) {
                    $t = explode(';', $s);
                    if ($type == 'rulessetlist') {
                        if (preg_match('~^.+:.+:https?://.+~', $t[0])) {
                            $list[$t[0]] = (bool) $t[1];
                        }
                    } else {
                        $list[$t[0]] = (bool) $t[1];
                    }
                }
            }
            $p = $this->getPacConf();
            $p[$type] = $list;
            $this->setPacConf($p);
        }
        $this->backXtlsList($type);
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
        $period = 10;
        while (true) {
            $this->shutdownClient();
            $this->shutdownClientXr();
            $this->checkVersion();
            $this->checkBackup($period);
            $this->checkLogs($period);
            $this->checkResetXrayStats($period);
            $this->checkCert();
            $this->autoAnalyzeLogs();
            $this->xrayStatsUser();
            sleep($period);
        }
    }

    public function xrayStatsUser()
    {
        try {
            $x  = $this->getXray();
            $td = json_decode($this->ssh('xray api stats --server=127.0.0.1:8080 -name "inbound>>>vless_tls>>>traffic>>>downlink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
            $tu = json_decode($this->ssh('xray api stats --server=127.0.0.1:8080 -name "inbound>>>vless_tls>>>traffic>>>uplink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
            $p  = $this->getXrayStats();
            $p['session'] = [
                'download' => $td,
                'upload'   => $tu,
            ];
            if (!empty($users = $x['inbounds'][0]['settings']['clients'])) {
                $tmp = [];
                foreach ($users as $k => $v) {
                    $d = json_decode($this->ssh('xray api stats --server=127.0.0.1:8080 -name "user>>>' . $v['email'] . '>>>traffic>>>downlink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
                    $u = json_decode($this->ssh('xray api stats --server=127.0.0.1:8080 -name "user>>>' . $v['email'] . '>>>traffic>>>uplink" 2>&1', 'xr'), true)['stat']['value'] ?: 0;
                    $tmp[$k] = [
                        'session' => [
                            'download' => $d,
                            'upload'   => $u,
                        ],
                        'global' => [
                            'download' => $p['users'][$k]['global']['download'],
                            'upload'   => $p['users'][$k]['global']['upload'],
                        ]
                    ];
                }
                $p['users'] = $tmp;
            }
            $this->setXrayStats($p);
        } catch (\Throwable $th) {
        }
    }

    public function autoAnalyzeLogs()
    {
        try {
            $pac = $this->getPacConf();
            if (!empty($pac['autoscan'])) {
                require __DIR__ . '/config.php';
                if (!empty($c['admin']) && (empty($this->time3) || ((time() - $this->time3) > $pac['autoscan_timeout']))) {
                    $this->time3 = time();
                    $r = $this->analysisIp(return: 1);
                    if (!empty($r)) {
                        foreach ($r as $k => $v) {
                            foreach ($v as $i) {
                                $t[$i['title']][$k] = 1;
                            }
                        }
                        foreach ($t as $k => $v) {
                            $text .= "\n" . count($v) . " $k";
                        }
                        if (!empty($pac['autodeny'])) {
                            $this->denyIp(array_keys($r));
                            $ban = count(array_keys($r));
                            foreach (array_keys($r) as $v) {
                                $ips[] = [[
                                    'text'          => $v,
                                    'callback_data' => "/searchLogs $v",
                                ]];
                            }
                        }
                        if ($pac['silence'] == 0 || $pac['silence'] == 1) {
                            foreach ($c['admin'] as $k => $v) {
                                $this->send($v, "suspicious ips found: $text" . ($ban ? "\nbanned:$ban" : ''), button: $ips ?: [[
                                    [
                                        'text'          => $this->i18n('analyze'),
                                        'callback_data' => '/analysisIp',
                                    ],
                                ]], disable_notification: $pac['silence'] ? true : false);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            file_put_contents('/logs/php_error', $e->getMessage());
        }
    }

    public function checkBackup($delta)
    {
        $c = $this->getPacConf();
        if (!empty($c['backup'])) {
            $now = strtotime(date('Y-m-d H:i:s'));
            [$start, $period] = explode('/', $c['backup']);
            $start  = strtotime(trim($start));
            $period = strtotime(trim($period), 0);
            if (
                !empty($start)
                && !empty($period)
                && empty($this->backup)
                && $now - $start >= 0
                && (($now - $start) % $period < $delta)
            ) {
                $this->pinBackup();
            }
        }
    }

    public function checkResetXrayStats($delta)
    {
        $pac = $this->getPacConf();
        if (!empty($pac['reset_monthly'])) {
            $now    = strtotime(date('Y-m-d H:i:s'));
            $start  = strtotime('first day of previous month midnight');
            $period = strtotime('1 month', 0);
            if (
                !empty($start)
                && !empty($period)
                && empty($this->backup)
                && $now - $start >= 0
                && (($now - $start) % $period < $delta)
            ) {
                $this->resetXrStats(1);
                require __DIR__ . '/config.php';
                foreach ($c['admin'] as $admin) {
                    $this->send($admin, "vless: reset stats");
                }
            }
        }
    }

    public function checkLogs($delta)
    {
        $c = $this->getPacConf();
        if (!empty($c['autocleanlogs'])) {
            $now = strtotime(date('Y-m-d H:i:s'));
            [$start, $period] = explode('/', $c['autocleanlogs']);
            $start  = strtotime(trim($start));
            $period = strtotime(trim($period), 0);
            if (
                !empty($start)
                && !empty($period)
                && empty($this->backup)
                && $now - $start >= 0
                && (($now - $start) % $period < $delta)
            ) {
                $this->cleanLog();
            }
        }
    }

    public function cleanQueue(): void
    {
        $r = $this->request('deleteWebhook', []);
        $r = $this->request('getUpdates', ['offset' => -1]);
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

    public function pinBackup($file = false)
    {
        require __DIR__ . '/config.php';
        $conf = $this->getPacConf();
        $bot  = preg_replace('~[\W]~iu', '_', $this->request('getMyName', [])['result']['name']);
        $json = $this->export();
        if (!empty($file)) {
            file_put_contents($file, $json);
        }
        if (!empty($conf['pinbackup'])) {
            $this->pinAdmin($conf['pinbackup'], 1);
        }
        $conf['pinbackup'] = $this->upload("{$bot}_export_" . date('d_m_Y_H_i') . '.json', $json, $c['admin'][0])['result']['message_id'];
        $this->setPacConf($conf);
        $this->pinAdmin($conf['pinbackup']);
    }

    public function checkVersion()
    {
        try {
            require __DIR__ . '/config.php';
            if (!empty($c['admin']) && (empty($this->time) || ((time() - $this->time) > 3600))) {
                $this->time = time();
                $current    = file_get_contents('/version');
                $b          = exec('git -C / rev-parse --abbrev-ref HEAD');
                $last       = file_get_contents("https://raw.githubusercontent.com/mercurykd/vpnbot/$b/version");
                if (!empty($last) && $last != $this->last && $last != $current) {
                    $this->last = $last;
                    $diff       = array_slice(explode("\n", $last), 0, count(explode("\n", $last)) - count(explode("\n", $current)));
                    $diff       = array_slice($diff, 0, 10);
                    if (!empty($diff)) {
                        exec('git -C / fetch');
                        foreach ($c['admin'] as $k => $v) {
                            $this->send($v, implode("\n", $diff), 0, [
                                [
                                    [
                                        'text'    => 'changelog',
                                        'web_app' => ['url' => "https://raw.githubusercontent.com/mercurykd/vpnbot/$b/version"],
                                    ],
                                    [
                                        'text'          => $this->i18n('update bot'),
                                        'callback_data' => "/applyupdatebot",
                                    ],
                                ]
                            ]);
                        }
                        if ($this->getPacConf()['autoupdate']) {
                            $this->input['chat'] = $this->input['from'] = $c['admin'][0];
                            $this->applyupdatebot();
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    public function checkCert()
    {
        try {
            require __DIR__ . '/config.php';
            if (!empty($c['admin']) && date('H') == 12 && (empty($this->time2) || ((time() - $this->time2) > 4600))) {
                $this->time2 = time();
                $cert = $this->expireCert();
                if (!empty($cert) && $cert - 60 * 60 * 24 * 14 < time()) {
                    foreach ($c['admin'] as $k => $v) {
                        $this->send($v, "certificate expire: " . date('Y-m-d H:i:s', $cert));
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
                if (!empty($i)) {
                    break;
                }
                $i++;
            }
        }
        return trim($text) ?: '♾';
    }

    public function shutdownClient()
    {
        try {
            for ($i=0; $i < 2; $i++) {
                $this->wg = $i;
                $clients  = $this->readClients();
                if ($clients) {
                    foreach ($clients as $k => $v) {
                        if (!empty($v['interface']['## time'])) {
                            if (strtotime($v['interface']['## time']) < time()) {
                                $this->switchClient($k);
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
    }

    public function shutdownClientXr()
    {
        try {
            $c = $this->getXray();
            foreach ($c['inbounds'][0]['settings']['clients'] as $k => $v) {
                if (!empty($v['time']) && ($v['time'] < time())) {
                    $this->switchXr($k, 1);
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
        return json_decode(file_get_contents($this->getInstanceWG(1) ? $this->clients1 : $this->clients), true) ?: [];
    }

    public function export()
    {
        $this->wg = 0;
        $wg = [
            'server'  => $this->readConfig(),
            'clients' => json_decode(file_get_contents($this->clients), true) ?: [],
        ];
        $this->wg = 1;
        $wg1 = [
            'server'  => $this->readConfig(),
            'clients' => json_decode(file_get_contents($this->clients1), true) ?: [],
        ];
        $conf = [
            'wg'  => $wg,
            'wg1' => $wg1,
            'ad'  => yaml_parse_file($this->adguard),
            'pac' => $this->getPacConf(),
            'hwid' => file_exists($this->hwid) ? (json_decode(file_get_contents($this->hwid), true) ?: []) : [],
            'ssl' => file_exists('/certs/cert_private') && preg_match('~BEGIN PRIVATE KEY~', file_get_contents('/certs/cert_private')) ? [
                'private' => file_get_contents('/certs/cert_private'),
                'public'  => file_get_contents('/certs/cert_public'),
            ] : false,
            'mtproto'       => file_get_contents('/config/mtprotosecret'),
            'mtprotodomain' => file_get_contents('/config/mtprotodomain'),
            'xray'          => $this->getXray(),
            'oc'            => file_get_contents('/config/ocserv.conf'),
            'ocu'           => file_get_contents('/config/ocserv.passwd'),
            'ss'            => $this->getSSConfig(),
            'sl'            => $this->getSSLocalConfig(),
            'xraystats'     => $this->getXrayStats(),
        ];
        return json_encode($conf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

    public function importFile($file = false)
    {
        if (!empty($file)) {
            $json = json_decode(file_get_contents($file), true);
        } else {
            $r    = $this->request('getFile', ['file_id' => $this->input['file_id']]);
            $json = json_decode(file_get_contents($this->file . $r['result']['file_path']), true);
        }
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
            // pac
            if (!empty($json['pac'])) {
                $out[] = 'update pac';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                if ($this->getPacConf()['amnezia'] != $json['pac']['amnezia']) {
                    $switch_amnezia = 1;
                }
                if ($this->getPacConf()['wg1_amnezia'] != $json['pac']['wg1_amnezia']) {
                    $switch_wg1amnezia = 1;
                }
                $this->setPacConf($json['pac']);
                $out[] = 'update naiveproxy';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->restartNaive();
                $this->pacUpdate('1');
            }
            // wg
            if (!empty($json['wg'])) {
                $out[] = 'update wireguard';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->wg = 0;
                $this->saveClients($json['wg']['clients']);
                $this->restartWG($this->createConfig($json['wg']['server']), $switch_amnezia);
                $this->iptablesWG();
            }
            // wg1
            if (!empty($json['wg1'])) {
                $out[] = 'update wireguard 1';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->wg = 1;
                $this->saveClients($json['wg1']['clients']);
                $this->restartWG($this->createConfig($json['wg1']['server']), $switch_wg1amnezia);
                $this->iptablesWG();
            }
            // ad
            if (!empty($json['ad'])) {
                $out[] = 'update adguard';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->stopAd();
                yaml_emit_file($this->adguard, $json['ad']);
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
                file_put_contents('/config/mtprotosecret', $json['mtproto']);
                file_put_contents('/config/mtprotodomain', $json['mtprotodomain'] ?: '');
                $this->restartTG();
            }
            // hwid
            if (array_key_exists('hwid', $json)) {
                $out[] = 'update hwid devices';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $dir = dirname($this->hwid);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                $data = is_array($json['hwid']) ? $json['hwid'] : [];
                file_put_contents($this->hwid, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            // xray
            if (!empty($json['xray'])) {
                $out[] = 'update xray';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->restartXray($json['xray']);
                $this->adguardXrayClients();
                $this->setUpstreamDomain($json['pac']['transport'] != 'Reality' ? 't' : ($json['pac']['reality']['domain'] ?: $json['xray']['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]));
            }
            // xraystats
            if (!empty($json['xraystats'])) {
                $out[] = 'update xray stats';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $this->setXrayStats($json['xraystats']);
            }
            // ocserv
            if (!empty($json['oc'])) {
                $out[] = 'update ocserv';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                file_put_contents('/config/ocserv.passwd', $json['ocu']);
                $this->restartOcserv($json['oc']);
            }
            if (!empty($json['pac']['domain'])) {
                $this->setUpstreamDomainOcserv($json['pac']['domain']);
                $this->setUpstreamDomainNaive($json['pac']['domain']);
            }
            // nginx
            $out[] = 'reset nginx';
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));

            $this->cloakNginx();

            $out[] = "end import";
            $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
            $this->language = $this->getPacConf()['language'] ?: 'en';
            $this->limit    = $this->getPacConf()['limitpage'] ?: 5;
            if (empty($file)) {
                sleep(3);
                $this->menu();
            }
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

    public function switchAmnezia($page = 0)
    {
        $c = $this->getPacConf();
        $amnezia = $c[$this->getInstanceWG(1) . 'amnezia'] = $c[$this->getInstanceWG(1) . 'amnezia'] ? 0 : 1;
        $this->setPacConf($c);

        $pk = $this->presharedKey();
        $ak = $this->amneziaKeys();
        $clients = $this->readClients();
        foreach ($clients as $k => $v) {
            if (!empty($amnezia)) {
                $clients[$k]['peers'][0]['PresharedKey'] = $pk;
                $clients[$k]['interface']['Jc']          = $ak['Jc'];
                $clients[$k]['interface']['Jmin']        = $ak['Jmin'];
                $clients[$k]['interface']['Jmax']        = $ak['Jmax'];
                $clients[$k]['interface']['S1']          = $ak['S1'];
                $clients[$k]['interface']['S2']          = $ak['S2'];
                $clients[$k]['interface']['H1']          = $ak['H1'];
                $clients[$k]['interface']['H2']          = $ak['H2'];
                $clients[$k]['interface']['H3']          = $ak['H3'];
                $clients[$k]['interface']['H4']          = $ak['H4'];
            } else {
                unset($clients[$k]['peers'][0]['PresharedKey']);
                unset($clients[$k]['interface']['Jc']);
                unset($clients[$k]['interface']['Jmin']);
                unset($clients[$k]['interface']['Jmax']);
                unset($clients[$k]['interface']['S1']);
                unset($clients[$k]['interface']['S2']);
                unset($clients[$k]['interface']['H1']);
                unset($clients[$k]['interface']['H2']);
                unset($clients[$k]['interface']['H3']);
                unset($clients[$k]['interface']['H4']);
            }
        }
        $this->saveClients($clients);

        $wg = $this->readConfig();
        if (!empty($amnezia)) {
            $wg['interface']['Jc']   = $ak['Jc'];
            $wg['interface']['Jmin'] = $ak['Jmin'];
            $wg['interface']['Jmax'] = $ak['Jmax'];
            $wg['interface']['S1']   = $ak['S1'];
            $wg['interface']['S2']   = $ak['S2'];
            $wg['interface']['H1']   = $ak['H1'];
            $wg['interface']['H2']   = $ak['H2'];
            $wg['interface']['H3']   = $ak['H3'];
            $wg['interface']['H4']   = $ak['H4'];
        } else {
            unset($wg['interface']['Jc']);
            unset($wg['interface']['Jmin']);
            unset($wg['interface']['Jmax']);
            unset($wg['interface']['S1']);
            unset($wg['interface']['S2']);
            unset($wg['interface']['H1']);
            unset($wg['interface']['H2']);
            unset($wg['interface']['H3']);
            unset($wg['interface']['H4']);
        }

        foreach ($wg['peers'] as $k => $v) {
            if (!empty($amnezia)) {
                $wg['peers'][$k]['PresharedKey'] = $pk;
            } else {
                unset($wg['peers'][$k]['PresharedKey']);
            }
        }
        $this->restartWG($this->createConfig($wg), 1);
        $this->menu('wg', $page);
    }

    public function switchTorrent($page = 0, $restart = false)
    {
        $c = $this->getPacConf();
        $c[$this->getInstanceWG(1) . 'blocktorrent'] = $c[$this->getInstanceWG(1) . 'blocktorrent'] ? 0 : 1;
        $this->setPacConf($c);
        $this->iptablesWG();
        $this->answer($this->input['callback_id'], 'доступ к торрентам ' . ($c[$this->getInstanceWG(1) . 'blocktorrent'] ? 'заблокирован' : 'разблокирован'), true);
        $this->menu('wg', $page);
    }

    public function switchEndpoint($page = 0)
    {
        $c = $this->getPacConf();
        $c[$this->getInstanceWG(1) . 'endpoint'] = $c[$this->getInstanceWG(1) . 'endpoint'] ? 0 : 1;
        $this->setPacConf($c);
        $this->menu('wg', $page);
    }

    public function iptablesWG()
    {
        $c = $this->getPacConf();
        $this->ssh('iptables -F', $this->getInstanceWG());
        if ($c['exchange']) {
            $this->ssh('bash /block_exchange.sh', $this->getInstanceWG());
        }
        if ($c['blocktorrent']) {
            $this->ssh('bash /block_torrent.sh', $this->getInstanceWG());
        }
    }
    public function switchExchange($page)
    {
        $c = $this->getPacConf();
        $c[$this->getInstanceWG(1) . 'exchange'] = $c[$this->getInstanceWG(1) . 'exchange'] ? 0 : 1;
        $this->setPacConf($c);
        $this->iptablesWG();
        $this->answer($this->input['callback_id'], 'обмен между пользователями ' . ($c[$this->getInstanceWG(1) . 'exchange'] ? 'заблокирован' : 'разблокирован'), true);
        $this->menu('wg', $page);
    }

    public function blinkmenuswitch()
    {
        $c = $this->getPacConf();
        $c['blinkmenu'] = $c['blinkmenu'] ? 0 : 1;
        $this->setPacConf($c);
        $this->menu('config');
    }

    public function sendQr($name, $code, $title = false)
    {
        $qr      = preg_replace(['~\s+~', '~\(~', '~\)~'], ['_'], $name);
        $qr_file = __DIR__ . "/qr/$qr.png";
        exec("qrencode -t png -o $qr_file '$code'");
        $r = $this->sendPhoto(
            $this->input['chat'],
            curl_file_create($qr_file),
            $title ?: $name
        );
        unlink($qr_file);
    }

    public function qrPeer($client)
    {
        $cl      = $client;
        $client  = $this->readClients()[$client];
        $name    = $this->getName($client['interface']);
        if ($this->getWGType() == 'awg') {
            $this->sendQr($name, preg_replace('/^vpn:\/\//', '', $this->getAmneziaShortLink($client)), "$name for AmneziaVPN");
            $this->sendQr($name, $this->createConfig($client), "$name for AmneziaWG");
        } else {
            $this->sendQr($name, $this->createConfig($client), "$name for Wireguard");
        }
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
        $domain  = $this->getDomain();
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

    public function qrXray($i, $s = false)
    {
        $link    = $this->linkXray($i, $s);
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

    public function addNipdomain()
    {
        $this->addDomain(str_replace('.', '-', $this->ip) . '.nip.io');
    }

    public function addDomain($domain, $nomenu = false)
    {
        $domain = trim($domain);
        if (!empty($domain)) {
            $conf = $this->getPacConf();
            $conf['domain'] = idn_to_ascii($domain);
            $this->setPacConf($conf);
            $this->chocdomain($domain);
            $this->setUpstreamDomainOcserv($domain);
            $this->setUpstreamDomainNaive($domain);
            $this->cloakNginx();
        }
        if (empty($nomenu)) {
            sleep(3);
            $this->menu('config');
        }
    }

    public function sslip()
    {
        require __DIR__ . '/config.php';
        $p  = $this->getPacConf();
        $ip = getenv('IP');
        $r  = $this->send($c['admin'][0], "start $ip");

        $this->input['chat']        = $c['admin'][0];
        $this->input['message_id']  = $r['result']['message_id'];
        $this->input['callback_id'] = false;
        if (empty($p)) {
            $this->addDomain(str_replace('.', '-', $this->ip) . '.nip.io', 1);
            $this->setSSL('letsencrypt');
        }
        $this->menu();
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
        unlink('/certs/cert_private');
        unlink('/certs/cert_public');
        $conf = $this->getPacConf();
        unset($conf['letsencrypt']);
        $this->setPacConf($conf);
        $this->adguardSync();
        $this->cloakNginx();
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
                $adguardClient = $conf['adguardkey'] ? "-d {$conf['adguardkey']}.{$conf['domain']}" : '';
                $oc = $this->getHashSubdomain('oc');
                $np = $this->getHashSubdomain('np');
                exec("certbot certonly --force-renew --preferred-chain 'ISRG Root X1' -n --agree-tos --email mail@{$conf['domain']} -d {$conf['domain']} -d $oc.{$conf['domain']} -d $np.{$conf['domain']} $adguardClient --webroot -w /certs/ --logs-dir /logs --max-log-backups 0 2>&1", $out, $code);
                if ($code > 0) {
                    $this->send($this->input['chat'], "ERROR\n" . implode("\n", $out));
                    break;
                }
                $out[] = 'Generate bundle';
                $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
                $bundle = file_get_contents("/etc/letsencrypt/live/{$conf['domain']}/privkey.pem") . file_get_contents("/etc/letsencrypt/live/{$conf['domain']}/fullchain.pem");
                $conf['letsencrypt'] = 'letsencrypt';
                break;
            case 'self':
                $r      = $this->request('getFile', ['file_id' => $this->input['file_id']]);
                $bundle = file_get_contents($this->file . $r['result']['file_path']);
                $conf['letsencrypt'] = 'self';
                break;
        }
        if (preg_match('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', $bundle, $m)) {
            $this->setPacConf($conf);
            file_put_contents('/certs/cert_private', $m[0]);
            file_put_contents('/certs/cert_public', preg_replace('~[^\s]+BEGIN PRIVATE KEY.+?END PRIVATE KEY[^\s]+~s', '', $bundle));
            $this->adguardSync();
            $this->cloakNginx();
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
        $this->setPacConf($conf);
        $this->setUpstreamDomainOcserv('');
        $this->chocdomain('');
        $this->adguardSync();
        $this->cloakNginx();
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

    public function adguardSync()
    {
        $pac = $this->getPacConf();
        $pac['adpswd'] = $pac['adpswd'] ?: substr(hash('md5', time()), 0, 10);
        $this->setPacConf($pac);
        $ssl = $this->nginxGetTypeCert();
        $c   = yaml_parse_file($this->adguard);
        $this->stopAd();
        $c['users'][0]['password'] = password_hash($pac['adpswd'], PASSWORD_DEFAULT);
        if (!empty($ssl) && !empty($pac['domain']) && empty($c['tls']['enabled'])) {
            $c['tls']['enabled']     = true;
            $c['tls']['server_name'] = $pac['domain'];
        }
        yaml_emit_file($this->adguard, $c);
        $this->startAd();
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

    public function setAdguardKey()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter key",
            $this->input['message_id'],
            reply: 'enter key',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setAdKey',
            'args'          => [],
        ];
    }

    public function timerXr($k)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter time like https://www.php.net/manual/ru/function.strtotime.php:",
            $this->input['message_id'],
            reply: 'enter time like https://www.php.net/manual/ru/function.strtotime.php:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setTimerXr',
            'args'          => [$k],
        ];
    }

    public function setAdKey($key)
    {
        $c = $this->getPacConf();
        $c['adguardkey'] = $key;
        $this->setPacConf($c);
        $this->menu('adguard');
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

    public function addSubdomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter subdomain",
            $this->input['message_id'],
            reply: 'enter subdomain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setSubdomain',
            'args'          => [],
        ];
    }

    public function setIpLimit()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter seconds and count ip",
            $this->input['message_id'],
            reply: 'enter seconds:count_ip',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'switchIpLimit',
            'args'          => [],
        ];
    }

    public function addLinkDomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter domain for link",
            $this->input['message_id'],
            reply: 'enter domain for link',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setLinkDomain',
            'args'          => [],
        ];
    }

    public function setLinkDomain($text)
    {
        $c = $this->getPacConf();
        if (empty($text)) {
            unset($c['linkdomain']);
        } else {
            $c['linkdomain'] = trim($text);
        }
        $this->setPacConf($c);
        $this->xray();
    }

    public function setSubdomain($text)
    {
        $c = $this->getPacConf();
        if (empty($text)) {
            unset($c['subdomain']);
        } else {
            $c['subdomain'] = array_filter(explode(',', $text), fn($e) => !empty(trim($e)));
        }
        $this->setPacConf($c);
        $this->menu('config');
    }

    public function enterPage()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter limit on page",
            $this->input['message_id'],
            reply: 'enter limit on page',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setPage',
            'args'          => [],
        ];
    }

    public function setPage($text) {
        $c = $this->getPacConf();
        $c['limitpage'] = (int) $text;
        $this->setPacConf($c);
        $this->menu('config');
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
        $c = yaml_parse_file($this->adguard);
        $c['users'][0]['password'] = password_hash($pass, PASSWORD_DEFAULT);
        yaml_emit_file($this->adguard, $c);
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
        exec('git -C / checkout config/AdGuardHome.yaml');
        $this->adguardSync();
        $this->cloakNginx();
        sleep(3);
        $this->menu('adguard');
    }

    public function guidv4($data = null) {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function adguardXrayClients()
    {
        $xr = $this->getXray();
        $ad = yaml_parse_file($this->adguard);
        foreach ($xr['inbounds'][0]['settings']['clients'] as $k => $v) {
            $tmp[] = [
                'safe_search' => [
                    'enabled'    => true,
                    'bing'       => true,
                    'duckduckgo' => true,
                    'google'     => true,
                    'pixabay'    => true,
                    'yandex'     => true,
                    'youtube'    => true,
                ],
                'blocked_services' => [
                    'schedule' => ['time_zone' => date_default_timezone_get()],
                    'ids'      => [],
                ],
                'name'                        => $v['email'],
                'ids'                         => [$v['id']],
                'tags'                        => [],
                'upstreams'                   => [],
                'uid'                         => $v['id'],
                'upstreams_cache_size'        => 0,
                'upstreams_cache_enabled'     => false,
                'use_global_settings'         => true,
                'filtering_enabled'           => false,
                'parental_enabled'            => false,
                'safebrowsing_enabled'        => false,
                'use_global_blocked_services' => true,
                'ignore_querylog'             => false,
                'ignore_statistics'           => false,
            ];
        }
        $ad['clients']['persistent'] = $tmp;
        yaml_emit_file($this->adguard, $ad);
        $this->stopAd();
        $this->startAd();
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
        $c = yaml_parse_file($this->adguard);
        $c['dns']['upstream_dns'][] = $url;
        yaml_emit_file($this->adguard, $c);
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
        $c = yaml_parse_file($this->adguard);
        unset($c['dns']['upstream_dns'][$k]);
        $c['dns']['upstream_dns'] = array_values($c['dns']['upstream_dns']);
        yaml_emit_file($this->adguard, $c);
        $this->startAd();
        $this->menu('adguard');
    }

    public function startAd()
    {
        return $this->ssh('/opt/adguardhome/AdGuardHome --no-check-update --pidfile /opt/adguardhome/pid -c /config/AdGuardHome.yaml -h 0.0.0.0 -w /opt/adguardhome/work > /dev/null 2>&1 &', 'ad', false);
    }

    public function stopAd()
    {
        return $this->ssh('kill -15 $(cat /opt/adguardhome/pid)', 'ad');
    }

    public function selfsslInstall()
    {
        $this->setSSL('self');
    }

    public function include($type)
    {
        switch ($type) {
            case 'rulessetlist':
                $r = $this->send(
                    $this->input['chat'],
                    "@{$this->input['username']} outbound[:behavior]:time:URL",
                    $this->input['message_id'],
                    reply: 'outbound[:behavior]:time:URL',
                );
                break;

            default:
                $r = $this->send(
                    $this->input['chat'],
                    "@{$this->input['username']} list separated by commas",
                    $this->input['message_id'],
                    reply: 'list separated by commas',
                );
                break;
        }
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'addInclude',
            'args'          => [$type],
        ];
    }

    public function addInclude(string $domains, $type)
    {
        if ($type == 'rulessetlist' && !preg_match('~^.+:.+:https?://.+~', $domains)) {
            $this->send($this->input['from'], 'wrong pattern, enter [direct|block|proxy|custom outbound]:time:URL');
            return;
        }
        $domains = explode(',', $domains);
        $domains = array_filter($domains, fn($x) => !empty(trim($x)));
        if (!empty($domains)) {
            $conf = $this->getPacConf();
            foreach ($domains as $k => $v) {
                if (in_array($type, ['white', 'deny'])) {
                    $conf[$type][] = $v;
                } else {
                    $conf[$type][in_array($type, ['rulessetlist', 'packagelist', 'processlist']) ? trim($v) : idn_to_ascii(trim($v))] = true;
                }
            }
            ksort($conf[$type]);
            $this->setPacConf($conf);
            $page = (int) floor(array_search($v, array_keys($conf[$type])) / $this->limit);
        }
        $page = $page ?: -2;
        $this->backXtlsList($type);
    }

    public function backXtlsList($type)
    {
        switch ($type) {
            case 'includelist':
                $this->pacUpdate($_SESSION['proxylistentry']);
                if (!empty($_SESSION['proxylistentry'])) {
                    $this->xtlsproxy();
                }
                break;
            case 'blocklist':
                $this->xrayUpdateRules();
                $this->xtlsblock();
                break;
            case 'warplist':
                $this->xrayUpdateRules();
                $this->xtlswarp();
                break;
            case 'processlist':
                $this->xtlsprocess();
                break;
            case 'packagelist':
                $this->xtlsapp();
                break;
            case 'subnetlist':
                $this->xtlssubnet();
                break;
            case 'rulessetlist':
                $this->xtlsrulesset();
                break;
            case 'white':
            case 'deny':
                $this->syncDeny();
                $this->denyList(0, $type == 'white' ? 1 : 0);
                break;
        }
    }

    public function xrayUpdateRules()
    {
        $c  = $this->getPacConf();
        $xr = $this->getXray();
        $xr['outbounds'] = [
            [
                "protocol" => "freedom",
                "tag"      => "direct",
            ],
            [
                "protocol" => "blackhole",
                "tag"      => "block",
            ],
            [
                "protocol" => "socks",
                "tag"      => "warp",
                "settings" => [
                    'servers' => [
                        [
                            "address" => "10.10.0.13",
                            "port"    => 4000,
                        ],
                    ],
                ],
            ],
        ];
        if (!empty($c['blocklist']) && !empty(array_filter($c['blocklist']))) {
            $rules[] = [
                "type"        => "field",
                "outboundTag" => "block",
                "domain"      => array_keys(array_filter($c['blocklist'])),
            ];
        }
        if (!empty($c['warplist']) && !empty(array_filter($c['warplist']))) {
            $rules[] = [
                "type"        => "field",
                "outboundTag" => "warp",
                "domain"      => array_keys(array_filter($c['warplist'])),
            ];
        }
        $xr['routing']['rules'] = $rules ?: [];
        $this->restartXray($xr);
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

    public function getTitleWG()
    {
        $c = $this->getPacConf();
        return $this->i18n($c[$this->getInstanceWG(1) . 'amnezia'] ? 'amnezia' : 'wg_title') . ' ' . $c['wg_instance'];
    }

    public function statusWg(int $page = 0)
    {
        $c       = $this->getPacConf();
        $conf    = $this->readConfig();
        $status  = $this->readStatus();
        if (empty($status)) {
            return [
                'text' => "Menu -> " . $this->getTitleWG() . "\n\nerror status",
                'data' => [[
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/menu",
                    ],
                ]],
            ];
        }
        $clients = $this->getClients($page);
        $bt      = $c[$this->getInstanceWG(1) . 'blocktorrent'];
        $ex      = $c[$this->getInstanceWG(1) . 'exchange'];
        $dns     = $c[$this->getInstanceWG(1) . 'dns'];
        $mtu     = $c[$this->getInstanceWG(1) . 'mtu'] ?: $this->mtu;
        $am      = $c[$this->getInstanceWG(1) . 'amnezia'];
        $end     = $c[$this->getInstanceWG(1) . 'endpoint'];
        $data    = [
            [
                [
                    'text'          => $this->i18n($am ? 'on' : 'off') . " amnezia",
                    'callback_data' => "/switchAmnezia $page",
                ],
            ],
            [
                [
                    'text'          => $this->i18n(!$bt ? 'on' : 'off') . " {$this->i18n('torrent')} ",
                    'callback_data' => "/switchTorrent $page",
                ],
                [
                    'text'          => $this->i18n(!$ex ? 'on' : 'off') . " {$this->i18n('exchange')} ",
                    'callback_data' => "/switchExchange $page",
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
                [
                    'text'          =>  $this->i18n('defaultMTU') . ': ' . $mtu,
                    'callback_data' => "/defaultMTU $page",
                ],
            ],
            [
                [
                    'text'          => $this->i18n('endpoint') . ': ' . ($end ? $this->ip : $this->getDomain()),
                    'callback_data' => "/switchEndpoint $page",
                ],
            ],
            [
                [
                    'text'          =>  $this->i18n('add peer'),
                    'callback_data' => "/menu addpeer $page",
                ],
            ],
        ];
        if ($clients) {
            $data = array_merge($data, $clients);
        }
        if (!empty($conf['peers'])) {
            $all     = (int) ceil(count($conf['peers']) / $this->limit);
            $page    = min($page, $all - 1);
            $page    = $page == -2 ? $all - 1 : $page;
            $conf['peers'] = array_slice($conf['peers'], $page * $this->limit, $this->limit, true);
            foreach ($conf['peers'] as $k => $v) {
                if (!empty($v['# PublicKey'])) {
                    $conf['peers'][$k]['online'] = 'off';
                } else {
                    $conf['peers'][$k]['status'] = $status ? $this->getStatusPeer($v['PublicKey'], $status['peers']) : 'error';
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
                    'status'  => $v['online'] == 'off' ? '🚷' : $this->i18n($v['online'] ? 'on' : 'off'),
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
        $text = "Menu -> " . $this->getTitleWG() . "\n\n<code>" . implode(PHP_EOL, $text ?: []) . '</code>';
        $data[] = [
            [
                'text'          =>  $this->i18n('update status'),
                'callback_data' => "/menu wg $page",
            ],
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ]
        ];
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

    public function defaultMTU($page = 0)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter MTU",
            $this->input['message_id'],
            reply: 'enter MTU',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setMTU',
            'args'           => [$page],
        ];
    }

    public function changeMTU($client, $page = 0)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter MTU",
            $this->input['message_id'],
            reply: 'enter MTU',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'changeClientMTU',
            'args'           => [$client, $page],
        ];
    }

    public function setDNS($text, $page = 0)
    {
        $c = $this->getPacConf();
        if ($text) {
            $c[$this->getInstanceWG(1) . 'dns'] = $text;
        } else {
            unset($c[$this->getInstanceWG(1) . 'dns']);
        }
        $this->setPacConf($c);
        $this->menu('wg', $page);
    }

    public function setMTU($text, $page = 0)
    {
        $c = $this->getPacConf();
        if ($text) {
            $c[$this->getInstanceWG(1) . 'mtu'] = $text;
        } else {
            unset($c[$this->getInstanceWG(1) . 'mtu']);
        }
        $this->setPacConf($c);
        $this->menu('wg', $page);
    }

    public function changeClientMTU($text, $client, $page = 0)
    {
        $clients = $this->readClients();
        if (!empty((int) $text)) {
            $clients[$client]['interface']['MTU'] = $text;
        } else {
            unset($clients[$client]['interface']['MTU']);
        }
        $this->saveClients($clients);
        $this->menu('client', "{$client}_$page");
    }

    public function subnetAdd($wgpage, $page, $openconnect)
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
            'args'           => [$wgpage, $page, $openconnect],
        ];
    }

    public function subnetSave($text, $wgpage, $page, $openconnect)
    {
        $c = $this->getPacConf();
        $subnets = explode(',', $text);
        if ($subnets) {
            $c['subnets'] = array_merge($c['subnets'] ?: [], array_filter(array_map(fn ($e) => trim($e), $subnets)));
            $this->setPacConf($c);
            $page = floor(count($c['subnets']) / $this->limit);
        }
        if (!empty($openconnect)) {
            $this->ocservRoute();
        }
        $this->subnet($wgpage, $page, $openconnect);
    }

    public function subnetDelete($wgpage, $k, $page = 0, $openconnect = 0)
    {
        $c = $this->getPacConf();
        unset($c['subnets'][$k]);
        $this->setPacConf($c);
        if (!empty($openconnect)) {
            $this->ocservRoute();
        }
        $this->subnet($wgpage, $page, $openconnect);
    }

    public function ocservRoute()
    {
        $p = $this->getPacConf();
        $c = file_get_contents('/config/ocserv.conf');
        $t = preg_replace('~^route[^\n]+~sm', '', $c);
        if (!empty($p['subnets'])) {
            foreach ($p['subnets'] as $v) {
                if (preg_match('~^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}~', $v)) {
                    $t .= "route = $v";
                    $flag = true;
                }
            }
            if (empty($flag)) {
                $t .= 'route = default';
            }
        } else {
            $t .= 'route = default';
        }
        $this->restartOcserv($t);
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

    public function subnet($wgpage = 0, $page = 0, $openconnect = 0)
    {
        $count  = $this->limit;
        $text   = 'Menu -> ' . ($openconnect ? 'Openconnect' : 'Wireguard') . ' -> ' . $this->i18n('listSubnet') . "\n";
        $data[] = [
            [
                'text'          => $this->i18n('calc'),
                'callback_data' => "/calc",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/subnetAdd {$wgpage}_{$page}_$openconnect",
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
                        'callback_data' => "/subnetDelete {$wgpage}_{$k}_{$page}_$openconnect",
                    ],
                ];
            }
            if ($page != -1 && $all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/subnet {$wgpage}_" . ($page - 1 >= 0 ? $page - 1 : $all - 1) . ($openconnect ? '_1' : ''),
                    ],
                    [
                        'text'          => $page + 1,
                        'callback_data' => "/subnet {$wgpage}_$page" . ($openconnect ? '_1' : ''),
                    ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/subnet {$wgpage}_" . ($page < $all - 1 ? $page + 1 : 0) . ($openconnect ? '_1' : ''),
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => $openconnect ? '/menu oc' : "/menu wg $wgpage",
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

    public function getAmneziaShortLink($client)
    {
        $dns = explode(',', $client['interface']['DNS']);
        $c   = json_encode([
            "containers" => [
                [
                    "awg" => [
                        "isThirdPartyConfig" => True,
                        "last_config" => json_encode([
                            "H1"              => "{$client['interface']['H1']}",
                            "H2"              => "{$client['interface']['H2']}",
                            "H3"              => "{$client['interface']['H3']}",
                            "H4"              => "{$client['interface']['H4']}",
                            "Jc"              => "{$client['interface']['Jc']}",
                            "Jmax"            => "{$client['interface']['Jmax']}",
                            "Jmin"            => "{$client['interface']['Jmin']}",
                            "S1"              => "{$client['interface']['S1']}",
                            "S2"              => "{$client['interface']['S2']}",
                            "client_ip"       => explode('/', $client['interface']['Address'])[0],
                            "client_priv_key" => $client['interface']['PrivateKey'],
                            "client_pub_key"  => "0",
                            "config"          => $this->createConfig($client),
                            "hostName"        => $this->ip,
                            "port"            => (int) getenv('WG1PORT'),
                            "psk_key"         => $client['peers'][0]['PresharedKey'],
                            "server_pub_key"  => $client['peers'][0]['PublicKey']
                        ]),
                        "port" => (int) getenv('WG1PORT'),
                        "transport_proto" => "udp"
                    ],
                    "container" => "amnezia-awg"
                ]
            ],
            "defaultContainer" => "amnezia-awg",
            "description"      => $client['interface']['## name'],
            "dns1"             => $dns[0],
            "dns2"             => $dns[1] ?: '',
            "hostName"         => $this->ip
        ]);
        exec("echo '$c' | python amnezia.py", $o);
        return $o[0];
    }

    public function getClient($client, $page)
    {
        $clients = $this->readClients();
        if ($clients) {
            $name = $this->getName($clients[$client]['interface']);
            $conf = $this->createConfig($clients[$client]);
            if ($this->getWGType() == 'awg') {
                $sl = $this->getAmneziaShortLink($clients[$client]);
            }
            return [
                'text' => "<pre>$conf</pre>\n\n<code>$sl</code>\n\n<b>$name</b> ({$this->getTitleWG()})",
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
                            'text'          => $this->i18n('MTU') . " " . ($clients[$client]['interface']['MTU'] ?: $this->getPacConf()[$this->getInstanceWG(1) . 'mtu'] ?: $this->mtu),
                            'callback_data' => "/changeMTU {$client}_$page",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('delete'),
                            'callback_data' => "/delete {$client}_$page",
                        ],
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
        $count   = $this->limit;
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
                    [
                        'text'          => $page + 1,
                        'callback_data' => "/menu wg $page",
                    ],
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

    public function addCommunityFilter()
    {
        $pac = $this->getPacConf();
        $l   = array_filter(array_map(fn($e) => trim($e), explode("\n", file_get_contents('https://community.antifilter.download/list/domains.lst'))));
        if (!empty($l)) {
            foreach ($l as $k => $v) {
                $pac['includelist'][$v] = true;
            }
        }
        $this->setPacConf($pac);
        $this->pacUpdate();
    }

    public function addLegizFilter()
    {
        $pac = $this->getPacConf();
        $l   = array_filter(array_map(fn($e) => trim($e), explode("\n", file_get_contents('https://github.com/legiz-ru/sb-rule-sets/raw/main/ru-bundle.lst'))));
        if (!empty($l)) {
            foreach ($l as $k => $v) {
                $pac['includelist'][$v] = true;
            }
        }
        $this->setPacConf($pac);
        $this->pacUpdate();
    }

    public function pacMenu($page = 0)
    {
        unset($_SESSION['proxylistentry']);
        $rmpac  = stat(__DIR__ . '/zapretlists/rmpac');
        $rpac   = stat(__DIR__ . '/zapretlists/rpac');
        $mpac   = stat(__DIR__ . '/zapretlists/mpac');
        $pac    = stat(__DIR__ . '/zapretlists/pac');
        $conf   = $this->getPacConf();
        $ip     = $this->getDomain();
        $hash   = $this->getHashBot();
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $text   = <<<text
                Menu -> pac
                text;
        if ($pac) {
            $pac['time']  = date('d.m.Y H:i:s', $pac['mtime']);
            $pac['sz']    = $this->sizeFormat($pac['size']);
            $text .= <<<text


                    <b>PAC ({$pac['time']} / {$pac['sz']}):</b>
                    <code>$scheme://$ip/pac$hash?a=127.0.0.1&p=1080</code>
                    text;
            $urls[0][] = [
                'text'    => "PAC",
                'web_app' => ['url'  => "https://$ip/pac$hash&a=127.0.0.1&p=1080"],
            ];
        }
        if ($mpac) {
            $mpac['time']  = date('d.m.Y H:i:s', $mpac['mtime']);
            $mpac['sz']    = $this->sizeFormat($mpac['size']);
            $text .= <<<text


                    <b>Shadowsocks-android PAC ({$mpac['time']} / {$mpac['sz']}):</b>
                    <code>$scheme://$ip/pac$hash&t=mpac</code>
                    text;
            $urls[0][] = [
                'text'    => "PAC ShadowSocks(Android)",
                'web_app' => ['url'  => "https://$ip/pac$hash&t=mpac"],
            ];
        }
        if ($rpac) {
            $rpac['time']  = date('d.m.Y H:i:s', $rpac['mtime']);
            $rpac['sz']    = $this->sizeFormat($rpac['size']);
            $text .= <<<text


                    <b>Reverse PAC ({$rpac['time']} / {$rpac['sz']}):</b>
                    <code>$scheme://$ip/pac$hash&t=rpac&a=127.0.0.1&p=1080</code>
                    text;
            $urls[0][] = [
                'text' => "Reverse PAC",
                'url'  => "$scheme://$ip/pac$hash&t=rpac",
            ];
            $urls[1][] = [
                'text' => "Reverse PAC Wireguard proxy",
                'url'  => "$scheme://$ip/pac$hash&t=rpac&a=10.10.0.3",
            ];
        }
        if ($rmpac) {
            $rmpac['time']  = date('d.m.Y H:i:s', $rmpac['mtime']);
            $rmpac['sz']    = $this->sizeFormat($rmpac['size']);
            $text .= <<<text


                    <b>Reverse shadowsocks-android PAC ({$rmpac['time']} / {$rmpac['sz']}):</b>
                    <code>$scheme://$ip/pac$hash&t=rmpac</code>
                    text;
            $urls[2][] = [
                'text' => "Reverse PAC SS(Android)",
                'url'  => "$scheme://$ip/pac$hash&t=rmpac",
            ];
        }
        if ($urls) {
            $data = $urls;
        }
        $data[] = [
            [
                'text'          => $this->i18n('add') . ' community antifilter',
                'callback_data' => "/addCommunityFilter",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add') . ' ru-bundle',
                'callback_data' => "/addLegizFilter",
            ],
        ];
        $data   = array_merge($data, $this->listPac('includelist', $page, 'pacMenu')[0]);
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function deleteYes($type)
    {
        $c = $this->getPacConf();
        unset($c[$type]);
        $this->setPacConf($c);
        switch ($type) {
            case 'includelist':
                $this->pacUpdate();
                break;
            case 'blocklist':
                $this->xtlsblock();
                break;
            case 'warplist':
                $this->xtlswarp();
                break;
            case 'packagelist':
                $this->xtlsapp();
                break;
            case 'processlist':
                $this->xtlsprocess();
                break;
            case 'rulessetlist':
                $this->xtlsrulesset();
                break;
        }
    }

    public function deleteAll($type)
    {
        switch ($type) {
            case 'includelist':
                $dir = 'PAC';
                break;
            case 'warplist':
                $dir = 'WARP';
                break;
            case 'blocklist':
                $dir = 'BLOCK';
                break;
            case 'packagelist':
                $dir = 'PACKAGE';
                break;
            case 'processlist':
                $dir = 'PROCESS';
                break;
            case 'subnetlist':
                $dir = 'SUBNET';
                break;
            case 'rulessetlist':
                $dir = 'rulesset';
                break;
        }
        $text   = <<<text
                Menu -> $dir -> delete all
                text;
        $data[] = [
            [
                'text'          => $this->i18n('yes'),
                'callback_data' => "/deleteYes $type",
            ],
        ];
        switch ($type) {
            case 'includelist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/pacMenu 0",
                    ],
                ];
                break;
            case 'warplist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlswarp",
                    ],
                ];
                break;
            case 'blocklist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlsblock",
                    ],
                ];
                break;
            case 'packagelist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlsapp",
                    ],
                ];
                break;
            case 'processlist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlsprocess",
                    ],
                ];
                break;
            case 'subnetlist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlssubnet",
                    ],
                ];
                break;
            case 'rulessetlist':
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/xtlsrulesset",
                    ],
                ];
                break;
        }
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function exportList($type)
    {
        $domains = $this->getPacConf()[$type];
        if (!empty($domains)) {
            foreach ($domains as $k => $v) {
                $text .= "$k;$v\n";
            }
            $this->sendFile(
                $this->input['chat'],
                new CURLStringFile($text, "$type.csv", 'application/csv'),
                to: $this->input['message_id'],
            );
        }
    }

    public function xtlsblock($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> block list';

        [$data] = $this->listPac('blocklist', $page, 'xtlsblock');
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function xtlswarp($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> warp list';

        [$data] = $this->listPac('warplist', $page, 'xtlswarp');
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function xtlsproxy($page = 0)
    {
        $_SESSION['proxylistentry'] = 1;
        $p = $this->getPacConf();
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> proxy list';
        [$data] = $this->listPac('includelist', $page, 'xtlsproxy');
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function appOutbound()
    {
        $p = $this->getPacConf();
        $p['app_outbound'] = !$p['app_outbound'];
        $p = $this->setPacConf($p);
        $this->xtlsapp();
    }

    public function domainsOutbound()
    {
        $p = $this->getPacConf();
        $p['domains_outbound'] = !$p['domains_outbound'];
        $p = $this->setPacConf($p);
        $this->xtlsproxy();
    }

    public function finalOutbound()
    {
        $p = $this->getPacConf();
        $p['final_outbound'] = !$p['final_outbound'];
        $p = $this->setPacConf($p);
        $this->routes();
    }

    public function processOutbound()
    {
        $p = $this->getPacConf();
        $p['process_outbound'] = !$p['process_outbound'];
        $p = $this->setPacConf($p);
        $this->xtlsprocess();
    }

    public function xtlsapp($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> package list';

        [$data] = $this->listPac('packagelist', $page, 'xtlsapp');
        $p      = $this->getPacConf();
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function xtlsprocess($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> process list';

        [$data] = $this->listPac('processlist', $page, 'xtlsprocess');
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function xtlssubnet($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> subnet';

        [$data] = $this->listPac('subnetlist', $page, 'xtlssubnet');
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function xtlsrulesset($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> ' . $this->i18n('routes') . ' -> rulesset list';

        [$data, $tmp] = $this->listPac('rulessetlist', $page, 'xtlsrulesset', 1);
        $text = array_merge($text, $tmp ?: []);
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/routes",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function listPac($type, $page, $menu, $basename = false)
    {
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/include $type",
            ],
        ];
        $domains = $this->getPacConf()[$type];
        if (!empty($domains)) {
            $all     = (int) ceil(count($domains) / $this->limit);
            $page    = min($page, $all - 1);
            $page    = $page < 0 ? $all - 1 : $page;
            $domains = array_slice($domains, $page * $this->limit, $this->limit, true);
            $i = 0;
            foreach ($domains as $k => $v) {
                if ($type == 'rulessetlist') {
                    $text[] = "<blockquote><code>$k</code></blockquote>";
                }
                $data[] = [
                    [
                        'text'          => $this->i18n($v ? 'on' : 'off') . ' ' . ($basename ? basename($k) . ' ' : '') . (in_array($type, ['rulessetlist', 'packagelist', 'processlist', 'subnetlist']) ? $k : idn_to_utf8($k)),
                        'callback_data' => "/change$type " . ($i + $page * $this->limit),
                    ],
                    [
                        'text'          => 'delete',
                        'callback_data' => "/delete$type " . ($i + $page * $this->limit),
                    ],
                ];
                $i++;
            }
            if ($all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/$menu " . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                    ],
                    [
                        'text'          => $page + 1,
                        'callback_data' => "/$menu $page",
                    ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/$menu " . ($page < $all - 1 ? $page + 1 : 0),
                    ],
                ];
            }
            $data[] = [
                [
                    'text'          => $this->i18n('delete all'),
                    'callback_data' => "/deleteAll $type",
                ],
                [
                    'text'          => $this->i18n('export'),
                    'callback_data' => "/exportList $type",
                ],
                [
                    'text'          => $this->i18n('import'),
                    'callback_data' => "/importList $type",
                ],
            ];
        } else {
            $data[] = [
                [
                    'text'          => $this->i18n('import'),
                    'callback_data' => "/importList $type",
                ],
            ];
        }
        return [$data, $text];
    }

    public function listPacChange($type, $action, $key)
    {
        $conf = $this->getPacConf();
        $i = 0;
        foreach ($conf[$type] as $k => $v) {
            if ($key == $i) {
                switch ($action) {
                    case 'change':
                        $conf[$type][$k] = !$v;
                        break;
                    case 'delete':
                        unset($conf[$type][$k]);
                        break;
                }
                break;
            }
            $i++;
        }
        $this->setPacConf($conf);
        $this->backXtlsList($type);
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
        $hash    = $this->getHashBot();
        $domain  = $this->getDomain();
        $ss      = $this->getSSConfig();
        $v2ray   = !empty($ss['plugin']) ? 'ON' : 'OFF';
        $port    = !empty($ss['plugin']) ? 443 : getenv('SSPORT');
        $options = !empty($ss['plugin']) ? "tls;fast-open;path=/v2ray$hash;host=$domain" : "path=/v2ray$hash;host=$domain";

        $text = "Menu -> ShadowSocks";
        $data[] = [
            [
                'text'          => $this->i18n('change password'),
                'callback_data' => "/sspswd",
            ],
        ];
        $ss_link = preg_replace('~==~', '', 'ss://' . base64_encode("{$ss['method']}:{$ss['password']}")) . "@$domain:$port" . (!empty($ss['plugin']) ? '?plugin=' . urlencode("v2ray-plugin;path=/v2ray$hash;host=$domain;tls") : '');
        $text .= "\n\n<code>$ss_link</code>\n";
        $text .= "\n\npassword: <span class='tg-spoiler'>{$ss['password']}</span>";
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

    public function changeWG($i)
    {
        $c = $this->getPacConf();
        $c['wg_instance'] = $i;
        $this->setPacConf($c);
        $this->menu('wg', 0);
    }

    public function alignColumns(array $columns): string
    {
        // Находим максимальную длину для каждого столбца
        $columnLengths = [];
        foreach ($columns as $column) {
            $maxLength = 0;
            foreach ($column as $cell) {
                $len = mb_strlen($cell, 'UTF-8');
                $maxLength = max($maxLength, $len);
            }
            $columnLengths[] = $maxLength;
        }

        // Получаем количество строк из первого столбца
        $rowCount = count($columns[0]);
        $columnCount = count($columns);

        // Формируем строки с выравниванием
        $result = [];
        for ($row = 0; $row < $rowCount; $row++) {
            $line = '';
            for ($col = 0; $col < $columnCount; $col++) {
                $cell = $columns[$col][$row];
                $padding = str_repeat(' ', $columnLengths[$col] - mb_strlen($cell, 'UTF-8'));
                $line .= $cell . $padding;

                // Добавляем разделитель между столбцами, кроме последнего
                if ($col < $columnCount - 1) {
                    $line .= '  '; // Два пробела между столбцами
                }
            }
            $result[] = $line;
        }

        return implode("\n", $result);
    }

    public function iodineDomain()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter domain",
            $this->input['message_id'],
            reply: 'enter domain',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setIodineDomain',
            'args'          => [],
        ];
    }

    public function iodinePassword()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter password",
            $this->input['message_id'],
            reply: 'enter password',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'setIodinePassword',
            'args'          => [],
        ];
    }

    public function setIodinePassword($text)
    {
        $c = $this->getPacConf();
        if ($text) {
            $c['iodinePassword'] = $text;
        } else {
            unset($c['iodinePassword']);
        }
        $this->setPacConf($c);
        $this->iodineRestart();
        $this->iodine();
    }

    public function setIodineDomain($text)
    {
        $c = $this->getPacConf();
        if ($text) {
            $c['iodineDomain'] = $text;
        } else {
            unset($c['iodineDomain']);
        }
        $this->setPacConf($c);
        $this->iodineRestart();
        $this->iodine();
    }

    public function iodineRestart()
    {
        $c = $this->getPacConf();
        $this->ssh('pkill iodine', 'io');
        if (!empty($c['iodineDomain']) && !empty($c['iodinePassword'])) {
            $this->ssh("iodined -c -P {$c['iodinePassword']} 10.0.0.1 {$c['iodineDomain']}", 'io');
        }
    }

    public function iodine()
    {
        $c      = $this->getPacConf();
        $text[] = 'Iodine';
        $text[] = "domain: {$c['iodineDomain']}";
        $text[] = "password: {$c['iodinePassword']}";

        $data[] = [
            [
                'text'          => $this->i18n('set domain'),
                'callback_data' => "/iodineDomain",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('set password'),
                'callback_data' => "/iodinePassword",
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
            implode("\n", $text),
            $data ?: false,
        );
    }

    public function menu($type = false, $arg = false, $return = false)
    {
        $conf   = $this->getPacConf();
        $domain = $conf['domain'] ?: $this->ip;
        $hash   = $this->getHashBot();
        if ($type == false) {
            $update = exec('git -C / rev-list --count HEAD..@{u}');
            $branch = exec('git -C / rev-parse --abbrev-ref HEAD');
            $backup = array_filter(explode('/', $conf['backup']));
            if (!empty($backup)) {
                if (!empty(strtotime($backup[0])) && !empty(strtotime($backup[1]))) {
                    $backup = "{$backup[0]} start / {$backup[1]} period";
                } else {
                    $backup = "{$conf['backup']} - wrong format";
                }
            }
            $cron   = $this->dontshowcron ? '' : $this->i18n($this->ssh('pgrep -f cron.php', 'service') ? 'on' : 'off') . ' cron';
            $f      = '/docker/compose';
            $c      = yaml_parse_file($f)['services'];
            $main[] = 'v' . getenv('VER') . " $branch" . ($update ? ' (have updates)' : '');

            if (!empty($conf['domain'])) {
                $main[] = '';
                $oc     = $this->getHashSubdomain('oc');
                $np     = $this->getHashSubdomain('np');
                if (!empty($conf['domain'])) {
                    $ssl_expiry = $this->expireCert();
                    $certs      = $this->domainsCert() ?: [];

                    $main[] = "<blockquote>";
                    $main[] = "Domains:";
                    $main[] = $conf['domain'] . (in_array($conf['domain'], $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
                    $main[] = 'naive ' . "$np.{$conf['domain']}" . (in_array("$np.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
                    $main[] = 'openconnect ' . "$oc.{$conf['domain']}" . (in_array("$oc.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
                    if (!empty($conf['adguardkey'])) {
                        $main[] = "{$conf['adguardkey']}.{$conf['domain']}" . (in_array("{$conf['adguardkey']}.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '') . ' adguard DOT';;
                    }
                    $main[] = "</blockquote>";
                } else {
                    $main[] = $this->i18n('domain explain');
                }
            }
            $main[] = '';

            $main[] = '<code>';
            $main[] = $this->alignColumns([
                [
                    $this->i18n($this->ssh($this->getPacConf()['amnezia'] ? 'awg' : 'wg', 'wg') ? 'on' : 'off') . ' ' . $this->i18n($this->getPacConf()['amnezia'] ? 'amnezia' : 'wg_title'),
                    $this->i18n($this->ssh($this->getPacConf()['wg1_amnezia'] ? 'awg' : 'wg', 'wg1') ? 'on' : 'off') . ' ' . $this->i18n($this->getPacConf()['wg1_amnezia'] ? 'amnezia' : 'wg_title'),
                    $this->i18n($this->ssh('pgrep xray', 'xr') ? 'on' : 'off') . ' ' . $this->i18n('xray'),
                    $this->i18n($this->ssh('pgrep caddy', 'np') ? 'on' : 'off') . ' ' . $this->i18n('naive'),
                    $this->i18n($this->ssh('pgrep ocserv', 'oc') ? 'on' : 'off') . ' ' . $this->i18n('ocserv'),
                    $this->i18n($this->ssh('pgrep mtproto-proxy', 'tg') ? 'on' : 'off') . ' ' . $this->i18n('mtproto'),
                    $this->i18n(exec("JSON=1 timeout 2 dnslookup google.com ad") ? 'on' : 'off') . ' ' . $this->i18n('ad_title'),
                    $this->i18n($this->ssh('pgrep ssserver', 'ss') ? 'on' : 'off') . ' ' . $this->i18n('sh_title'),
                    $this->i18n($this->ssh('pgrep iodine', 'io') ? 'on' : 'off') . ' ' . $this->i18n('Iodine'),
                    $this->i18n($this->warpStatus()) . ' ' . $this->i18n('warp'),
                ],
                [
                    $this->i18n($c['wg'] ? 'on' : 'off') . ' ' . getenv('WGPORT'),
                    $this->i18n($c['wg1'] ? 'on' : 'off') . ' ' . getenv('WG1PORT'),
                    $this->i18n('on') . ' 443',
                    $this->i18n('on') . ' 443',
                    $this->i18n('on') . ' 443',
                    $this->i18n($c['tg'] ? 'on' : 'off') . ' ' . getenv('TGPORT'),
                    $this->i18n($c['ad'] ? 'on' : 'off') . ' 853',
                    $this->i18n($c['ss'] ? 'on' : 'off') . ' ' . getenv('SSPORT'),
                    $this->i18n($c['io'] ? 'on' : 'off') . ' 53',
                    '',
                ],
            ]);
            $main[] = '';
            $main[] = $this->alignColumns([
                [
                    $this->i18n($backup ? 'on' : 'off') . ' autobackup',
                    $this->i18n($conf['autoupdate'] ? 'on' : 'off') . ' autoupdate',
                    $this->i18n($conf['autoscan'] ? 'on' : 'off') . ' autoscan',
                ],
                [
                    $this->i18n($conf['autodeny'] ? 'on' : 'off') . ' autoblock' . ($conf['deny'] ? ': ' . count($conf['deny']) : ''),
                    $this->i18n($conf['reset_monthly'] ? 'on' : 'off') . ' autoreset',
                    $cron,
                ],
            ]);
            $main[] = '</code>';

        }
        $menu   = [
            'main' => [
                'text' => implode("\n", $main ?: []),
                'data' => [
                    [
                        [
                            'text'          => $this->i18n($this->getPacConf()['amnezia'] ? 'amnezia' : 'wg_title'),
                            'callback_data' => "/changeWG 0",
                        ],
                        [
                            'text'          => $this->i18n($this->getPacConf()['wg1_amnezia'] ? 'amnezia' : 'wg_title'),
                            'callback_data' => "/changeWG 1",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('xray'),
                            'callback_data' => "/xray",
                        ],
                        [
                            'text'          => $this->i18n('naive'),
                            'callback_data' => "/menu naive",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('ocserv'),
                            'callback_data' => "/menu oc",
                        ],
                        [
                            'text'          => $this->i18n('mtproto'),
                            'callback_data' => "/mtproto",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('ad_title'),
                            'callback_data' => "/menu adguard",
                        ],
                        [
                            'text'          => $this->i18n('warp'),
                            'callback_data' => "/warp",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('sh_title'),
                            'callback_data' => "/menu ss",
                        ],
                        [
                            'text'          => $this->i18n('pac'),
                            'callback_data' => "/pacMenu 0",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('Iodine'),
                            'callback_data' => "/iodine",
                        ],
                    ],
                    [
                        [
                            'text'          => $this->i18n('config'),
                            'callback_data' => "/menu config",
                        ],
                    ],
                    [
                        [
                            'text' => $this->i18n('chat'),
                            'url'  => base64_decode('aHR0cHM6Ly90Lm1lLytXZnhnNi1ucm9rQmxNbVl5'),
                        ],
                        [
                            'text' => $this->i18n('donate'),
                            'web_app' => [
                                'url'  => "https://$domain/webapp$hash/donate.html",
                            ]
                        ],
                    ],
                ],
            ],
            'wg'           => $type == 'wg'      ? $this->statusWg($arg)                   : false,
            'client'       => $type == 'client'  ? $this->getClient(...explode('_', $arg)) : false,
            'addpeer'      => $type == 'addpeer' ? $this->addWg(...explode('_', $arg))     : false,
            'pac'          => $type == 'pac'     ? $this->pacMenu($arg)                    : false,
            'adguard'      => $type == 'adguard' ? $this->adguardMenu()                    : false,
            'config'       => $type == 'config'  ? $this->configMenu()                     : false,
            'ss'           => $type == 'ss'      ? $this->menuSS()                         : false,
            'lang'         => $type == 'lang'    ? $this->menuLang()                       : false,
            'oc'           => $type == 'oc'      ? $this->ocMenu()                         : false,
            'naive'        => $type == 'naive'   ? $this->naiveMenu()                      : false,
            'mirror'       => $type == 'mirror'  ? $this->mirrorMenu()                     : false,
            'update'       => $type == 'update'  ? $this->updatebot()                      : false,
        ];

        $text = $menu[$type ?: 'main' ]['text'];
        $data = $menu[$type ?: 'main' ]['data'];

        if (empty($type) && $update) {
            $b = exec('git -C / rev-parse --abbrev-ref HEAD');
            array_unshift($data, [
                [
                    'text'    => 'changelog',
                    'web_app' => ['url' => "https://raw.githubusercontent.com/mercurykd/vpnbot/$b/version"],
                ],
                [
                    'text'          => $this->i18n('update bot'),
                    'callback_data' => "/applyupdatebot",
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

    public function switchScanIp()
    {
        $c = $this->getPacConf();
        $c['autoscan'] = $c['autoscan'] ? 0 : 1;
        $this->setPacConf($c);
        $this->ipMenu();
    }

    public function switchBanIp()
    {
        $c = $this->getPacConf();
        $c['autodeny'] = $c['autodeny'] ? 0 : 1;
        $this->setPacConf($c);
        $this->ipMenu();
    }

    public function switchMonthlyStats()
    {
        $c = $this->getPacConf();
        $c['reset_monthly'] = $c['reset_monthly'] ? 0 : 1;
        $this->setPacConf($c);
        $this->xray();
    }

    public function switchIpLimit($limit)
    {
        $limit = explode(':', $limit);
        $c = $this->getPacConf();
        if ((int) $limit[0] <= 0) {
            unset($c['ip_limit']);
            unset($c['ip_count']);
        } else {
            $c['ip_limit'] = (int) $limit[0];
            $c['ip_count'] = (int) $limit[1] ?: 1;
        }
        $this->setPacConf($c);
        $this->xray();
    }

    public function hwidLimit()
    {
        $pac     = $this->getPacConf();
        $enabled = !empty($pac['hwid_limit_enabled']);
        $count   = max(1, (int) ($pac['hwid_device_count'] ?: 1));

        $text[] = 'Settings -> ' . $this->i18n('hwid limit');
        $text[] = $this->i18n('hwid notice');
        $text[] = $this->i18n('hwid limit') . ': ' . ($enabled ? $count : $this->i18n('off'));

        $data[] = [
            [
                'text'          => $this->i18n($enabled ? 'on' : 'off'),
                'callback_data' => '/toggleHwidLimit',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('set hwid devices count') . ': ' . $count,
                'callback_data' => '/setHwidDevices',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => '/xray',
            ],
        ];

        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function toggleHwidLimit($context = null)
    {
        $pac = $this->getPacConf();
        $pac['hwid_limit_enabled'] = $pac['hwid_limit_enabled'] ? 0 : 1;
        if (!empty($pac['hwid_limit_enabled']) && empty($pac['hwid_device_count'])) {
            $pac['hwid_device_count'] = 1;
        }
        $this->setPacConf($pac);
        $this->answer($this->input['callback_id'], $this->i18n('hwid notice'), true);
        if ($context === 'xray') {
            $this->xray();
        } else {
            $this->hwidLimit();
        }
    }

    public function setHwidDevices($context = null)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter hwid devices count",
            $this->input['message_id'],
            reply: 'enter hwid devices count',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'saveHwidDevices',
            'args'          => [$context],
        ];
    }

    public function saveHwidDevices($count, $context = null)
    {
        $count = (int) $count;
        if ($count <= 0) {
            $count = 1;
        }
        $pac = $this->getPacConf();
        $pac['hwid_device_count'] = $count;
        $this->setPacConf($pac);
        $this->send($this->input['chat'], $this->i18n('hwid notice'), $this->input['message_id']);
        if ($context === 'xray') {
            $this->xray();
        } else {
            $this->hwidLimit();
        }
    }

    public function getHwidStorage()
    {
        if (!file_exists($this->hwid)) {
            return [];
        }
        $data = json_decode(file_get_contents($this->hwid), true);
        return is_array($data) ? $data : [];
    }

    public function setHwidStorage(array $storage)
    {
        $dir = dirname($this->hwid);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->hwid, json_encode($storage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getHwidDevicesByUser($uid)
    {
        $storage = $this->getHwidStorage();
        return $storage[$uid] ?? [];
    }

    public function setHwidDevice($uid, $hwid, array $info)
    {
        $storage = $this->getHwidStorage();
        $storage[$uid][$hwid] = $info;
        $this->setHwidStorage($storage);
    }

    public function deleteHwidDevice($uid, $hwid)
    {
        $storage = $this->getHwidStorage();
        if (isset($storage[$uid][$hwid])) {
            unset($storage[$uid][$hwid]);
            if (empty($storage[$uid])) {
                unset($storage[$uid]);
            }
            $this->setHwidStorage($storage);
        }
    }

    public function deleteHwidUser($uid)
    {
        $storage = $this->getHwidStorage();
        if (isset($storage[$uid])) {
            unset($storage[$uid]);
            $this->setHwidStorage($storage);
        }
    }

    protected function getHwidTokenScope($index)
    {
        return ($this->input['chat'] ?? 'global') . ':' . $index;
    }

    protected function rememberHwidToken($scope, $hwid)
    {
        if (!isset($_SESSION['hwidTokens'])) {
            $_SESSION['hwidTokens'] = [];
        }
        if (!isset($_SESSION['hwidTokens'][$scope])) {
            $_SESSION['hwidTokens'][$scope] = [];
        }
        do {
            try {
                $token = bin2hex(random_bytes(5));
            } catch (\Throwable $e) {
                $token = substr(hash('sha256', $hwid . microtime(true)), 0, 10);
            }
        } while (isset($_SESSION['hwidTokens'][$scope][$token]));

        $_SESSION['hwidTokens'][$scope][$token] = $hwid;

        return $token;
    }

    protected function resolveHwidToken($scope, $token)
    {
        if (isset($_SESSION['hwidTokens'][$scope][$token])) {
            $hwid = $_SESSION['hwidTokens'][$scope][$token];
            unset($_SESSION['hwidTokens'][$scope][$token]);
            return $hwid;
        }

        $decoded = base64_decode($token, true);

        return $decoded !== false ? $decoded : '';
    }

    public function processHwidRequest(array $client)
    {
        $pac = $this->getPacConf();
        if (empty($pac['hwid_limit_enabled']) || !empty($client['hwid_disabled'])) {
            return true;
        }

        $limit = (int) ($client['hwid_limit'] ?: ($pac['hwid_device_count'] ?: 0));
        if ($limit <= 0) {
            return true;
        }

        $devices   = $this->getHwidDevicesByUser($client['id']);
        $hwid      = trim($_SERVER['HTTP_X_HWID'] ?? '');
        $isBrowser = $this->isBrowserRequest();

        if ($hwid === '') {
            if ($isBrowser) {
                return true;
            }

            $message = 'HWID device limit exceeded';
            header('announce: base64:' . base64_encode($message));
            header('X-HWID-Status: ' . $message);
            header('HTTP/1.1 429 Too Many Requests', true, 429);

            return false;
        }

        $isNew = !isset($devices[$hwid]);

        if ($isNew && count($devices) >= $limit) {
            $message = 'HWID device limit exceeded';
            header('announce: base64:' . base64_encode($message));
            header('X-HWID-Status: ' . $message);
            header('HTTP/1.1 429 Too Many Requests', true, 429);
            return false;
        }

        $this->setHwidDevice($client['id'], $hwid, [
            'time'         => time(),
            'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'device_os'    => $_SERVER['HTTP_X_DEVICE_OS'] ?? '',
            'os_version'   => $_SERVER['HTTP_X_VER_OS'] ?? '',
            'device_model' => $_SERVER['HTTP_X_DEVICE_MODEL'] ?? '',
        ]);

        return true;
    }

    protected function isBrowserRequest()
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $accept    = $_SERVER['HTTP_ACCEPT'] ?? '';

        if ($userAgent === '' && $accept === '') {
            return false;
        }

        $browserPatterns = [
            'Mozilla/',
            'Chrome/',
            'Safari/',
            'Firefox/',
            'Edge/',
            'Edg/',
            'MSIE ',
            'Trident/',
            'Opera/',
            'OPR/',
        ];

        foreach ($browserPatterns as $pattern) {
            if (stripos($userAgent, $pattern) !== false) {
                return true;
            }
        }

        if (stripos($accept, 'text/html') !== false) {
            return true;
        }

        return false;
    }

    public function switchSilence()
    {
        $c = $this->getPacConf();
        $c['silence'] = (($c['silence'] ?: 0) + 1) % 3;
        $this->setPacConf($c);
        $this->ipMenu();
    }

    public function ipMenu()
    {
        $text   = 'Settings -> IP & Logs';
        $pac    = $this->getPacConf();
        $d      = count($pac['deny'] ?: []);
        $w      = count($pac['white'] ?: []);
        $data[] = [
            [
                'text'          => $this->i18n('autoscan') . ': ' . ($pac['autoscan'] ? $this->getTime(strtotime(($pac['autoscan_timeout'] ?: 3600) . ' seconds')) : $this->i18n('off')),
                'callback_data' => '/autoScanTimeout',
            ],
        ];
        if (!empty($pac['autoscan'])) {
            $data[] = [
                [
                    'text'          => $this->i18n('autoblock') . ': ' . $this->i18n($pac['autodeny'] ? 'on' : 'off'),
                    'callback_data' => '/switchBanIp',
                ],
                [
                    'text'          => $this->i18n('notify') . ': ' . ((function ($pac) {
                        switch ($pac['silence']) {
                            case 0:
                                return '🔊';
                            case 1:
                                return '🔈';
                            case 2:
                                return '🔇';
                        }
                    })($pac)),
                    'callback_data' => '/switchSilence',
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('ignorelist') . ": $w",
                'callback_data' => '/denyList 0 1',
            ],
            [
                'text'          => $this->i18n('blocklist') . ": $d",
                'callback_data' => '/denyList 0 0',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('analyze'),
                'callback_data' => '/analysisIp',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu config",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function ipInRange($ip, $range) {
        [$range, $netmask] = explode('/', $range, 2);
        $rangeDecimal      = ip2long($range);
        $ipDecimal         = ip2long($ip);
        $wildcardDecimal   = pow(2, 32 - $netmask) - 1;
        $netmaskDecimal    = ~$wildcardDecimal;
        return ($ipDecimal & $netmaskDecimal) == ($rangeDecimal & $netmaskDecimal);
    }

    public function suspicious($regexp, $file, $ranges, $title, $reverse = false)
    {
        if ($r = fopen($file, 'r')) {
            while (feof($r) === false) {
                $l = fgets($r);
                if (preg_match('~(\d+\.\d+\.\d+\.\d+)~', $l, $m)) {
                    if ($reverse xor preg_match($regexp, $l)) {
                        if (is_array($ranges)) {
                            $flag = true;
                            foreach ($ranges as $range) {
                                if ($this->ipInRange($m[1], $range)) {
                                    $flag = false;
                                    break;
                                }
                            }
                            if ($flag) {
                                $ret[$m[1]][] = [
                                    'title' => $title,
                                    'log'   => $l,
                                ];
                            }
                        } else {
                            if ($this->ipInRange($m[1], $ranges)) {
                                $ret[$m[1]][] = [
                                    'title' => $title,
                                    'log'   => $l,
                                ];
                            }
                        }
                    }
                }
            }
            fclose($r);
        }
        return $ret ?: [];
    }

    public function analysisIp(int $page = 0, $return = false)
    {
        $pac = $this->getPacConf();
        $xr  = [];
        foreach (array_merge($pac['white'] ?: [], $pac['deny'] ?: [], ['10.10.0.0/23']) as $v) {
            if (preg_match('~^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:(/\d{1,2}))?$~', $v, $m)) {
                if (!in_array($m[1] . ($m[2] ?: '/32'), $xr)) {
                    $xr[] = $m[1] . ($m[2] ?: '/32');
                }
            }
        }
        if ($r = fopen('/logs/nginx_tlgrm_access', 'r')) {
            while (feof($r) === false) {
                $l = fgets($r);
                if (preg_match('~(\d+\.\d+\.\d+\.\d+)~', $l, $m)) {
                    if (!in_array("{$m[1]}/32", $xr)) {
                        $xr[] = "{$m[1]}/32";
                    }
                }
            }
            fclose($r);
        }
        if ($r = fopen('/logs/nginx_doh_access', 'r')) {
            while (feof($r) === false) {
                $l = fgets($r);
                if (preg_match('~(\d+\.\d+\.\d+\.\d+)~', $l, $m)) {
                    if (!in_array("{$m[1]}/32", $xr)) {
                        $xr[] = "{$m[1]}/32";
                    }
                }
            }
            fclose($r);
        }
        if ($r = fopen('/logs/xray', 'r')) {
            while (feof($r) === false) {
                $l = fgets($r);
                if (preg_match('~(\d+\.\d+\.\d+\.\d+)(?=.+accepted)~', $l, $m)) {
                    if (!in_array("{$m[1]}/32", $xr)) {
                        $xr[] = "{$m[1]}/32";
                    }
                }
            }
            fclose($r);
        }

        $t = [
            $this->suspicious($this->reg, '/logs/nginx_default_access', $xr, 'possibly a scanner', true),
            $this->suspicious($this->reg, '/logs/nginx_domain_access', $xr, 'possibly a scanner', true),
        ];

        $ip = [];
        foreach ($t as $r) {
            foreach ($r as $k => $v) {
                $ip[$k] = $v;
            }
        }

        $r = $this->suspicious('~\d+\.\d+\.\d+\.\d+.+200\s\d+\s0$~', '/logs/upstream_access', $xr, 'possibly a Reality Degenerate');
        if (!empty($r)) {
            foreach ($r as $k => $v) {
                if (count($v) > 30) {
                    $ip[$k] = $v;
                }
            }
        }

        if (!empty($return)) {
            return $ip;
        }
        if (!empty($ip)) {
            foreach ($ip as $k => $v) {
                $data[] = [
                    [
                        'text'          => $k,
                        'callback_data' => "/searchLogs $k analysisIp $page 0",
                    ]
                ];
            }
            $all  = (int) ceil(count($data) / $this->limit);
            $page = min($page, $all - 1);
            $page = $page < 0 ? $all - 1 : $page;
            $data = array_slice($data ?: [], $page * $this->limit, $this->limit);
            if ($all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/analysisIp " . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                    ],
                    [
                        'text'          => $page + 1,
                        'callback_data' => "/analysisIp $page",
                    ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/analysisIp " . ($page < $all - 1 ? $page + 1 : 0),
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/ipMenu",
            ],
        ];
        $this->update($this->input['from'], $this->input['message_id'], count($ip) ?: 'empty', $data);
    }

    public function searchLogs($search, $fun = false, $page = 0, $white = 0)
    {
        if (preg_match('~^\d+\.\d+\.\d+\.\d+$~', $search)) {
            $info = file_get_contents("https://ipinfo.io/$search/json", context: stream_context_create(['http' => ['timeout' => 2]]));
            $text = "$search\n<pre>$info</pre>";
            $data[] = [
                [
                    'text'          => $this->i18n('block'),
                    'callback_data' => "/denyIp $search" . ($fun ? " $fun $page $white" : ''),
                ],
                [
                    'text'          => $this->i18n('ignore'),
                    'callback_data' => "/whiteIp $search" . ($fun ? " $fun $page $white" : ''),
                ],
            ];
            $data[] = [
                [
                    'text'          => $this->i18n('all logs'),
                    'callback_data' => "/searchIp $search",
                ],
                [
                    'text'          => $this->i18n('suspicious log'),
                    'callback_data' => "/searchSuspiciousIp $search",
                ],
            ];
            $data[] = [
                [
                    'text'          => $this->i18n("clean logs $search"),
                    'callback_data' => "/cleanLogs $search",
                ],
            ];
            if (!empty($fun)) {
                $data[] = [
                    [
                        'text'          => $this->i18n('back'),
                        'callback_data' => "/$fun $page" . ($white ? " $white" : ''),
                    ],
                ];
                $this->update($this->input['from'], $this->input['message_id'], $text, button: $data);
            } else {
                if (empty($this->input['callback_id'])) {
                    $this->delete($this->input['from'], $this->input['message_id']);
                }
                $this->send($this->input['from'], $text, button: $data);
            }
        }
    }

    public function searchIp($ip)
    {
        foreach ($this->logs as $v) {
            if ($r = fopen("/logs/$v", 'r')) {
                while (feof($r) === false) {
                    $l = fgets($r);
                    if (preg_match('~' . preg_quote($ip) . '~', $l)) {
                        $res[$v][] = $l;
                    }
                }
                fclose($r);
            }
        }
        if (!empty($res)) {
            foreach ($res as $k => $v) {
                $head= "$k:\n";
                $t = array_chunk($v, 10);
                foreach ($t as $j) {
                    $text = "$head<pre>";
                    foreach ($j as $i) {
                        $text .= htmlspecialchars($i, ENT_HTML5, 'UTF-8');
                    }
                    $text .= '</pre>';
                    $this->send($this->input['from'], $text, $this->input['message_id']);
                }
            }
        } else {
            $this->answer($this->input['callback_id'], 'empty');
        }
    }

    public function searchSuspiciousIp($ip)
    {
        $t = [
            $this->suspicious('~\d+\.\d+\.\d+\.\d+.+200\s\d+\s0$~', '/logs/upstream_access', "$ip/32", 'possibly a Reality Degenerate'),
            $this->suspicious($this->reg, '/logs/nginx_default_access', "$ip/32", 'possibly a scanner', true),
            $this->suspicious($this->reg, '/logs/nginx_domain_access', "$ip/32", 'possibly a scanner', true),
        ];
        foreach ($t as $r) {
            if (!empty($r)) {
                foreach ($r as $v) {
                    foreach ($v as $k) {
                        $logs[$k['title']][] = $k['log'];
                    }
                }
            }
        }
        if (!empty($logs)) {
            foreach ($logs as $k => $v) {
                $head= "$k:\n";
                $t = array_chunk($v, 10);
                foreach ($t as $j) {
                    $text = "$head<pre>";
                    foreach ($j as $i) {
                        $text .= htmlspecialchars($i, ENT_HTML5, 'UTF-8');
                    }
                    $text .= '</pre>';
                    $this->send($this->input['from'], $text, $this->input['message_id']);
                }
            }
        } else {
            $this->answer($this->input['callback_id'], 'empty');
        }
    }

    public function importIps($type)
    {
        switch ($type) {
            case 'telegram':
                $r = file_get_contents('https://core.telegram.org/resources/cidr.txt');
                if (!empty($r)) {
                    $domains = explode("\n", $r);
                }
                break;
            case 'gcore':
                $r = json_decode(file_get_contents('https://api.gcore.com/cdn/public-ip-list'), true);
                if (!empty($r['addresses'])) {
                    $domains = $r['addresses'];
                }
                break;
            case 'cloudflare':
                $r = json_decode(file_get_contents('https://api.cloudflare.com/client/v4/ips'), true);
                if (!empty($r['result']['ipv4_cidrs'])) {
                    $domains = $r['result']['ipv4_cidrs'];
                }
                break;
        }
        if (!empty($domains = array_filter($domains ?: [], fn($e) => preg_match('~^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/\d{1,2}~', $e)))) {
            $this->addInclude(implode(',', $domains), 'white');
        }
    }

    public function denyList($page = 0, $white = 0)
    {
        $text    = 'Menu -> IP -> ' . ($white ? 'ignore' : 'block') . 'list';
        $domains = $this->getPacConf()[$white ? 'white' : 'deny'] ?: [];
        $all     = (int) ceil(count($domains) / $this->limit);
        $page    = min($page, $all - 1);
        $page    = $page < 0 ? $all - 1 : $page;

        if (!empty($white)) {
            $data[] = [
                [
                    'text'          => $this->i18n('telegram IPs'),
                    'callback_data' => "/importIps telegram",
                ],
            ];
            $data[] = [
                [
                    'text'          => $this->i18n('gcore IPs'),
                    'callback_data' => "/importIps gcore",
                ],
            ];
            $data[] = [
                [
                    'text'          => $this->i18n('cloudflare IPs'),
                    'callback_data' => "/importIps cloudflare",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/include " . ($white ? 'white' : 'deny'),
            ],
        ];
        if (!empty($domains)) {
            foreach (array_slice($domains, $page * $this->limit, $this->limit) as $v) {
                $data[] = [
                    [
                        'text'          => $v,
                        'callback_data' => "/searchLogs $v denyList $page $white",
                    ],
                    [
                        'text'          => $this->i18n('delete'),
                        'callback_data' => "/allowIp $v $page" . ($white ? " 1" : ''),
                    ],
                ];
            }
            if ($all > 1) {
                $data[] = [
                    [
                        'text'          => '<<',
                        'callback_data' => "/denyList " . ($page - 1 >= 0 ? $page - 1 : $all - 1) . ($white ? " 1" : ' 0'),
                    ],
                    [
                        'text'          => $page + 1,
                        'callback_data' => "/denyList $page" . ($white ? " 1" : ' 0'),
                    ],
                    [
                        'text'          => '>>',
                        'callback_data' => "/denyList " . ($page < $all - 1 ? $page + 1 : 0) . ($white ? " 1" : ' 0'),
                    ],
                ];
            }
            $data[] = [
                [
                    'text'          => $this->i18n('delete all'),
                    'callback_data' => "/cleanDeny" . ($white ? " 1" : ''),
                ],
            ];
        }

        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/ipMenu",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            $text,
            $data ?: false,
        );
    }

    public function cleanDeny($white = 0)
    {
        $pac = $this->getPacConf();
        unset($pac[$white ? 'white' : 'deny']);
        $this->setPacConf($pac);
        $this->syncDeny();
        $this->ipMenu();
    }

    public function denyIp($ip, $fun = false, $page = 0, $white = 0)
    {
        $pac = $this->getPacConf();
        if (is_array($ip)) {
            foreach ($ip as $v) {
                $pac['deny'][] = $v;
                if (($t = array_search($v, $pac['white'] ?: [])) !== false) {
                    unset($pac['white'][$t]);
                }
            }
        } else {
            $pac['deny'][] = $ip;
            if (($t = array_search($ip, $pac['white'] ?: [])) !== false) {
                unset($pac['white'][$t]);
            }
        }
        $this->setPacConf($pac);
        if (empty($fun)) {
            $this->delete($this->input['from'], $this->input['message_id']);
        }
        $this->syncDeny();
        if (!empty($fun)) {
            $this->{$fun}($page, $white);
        }
    }

    public function whiteIp($ip, $fun = false, $page = 0, $white = 0)
    {
        $pac = $this->getPacConf();
        if (is_array($ip)) {
            foreach ($ip as $v) {
                $pac['white'][] = $v;
                if (($t = array_search($v, $pac['deny'] ?: [])) !== false) {
                    unset($pac['deny'][$t]);
                }
            }
        } else {
            $pac['white'][] = $ip;
            if (($t = array_search($ip, $pac['deny'] ?: [])) !== false) {
                unset($pac['deny'][$t]);
            }
        }
        $this->setPacConf($pac);
        if (empty($fun)) {
            $this->delete($this->input['from'], $this->input['message_id']);
        }
        $this->syncDeny();
        if (!empty($fun)) {
            $this->{$fun}($page, $white);
        }
    }

    public function allowIp($ip, $page, $white = 0)
    {
        $pac = $this->getPacConf();
        unset($pac[$white ? 'white' : 'deny'][array_search($ip, $pac[$white ? 'white' : 'deny'])]);
        $this->setPacConf($pac);
        $this->syncDeny();
        $this->denyList($page, $white);
    }

    public function cleanLogs($ip, $nodelete = false)
    {
        foreach ($this->logs as $v) {
            exec("sed -i '/$ip/d' /logs/$v");
        }
        if (empty($nodelete)) {
            $this->delete($this->input['from'], $this->input['message_id']);
        }
    }

    public function syncDeny()
    {
        $pac = $this->getPacConf();
        if ($r = fopen('/logs/nginx_tlgrm_access', 'r')) {
            while (feof($r) === false) {
                $l = fgets($r);
                if (preg_match('~(\d+\.\d+\.\d+\.\d+)~', $l, $m)) {
                    $xr[$m[1]] = true;
                }
            }
            fclose($r);
        }
        if (!empty($xr)) {
            foreach (array_keys($xr) as $v) {
                $text .= "allow $v;\n";
            }
        }
        if (!empty($pac['white'])) {
            $pac['white'] = array_unique($pac['white']);
            sort($pac['white']);
            foreach ($pac['white'] as $v) {
                $text .= "allow $v;\n";
            }
        }
        if (!empty($pac['deny'])) {
            $pac['deny'] = array_unique($pac['deny']);
            sort($pac['deny']);
            foreach ($pac['deny'] as $k => $v) {
                if (!in_array($v, $pac['white'] ?: []) && !in_array($v, array_keys($xr ?: []))) {
                    $text .= "deny $v;\n";
                } else {
                    unset($pac['deny'][$k]);
                }
            }
        }
        $this->setPacConf($pac);
        file_put_contents('/config/deny', $text ?: '');
        $this->ssh('nginx -s reload', 'up');
    }

    public function linkXray($i, $s = false)
    {
        $c      = $this->getXray();
        $pac    = $this->getPacConf();
        $domain = $this->getDomain($pac['transport'] != 'Reality');
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();
        $si     = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'si',
            's' => $c['inbounds'][0]['settings']['clients'][$i]['id'],
        ]));
        $v2     = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 's',
            's' => $c['inbounds'][0]['settings']['clients'][$i]['id'],
        ]));

        switch ($s) {
            case 1:
                return "v2rayng://install-config?url=$v2#{$c['inbounds'][0]['settings']['clients'][$i]['id']}";
            case 2:
                return "sing-box://import-remote-profile/?url={$si}#{$c['inbounds'][0]['settings']['clients'][$i]['email']}";

            default:
                if ($pac['transport'] != 'Reality') {
                    return "vless://{$c['inbounds'][0]['settings']['clients'][$i]['id']}@$domain:443?flow=&path=%2Fws$hash&security=tls&sni=$domain&fp=chrome&type=ws#{$c['inbounds'][0]['settings']['clients'][$i]['email']}";
                }
                return "vless://{$c['inbounds'][0]['settings']['clients'][$i]['id']}@$domain:443?security=reality&sni={$c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]}&fp=chrome&pbk={$pac['xray']}&sid={$c['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0]}&type=tcp&flow=xtls-rprx-vision#{$c['inbounds'][0]['settings']['clients'][$i]['email']}";
        }
    }

    public function dockerApi($url, $method = 'GET', $data = [])
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST    => $method,
            CURLOPT_POSTFIELDS       => !empty($data) ? json_encode($data) : null,
            CURLOPT_URL              => "http://localhost$url",
            CURLOPT_RETURNTRANSFER   => true,
            CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock'
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        return $r;
    }

    public function cleanDocker()
    {
        $r = $this->dockerApi('/images/json');
        foreach ($r as $v) {
            if (!empty($v['RepoTags'])) {
                foreach ($v['RepoTags'] as $j) {
                    if (preg_match('~^mercurykd/vpnbot~', $j)) {
                        $i[] = $v['Id'];
                        break;
                    }
                }
            }
        }
        $r = $this->dockerApi('/containers/json?all=1');
        foreach ($r as $v) {
            if (preg_match('~^mercurykd/vpnbot~', $v['Image'])) {
                $c[] = $v['ImageID'];
            }
        }
        if (!empty($d = array_diff($i, $c))) {
            foreach ($d as $v) {
                $this->dockerApi("/images/$v", 'DELETE');
            }
        }
        $this->dockerApi('/images/prune', 'POST', ['dangling' => true]);
        $this->dockerApi('/build/prune', 'POST');
    }

    public function naiveMenu()
    {
        $pac    = $this->getPacConf();
        $domain = $this->getDomain();
        $text[] = "Menu -> NaiveProxy";
        $np     = $this->getHashSubdomain('np');
        $text[] = "<code>https://{$pac['naive']['user']}:{$pac['naive']['pass']}@$np.$domain</code>";
        $data[] = [
            [
                'text'          => $this->i18n('change subdomain'),
                'callback_data' => "/changeNaiveSubdomain",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('change login'),
                'callback_data' => "/changeNaiveUser",
            ],
            [
                'text'          => $this->i18n('change password'),
                'callback_data' => "/changeNaivePass",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }

    public function mirrorMenu()
    {
        $ip     = $this->getPacConf()['domain'] ?: $this->ip;
        $text[] = "Menu -> Mirror";
        $text[] = <<<PNG
                    <pre>client -> intermediate VPS -> vpnbot
                                         ^           |
                                         |  install  |
                                         |  mirror   |
                                          -----------
                    </pre>
                    PNG;
        $data[] = [
            [
                'text'          => $this->i18n('download'),
                'callback_data' => "/getMirror",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }

    public function getMirror()
    {
        $s = file_get_contents('/mirror/start_socat.sh');
        $t = str_replace([
            '~ip~',
            '~tg~',
            '~ss~',
            '~wg1~',
            '~wg2~',
        ], [
            getenv('IP'),
            getenv('TGPORT'),
            getenv('SSPORT'),
            getenv('WGPORT'),
            getenv('WG1PORT'),
        ], $s);
        $this->sendFile($this->input['from'], new CURLStringFile($t, 'socat.sh', 'application/x-sh'));
    }

    public function ocMenu()
    {
        $pac    = $this->getPacConf();
        $domain = $this->getDomain();
        $ocserv = file_get_contents('/config/ocserv.conf');
        preg_match('~^camouflage_secret[^\n]+?"([^"]+)*"~sm', $ocserv, $m);
        $cs = $m[1];
        preg_match('~^dns = ([^\n]+)~sm', $ocserv, $m);
        $dns = $m[1];
        preg_match('~^expose-iroutes = (true)~sm', $ocserv, $m);
        $expose = $m[1];
        $pass   = htmlspecialchars($pac['ocserv']);
        $text[] = "Menu -> OpenConnect";
        if (!empty($cs)) {
            $oc = $this->getHashSubdomain('oc');
            $text[] = "<code>https://$oc.$domain/?$cs</code>";
        }
        $text[] = "password: <span class='tg-spoiler'>$pass</span>";
        $data[] = [
            [
                'text'          => $this->i18n('change subdomain'),
                'callback_data' => "/changeOcDomain",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('change secret'),
                'callback_data' => "/changeCamouflage",
            ],
            [
                'text'          => $this->i18n('change password'),
                'callback_data' => "/changeOcPass",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('dns') . ": $dns",
                'callback_data' => "/changeOcDns",
            ],
        ];
        $data[] = [
            [
                'text'          =>  $this->i18n('listSubnet'),
                'callback_data' => "/subnet 0_0_1",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('expose-iroutes') . ' ' . $this->i18n($expose ? 'on' : 'off'),
                'callback_data' => "/changeOcExpose",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add peer'),
                'callback_data' => "/addOcUser",
            ],
        ];
        $clients = $this->getClientsOc();
        foreach ($clients as $k => $v) {
            $data[] = [
                [
                    'text'          => $this->i18n('delete') . " $v",
                    'callback_data' => "/deloc $k",
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
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }

    public function changeOcExpose()
    {
        $c = file_get_contents('/config/ocserv.conf');
        preg_match('~^expose-iroutes = ([^\n]+)~sm', $c, $m);
        $t = preg_replace('~^expose-iroutes[^\n]+~sm', "expose-iroutes = " . ($m[1] == 'true' ? 'false' : 'true'), $c);
        $this->restartOcserv($t);
        $this->menu('oc');
    }

    public function deloc($i)
    {
        $clients = $this->getClientsOc();
        foreach ($clients as $k => $v) {
            if ($i == $k) {
                $this->ssh("ocpasswd -c /etc/ocserv/ocserv.passwd -d $v", 'oc');
                break;
            }
        }
        $this->menu('oc');
    }

    public function delxr($i)
    {
        $r  = $this->getXray();
        $st = $this->getXrayStats();
        foreach ($r['inbounds'][0]['settings']['clients'] as $k => $v) {
            if ($i == $k) {
                $this->deleteHwidUser($r['inbounds'][0]['settings']['clients'][$k]['id']);
                unset($r['inbounds'][0]['settings']['clients'][$k]);
                unset($st['users'][$k]);
                $this->setXrayStats($st);
                $this->restartXray($r);
                $this->adguardXrayClients();
                break;
            }
        }
        $this->xray();
    }

    public function getClientsOc()
    {
        $users = array_filter(explode("\n", file_get_contents('/config/ocserv.passwd')), fn ($e) => !empty($e));
        return array_map(fn($e) => explode(':', $e)[0], $users);
    }

    public function addocus($user)
    {
        $pac = $this->getPacConf();
        $this->ssh("echo '{$pac['ocserv']}' | ocpasswd -c /etc/ocserv/ocserv.passwd $user", 'oc');
        $this->menu('oc');
    }

    public function addxrus($users)
    {
        $c     = $this->getXray();
        $p     = $this->getPacConf();
        $users = array_map(fn ($e) => trim($e), explode(',', $users));
        $users = array_map(fn ($e) => explode(':', $e), $users);
        foreach ($c['inbounds'][0]['settings']['clients'] as $k => $v) {
            $uuids[]  = $v['id'];
            $emails[] = $v['email'];
        }
        foreach ($users as $user) {
            $uuid = $user[1] ?: trim($this->ssh('xray uuid', 'xr'));
            if (in_array($uuid, $uuids ?: []) || in_array($user[0], $emails ?: [])) {
                $this->send($this->input['chat'], "user {$user[0]} already exists");
                return $this->xray();
            }
            $c['inbounds'][0]['settings']['clients'][] = $p['transport'] != 'Reality' ? [
                    'id'    => $uuid,
                    'email' => $user[0],
                ] : [
                    'id'    => $uuid,
                    'flow'  => 'xtls-rprx-vision',
                    'email' => $user[0],
            ];
        }
        $this->restartXray($c);
        $this->adguardXrayClients();
        if (count($users) == 1) {
            $this->userXr(count($c['inbounds'][0]['settings']['clients']) - 1);
        } else {
            $this->xray();
        }
    }

    public function setTimerXr($time, $i)
    {
        $c = $this->getXray();
        if (empty($time)) {
            unset($c['inbounds'][0]['settings']['clients'][$i]['time']);
        } else {
            $time = strtotime($time);
            if ($time === false) {
                $this->send($this->input['chat'], 'wrong format');
                return;
            }
            $c['inbounds'][0]['settings']['clients'][$i]['time'] = $time;
        }
        $this->restartXray($c, 1);
        if (!empty($c['inbounds'][0]['settings']['clients'][$i]['off'])) {
            $this->switchXr($i, 0, 1);
        } else {
            $this->userXr($i);
        }
    }

    public function switchXr($i, $nm = 0, $time = false)
    {
        $c = $this->getXray();
        if (empty($time)) {
            unset($c['inbounds'][0]['settings']['clients'][$i]['time']);
        }
        if (empty($c['inbounds'][0]['settings']['clients'][$i]['off'])) {
            $c['inbounds'][0]['settings']['clients'][$i]['off'] = $c['inbounds'][0]['settings']['clients'][$i]['id'];
            $c['inbounds'][0]['settings']['clients'][$i]['id']  = trim($this->ssh('xray uuid', 'xr'));
        } else {
            $c['inbounds'][0]['settings']['clients'][$i]['id'] = $c['inbounds'][0]['settings']['clients'][$i]['off'];
            unset($c['inbounds'][0]['settings']['clients'][$i]['off']);
        }
        $this->restartXray($c);
        if (empty($nm)) {
            $this->userXr($i);
        }
    }

    public function renXrUs($name, $i)
    {
        $c = $this->getXray();
        $c['inbounds'][0]['settings']['clients'][$i]['email'] = $name;
        $this->restartXray($c);
        $this->adguardXrayClients();
        $this->userXr($i);
    }

    public function getXrayStats()
    {
        return json_decode(file_get_contents('/config/xray.stats'), true) ?: [];
    }

    public function setXrayStats($x)
    {
        file_put_contents('/config/xray.stats', json_encode($x));
    }

    public function resetXrUser($i)
    {
        $c = $this->getXrayStats();
        unset($c['users'][$i]);
        $this->restartXray($this->getXray());
        $this->userXr($i);
    }

    public function resetXrStats($nomenu = false)
    {
        $this->restartXray($this->getXray());
        $this->setXrayStats([]);
        if (empty($nomenu)) {
            $this->xray();
        }
    }

    public function listXr($i)
    {
        $c = $this->getPacConf();
        $c['xtlslist'] = $i;
        $this->setPacConf($c);
        $this->xray();
    }

    public function templateAdd($type)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} send the template file:",
            $this->input['message_id'],
            reply: 'send the template file:',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'addTemplate',
            'args'           => [$type],
        ];
    }

    public function autoScanTimeout()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} send time like 1 hour or 1 day etc",
            $this->input['message_id'],
            reply: 'send time like 1 hour or 1 day etc',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setAutoScanTimeout',
            'args'           => [],
        ];
    }

    public function setAutoScanTimeout($time)
    {
        $pac = $this->getPacConf();
        if (empty($time)) {
            unset($pac['autoscan_timeout']);
            unset($pac['autoscan']);
        } elseif ($t = strtotime($time, 0)) {
            $pac['autoscan_timeout'] = $t;
            $pac['autoscan'] = 1;
        } else {
            $this->send($this->input['from'], "$time - wrong format", $this->input['message_id']);
        }
        $this->setPacConf($pac);
        $this->ipMenu();
    }

    public function templateCopy($type)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} send the template name",
            $this->input['message_id'],
            reply: 'send the template name',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'copyTemplate',
            'args'           => [$type],
        ];
    }

    public function addTemplate($n, $type)
    {
        if (empty($this->input['caption'])) {
            $this->send($this->input['chat'], 'empty name');
            return;
        }
        $r    = $this->request('getFile', ['file_id' => $this->input['file_id']]);
        $json = json_decode(file_get_contents($this->file . $r['result']['file_path']), true);
        if ($json === false) {
            $this->send($this->input['chat'], 'wrong format');
            return;
        }
        $pac = $this->getPacConf();
        $pac["{$type}templates"][$this->input['caption']] = $json;
        $this->setPacConf($pac);
        $this->templates($type);
    }

    public function saveTemplate($name, $type, $json)
    {
        if (json_decode($json, true) === false) {
            return [
                'status'  => false,
                'message' => 'wrong format',
            ];
        }
        $pac = $this->getPacConf();
        switch ($name) {
            case 'origin':
                file_put_contents("/config/$type.json", json_encode(json_decode($json, true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                break;

            default:
                $pac["{$type}templates"][$name] = json_decode($json, true);
                break;
        }
        $this->setPacConf($pac);
        return [
            'status' => true,
        ];
    }

    public function delTemplate($type, $name)
    {
        $pac = $this->getPacConf();
        unset($pac["{$type}templates"][base64_decode($name)]);
        $this->setPacConf($pac);
        $this->templates($type);
    }

    public function copyTemplate($name, $type)
    {
        $pac  = $this->getPacConf();
        $pac["{$type}templates"][$name] = json_decode(file_get_contents("/config/$type.json"), true);
        $this->setPacConf($pac);
        $this->templates($type);
    }

    public function downloadOrigin($type)
    {
        $f = new \CURLFile("/config/$type.json", 'application/json', 'origin.json');
        $this->sendFile($this->input['chat'], $f);
    }

    public function downloadTemplate($type, $name)
    {
        $pac = $this->getPacConf();
        $f = new \CURLStringFile(json_encode($pac["{$type}templates"][base64_decode($name)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), base64_decode($name) . '.json', 'application/json');
        $this->sendFile($this->input['chat'], $f);
    }

    public function defaultTemplate($type, $name)
    {
        $pac = $this->getPacConf();
        if (!empty($name)) {
            $pac["default{$type}template"] = $name;
        } else {
            unset($pac["default{$type}template"]);
        }
        $this->setPacConf($pac);
        $this->templates($type);
    }

    public function templates($type)
    {
        $pac    = $this->getPacConf();
        $domain = $this->getDomain();
        $hash   = $this->getHashBot();
        $text[] = "Menu -> " . $this->i18n('xray') . " -> " . $this->i18n($type) . " templates";
        $text[] = <<<TEXT
            <code>~outbound~</code>
            <code>~pac~</code>
            <code>~package~</code>
            <code>~process~</code>
            <code>~subnet~</code>
            <code>~block~</code>
            <code>~warp~</code>
            <code>~dns~</code>
            <code>~dnspath~</code>
            <code>~uid~</code>
            <code>~domain~</code>
            <code>~directdomain~</code>
            <code>~cdndomain~</code>
            <code>~short_id~</code>
            <code>~email~</code>
            <code>~public_key~</code>
            <code>~server_name~</code>
            <code>~ip~</code>
            TEXT;
        $templates = $pac["{$type}templates"];

        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/templateAdd $type",
            ],
        ];
        $data[] = [
            [
                'text'          => "origin",
                'web_app' => ['url' => "https://$domain/pac$hash?t=te&ty=$type"],
            ],
            [
                'text'          => $this->i18n('download'),
                'callback_data' => "/downloadOrigin $type",
            ],
            [
                'text'          => $this->i18n('copy'),
                'callback_data' => "/templateCopy $type",
            ],
            [
                'text'          => $this->i18n($pac["default{$type}template"] && !empty($pac["{$type}templates"][base64_decode($pac["default{$type}template"])]) ? 'off' : 'on'),
                'callback_data' => "/defaultTemplate $type",
            ],
        ];
        foreach ($templates as $k => $v) {
            $data[] = [
                [
                    'text'          => $k,
                    'web_app' => ['url' => "https://$domain/pac$hash?t=te&ty=$type&te=" . urlencode($k)],
                ],
                [
                    'text'          => $this->i18n('download'),
                    'callback_data' => "/downloadTemplate $type " . base64_encode($k),
                ],
                [
                    'text'          => $this->i18n('delete'),
                    'callback_data' => "/delTemplate $type " . base64_encode($k),
                ],
                [
                    'text'          => $this->i18n($pac["default{$type}template"] == base64_encode($k) ? 'on' : 'off'),
                    'callback_data' => "/defaultTemplate $type " . base64_encode($k),
                ],
            ];
        }

        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/xray",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function mainOutbound()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} send name",
            $this->input['message_id'],
            reply: 'send name',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setMainOutbound',
            'args'           => [],
        ];
    }

    public function setMainOutbound($text)
    {
        $pac = $this->getPacConf();
        if (!empty($text)) {
            $pac['outbound'] = $text;
        } else {
            unset($pac['outbound']);
        }
        $this->setPacConf($pac);
        $this->xray();
    }

    public function getBytes($bytes)
    {
        $t = [
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
        ];
        foreach ($t as $k => $v) {
            if ($k == 0) {
                continue;
            }
            if ($bytes / (1024 ** $k) < 1) {
                return round($bytes / (1024 ** ($k - 1)), 2) . " {$t[$k - 1]}";
            }
        }
    }

    public function xray($page = 0)
    {
        if (!$this->ssh('pgrep xray', 'xr')) {
            $this->generateSecretXray();
        }
        $c      = $this->getXray();
        $p      = $this->getPacConf();
        $text[] = "Menu -> " . $this->i18n('xray');
        if (!empty($fake = $c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0])) {
            $text[] = "fake domain: <code>$fake</code>";
        }
        $text[] = 'transport: ' . ($p['transport'] ?: 'Websocket');
        $st = $this->getXrayStats();
        $td = $this->getBytes($st['global']['download'] + $st['session']['download']);
        $tu = $this->getBytes($st['global']['upload'] + $st['session']['upload']);
        $text[] = "↓$td  ↑$tu";
        $data[] = [
            [
                'text'          => $this->i18n('reset stats'),
                'callback_data' => '/resetXrStats',
            ],
            [
                'text'          => $this->i18n('reset monthly') . ": " . $this->i18n($this->getPacConf()['reset_monthly'] ? 'on' : 'off'),
                'callback_data' => '/switchMonthlyStats',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('main outbound name: ') . ($p['outbound'] ?: 'proxy'),
                'callback_data' => '/mainOutbound',
            ],
        ];
        $data[] = [
            [
                'text'          => $p['linkdomain'] ?: $this->i18n('cdn'),
                'callback_data' => '/addLinkDomain',
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('Reality') . ' ' . ($p['transport'] == 'Reality' ? $this->i18n('on') : $this->i18n('off')),
                'callback_data' => "/changeTransport",
            ],
            [
                'text'          => $this->i18n('Websocket') . ' ' . ($p['transport'] != 'Reality' ? $this->i18n('on') : $this->i18n('off')),
                'callback_data' => "/changeTransport 1",
            ],
        ];
        $ip_count      = $p['ip_count'] ?: 1;
        $hwidEnabled   = !empty($p['hwid_limit_enabled']);
        $defaultHwids  = max(1, (int) ($p['hwid_device_count'] ?: 1));
        $data[] = [
            [
                'text'          => $this->i18n('ip limit') . ' ' . ($p['ip_limit'] ? ": {$p['ip_limit']} sec & $ip_count" : $this->i18n('off')),
                'callback_data' => "/setIpLimit",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('hwid limit') . ': ' . $this->i18n($hwidEnabled ? 'on' : 'off') . " ({$defaultHwids})",
                'callback_data' => '/toggleHwidLimit xray',
            ],
            [
                'text'          => $this->i18n('set hwid devices count'),
                'callback_data' => '/setHwidDevices xray',
            ],
        ];
        if ($p['transport'] == 'Reality') {
            $data[] = [
                [
                    'text'          => $this->i18n('changeFakeDomain'),
                    'callback_data' => "/changeFakeDomain",
                ],
                [
                    'text'          => $this->i18n('selfFakeDomain'),
                    'callback_data' => "/selfFakeDomain",
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('v2ray templates'),
                'callback_data' => "/templates v2ray",
            ],
            [
                'text'          => $this->i18n('sing-box templates'),
                'callback_data' => "/templates sing",
            ],
            [
                'text'          => $this->i18n('mihomo templates'),
                'callback_data' => "/templates clash",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('routes'),
                'callback_data' => "/routes",
            ],
        ];
        foreach ($c['inbounds'][0]['settings']['clients'] as $k => $v) {
            if (!empty($v['off'])) {
                $off++;
            } else {
                $on++;
            }
        }
        $type   = $this->getPacConf()['xtlslist'];
        $clients = array_filter($c['inbounds'][0]['settings']['clients'], fn($e) => !$type ? empty($e['off']) : !empty($e['off']));
        uasort($clients, fn($a, $b) => ($a['time'] ?: PHP_INT_MAX) <=> ($b['time'] ?: PHP_INT_MAX));

        $all     = (int) ceil(count($clients) / $this->limit);
        $page    = min($page, $all - 1);
        $page    = $page == -2 ? $all - 1 : $page;
        $clients = $page != -1 ? array_slice($clients, $page * $this->limit, $this->limit, true) : $clients;
        foreach ($clients as $k => $v) {
            $download = $this->getBytes($st['users'][$k]['global']['download'] + $st['users'][$k]['session']['download']);
            $upload   = $this->getBytes($st['users'][$k]['global']['upload'] + $st['users'][$k]['session']['upload']);
            $time     = $v['time'] ? $this->getTime($v['time']) : '';
            $data[]   = [
                [
                    'text'          => "{$v['email']}" . ($time ? ": $time" : '') . " (↓$download  ↑$upload)",
                    'callback_data' => "/userXr $k",
                ],
            ];
        }
        if ($page != -1 && $all > 1) {
            $data[] = [
                [
                    'text'          => '<<',
                    'callback_data' => "/xray " . ($page - 1 >= 0 ? $page - 1 : $all - 1),
                ],
                [
                    'text'          => $page + 1,
                    'callback_data' => "/xray $page",
                ],
                [
                    'text'          => '>>',
                    'callback_data' => "/xray " . ($page < $all - 1 ? $page + 1 : 0),
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('add'),
                'callback_data' => "/addXrUser",
            ],
            [
                'text'          => $this->i18n('on') . " $on " . (!$type ? "✅" : ''),
                'callback_data' => "/listXr 0",
            ],
            [
                'text'          => $this->i18n('off') . " $off " . ($type ? "✅" : ''),
                'callback_data' => "/listXr 1",
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

    public function routes($page = 0)
    {
        $text[] = "Menu -> " . $this->i18n('xray') . ' -> routes';

        $data = [
            [[
                'text'          => $this->i18n('block'),
                'callback_data' => "/xtlsblock",
            ]],
            [[
                'text'          => $this->i18n('warp'),
                'callback_data' => "/xtlswarp",
            ]],
            [[
                'text'          => 'domains',
                'callback_data' => "/xtlsproxy",
            ]],
            [[
                'text'          => "subnet",
                'callback_data' => "/xtlssubnet",
            ]],
            [[
                'text'          => 'process',
                'callback_data' => "/xtlsprocess",
            ]],
            [[
                'text'          => 'package',
                'callback_data' => "/xtlsapp",
            ]],
            [[
                'text'          => $this->i18n('rulesset'),
                'callback_data' => "/xtlsrulesset",
            ]],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/xray",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function warpPlus()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter key",
            $this->input['message_id'],
            reply: 'enter key',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'addWarpPlus',
            'args'           => [],
        ];
    }

    public function addWarpPlus($key)
    {
        $c = $this->getPacConf();
        if (!empty($key)) {
            $c['warp'] = $key;
            $this->send($this->input['chat'], 'Warp registration license: ' . $this->ssh("warp-cli --accept-tos registration license $key 2>&1", 'wp'));
        } else {
            unset($c['warp']);
        }
        $this->setPacConf($c);
        sleep(1);
        $this->warp();
    }

    public function warpStatus()
    {
        if (!empty($this->ssh('pgrep warp-svc', 'wp'))) {
            $st = $this->ssh('curl -m 1 -x socks5://127.0.0.1:40000 https://cloudflare.com/cdn-cgi/trace', 'wp');
            preg_match('~warp=(\w+)~', $st, $m);
            return trim($m[1]) ?: 'off';
        }
        return 'off';
    }

    public function analyzeXray()
    {
        while (true) {
            if (!empty($this->getPacConf()['ip_limit'])) {
                $this->pool = [];
                $log = '/logs/xray';
                $r   = fopen($log, 'r');
                fseek($r, 0, SEEK_END);
                while (true) {
                    $pac = $this->getPacConf();
                    if (empty($pac['ip_limit'])) {
                        break;
                    }
                    $currentPosition = ftell($r);
                    clearstatcache();
                    $fileSize = filesize($log);
                    if ($fileSize > $currentPosition) {
                        fseek($r, $currentPosition); // сброс флага feof
                        while (!feof($r)) {
                            $line = fgets($r);
                            if ($line !== false) {
                                $this->frequencyAnalyze($line, $pac);
                            }
                        }
                    }
                    sleep(1);
                }
                fclose($r);
            }
            sleep(10);
        }
    }

    public function frequencyAnalyze($line, $pac)
    {
        if (!empty($this->pool)) {
            foreach ($this->pool as $i => $j) {
                if (!empty($j['ips'])) {
                    foreach ($j['ips'] as $k => $v) {
                        if ($v + $pac['ip_limit'] < time()) {
                            unset($this->pool[$i]['ips'][$k]);
                        }
                    }
                }
            }
        }
        if (empty(preg_match('~(?<date>.+)\sfrom\s(?<ip>\d+\.\d+\.\d+\.\d+)(?=.+email:\s(?<email>.+))~', $line, $m))) {
            return;
        }
        if (!empty($m['ip']) && !empty($m['email'])) {
            $ip = ip2long($m['ip']);
            if (!empty($this->pool[$m['email']]['ip']) && $this->pool[$m['email']]['ip'] != $ip) {
                $this->pool[$m['email']]['ips'][$ip] = time();
            }
            if (!empty($this->pool[$m['email']]['ips']) && count($this->pool[$m['email']]['ips']) > ($pac['ip_count'] ?: 1)) {
                $xr = $this->getXray();
                foreach ($xr['inbounds'][0]['settings']['clients'] as $k => $v) {
                    if (empty($v['off']) && $v['email'] == $m['email']) {
                        require __DIR__ . '/config.php';
                        foreach ($c['admin'] as $admin) {
                            $this->send($admin, "vless: {$m['email']} limit ip " . count($this->pool[$m['email']]['ips']) . ' > ' . ($pac['ip_count'] ?: 1), button: [[
                                [
                                    'text'          => $this->i18n($c['off'] ? 'off' : 'on'),
                                    'callback_data' => "/switchXr $k",
                                ],
                            ]]);
                        }
                        unset($this->pool[$m['email']]);
                        break;
                    }
                }
            }
            $this->pool[$m['email']]['ip'] = $ip;
        }
    }

    public function offWarp()
    {
        $p    = $this->getPacConf();
        if (!empty($this->selfupdate)) {
            if (!empty($p['warpoff'])) {
                $this->ssh('warp-cli --accept-tos registration delete 2>&1', 'wp');
                $this->ssh('pkill warp-svc', 'wp');
            }
        } elseif (!empty($p['warpoff'])) {
            $this->ssh('warp-svc > /dev/null 2>&1 &', 'wp');
            sleep(3);
            if (empty($this->ssh('[ -f "/var/lib/cloudflare-warp/conf.json" ] && echo 1', 'wp'))) {
                $this->send($this->input['chat'], 'Registration: ' . $this->ssh('warp-cli --accept-tos registration new 2>&1', 'wp'));
                if (!empty($p['warp'])) {
                    $this->send($this->input['chat'], 'License: ' . $this->ssh("warp-cli --accept-tos registration license {$p['warp']} 2>&1", 'wp'));
                }
            }
            $this->send($this->input['chat'], 'Proxy mode: ' . $this->ssh('warp-cli --accept-tos mode proxy 2>&1', 'wp'));
            $this->send($this->input['chat'], 'Connect: ' . $this->ssh('warp-cli --accept-tos connect 2>&1', 'wp'));
            unset($p['warpoff']);
        } else {
            $this->send($this->input['chat'], 'Registration delete: ' . $this->ssh('warp-cli --accept-tos registration delete 2>&1', 'wp'));
            $this->ssh('pkill warp-svc', 'wp');
            $p['warpoff'] = 1;
        }
        $this->setPacConf($p);
        if (empty($this->selfupdate)) {
            $this->warp();
        }
    }

    public function warp()
    {
        $p      = $this->getPacConf();
        $text[] = "Menu -> " . $this->i18n('warp');
        $text[] = "status: " . $this->warpStatus();
        $text[] = "key: <code>{$this->getPacConf()['warp']}</code>";
        $data[] = [
            [
                'text'          => $this->i18n($p['warpoff'] ? 'off' : 'on'),
                'callback_data' => "/offWarp",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('set key'),
                'callback_data' => "/warpPlus",
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

    public function choiceTemplate($arg)
    {
        $arg = explode('_', $arg);
        $c   = $this->getXray();
        if (!empty($arg[2])) {
            $c['inbounds'][0]['settings']['clients'][$arg[1]]["{$arg[0]}template"] = $arg[2];
        } else {
            unset($c['inbounds'][0]['settings']['clients'][$arg[1]]["{$arg[0]}template"]);
        }
        file_put_contents('/config/xray.json', json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->userXr($arg[1]);
    }

    public function templateUser($type, $i)
    {
        $c         = $this->getXray();
        $pac       = $this->getPacConf();
        $text[]    = "Menu -> " . $this->i18n('xray') . " -> {$c['inbounds'][0]['settings']['clients'][$i]['email']}\n";
        $templates = $pac["{$type}templates"];
        $data[]    = [
            [
                'text'          => 'default',
                'callback_data' => "/choiceTemplate {$type}_$i",
            ],
        ];
        $data[] = [
            [
                'text'          => 'origin',
                'callback_data' => "/choiceTemplate {$type}_{$i}_" . base64_encode('origin'),
            ],
        ];
        foreach ($templates as $k => $v) {
            $data[] = [
                [
                    'text'          => $k,
                    'callback_data' => "/choiceTemplate {$type}_{$i}_" . base64_encode($k),
                ],
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/userXr $i",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function userXr($i)
    {
        $xray   = $this->getXray();
        $c      = $xray['inbounds'][0]['settings']['clients'][$i];
        $pac    = $this->getPacConf();
        $domain = $this->getDomain($pac['transport'] != 'Reality');
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();

        $devices      = $this->getHwidDevicesByUser($c['id']);
        $hwidEnabled  = !empty($pac['hwid_limit_enabled']) && empty($c['hwid_disabled']);
        $defaultHwid  = max(1, (int) ($pac['hwid_device_count'] ?: 1));
        $hwidLimit    = $c['hwid_limit'] ? (int) $c['hwid_limit'] : $defaultHwid;

        $text[] = "Menu -> " . $this->i18n('xray') . " -> {$c['email']}\n";
        if (file_exists(__DIR__ . '/subscription.php')) {
            $text[] = "<a href='$scheme://{$domain}/pac$hash/sub?id={$c['id']}'>subscription</a>";
        }
        $text[] = "<pre><code>{$this->linkXray($i)}</code></pre>\n";

        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=s&r=v&s={$c['id']}#{$c['email']}'>import://v2rayng</a>";
        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=si&r=si&s={$c['id']}#{$c['email']}'>import://sing-box</a>";
        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=s&r=st&s={$c['id']}#{$c['email']}'>import://streisand</a>";
        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=si&r=h&s={$c['id']}#{$c['email']}'>import://hiddify</a>";
        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=si&r=k&s={$c['id']}#{$c['email']}'>import://karing</a>";
        $text[] = "<a href='$scheme://{$domain}/pac$hash?t=si&r=c&s={$c['id']}#{$c['email']}'>import://mihomo</a>";

        $si = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'si',
            's' => $c['id'],
        ]));
        $xr = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 's',
            's' => $c['id'],
        ]));
        $cl = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'cl',
            's' => $c['id'],
        ]));

        $text[] = "\nxray config: <pre><code>$xr</code></pre>";
        $text[] = "sing-box config: <pre><code>$si</code></pre>";
        $text[] = "mihomo config: <pre><code>$cl</code></pre>";

        $text[]   = "sing-box windows: <a href='$scheme://{$domain}/pac$hash?t=si&r=w&s={$c['id']}'>windows service</a>";
        $st       = $this->getXrayStats();
        $download = $this->getBytes($st['users'][$i]['global']['download'] + $st['users'][$i]['session']['download']);
        $upload   = $this->getBytes($st['users'][$i]['global']['upload'] + $st['users'][$i]['session']['upload']);
        $data[]   = [
            [
                'text'          => $this->i18n('reset stats') . ": ↓$download  ↑$upload",
                'callback_data' => "/resetXrUser $i",
            ],
        ];
        $data[] = [
            [
                'text'    => $this->i18n('v2ray'),
                'web_app' => ['url' => "https://{$domain}/pac$hash?t=s&s={$c['id']}"]
            ],
            [
                'text'    => $this->i18n('singbox'),
                'web_app' => ['url' => "https://{$domain}/pac$hash?t=si&s={$c['id']}"]
            ],
            [
                'text'    => $this->i18n('mihomo'),
                'web_app' => ['url' => "https://{$domain}/pac$hash?t=cl&s={$c['id']}"]
            ],
        ];
        $data[] = [
            [
                'text'          => $c['time'] ? "timer: " . $this->getTime($c['time']) : $this->i18n('timer'),
                'callback_data' => "/timerXr $i",
            ],
            [
                'text'          => $this->i18n($c['off'] ? 'off' : 'on'),
                'callback_data' => "/switchXr $i",
            ],
        ];
        $singtemplate  = $c['singtemplate'] ? base64_decode($c['singtemplate']) : 'default(' . ($pac['defaultsingtemplate'] && !empty($pac['singtemplates'][base64_decode($pac['defaultsingtemplate'])]) ? base64_decode($pac['defaultsingtemplate']) : 'origin') . ')';
        $v2raytemplate = $c['v2raytemplate'] ? base64_decode($c['v2raytemplate']) : 'default(' . ($pac['defaultv2raytemplate'] && !empty($pac['v2raytemplates'][base64_decode($pac['defaultv2raytemplate'])]) ? base64_decode($pac['defaultv2raytemplate']) : 'origin') . ')';
        $clashtemplate = $c['clashtemplate'] ? base64_decode($c['clashtemplate']) : 'default(' . ($pac['defaultclashtemplate'] && !empty($pac['clashtemplates'][base64_decode($pac['defaultclashtemplate'])]) ? base64_decode($pac['defaultclashtemplate']) : 'origin') . ')';
        $data[]        = [
            [
                'text'          => $this->i18n('v2ray') . ": $v2raytemplate",
                'callback_data' => "/templateUser v2ray $i",
            ],
            [
                'text'          => $this->i18n('singbox') . ": $singtemplate",
                'callback_data' => "/templateUser sing $i",
            ],
            [
                'text'          => $this->i18n('mihomo') . ": $clashtemplate",
                'callback_data' => "/templateUser clash $i",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('qr short'),
                'callback_data' => "/qrXray $i",
            ],
            [
                'text'          => $this->i18n('qr v2ray'),
                'callback_data' => "/qrXray {$i}_1",
            ],
            [
                'text'          => $this->i18n('qr singbox'),
                'callback_data' => "/qrXray {$i}_2",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('hwid limit') . ': ' . ($hwidEnabled ? $hwidLimit : $this->i18n('off')) . ' (' . count($devices) . ')',
                'callback_data' => "/hwidUser $i",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('rename'),
                'callback_data' => "/renameXrUser $i",
            ],
            [
                'text'          => $this->i18n('delete'),
                'callback_data' => "/delxr $i",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/xray",
            ],
        ];
        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function hwidUser($i, $page = 0)
    {
        $xray   = $this->getXray();
        $client = $xray['inbounds'][0]['settings']['clients'][$i];
        $pac    = $this->getPacConf();

        $devices = $this->getHwidDevicesByUser($client['id']);
        $scope   = $this->getHwidTokenScope($i);
        if (!isset($_SESSION['hwidTokens'])) {
            $_SESSION['hwidTokens'] = [];
        }
        $_SESSION['hwidTokens'][$scope] = [];
        uasort($devices, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        $hwids        = array_keys($devices);
        $perPage      = max(1, $this->limit ?: 5);
        $total        = count($hwids);
        $pages        = max(1, (int) ceil($total / $perPage));
        $page         = min(max((int) $page, 0), $pages - 1);
        $hwidsPage    = array_slice($hwids, $page * $perPage, $perPage);
        $defaultHwid  = max(1, (int) ($pac['hwid_device_count'] ?: 1));

        $text[] = "Menu -> " . $this->i18n('xray') . " -> {$client['email']} -> " . $this->i18n('hwid devices');
        $text[] = $this->i18n('hwid notice');
        if (empty($pac['hwid_limit_enabled'])) {
            $status = $this->i18n('off');
        } elseif (!empty($client['hwid_disabled'])) {
            $status = $this->i18n('off');
        } elseif (!empty($client['hwid_limit'])) {
            $status = (int) $client['hwid_limit'];
        } else {
            $status = "default($defaultHwid)";
        }
        $text[] = $this->i18n('hwid limit') . ': ' . $status;
        $text[] = $this->i18n('hwid devices') . ': ' . $total;

        $data[] = [
            [
                'text'          => $this->i18n(!empty($client['hwid_disabled']) ? 'off' : 'on'),
                'callback_data' => "/hwidUserToggle $i",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('set hwid devices count'),
                'callback_data' => "/setHwidUserLimit $i",
            ],
        ];
        if (!empty($client['hwid_limit'])) {
            $data[] = [
                [
                    'text'          => $this->i18n('use default hwid limit'),
                    'callback_data' => "/hwidUserDefault $i",
                ],
            ];
        }

        if ($total == 0) {
            $text[] = $this->i18n('no devices');
        }

        foreach ($hwidsPage as $index => $hwid) {
            $info    = $devices[$hwid];
            $number  = $page * $perPage + $index + 1;
            $details = array_filter([
                $info['device_os'] ?? '',
                $info['os_version'] ?? '',
                $info['device_model'] ?? '',
            ], fn($v) => $v !== '');
            $line = $number . '. <code>' . htmlspecialchars($hwid, ENT_HTML5, 'UTF-8') . '</code>';
            if (!empty($details)) {
                $line .= ' - ' . htmlspecialchars(implode(' ', $details), ENT_HTML5, 'UTF-8');
            }
            if (!empty($info['time'])) {
                $line .= ' (' . date('d.m.Y H:i', $info['time']) . ')';
            }
            $text[] = $line;
            if (!empty($info['user_agent'])) {
                $text[] = 'UA: ' . htmlspecialchars($info['user_agent'], ENT_HTML5, 'UTF-8');
            }
            $token = $this->rememberHwidToken($scope, $hwid);
            $data[] = [
                [
                    'text'          => '🗑 ' . $number,
                    'callback_data' => "/hwidUserDel {$i}_{$page} $token",
                ],
            ];
        }

        if ($pages > 1) {
            $data[] = [
                [
                    'text'          => '<<',
                    'callback_data' => "/hwidUser {$i}_" . ($page - 1 >= 0 ? $page - 1 : $pages - 1),
                ],
                [
                    'text'          => ($page + 1) . '/' . $pages,
                    'callback_data' => "/hwidUser {$i}_$page",
                ],
                [
                    'text'          => '>>',
                    'callback_data' => "/hwidUser {$i}_" . (($page + 1) % $pages),
                ],
            ];
        }

        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/userXr $i",
            ],
        ];

        $this->update(
            $this->input['chat'],
            $this->input['message_id'],
            implode("\n", $text ?: ['...']),
            $data ?: false,
        );
    }

    public function hwidUserToggle($i)
    {
        $xray = $this->getXray();
        if (!empty($xray['inbounds'][0]['settings']['clients'][$i]['hwid_disabled'])) {
            unset($xray['inbounds'][0]['settings']['clients'][$i]['hwid_disabled']);
        } else {
            $xray['inbounds'][0]['settings']['clients'][$i]['hwid_disabled'] = 1;
        }
        file_put_contents('/config/xray.json', json_encode($xray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->answer($this->input['callback_id'], $this->i18n('hwid notice'), true);
        $this->hwidUser($i);
    }

    public function hwidUserDefault($i)
    {
        $xray = $this->getXray();
        unset($xray['inbounds'][0]['settings']['clients'][$i]['hwid_limit']);
        file_put_contents('/config/xray.json', json_encode($xray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->hwidUser($i);
    }

    public function setHwidUserLimit($i)
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter hwid devices count",
            $this->input['message_id'],
            reply: 'enter hwid devices count',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message' => $this->input['message_id'],
            'callback'      => 'saveHwidUserLimit',
            'args'          => [$i],
        ];
    }

    public function saveHwidUserLimit($count, $i)
    {
        $xray = $this->getXray();
        $count = (int) $count;
        if ($count > 0) {
            $xray['inbounds'][0]['settings']['clients'][$i]['hwid_limit'] = $count;
        } else {
            unset($xray['inbounds'][0]['settings']['clients'][$i]['hwid_limit']);
        }
        file_put_contents('/config/xray.json', json_encode($xray, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->send($this->input['chat'], $this->i18n('hwid notice'), $this->input['message_id']);
        $this->hwidUser($i);
    }

    public function hwidUserDel($i, $page, $hwid)
    {
        $xray = $this->getXray();
        $uid    = $xray['inbounds'][0]['settings']['clients'][$i]['id'];
        $scope  = $this->getHwidTokenScope($i);
        $decoded = $this->resolveHwidToken($scope, $hwid);
        if ($decoded !== '') {
            $this->deleteHwidDevice($uid, $decoded);
        }
        $this->hwidUser($i, $page);
    }

    public function getDomain($cdn = false)
    {
        $c = $this->getPacConf();
        if ($cdn && $c['linkdomain']) {
            return $c['linkdomain'];
        }
        return $c['domain'] ?: $this->ip;
    }

    public function sub()
    {
        $xr     = $this->getXray();
        $pac    = $this->getPacConf();
        $st     = $this->getXrayStats();
        $domain = $_GET['cdn'] ?: ($_SERVER['SERVER_NAME'] ?: $this->getDomain($pac['transport'] != 'Reality'));
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();
        $flag   = true;
        $client = null;
        foreach ($xr['inbounds'][0]['settings']['clients'] as $k => $v) {
            if ($v['id'] == $_GET['id']) {
                if (empty($v['off'])) {
                    $flag = false;
                }
                $uid    = $v['id'];
                $email  = $v['email'];
                $expire = $v['time'];
                $client = $v;
                break;
            }
        }
        if (!$flag && !$this->processHwidRequest($client)) {
            exit;
        }
        $suburl   = "<a href='$scheme://{$domain}/pac$hash/sub?id={$uid}'>subscription</a>";
        $download = $this->getBytes($st['users'][$k]['global']['download'] + $st['users'][$k]['session']['download']);
        $upload   = $this->getBytes($st['users'][$k]['global']['upload'] + $st['users'][$k]['session']['upload']);
        $singbox  = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'si',
            's' => $uid,
        ]));
        $xray = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 's',
            's' => $uid,
        ]));
        $clash = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
            'h' => $hash,
            't' => 'cl',
            's' => $uid,
        ]));
        $vless   = $this->linkXray($k);
        $windows = "$scheme://{$domain}/pac$hash?t=si&r=w&s=$uid";
        $_GET['s'] = $uid;
        foreach ([
          'xray'    => 's',
          'singbox' => 'si',
          'clash'   => 'cl'
        ] as $k     => $v) {
            $_GET['t'] = $v;
            $configs[$k] = $this->subscription(1);
        }
        require __DIR__ . '/subscription.php';
    }

    public function subscription($return = false)
    {
        switch ($_GET['t']) {
            case 's':
                $type = 'v2ray';
                break;
            case 'si':
                $type = 'sing';
                break;
            case 'cl':
                $type = 'clash';
                break;
        }
        $pac    = $this->getPacConf();
        $domain = $_GET['cdn'] ?: ($_SERVER['SERVER_NAME'] ?: $this->getDomain($pac['transport'] != 'Reality'));
        $xr     = $this->getXray();
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();

        $flag = true;
        $client = null;
        foreach ($xr['inbounds'][0]['settings']['clients'] as $k => $v) {
            if ($v['id'] == $_GET['s']) {
                if (empty($v['off'])) {
                    $flag = false;
                }
                $template = base64_decode($v["{$type}template"]);
                $uid      = $v['id'];
                $email    = $v['email'];
                $client   = $v;
                break;
            }
        }
        if ($flag) {
            header('500', true, 500);
            exit;
        }

        if (!$return && !$this->processHwidRequest($client)) {
            exit;
        }

        if (!empty($_GET['r'])) {
            $si = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
                'h' => $hash,
                't' => 'si',
                's' => $uid,
            ]));
            $v2 = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
                'h' => $hash,
                't' => 's',
                's' => $uid,
            ]));
            $cl = "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
                'h' => $hash,
                't' => 'cl',
                's' => $uid,
            ]));
            switch ($_GET['r']) {
                case 'si':
                    header("Location: sing-box://import-remote-profile/?url=$si");
                    exit;
                case 'st':
                    header("Location: streisand://import/$v2");
                    exit;
                case 'v':
                    header("Location: v2rayng://install-config?url=$v2");
                    exit;
                case 'k':
                    header("Location: karing://install-config?url=$si");
                    exit;
                case 'h':
                    header("Location: hiddify://install-config/?url=$si");
                    exit;
                case 'c':
                    header("Location: clash://install-config/?url=$cl&overwrite=no&name=$email");
                    exit;
                case 'w':
                    $link = htmlspecialchars($si, ENT_XML1, 'UTF-8');
                    $n    = "singbox_$uid.zip";
                    copy('/singbox/singbox.zip', $n);
                    $zip = new ZipArchive();
                    $zip->open($n, ZipArchive::CREATE);
                    $zip->addFromString('winsw3.xml', preg_replace('#~url~#', $link, file_get_contents('/singbox/winsw3.xml')));
                    $zip->close();
                    header('Content-Disposition: attachment; filename="singbox.zip"');
                    echo file_get_contents($n);
                    unlink($n);
                    exit;
            }
        }
        switch (true) {
            case !empty($template) && $template == 'origin':
            case empty($template) && empty($pac["default{$type}template"]):
            case empty($template) && empty($pac["{$type}templates"][base64_decode($pac["default{$type}template"])]):
            case !empty($template) && empty($pac["{$type}templates"][$template]):
                $c = json_decode(file_get_contents("/config/{$type}.json"), true);
                break;
            case !empty($template):
                $c = $pac["{$type}templates"][$template];
                break;

            default:
                $c = $pac["{$type}templates"][base64_decode($pac["default{$type}template"])];
                break;
        }

        $outbound = $pac['outbound'] ?: 'proxy';
        $c = json_decode($this->replaceTags(json_encode($c), [
            '~outbound~' => $outbound,
        ]), true);
        foreach ($c['outbounds'] as $k => $v) {
            if ($v['tag'] == $outbound) {
                $index = $k;
                break;
            }
        }
        if (!isset($index)) {
            foreach ($c['proxies'] as $k => $v) {
                if ($v['name'] == $outbound) {
                    $index = $k;
                    break;
                }
            }
        }

        switch ($_GET['t']) {
            case 's':
                $c['outbounds'][$index]['settings']['vnext'][0]['address']  = '~domain~';
                $c['outbounds'][$index]['settings']['vnext'][0]['users'][0] = [
                    'id'         => '~uid~',
                    'encryption' => 'none',
                ];
                if ($pac['transport'] != 'Reality') {
                    $c['outbounds'][$index]['streamSettings'] = [
                        "network"    => "ws",
                        "security"   => "tls",
                        "wsSettings" => [
                            "path" => "/ws$hash?ed=2560"
                        ],
                        "tlsSettings" => [
                            "allowInsecure" => false,
                            "serverName"    => '~domain~',
                            "fingerprint"   => "chrome"
                        ]
                    ];
                    unset($c['outbounds'][$index]['mux']);
                } else {
                    $c['outbounds'][$index]['settings']['vnext'][0]['users'][0]["flow"] = "xtls-rprx-vision";
                    $c['outbounds'][$index]['streamSettings']                           = [
                        "network"         => "tcp",
                        "security"        => "reality",
                        "realitySettings" => [
                            "serverName"  => '~server_name~',
                            "fingerprint" => "chrome",
                            "publicKey"   => '~public_key~',
                            "shortId"     => '~short_id~',
                        ]
                    ];
                    $c['outbounds'][$index]['mux'] = [
                        "enabled"     => false,
                        "concurrency" => -1
                    ];
                }
                break;
            case 'si':
                $c['outbounds'][$index]['uuid']   = '~uid~';
                if ($pac['transport'] != 'Reality') {
                    unset($c['outbounds'][$index]['tls']['reality']);
                    unset($c['outbounds'][$index]['flow']);
                    $c['outbounds'][$index]["transport"] = [
                        "type" => "ws",
                        "path" => "/ws$hash"
                    ];
                    $c['outbounds'][$index]['tls']['server_name'] = '~domain~';
                } else {
                    unset($c['outbounds'][$index]["transport"]);
                    $c['outbounds'][$index]['flow']                         = 'xtls-rprx-vision';
                    $c['outbounds'][$index]['tls']['reality']['public_key'] = '~public_key~';
                    $c['outbounds'][$index]['tls']['server_name']           = '~server_name~';
                    $c['outbounds'][$index]['tls']['reality']['short_id']   = '~short_id~';
                }
                break;
            case 'cl':
                $c['proxies'][$index]['server'] = '~domain~';
                $c['proxies'][$index]['uuid']   = '~uid~';
                if ($pac['transport'] != 'Reality') {
                    unset($c['proxies'][$index]['flow']);
                    unset($c['proxies'][$index]['reality-opts']);
                    $c['proxies'][$index]["network"]          = "ws";
                    $c['proxies'][$index]["ws-opts"]['path']  = "/ws$hash";
                    $c['proxies'][$index]["skip-cert-verify"] = false;
                    $c['proxies'][$index]['servername']      = '~domain~';
                } else {
                    unset($c['proxies'][$index]["ws-opts"]);
                    unset($c['proxies'][$index]["skip-cert-verify"]);
                    $c['proxies'][$index]["network"]      = "tcp";
                    $c['proxies'][$index]['flow']         = 'xtls-rprx-vision';
                    $c['proxies'][$index]['servername']  = '~server_name~';
                    $c['proxies'][$index]['reality-opts'] = [
                        'public-key' => '~public_key~',
                        'short-id'   => '~short_id~',
                    ];
                }
                break;
        }
        $c = json_decode($this->replaceTags(json_encode($c), [
            '"~pac~"'        => json_encode(array_keys(array_filter($pac['includelist'] ?: []))),
            '"~block~"'      => json_encode(array_keys(array_filter($pac['blocklist'] ?: []))),
            '"~warp~"'       => json_encode(array_keys(array_filter($pac['warplist'] ?: []))),
            '"~process~"'    => json_encode(array_keys(array_filter($pac['processlist'] ?: []))),
            '"~package~"'    => json_encode(array_keys(array_filter($pac['packagelist'] ?: []))),
            '"~subnet~"'     => json_encode(array_keys(array_filter($pac['subnetlist'] ?: []))),
            '~dns~'          => "https://$domain/dns-query$hash/$uid",
            '~dnspath~'      => "/dns-query$hash/$uid",
            '~uid~'          => $uid,
            '~domain~'       => $domain,
            '~directdomain~' => $pac['domain'],
            '~cdndomain~'    => $pac['linkdomain'],
            '~short_id~'     => $xr['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0],
            '~email~'        => $email,
            '~public_key~'   => $pac['xray'],
            '~server_name~'  => $xr['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0],
            '~ip~'           => $this->ip,
        ]), true);

        switch ($_GET['t']) {
            case 's':
                if (!empty($c['routing']['rules'])) {
                    foreach ($c['routing']['rules'] as $k => $v) {
                        if (array_key_exists('domain', $v) && empty($v['domain'])) {
                            unset($c['routing']['rules'][$k]);
                        }
                    }
                    $c['routing']['rules'] = array_values($c['routing']['rules']);
                }
                break;
            case 'si':
                $c['route'] = $this->addRuleSet($c['route']);
                $c['route'] = $this->createRuleSet($c['route'], $uid, $domain);
                if (!empty($c['route']['rules'])) {
                    foreach ($c['route']['rules'] as $k => $v) {
                        if (count($v) == 1 && array_key_exists('outbound', $v)) {
                            unset($c['route']['rules'][$k]);
                        }
                    }
                    $c['route']['rules'] = array_values($c['route']['rules']);
                }
                if (empty($c['route'])) {
                    unset($c['route']);
                }
                break;
            case 'cl':
                $c = $this->addClashRuleSet($c);
                if (!empty($c['rules'])) {
                    $c = $this->clashRules($c, $uid, $domain);
                    if (count($c['rules']) == 1) {
                        unset($c['rules']);
                    }
                }
                break;
        }
        if (!empty($return)) {
            if ($_GET['t'] == 'cl') {
                return yaml_emit($c);
            }
            return json_encode($c);
        }

        if ($_GET['t'] == 'cl') {
            header('Content-type: text/yaml');
            echo yaml_emit($c);
            return;
        }

        header('Content-type: application/json');
        echo json_encode($c);
    }

    public function addClashRuleSet($c)
    {
        $p = $this->getPacConf();
        if (!empty($p['rulessetlist']) && $c['add-rule-providers']) {
            foreach ($p['rulessetlist'] as $k => $v) {
                if (!empty($v)) {
                    [$type, $behavior, $time, $url] = explode(':', $k, 4);
                    if (preg_match('~\.(mrs|yaml|yml)$~', $url, $m)) {
                        $c['rule-providers'][$url] = [
                            'type'     => 'http',
                            'url'      => $url,
                            'interval' => (int) $time,
                            'behavior' => $behavior,
                            'format'   => $m[1],
                        ];
                        switch ($type) {
                            case 'reject':
                            case 'REJECT':
                                array_unshift($c['rules'], [
                                    'RULE-SET', $url, strtoupper($type)
                                ]);
                                break;

                            default:
                                array_splice($c['rules'], count($c['rules']) - 1, 0, [[
                                    'RULE-SET', $url, strtoupper($type)
                                ]]);
                                break;
                        }
                    }
                }
            }
        }
        unset($c['add-rule-providers']);
        if (empty($c['rule-providers'])) {
            unset($c['rule-providers']);
        }
        return $c;
    }

    public function clashRules($c, $uid, $domain)
    {
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();
        foreach ($c['rules'] as $v) {
            if (array_key_exists('list', $v)) {
                if ($v['type'] == 'RULE-SET') {
                    if (!empty($_GET['r']) && $v['name'] == $_GET['r']) {
                        header("Content-Disposition: attachment; filename={$v['name']}.yaml");
                        header('Content-Type: text/yaml');
                        switch ($v['behavior']) {
                            case 'domain':
                                echo yaml_emit(['payload' => array_map(fn($e) => "+.$e", $v['list'])]);
                                break;
                            case 'ipcidr':
                                echo yaml_emit(['payload' => array_map(fn($e) => $e, $v['list'])]);
                                break;

                            default:
                                echo yaml_emit(['payload' => array_map(fn($e) => "PROCESS-NAME,$e", $v['list'])]);
                                break;
                        }
                        exit;
                    }
                    $c['rule-providers'][$v['name']] = [
                        'type'     => 'http',
                        'url'      => "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
                            'h' => $hash,
                            't' => 'cl',
                            's' => $uid,
                            'r' => $v['name'],
                        ])),
                        'interval' => $v['interval'],
                        'behavior' => $v['behavior'],
                        'format'   => 'yaml',
                    ];
                    $tmp[] = "{$v['type']}, {$v['name']}, {$v['action']}";
                } else {
                    if (!empty($v['list'])) {
                        foreach ($v['list'] as $j) {
                            $tmp[] = "{$v['type']}, $j, {$v['action']}";
                        }
                    }
                }
            } else {
                $tmp[] = implode(', ', $v);
            }
        }
        $c['rules'] = $tmp;
        return $c;
    }

    public function replaceTags($subject, $tags)
    {
        return str_replace(array_keys($tags), array_values($tags), $subject);
    }

    public function addRuleSet($route)
    {
        if (!empty($route['rules'])) {
            foreach ($route['rules'] as $k => $v) {
                if (!empty($v['addruleset'])) {
                    $t[$v['outbound'] ?: 'block'] = $k;
                }
            }
            $p = $this->getPacConf();
            if (!empty($p['rulessetlist'])) {
                foreach ($p['rulessetlist'] as $k => $v) {
                    if (!empty($v)) {
                        [$type, $time, $url] = explode(':', $k, 3);
                        if (preg_match('~\.srs$~', $url) && !empty($route['rules'][$t[$type]])) {
                            $route['rule_set'][] = [
                                "tag"             => $k,
                                "type"            => "remote",
                                "format"          => "binary",
                                "url"             => $url,
                                "download_detour" => "direct",
                                "update_interval" => $time
                            ];
                            $route['rules'][$t[$type]]['rule_set'][] = $k;
                        }
                    }
                }
            }
            foreach ($route['rules'] as $k => $v) {
                unset($route['rules'][$k]['addruleset']);
            }
        }
        return $route;
    }

    public function cleanEmptyKeys(array $arr)
    {
        foreach ($arr as $k => $v) {
            if (empty($v)) {
                unset($arr[$k]);
            } elseif (is_array($v)) {
                $arr[$k] = $this->cleanEmptyKeys($v);
                if (empty($arr[$k])) {
                    unset($arr[$k]);
                }
            }
        }
        return $arr;
    }

    public function createSrs(string $name, array $rules)
    {
        $rules = $this->cleanEmptyKeys($rules);
        header("Content-Disposition: attachment; filename=$name.srs");
        header('Content-Type: application/binary');
        $f = "/tmp/$name" . time() . rand(1, 100);
        file_put_contents($f, json_encode([
            'version' => 1,
            'rules'   => $rules ?: [],
        ]));
        exec("sing-box rule-set compile $f");
        echo file_get_contents("$f.srs");
        unlink($f);
        unlink("$f.srs");
        exit;
    }

    public function createRuleSet($route, $uid, $domain)
    {
        $scheme = empty($this->nginxGetTypeCert()) ? 'http' : 'https';
        $hash   = $this->getHashBot();

        foreach ($route['rules'] as $k => $v) {
            if (!empty($v['createruleset'])) {
                foreach ($v['createruleset'] as $r) {
                    if (!empty($_GET['r']) && $r['name'] == $_GET['r']) {
                        $this->createSrs($r['name'], $r['rules']);
                    }
                    $ruleset[] = [
                        "tag"             => $r['name'],
                        "url"             => "$scheme://{$domain}/pac$hash/" . base64_encode(serialize([
                            'h' => $hash,
                            't' => 'si',
                            's' => $uid,
                            'r' => $r['name'],
                        ])),
                        "update_interval" => $r['interval'],
                        "type"            => "remote",
                        "format"          => "binary",
                        "download_detour" => "direct",
                    ];
                    $route['rules'][$k]['rule_set'][] = $r['name'];
                }
                unset($route['rules'][$k]['createruleset']);
                if (empty($route['rules'][$k]['rule_set'])) {
                    unset($route['rules'][$k]);
                }
            }
        }
        if (!empty($route['rules'])) {
            $route['rules']    = array_values($route['rules']);
        }
        $route['rule_set'] = array_merge($route['rule_set'] ?: [], $ruleset ?: []);
        if (empty($route['rule_set'])) {
            unset($route['rule_set']);
        }
        return $route;
    }

    public function getXray()
    {
        return json_decode(file_get_contents('/config/xray.json'), true);
    }

    public function generateSecretXray()
    {
        $c       = $this->getXray();
        $shortId = trim($this->ssh('openssl rand -hex 8', 'xr'));
        $keys    = $this->ssh('xray x25519', 'xr');
        preg_match('~^Private key:\s([^\s]+)~m', $keys, $m);
        $private = trim($m[1]);
        preg_match('~^Public key:\s([^\s]+)~m', $keys, $m);
        $public = trim($m[1]);
        $c['inbounds'][0]['streamSettings']['realitySettings']['privateKey'] = $private;
        $c['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0] = $shortId;
        $pac         = $this->getPacConf();
        $pac['xray'] = $public;
        $pac['reality']['shortId'] = $shortId;
        $pac['reality']['privateKey'] = $private;
        $this->setPacConf($pac);
        $this->restartXray($c);
    }

    public function setUpstreamDomain($domain)
    {
        $nginx = file_get_contents('/config/upstream.conf');
        $t = preg_replace('~#domain.+#domain~s', "#domain\n$domain reality;\n#domain", $nginx);
        file_put_contents('/config/upstream.conf', $t);
        $this->ssh("nginx -s reload 2>&1", 'up');
    }
    public function setUpstreamDomainOcserv($domain)
    {
        $sub   = $this->getHashSubdomain('oc');
        $nginx = file_get_contents('/config/upstream.conf');
        $t     = preg_replace('~#ocserv.+#ocserv~s', $domain ? "#ocserv\n$sub.$domain ocserv;\n#ocserv" : "#ocserv\n#$sub.\$domain ocserv;\n#ocserv", $nginx);
        file_put_contents('/config/upstream.conf', $t);
        $this->ssh("nginx -s reload 2>&1", 'up');
    }
    public function setUpstreamDomainNaive($domain)
    {
        $sub   = $this->getHashSubdomain('np');
        $nginx = file_get_contents('/config/upstream.conf');
        $t = preg_replace('~#naive.+#naive~s', $domain ? "#naive\n$sub.$domain naive;\n#naive" : "#naive\n#$sub.\$domain naive;\n#naive", $nginx);
        file_put_contents('/config/upstream.conf', $t);
        $this->ssh("nginx -s reload 2>&1", 'up');
    }

    public function getHashBot($notset = false)
    {
        $p = $this->getPacConf();
        if (!empty($p['hashbot'])) {
            return $p['hashbot'];
        }
        $p['hashbot'] = substr(hash('sha256', $this->key), 0, 8);
        if (empty($notset)) {
            $this->setPacConf($p);
        }
        return $p['hashbot'];
    }

    public function cloakNginx()
    {
        $conf     = $this->getPacConf();
        $template = file_get_contents('/config/nginx_default.conf');
        // $template = preg_replace('~server_name ip~', "server_name {$this->ip}", $template);
        $template = preg_replace('~server_name domain~', "server_name " . ($conf['domain'] ? " *.{$conf['domain']} {$conf['domain']}" : '_'), $template);
        if ($conf['domain'] && $conf['letsencrypt']) {
            $template = preg_replace('/#~([^\n]+)?/', "#~{$conf['letsencrypt']}", $template);
            preg_match_all('~#-domain.+?#-domain~s', $template, $m);
            foreach ($m[0] as $v) {
                $template = preg_replace('~#-domain.+?#-domain~s', $this->uncomment($v, 'domain'), $template, 1);
            }
        }
        $h = $this->getHashBot();
        $s = empty($conf['adgbrowser']) ? '' : '#';
        $r = <<<CONF
        location /adguard/ {
                access_log /logs/nginx_adguard_access;
                if (\$cookie_c != "$h") {
                    $s rewrite .* /webapp redirect;
                }
                proxy_pass http://ad/;
                proxy_redirect / /adguard/;
                proxy_cookie_path / /adguard/;
            }
            location
        CONF;
        $template = preg_replace('~(location /adguard.+?})\s*location~s', $r, $template);
        $template = preg_replace('~(/webapp|/pac|/adguard|/ws|/v2ray|location /dns-query)~', '${1}' . $h, $template);
        file_put_contents('/config/nginx.conf', $template);
        $x = $this->getXray();
        if (!empty($x['inbounds'][0]['streamSettings']['wsSettings']['path'])) {
            $x['inbounds'][0]['streamSettings']['wsSettings']['path'] = "/ws$h";
            $this->restartXray($x);
        }

        return $this->ssh('nginx -s reload', 'ng');
    }

    public function getHashSubdomain($subdomain)
    {
        $p = $this->getPacConf();
        if (!empty($p["{$subdomain}_domain"])) {
            return $p["{$subdomain}_domain"];
        }
        $p["{$subdomain}_domain"] = substr(hash('sha256', "$subdomain{$this->key}"), 0, 8);
        $this->setPacConf($p);
        return $p["{$subdomain}_domain"];
    }

    public function addWg($page)
    {
        $text = "Menu -> {$this->getTitleWG()} -> Add peer\n\n";
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
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu wg $page",
            ]
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function adguardBasicAuth()
    {
        return base64_encode('admin:' . $this->getPacConf()['adpswd']);
    }

    public function adguardChBr()
    {
        $c = $this->getPacConf();
        $c['adgbrowser'] = $c['adgbrowser'] ? 0 : 1;
        $this->setPacConf($c);
        $this->cloakNginx();
        $this->answer($this->input['callback_id'], $this->i18n($c['adgbrowser'] ? 'browser_notify_on' : 'browser_notify_off'), true);
        $this->menu('adguard');
    }

    public function adguardMenu()
    {
        $conf   = $this->getPacConf();
        $ip     = $this->ip;
        $domain = $this->getDomain();
        $hash   = $this->getHashBot();
        $scheme = empty($ssl = $this->nginxGetTypeCert()) ? 'http' : 'https';
        $text   = "$scheme://$domain/adguard$hash\nLogin: admin\nPass: <span class='tg-spoiler'>{$conf['adpswd']}</span>\n\n";
        if ($ssl) {
            $text .= "DNS over HTTPS:\n<code>$ip</code>\n<code>$scheme://$domain/dns-query$hash" . ($conf['adguardkey'] ? "/{$conf['adguardkey']}" : '') . "</code>\n\n";
            $text .= "DNS over TLS:\n<code>tls://" . ($conf['adguardkey'] ? "{$conf['adguardkey']}." : '') . "$domain</code>";
        }
        $status = $this->i18n(exec("JSON=1 timeout 2 dnslookup google.com ad") ? 'on' : 'off');
        $safesearch = yaml_parse_file($this->adguard)['filtering']['safe_search']['enabled'];
        $text .= "\n\nstatus: $status\t\tsafesearch: " . $this->i18n($safesearch ? 'on' : 'off');
        $allowedClients = yaml_parse_file($this->adguard)['dns']['allowed_clients'];
        $text .= $allowedClients ? "\n\nallowed clients: \n - " . implode("\n - ", $allowedClients) : '';

        $data = [
            [
                [
                    'text'          => 'web panel',
                    'web_app' => [
                        "url" => "https://$domain/adguard$hash"
                    ],
                ],
                [
                    'text'          => $this->i18n('third party browser') . ': ' . $this->i18n($conf['adgbrowser'] ? 'on' : 'off'),
                    'callback_data' => '/adguardChBr'
                ],
            ],
            [
                [
                    'text'          => $this->i18n('change password'),
                    'callback_data' => "/adguardpsswd",
                ],
                [
                    'text'          => 'ClientID' . ($conf['adguardkey'] ? ": {$conf['adguardkey']}" : ''),
                    'callback_data' => "/setAdguardKey",
                ],
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('fill allowed clients'),
                'callback_data' => "/adgFillAllowedClients 0",
            ],
            [
                'text'          => $this->i18n('delete allowed clients'),
                'callback_data' => "/adgFillAllowedClients 1",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('check DNS'),
                'callback_data' => "/checkdns",
            ],
            [
                'text'          => $this->i18n('reset settings'),
                'callback_data' => "/adguardreset",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('add upstream'),
                'callback_data' => "/addupstream",
            ],
        ];
        $upstreams = yaml_parse_file($this->adguard)['dns']['upstream_dns'];
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
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => $text,
            'data' => $data,
        ];
    }

    public function adgFillAllowedClients($delete = false)
    {
        $pac = $this->getPacConf();
        $out[] = 'Restart Adguard Home';
        $this->update($this->input['chat'], $this->input['message_id'], implode("\n", $out));
        $this->stopAd();
        $c = yaml_parse_file($this->adguard);
        if (!empty($delete)) {
            unset($c['dns']['allowed_clients']);
        } else {
            $c['dns']['allowed_clients'] = [];
            $c['dns']['allowed_clients'][] = '10.10.0.0/24';
            if (!empty($pac['adguardkey'])) {
                $c['dns']['allowed_clients'][] = $pac['adguardkey'];
            }
            $c['dns']['allowed_clients'][] = getenv('WGADDRESS');
            $c['dns']['allowed_clients'][] = getenv('WG1ADDRESS');
            $c['dns']['allowed_clients'][] = '10.0.2.0/24'; // openconnect
            if (!empty($xr = $this->getXray())) {
                foreach ($xr['inbounds'][0]['settings']['clients'] as $v) {
                    $c['dns']['allowed_clients'][] = $v['id'];
                }
            }
        }
        yaml_emit_file($this->adguard, $c);
        $this->startAd();
        $this->menu('adguard');
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

    public function expireCert()
    {
        $c = openssl_x509_read(file_get_contents("/certs/cert_public"));
        return openssl_x509_parse($c)["validTo_time_t"] ?: false;
    }

    public function domainsCert()
    {
        $domains = openssl_x509_parse(openssl_x509_read(file_get_contents("/certs/cert_public")))['extensions']["subjectAltName"];
        if (empty($domains)) {
            return false;
        }
        return array_map(fn($e) => trim($e), explode(',', str_replace('DNS:', '', $domains)));
    }

    public function updatebot()
    {
        $b = exec('git -C / rev-parse --abbrev-ref HEAD');
        $track  = trim(file_get_contents('/update/branch'));
        $data = [
            [
                [
                    'text'          => "$b => $track",
                    'callback_data' => "/branches",
                ],
                [
                    'text'    => $this->i18n('changelog'),
                    'web_app' => ['url' => "https://raw.githubusercontent.com/mercurykd/vpnbot/$b/version"],
                ],
            ],
            [
                [
                    'text'          => $this->i18n('update bot'),
                    'callback_data' => "/applyupdatebot",
                ],
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu config",
            ],
        ];
        exec("git -C / branch -vv", $mm);
        return [
            'text' => '<pre><code class="language-shell">' . htmlentities(implode("\n", $mm)) . '</code></pre>',
            'data' => $data,
        ];
    }

    public function applyupdatebot()
    {
        $this->pinBackup($this->update);
        $r = $this->send($this->input['from'], 'update...');
        file_put_contents('/update/reload_message', "{$this->input['from']}:{$r['result']['message_id']}");
        file_put_contents('/update/key', $this->key);
        file_put_contents('/update/curl', json_encode([
            'chat_id'    => $this->input['chat'],
            'message_id' => $r['result']['message_id'],
            'text'       => '~t~'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents('/update/pipe', '1');
        $this->delete($this->input['from'], $this->input['message_id']);
    }

    public function restart()
    {
        $r = $this->send($this->input['from'], 'restart...');
        file_put_contents('/update/reload_message', "{$this->input['from']}:{$r['result']['message_id']}");
        file_put_contents('/update/key', $this->key);
        file_put_contents('/update/curl', json_encode([
            'chat_id'    => $this->input['chat'],
            'message_id' => $r['result']['message_id'],
            'text'       => '~t~'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        file_put_contents('/update/pipe', '2');
        $this->delete($this->input['from'], $this->input['message_id']);
    }

    public function configMenu()
    {
        $conf = $this->getPacConf();
        $oc   = $this->getHashSubdomain('oc');
        $np   = $this->getHashSubdomain('np');
        if (!empty($conf['domain'])) {
            $ssl_expiry = $this->expireCert();
            $certs      = $this->domainsCert() ?: [];

            $text[] = "<blockquote>";
            $text[] = "Domains:";
            $text[] = $conf['domain'] . (in_array($conf['domain'], $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
            $text[] = 'naive ' . "$np.{$conf['domain']}" . (in_array("$np.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
            $text[] = 'openconnect ' . "$oc.{$conf['domain']}" . (in_array("$oc.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '');
            if (!empty($conf['adguardkey'])) {
                $text[] = "{$conf['adguardkey']}.{$conf['domain']}" . (in_array("{$conf['adguardkey']}.{$conf['domain']}", $certs) ? ' (ssl: ' . date('Y-m-d H:i:s', $ssl_expiry) . ')' : '') . ' adguard DOT';;
            }
            $text[] = "</blockquote>";
        } else {
            $text[] = $this->i18n('domain explain');
        }

        $data = [
            [
                [
                    'text'          => $conf['domain'] ? "{$this->i18n('delete')} {$conf['domain']}" : $this->i18n('install domain'),
                    'callback_data' => $conf['domain'] ? '/deldomain' : '/domain',
                ],
                [
                    'text'          => $this->i18n('nip.io'),
                    'callback_data' => '/addNipdomain',
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
        $data[] = [
            [
                'text'          => $this->i18n('Ports'),
                'callback_data' => "/ports",
            ],
            [
                'text'          => $this->i18n('logs'),
                'callback_data' => "/logs",
            ],
            [
                'text'          => $this->i18n('IP ban'),
                'callback_data' => "/ipMenu",
            ],
        ];

        $data[] = [
            [
                'text'          => $this->i18n('lang'),
                'callback_data' => "/menu lang",
            ],
            [
                'text'          => "{$this->i18n('page')}: " . ($conf['limitpage'] ?: 5),
                'callback_data' => "/enterPage",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('export'),
                'callback_data' => "/export",
            ],
            [
                'text'          => $this->i18n('import'),
                'callback_data' => "/import",
            ],
        ];
        $backup = array_filter(explode('/', $conf['backup']));
        if (!empty($backup)) {
            if (!empty(strtotime($backup[0])) && !empty(strtotime($backup[1]))) {
                $backup = "{$backup[0]} start / {$backup[1]} period";
            } else {
                $backup = $this->i18n('off') . " {$conf['backup']} - wrong format";
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('backup') . ': ' . ($backup ?: $this->i18n('off')),
                'callback_data' => "/backup",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('autoupdate') . ': ' .  $this->i18n($conf['autoupdate'] ? 'on' : 'off'),
                'callback_data' => "/autoupdate",
            ],
        ];
        $data[] = [
            [
                'text'          => $this->i18n('branches'),
                'callback_data' => "/menu update",
            ],
            [
                'text'          => $this->i18n('restart'),
                'callback_data' => "/restart",
            ],
        ];
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
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu",
            ],
        ];
        return [
            'text' => implode("\n", $text),
            'data' => $data,
        ];
    }

    public function ports()
    {
        $text[] = 'Settings -> Ports';
        $f      = '/docker/compose';
        $c      = yaml_parse_file($f)['services'];
        $pac = $this->getPacConf();
        $data   = [
            [[
                'text'          => $this->i18n($c['wg'] ? 'on' : 'off') . ' ' . getenv('WGPORT') . ' Wireguard',
                'callback_data' => "/hidePort wg",
            ]],
            [[
                'text'          => $this->i18n($c['wg1'] ? 'on' : 'off') . ' ' . getenv('WG1PORT') . ' Wireguard',
                'callback_data' => "/hidePort wg1",
            ]],
            [[
                'text'          => $this->i18n($c['tg'] ? 'on' : 'off') . ' ' . getenv('TGPORT') . ' MTProto ',
                'callback_data' => "/hidePort tg",
            ]],
            [[
                'text'          => $this->i18n($c['ad'] ? 'on' : 'off') . ' 853 AdguardHome DoT',
                'callback_data' => "/hidePort ad",
            ]],
            [[
                'text'          => $this->i18n($c['ss'] ? 'on' : 'off') . ' 8388 Shadowsocks',
                'callback_data' => "/hidePort ss",
            ]],
            [[
                'text'          => $this->i18n($c['io'] ? 'on' : 'off') . ' 53 Iodine',
                'callback_data' => "/hidePort io",
            ]],
        ];
        if (!empty($pac['restart'])) {
            $data[] = [
                [
                    'text'          => $this->i18n('restart'),
                    'callback_data' => "/restart",
                ],
            ];
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

    public function hidePort($container)
    {
        $ports = [
            'wg'  => getenv('WGPORT') . ':' . getenv('WGPORT') . '/udp',
            'wg1' => getenv('WG1PORT') . ':' . getenv('WG1PORT') . '/udp',
            'tg'  => getenv('TGPORT') . ':' . getenv('TGPORT'),
            'ad'  => '853:853',
            'ss'  => '8388:8388',
            'io'  => '53:53/udp',
        ];
        $f = '/docker/compose';
        $c = yaml_parse_file($f);
        if (!empty($c['services'][$container])) {
            unset($c['services'][$container]);
        } else {
            $c['services'][$container]['ports'][] = $ports[$container];
        }
        if (empty($c['services'])) {
            file_put_contents($f, '');
        } else {
            yaml_emit_file($f, $c);
        }
        $pac = $this->getPacConf();
        $pac['restart'] = 1;
        $this->setPacConf($pac);
        $this->ports();
    }

    public function branches()
    {
        exec('git -C / branch -r', $m);
        array_shift($m);
        foreach ($m as $k => $v) {
            $data[] = [
                [
                    'text'          => $v,
                    'callback_data' => "/changeBranch $k",
                ]
            ];
        }
        $data[] = [
            [
                'text'          => $this->i18n('back'),
                'callback_data' => "/menu update",
            ]
        ];
        $this->update($this->input['from'], $this->input['message_id'], 'branches', $data);
    }

    public function changeBranch($i)
    {
        exec('git -C / branch -r', $m);
        array_shift($m);
        foreach ($m as $k => $v) {
            if ($i == $k) {
                file_put_contents('/update/branch', trim(str_replace('origin/', '', $v)));
            }
        }
        $this->menu('update');
    }

    public function logs()
    {
        $p = $this->getPacConf();
        foreach (scandir('/logs/') as $k => $v) {
            if (!preg_match('~^\.~', $v)) {
                $size   = filesize("/logs/$v");
                $data[] = [
                    [
                        'text'          => "$size $v",
                        'callback_data' => "/getLog $k",
                    ],
                    [
                        'text'          => $this->i18n('clean'),
                        'callback_data' => "/clearLog $k",
                    ],
                ];
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('clean all'),
                'callback_data' => "/cleanLog",
            ],
        ];
        $autocleanlogs = array_filter(explode('/', $p['autocleanlogs']));
        if (!empty($autocleanlogs)) {
            if (!empty(strtotime($autocleanlogs[0])) && !empty(strtotime($autocleanlogs[1]))) {
                $autocleanlogs = "{$autocleanlogs[0]} start / {$autocleanlogs[1]} period";
            } else {
                $autocleanlogs = $this->i18n('off') . " {$p['autocleanlogs']} - wrong format";
            }
        }
        $data[] = [
            [
                'text'          => $this->i18n('autoclean'). ': ' . ($autocleanlogs ?: $this->i18n('off')),
                'callback_data' => "/autoCleanLogs",
            ],
        ];
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

    public function cleanLog()
    {
        foreach (scandir('/logs/') as $k => $v) {
            file_put_contents("/logs/$v", '');
        }
        $this->logs();
    }

    public function delLog($i)
    {
        foreach (scandir('/logs/') as $k => $v) {
            if ($i == $k) {
                unlink("/logs/$v");
                break;
            }
        }
        $this->logs();
    }

    public function selfUpdate()
    {
        $ip                         = getenv('IP');
        $rm                         = explode(':', trim(file_get_contents('/update/reload_message')));
        $m                          = file_get_contents('/update/message');
        $this->input['chat']        = $rm[0];
        $this->input['message_id']  = $rm[1];
        $this->input['callback_id'] = $rm[1];
        if (file_exists($this->update)) {
            $this->selfupdate = true;
            if (!empty($m)) {
                $this->send($this->input['chat'], "<pre>$m</pre>", $rm[1]);
            }
            $r = $this->send($this->input['chat'], "import settings");
            $this->input['message_id']  = $r['result']['message_id'];
            $this->input['callback_id'] = $r['result']['message_id'];
            $this->importFile($this->update);
            unlink($this->update);
        }
        file_put_contents('/update/message', '');
        file_put_contents('/update/reload_message', '');
        $pac = $this->getPacConf();
        unset($pac['restart']);
        $this->setPacConf($pac);
    }

    public function backup()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter like: start / period",
            $this->input['message_id'],
            reply: 'enter like: now / 12 hours',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setBackup',
            'args'           => [],
        ];
    }

    public function autoCleanLogs()
    {
        $r = $this->send(
            $this->input['chat'],
            "@{$this->input['username']} enter like: start / period",
            $this->input['message_id'],
            reply: 'enter like: now / 12 hours',
        );
        $_SESSION['reply'][$r['result']['message_id']] = [
            'start_message'  => $this->input['message_id'],
            'start_callback' => $this->input['callback_id'],
            'callback'       => 'setAutoCleanLogs',
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

    public function changeTGDomain()
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
            'callback'       => 'setTelegramDomain',
            'args'           => [],
        ];
    }

    public function setFakeDomain($domain, $self = false)
    {
        $c = $this->getXray();
        $p = $this->getPacConf();
        $c['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0] = $domain;
        $c['inbounds'][0]['streamSettings']['realitySettings']['dest'] = $self ? "10.10.1.2:443" : "$domain:443";
        $p['reality']['domain'] = $domain;
        $p['reality']['destination'] = $self ? "10.10.1.2:443" : "$domain:443";
        $this->setPacConf($p);
        $this->restartXray($c);
        $this->setUpstreamDomain($domain);
        $this->xray();
    }

    public function selfFakeDomain()
    {
        $c = $this->getPacConf();
        if (!empty($c['domain'])) {
            $this->setFakeDomain($c['domain'], 1);
        } else{
            $this->answer($this->input['callback_id'], 'empty domain', true);
        }
    }

    public function changeTransport($ws = null)
    {
        $p = $this->getPacConf();
        $x = $this->getXray();
        $h = $this->getHashBot();
        $p['reality']['domain']      = $p['reality']['domain'] ?: 'web.telegram.org';
        $p['reality']['destination'] = $p['reality']['destination'] ?: $p['reality']['domain'] . ':443';
        $p['transport']              = $ws ? 'Websocket' : 'Reality';
        if (!empty($ws)) {
            $p['reality']['domain']      = $x['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0] ?: $p['reality']['domain'];
            $p['reality']['destination'] = $x['inbounds'][0]['streamSettings']['realitySettings']['dest'] ?: $p['reality']['destination'];
            $p['reality']['shortId']     = $x['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0] ?: $p['reality']['shortId'];
            foreach ($x['inbounds'][0]['settings']['clients'] as $k => $v) {
                unset($x['inbounds'][0]['settings']['clients'][$k]['flow']);
            }
            $x['inbounds'][0]['streamSettings'] = [
                "network"    => "ws",
                "wsSettings" => [
                    "path" => "/ws$h"
                ]
            ];
        } else {
            foreach ($x['inbounds'][0]['settings']['clients'] as $k => $v) {
                $x['inbounds'][0]['settings']['clients'][$k]['flow'] = 'xtls-rprx-vision';
            }
            $x['inbounds'][0]['streamSettings'] = [
                "network"         => "tcp",
                "realitySettings" => [
                    "dest"         => $p['reality']['destination'] ?: $x['inbounds'][0]['streamSettings']['realitySettings']['dest'],
                    "maxClientVer" => "",
                    "maxTimeDiff"  => 0,
                    "minClientVer" => "",
                    "privateKey"   => $p['reality']['privateKey'],
                    "serverNames"  => [
                        $p['reality']['domain'] ?: $x['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]
                    ],
                    "shortIds" => [$p['reality']['shortId']] ?: $x['inbounds'][0]['streamSettings']['realitySettings']['shortIds'][0],
                    "show"     => false,
                    "xver"     => 0
                ],
                "tcpSettings" => [
                    "acceptProxyProtocol" => true
                ],
                "sockopt" => [
                    "acceptProxyProtocol" => true
                ],
                "security" => "reality"
            ];
        }
        $this->setUpstreamDomain($ws ? 't' : ($p['reality']['domain'] ?: $x['inbounds'][0]['streamSettings']['realitySettings']['serverNames'][0]));
        $this->setPacConf($p);
        $this->restartXray($x);
        $this->xray();
    }

    public function setBackup($text)
    {
        $text = trim($text);
        $c    = $this->getPacConf();
        if (empty($text)) {
            $c['backup'] = '';
        } else {
            [$start, $period] = explode('/', $text);
            if (!empty(strtotime($start)) && !empty(strtotime($period))) {
                $c['backup'] = implode(' / ', [date('Y-m-d H:i', strtotime($start)), trim($period)]);
            } else {
                $this->send($this->input['from'], $this->input['message'] . ' - wrong format');
            }
        }
        if ($c['pinbackup']) {
            $this->pinAdmin($c['pinbackup'], 1);
            $c['pinbackup'] = '';
        }
        $this->setPacConf($c);
        $this->menu('config');
    }

    public function setAutoCleanLogs($text)
    {
        $text = trim($text);
        $c    = $this->getPacConf();
        if (empty($text)) {
            $c['autocleanlogs'] = '';
        } else {
            [$start, $period] = explode('/', $text);
            if (!empty(strtotime($start)) && !empty(strtotime($period))) {
                $c['autocleanlogs'] = implode(' / ', [date('Y-m-d H:i', strtotime($start)), trim($period)]);
            } else {
                $this->send($this->input['from'], $this->input['message'] . ' - wrong format');
            }
        }
        $this->setPacConf($c);
        $this->logs();
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

    public function getInstanceWG($k = false)
    {
        if (!empty($k)) {
            return ($this->wg ?? $this->getPacConf()['wg_instance']) ? 'wg1_' : '';
        }
        return ($this->wg ?? $this->getPacConf()['wg_instance']) ? 'wg1' : 'wg';
    }

    public function readConfig()
    {
        $r = $this->ssh('cat /etc/wireguard/wg0.conf', $this->getInstanceWG());
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
        $r = $this->ssh($this->getWGType(), $this->getInstanceWG());
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
        $pac = $this->getPacConf();
        $conf[] = "[Interface]";
        if (empty($data['interface']['ListenPort'])) {
            if (empty($data['interface']['DNS'])) {
                $data['interface']['DNS'] = $pac[$this->getInstanceWG(1) . 'dns'] ?: $this->dns;
            }
            if (empty($data['interface']['MTU'])) {
                $data['interface']['MTU'] = $pac[$this->getInstanceWG(1) . 'mtu'] ?: $this->mtu;
            }
        }
        foreach ($data['interface'] as $k => $v) {
            $conf[] = "$k = $v";
        }
        if (!empty($data['peers'])) {
            foreach ($data['peers'] as $peer) {
                $conf[] = '';
                $conf[] = $peer['# PublicKey'] ? '# [Peer]' : '[Peer]';
                if (!empty($peer['Endpoint'])) {
                    $peer['Endpoint'] = ($pac[$this->getInstanceWG(1) . 'endpoint'] ? $this->ip : $this->getDomain()) . ":" . getenv($this->getInstanceWG(1) ? 'WG1PORT' : 'WGPORT');
                }
                foreach ($peer as $k => $v) {
                    $conf[] = "$k = $v";
                }
            }
        }
        return implode(PHP_EOL, $conf);
    }

    public function presharedKey()
    {
        $c = $this->getPacConf();
        if (empty($c[$this->getInstanceWG(1) . 'presharedkey'])) {
            $c[$this->getInstanceWG(1) . 'presharedkey'] = trim($this->ssh("{$this->getWGType()} genpsk", $this->getInstanceWG()));
            $this->setPacConf($c);
        }
        return $c[$this->getInstanceWG(1) . 'presharedkey'];
    }

    public function amneziaKeys()
    {
        $c = $this->getPacConf();
        if (empty($c[$this->getInstanceWG(1) . 'amnezia_keys'])) {
            $c[$this->getInstanceWG(1) . 'amnezia_keys'] = [
                'Jc'   => rand(3, 10),
                'Jmin' => 50,
                'Jmax' => 1000,
                'S1'   => rand(15, 150),
                'S2'   => rand(15, 150),
                'H1'   => rand(1, 2_147_483_647),
                'H2'   => rand(1, 2_147_483_647),
                'H3'   => rand(1, 2_147_483_647),
                'H4'   => rand(1, 2_147_483_647),
            ];
            $this->setPacConf($c);
        }
        return $c[$this->getInstanceWG(1) . 'amnezia_keys'];
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
        $public_server_key = trim($this->ssh("echo {$conf['interface']['PrivateKey']} | {$this->getWGType()} pubkey", $this->getInstanceWG()));
        $private_peer_key  = trim($this->ssh("{$this->getWGType()} genkey", $this->getInstanceWG()));
        $public_peer_key   = trim($this->ssh("echo $private_peer_key | {$this->getWGType()} pubkey", $this->getInstanceWG()));

        $name = ($name ? "$name" : '') . time();

        $conf['peers'][] = array_merge([
                '## name'    => $name,
                'PublicKey'  => $public_peer_key,
                'AllowedIPs' => "$client_ip/32",
            ],
            $this->getPacConf()[$this->getInstanceWG(1) . 'amnezia'] ? ['PresharedKey' => $this->presharedKey()] : []
        );
        $client_conf = [
            'interface' => array_merge(
                [
                    '## name'    => $name,
                    'PrivateKey' => $private_peer_key,
                    'Address'    => "$client_ip/32",
                ],
                $this->getPacConf()[$this->getInstanceWG(1) . 'amnezia'] ? $this->amneziaKeys() : []
            ),
            'peers' => [
                    array_merge(
                        [
                            'PublicKey'           => $public_server_key,
                            'AllowedIPs'          => $ips_user ?: "0.0.0.0/0",
                            'PersistentKeepalive' => 20,
                        ],
                        $this->getPacConf()[$this->getInstanceWG(1) . 'amnezia'] ? ['PresharedKey' => $this->presharedKey()] : []
                    ),
                ],
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
        $endpoint = [
            $this->ip . ':' . getenv('WGPORT'),
            $this->ip . ':' . getenv('WG1PORT'),
        ];
        for ($i=0; $i < 2; $i++) {
            $this->wg = $i;
            $clients  = $this->readClients();
            foreach ($clients as $k => $v) {
                foreach ($v['peers'] as $n => $j) {
                    $clients[$k]['peers'][$n]['Endpoint'] = $endpoint[$i];
                }
            }
            $this->saveClients($clients);
        }
        unset($this->wg);
    }

    public function saveClients(array $clients)
    {
        $c      = $this->getPacConf();
        $domain = ($c['domain'] ?: $this->ip) . ":" . getenv($this->getInstanceWG(1) ? 'WG1PORT' : 'WGPORT');
        foreach ($clients as $k => $v) {
            $clients[$k]['peers'][0]['Endpoint'] = $domain;
        }
        file_put_contents($this->getInstanceWG(1) ? $this->clients1 : $this->clients, json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function getWGType($revert = 0)
    {
        $wg = $this->getPacConf()[$this->getInstanceWG(1) . 'amnezia'];
        return ($revert ? !$wg : $wg) ? 'awg' : 'wg';
    }

    public function restartWG($conf_str, $switch = false)
    {
        $this->ssh("echo '$conf_str' > /etc/wireguard/wg0.conf", $this->getInstanceWG());
        if (!empty($switch)) {
            $this->ssh("{$this->getWGType(1)}-quick down wg0", $this->getInstanceWG());
            $this->ssh("{$this->getWGType()}-quick up wg0", $this->getInstanceWG());
        } else {
            $this->ssh("{$this->getWGType()} syncconf wg0 <({$this->getWGType()}-quick strip wg0)", $this->getInstanceWG());
        }
        return true;
    }

    public function autoupdate()
    {
        $p = $this->getPacConf();
        $p['autoupdate'] = !$p['autoupdate'];
        $this->setPacConf($p);
        $this->menu('config');
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
            if (!empty($GLOBALS['debug'])) {
                $this->send($this->input['chat'], $e->getMessage(), $this->input['message_id']);
            }
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
        $r   = json_decode($res, true);
        if (!empty($res['description']) || is_null($res)) {
            file_put_contents('/logs/requests_error', var_export([
                'r' => [
                    'method' => $method,
                    'data'   => $data,
                ],
                'a' => $res,
            ], true) . "\n", FILE_APPEND);
        }
        return $r;
    }

    public function setwebhook()
    {
        $ip = $this->ip;
        if (empty($ip)) {
            die('нет айпи');
        }
        echo "$ip\n";
        var_dump($r = $this->request('setWebhook', [
            'url'             => "https://$ip/tlgrm?k={$this->key}",
            'certificate'     => curl_file_create('/certs/self_public'),
            'allowed_updates' => json_encode(['*']),
        ]));
        if (!empty($r['result']) && $r['result'] == true) {
            file_put_contents('/start', 1);
        } else {
            die("set webhook fail\n");
        }
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

    public function send($chat, $text, ?int $to = 0, $button = false, $reply = false, $mode = 'HTML', $disable_notification = false)
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
                    'disable_notification'     => $disable_notification,
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
                'disable_notification'     => $disable_notification,
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
