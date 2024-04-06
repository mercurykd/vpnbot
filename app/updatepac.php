<?php

function elzw(string $str)
{
    global $text, $load;
    $str = mb_str_split($str);
    $code = 256;
    $r    = $phrase = '';
    $keys = [];
    $last = count($str) - 1;
    $size = count($str);
    $curr = $j = 0;
    $time = microtime(true);
    foreach ($str as $k => $v) {
        $curr ++;
        $percent = (int) ceil($curr * 100 / $size);
        $rotate = $curr != $size ? $load[$j % count($load)] : '';
        $tail = <<<text
                Minified pac $percent% $rotate
                text;
        if (microtime(true) - $time > 0.1) {
            update($text . $tail);
            $time = microtime(true);
            $j++;
        }
        switch (true) {
            case $last == $k:
                $r .= strlen($phrase) > 1 ? mb_chr($keys[$phrase]) : $v;
                break;
            case !empty($keys[$phrase . $str[$k + 1]]):
                $phrase .= $str[$k + 1];
                break;
            default:
                $r .= strlen($phrase) > 1 ? mb_chr($keys[$phrase]) : $v;
                $keys[$phrase . $str[$k + 1]] = $code;
                $code++;
                $phrase = $str[$k + 1];
        }
    }
    return $r;
}

function dlzw($text)
{
    $text = mb_str_split($text);
    $code = 256;
    $r    = $phrase = '';
    $keys = [];
    $last = count($text) - 1;
    foreach ($text as $k => $v) {
        $cc = mb_ord($v);
        $r .= $cc < 256 ? $v : $keys[$cc];
        if ($last == $k) {
            break;
        }
        if (!empty($phrase)) {
            $keys[$code] = $phrase . ($cc < 256 ? $v : $keys[$cc][0]);
            $code++;
        }
        $phrase = $cc < 256 ? $v : $keys[$cc];
    }
    return $r;
}

function getSize($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HEADER         => 1,
        CURLOPT_NOBODY         => 1,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    preg_match('~content-length.+?(\d+)~ius', $r, $m);
    return (int) $m[1];
}

function update($text)
{
    global $bot;
    $bot->update($bot->input['chat'], $bot->input['message_id'], $text);
}

function start()
{
    global $bot, $source, $res, $load, $text;

    $conf = $bot->getPacConf();

    $subzones = array_keys(array_filter($conf['subzoneslist'], fn($x) => $x == true));

    // check && exclude
    $include = array_filter($conf['includelist'], fn($x) => $x == true);
    if ($conf['zapret']) {
        foreach ($source as $k => $v) {
            $size = getSize($v['source']);
            if ($size > 0) {
                if (file_exists($v['dest'])) {
                    unlink($v['dest']);
                }
                touch($v['dest']);
                $percent  = 0;
                $curr = 0;
                exec("php updatepac.php download $k > /dev/null &");
                $i = $j = 0;
                while ($curr <= $size) {
                    $rotate = $curr != $size ? $load[$j % count($load)] : '';
                    $tail = <<<text
                            Downloading {$v['source']}
                            $curr/$size $percent% $rotate
                            text;
                    update($text . $tail);
                    if ($curr == $size || $i > 50) {
                        break;
                    }
                    usleep(50000);
                    clearstatcache();
                    if ($curr == filesize($v['dest'])) {
                        $i++;
                    }
                    $curr = filesize($v['dest']);
                    $percent  = (int) ceil($curr * 100 / $size);
                    $j++;
                }
                if ($curr != $size) {
                    $text .= "\nError downloading {$v['source']}\nabort script";
                    endScript($text);
                }
            } else {
                $text .= "\nError size {$v['source']}\nabort script";
                endScript($text);
            }
            $text .= $tail ? "$tail\n" : '';
        }
        // prepare
        $reg = '~^([^.*]+\.(?:(?:' . implode('|', $subzones) . ')\.)?[^.]+)$~';

        foreach ($source as $k => $v) {
            $size = filesize($v['dest']);
            $curr = $j = 0;
            $time = microtime(true);
            $name = basename($v['dest']);
            $f = fopen($v['dest'], 'r');
            while (($s = fgets($f)) !== false) {
                $curr += strlen($s);
                $percent = (int) ceil($curr * 100 / $size);
                $rotate = $curr != $size ? $load[$j % count($load)] : '';
                $tail = <<<text
                        Prepare $name $percent% $rotate
                        text;
                if (microtime(true) - $time > 0.1) {
                    update($text . $tail);
                    $time = microtime(true);
                    $j++;
                }
                if ($name == 'dump.csv') {
                    if (!preg_match('~;.*;~', $s)) {
                        continue;
                    }
                    $t = explode(';', iconv('CP1251', 'utf-8', $s));
                    if (empty($t[1])) {
                        continue;
                    }
                    if (preg_match($reg, idn_to_ascii(trim($t[1])), $m)) {
                        $domains[$m[1]] = 0;
                    }
                } else {
                    if (preg_match($reg, idn_to_ascii(iconv('CP1251', 'utf-8', trim($s))), $m)) {
                        $domains[$m[1]] = 0;
                    }
                }
            }
            fclose($f);
            $text .= $tail ? "$tail\n" : '';
        }
        $exclude = array_keys(array_filter($conf['excludelist'], fn($x) => $x == true));
        foreach ($include as $k => $v) {
            $domains[$k] = 0;
        }
    } else {
        $domains = $include;
    }
    if (empty($domains)) {
        $text = "Empty domains. Delete pac files";
        unlink(__DIR__ . '/zapretlists/mpac');
        unlink(__DIR__ . '/zapretlists/pac');
    } else {
        $domains = array_keys($domains);

        $size = count($domains);
        $curr = $j = 0;
        $time = microtime(true);
        $f = fopen(__DIR__ . '/zapretlists/mpac', 'w');
        $t = [];
        foreach ($domains as $v) {
            $curr ++;
            $percent = (int) ceil($curr * 100 / $size);
            $rotate = $curr != $size ? $load[$j % count($load)] : '';
            $tail = <<<text
                    Create pac for shadowsocks-android $percent% $rotate
                    text;
            if (microtime(true) - $time > 0.1) {
                update($text . $tail);
                $time = microtime(true);
                $j++;
            }
            if ($exclude && preg_match('~' . implode('|', $exclude) . '~', $v)) {
                continue;
            }
            fwrite($f, preg_quote($v) . "\n");

            preg_match('~(.+)\.([^.]+)$~', $v, $m);
            $t[$m[2]][strlen($m[1])][] = $m[1];
        }
        fclose($f);
        $text .= $tail ? "$tail\n" : '';

        // create pac
        $domains = json_encode($t);
        $pac     = <<<PAC
                    function FindProxyForURL(t, e) {
                        var n = JSON.parse('$domains'),
                        r = e.match(/((?:.+\.)*(.+))\.([^.]+)$/);
                        if (r && n.hasOwnProperty(r[3]) && (n[r[3]].hasOwnProperty(r[1].length) && (-1 !== n[r[3]][r[1].length].indexOf(r[1])) || n[r[3]].hasOwnProperty(r[2].length) && (-1 !== n[r[3]][r[2].length].indexOf(r[2])))) {
                            return "SOCKS5 ~address~:~port~; DIRECT";
                        }
                        return "DIRECT";
                    }
                    PAC;
        $pac = preg_replace("~\s{2,}~", '', $pac);
        $pac = preg_replace("~\n~", '', $pac);
        file_put_contents(__DIR__ . '/zapretlists/pac', $pac);
        $text .= "Create minified pac 100%\n";
    }

    // create reverse PAC
    $domains = array_filter($conf['reverselist'], fn($x) => $x == true);
    if (empty($domains)) {
        // $text .= "Empty reverse domains. Delete reverse pac files";
        unlink(__DIR__ . '/zapretlists/rmpac');
        unlink(__DIR__ . '/zapretlists/rpac');
    } else {
        $domains = array_keys($domains);

        // $size = count($domains);
        // $curr = $j = 0;
        // $time = microtime(true);
        // $f = fopen(__DIR__ . '/zapretlists/rmpac', 'w');
        // $t = [];
        // foreach ($domains as $v) {
        //     $curr ++;
        //     $percent = (int) ceil($curr * 100 / $size);
        //     $rotate = $curr != $size ? $load[$j % count($load)] : '';
        //     $tail = <<<text
        //             Create reverse pac for shadowsocks-android $percent% $rotate
        //             text;
        //     if (microtime(true) - $time > 0.1) {
        //         update($text . $tail);
        //         $time = microtime(true);
        //         $j++;
        //     }
        //     fwrite($f, '!(' . preg_quote($v) . ')' . "\n");

        //     preg_match('~(.+)\.([^.]+)$~', $v, $m);
        //     $t[$m[2]][strlen($m[1])][] = $m[1];
        // }
        // fclose($f);
        $text .= $tail ? "$tail\n" : '';

        $domains = json_encode($t);
        $pac     = <<<PAC
                    function FindProxyForURL(t, e) {
                        var n = JSON.parse('$domains'),
                        r = e.match(/((?:.+\.)*(.+))\.([^.]+)$/);
                        if (r && n.hasOwnProperty(r[3]) && (n[r[3]].hasOwnProperty(r[1].length) && (-1 !== n[r[3]][r[1].length].indexOf(r[1])) || n[r[3]].hasOwnProperty(r[2].length) && (-1 !== n[r[3]][r[2].length].indexOf(r[2])))) {
                            return "DIRECT";
                        }
                        return "SOCKS5 ~address~:~port~";
                    }
                    PAC;
        $pac = preg_replace("~\s{2,}+~", '', $pac);
        $pac = preg_replace("~\n~", '', $pac);
        file_put_contents(__DIR__ . '/zapretlists/rpac', $pac);
        $text .= 'Create minified reverse pac 100%';
    }
    endScript($text);
}

function endScript($text)
{
    global $bot;
    if (empty($_SERVER['argv'][5])) {
        update($text);
        sleep(2);
        $bot->menu('pac');
    }
    die();
}

function download($index)
{
    global $source;
    file_put_contents($source[$index]['dest'], file_get_contents($source[$index]['source']));
}

ini_set('memory_limit', '256M');
require __DIR__ . '/timezone.php';
// require __DIR__ . '/debug.php';
require __DIR__ . '/bot.php';
require __DIR__ . '/config.php';
require __DIR__ . '/i18n.php';
$source = [
    [
        'source' => 'https://raw.githubusercontent.com/zapret-info/z-i/master/nxdomain.txt',
        'dest'   => __DIR__ . '/zapretlists/nxdomain.txt',
    ],
    [
        'source' => 'https://raw.githubusercontent.com/zapret-info/z-i/master/dump.csv',
        'dest'   => __DIR__ . '/zapretlists/dump.csv',
    ],
];

$res = __DIR__ . '/zapretlists/result';

$text = '';
$load = [
    '/',
    '-',
    '\\',
];

switch ($_SERVER['argv'][1]) {
    case 'start':
        $bot                       = new Bot($c['key'], $i);
        $bot->input['chat']        = $_SERVER['argv'][2];
        $bot->input['message_id']  = $_SERVER['argv'][3];
        $bot->input['callback_id'] = $_SERVER['argv'][4];
        start();
        break;
    case 'download':
        download($_SERVER['argv'][2]);
        break;
}
