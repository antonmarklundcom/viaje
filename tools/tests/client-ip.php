<?php
declare(strict_types=1);

/**
 * tools/tests/client-ip.php — Util::clientIp() behind (and not behind) a trusted proxy.
 * Forwarding headers must only count when REMOTE_ADDR is a configured proxy. Exit 0 = pass.
 */

$repo = dirname(__DIR__, 2);
require_once $repo . '/engine/lib/util.php';
require_once $repo . '/engine/lib/config.php';

$fails = 0;
$n     = 0;
$check = static function (string $name, string $got, string $want) use (&$fails, &$n): void {
    $n++;
    if ($got !== $want) {
        $fails++;
        echo "  FAIL  $name: got $got, want $want\n";
    }
};

$proxies = ['10.0.0.0/8', '172.68.1.7', '2400:cb00::/32'];
$forged  = ['HTTP_X_FORWARDED_FOR' => '6.6.6.6', 'HTTP_CF_CONNECTING_IP' => '7.7.7.7'];

// Not behind a proxy: every header is the client's own words.
$check('no proxies configured, forged headers ignored',
    Util::clientIp(['REMOTE_ADDR' => '203.0.113.9'] + $forged, []), '203.0.113.9');
$check('untrusted REMOTE_ADDR, forged X-Forwarded-For ignored',
    Util::clientIp(['REMOTE_ADDR' => '203.0.113.9'] + $forged, $proxies), '203.0.113.9');
$check('untrusted REMOTE_ADDR, forged CF-Connecting-IP ignored',
    Util::clientIp(['REMOTE_ADDR' => '203.0.113.9'] + $forged, $proxies, 'CF-Connecting-IP'), '203.0.113.9');
$check('untrusted IPv6 REMOTE_ADDR, forged headers ignored',
    Util::clientIp(['REMOTE_ADDR' => '2001:db8::5'] + $forged, $proxies), '2001:db8::5');
$check('invalid REMOTE_ADDR',
    Util::clientIp(['REMOTE_ADDR' => 'nonsense'] + $forged, $proxies), '0.0.0.0');

// Behind a trusted proxy.
$check('trusted proxy (CIDR), single hop',
    Util::clientIp(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20'], $proxies), '198.51.100.20');
$check('trusted proxy, client-forged left entries are skipped (right-most untrusted wins)',
    Util::clientIp(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 1.1.1.1, 198.51.100.20'], $proxies), '198.51.100.20');
$check('trusted proxy chain: trusted hops on the right are walked past',
    Util::clientIp(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.20, 10.9.9.9, 172.68.1.7'], $proxies), '198.51.100.20');
$check('trusted proxy (exact IP), port and brackets stripped',
    Util::clientIp(['REMOTE_ADDR' => '172.68.1.7', 'HTTP_X_FORWARDED_FOR' => '[2001:db8::7]:4431'], $proxies), '2001:db8::7');
$check('trusted proxy, IPv4 with port',
    Util::clientIp(['REMOTE_ADDR' => '172.68.1.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20:5555'], $proxies), '198.51.100.20');
$check('trusted IPv6 proxy',
    Util::clientIp(['REMOTE_ADDR' => '2400:cb00:1::1', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20'], $proxies), '198.51.100.20');
$check('trusted proxy, no header → the proxy itself',
    Util::clientIp(['REMOTE_ADDR' => '10.1.2.3'], $proxies), '10.1.2.3');
$check('trusted proxy, garbage right-most entry → the proxy itself',
    Util::clientIp(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.20, <script>'], $proxies), '10.1.2.3');
$check('trusted proxy, CF-Connecting-IP mode',
    Util::clientIp(['REMOTE_ADDR' => '172.68.1.7', 'HTTP_CF_CONNECTING_IP' => '198.51.100.30', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6'], $proxies, 'CF-Connecting-IP'), '198.51.100.30');
$check('trusted proxy, CF-Connecting-IP mode, header missing → the proxy',
    Util::clientIp(['REMOTE_ADDR' => '172.68.1.7'], $proxies, 'CF-Connecting-IP'), '172.68.1.7');

// CIDR edge cases.
$check('/8 boundary', Util::ipInList('11.0.0.1', ['10.0.0.0/8']) ? 'in' : 'out', 'out');
$check('/31 odd bits', Util::ipInList('192.0.2.1', ['192.0.2.0/31']) ? 'in' : 'out', 'in');
$check('/31 odd bits, outside', Util::ipInList('192.0.2.2', ['192.0.2.0/31']) ? 'in' : 'out', 'out');
$check('/0 matches everything of that family', Util::ipInList('8.8.4.4', ['0.0.0.0/0']) ? 'in' : 'out', 'in');
$check('IPv4 never matches an IPv6 range', Util::ipInList('10.0.0.1', ['::/0']) ? 'in' : 'out', 'out');
$check('bad prefix is ignored', Util::ipInList('10.0.0.1', ['10.0.0.0/99', '10.0.0.0/x']) ? 'in' : 'out', 'out');

echo "client-ip: $n checks, $fails failure(s)\n";
exit($fails === 0 ? 0 : 1);
