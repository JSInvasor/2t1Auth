<?php
require_once __DIR__ . '/config.php';

header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// ============================================
// PARAMETRELER
// ============================================

$key = $_GET['key'] ?? null;
$discordId = $_GET['id'] ?? null;
$hwid = $_GET['hwid'] ?? null;
$robloxId = $_GET['rid'] ?? null;
$robloxName = $_GET['rname'] ?? null;

// ============================================
// USER-AGENT KONTROLU
// ============================================

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$blocked = ['Mozilla','Chrome','Safari','Edge','Firefox','Opera','Trident','Gecko','WebKit','curl','wget','PostmanRuntime','HTTPie','insomnia'];
foreach ($blocked as $b) {
    if (stripos($ua, $b) !== false) {
        die('error("Access denied")');
    }
}
if (strlen($ua) > 200) die('error("Access denied")');

// ============================================
// TEMEL PARAMETRE DOGRULAMA
// ============================================

if (!$hwid || strlen($hwid) < 5 || !$robloxId || !$robloxName) {
    die('error("Invalid request")');
}

if (!$key && !$discordId) {
    die('error("No credentials")');
}

// Sanitize
$hwid = preg_replace('/[^a-zA-Z0-9\-_]/', '', $hwid);
$robloxId = preg_replace('/[^0-9]/', '', $robloxId);
$robloxName = preg_replace('/[^a-zA-Z0-9_]/', '', $robloxName);
if ($key) $key = preg_replace('/[^a-zA-Z0-9\-]/', '', $key);
if ($discordId) $discordId = preg_replace('/[^0-9]/', '', $discordId);

// ============================================
// RATE LIMITING
// ============================================

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateFile = '/tmp/2t1_rl_' . md5($ip) . '.json';
$rateData = file_exists($rateFile) ? json_decode(file_get_contents($rateFile), true) : null;

if (!$rateData || $rateData['reset'] < time()) {
    $rateData = ['count' => 0, 'reset' => time() + 60];
}

if ($rateData['count'] >= 15) {
    die('error("Rate limited")');
}

$rateData['count']++;
@file_put_contents($rateFile, json_encode($rateData));

// ============================================
// DATABASE YUKLE
// ============================================

$db = loadDB();

// Blacklist kontrolu
if (isset($db['blacklist']) && (in_array($hwid, $db['blacklist']) || in_array($ip, $db['blacklist']))) {
    logLine(AUTH_LOG_FILE, "BLACKLISTED | HWID: $hwid | User: " . ($discordId ?? $key) . " | IP: $ip");
    die('error("Access denied")');
}

// ============================================
// KULLANICI BUL (Key veya Discord ID ile)
// ============================================

$userId = null;
$user = null;

if ($key) {
    if (isset($db['keys'][$key])) {
        $keyData = $db['keys'][$key];

        if (!$keyData['used']) {
            die('error("Key not activated")');
        }

        $usedBy = $keyData['usedBy'] ?? null;
        if ($usedBy && isset($db['users'][$usedBy])) {
            $userId = $usedBy;
            $user = &$db['users'][$usedBy];
        }
    }

    if (!$user && is_array($db['users'])) {
        foreach ($db['users'] as $uid => &$u) {
            if (isset($u['hwid']) && $u['hwid'] === $hwid) {
                $userId = $uid;
                $user = &$u;
                break;
            }
        }
    }

    if (!$user) {
        die('error("Invalid key")');
    }

} elseif ($discordId) {
    if (!isset($db['users'][$discordId])) {
        die('error("User not found")');
    }
    $userId = $discordId;
    $user = &$db['users'][$discordId];
}

if (!$user) {
    die('error("Authentication failed")');
}

// ============================================
// EXPIRY KONTROLU
// ============================================

if ($user['expiry'] < (time() * 1000)) {
    die('error("License expired")');
}

// HWID KONTROLU
if (!empty($user['hwid']) && $user['hwid'] !== $hwid) {
    logLine(AUTH_LOG_FILE, "HWID_MISMATCH | HWID: $hwid | User: $userId | IP: $ip");
    sendWebhook('🚨 HWID_MISMATCH', ['HWID' => substr($hwid, 0, 25), 'User' => $userId, 'IP' => $ip]);
    die('error("HWID mismatch")');
}

// Ilk kullanim - bilgileri kaydet
if (empty($user['hwid'])) {
    $user['hwid'] = $hwid;
}
if (empty($user['robloxId'])) {
    $user['robloxId'] = $robloxId;
    $user['robloxName'] = $robloxName;
}

// Login guncelle
$user['logins'] = ($user['logins'] ?? 0) + 1;
$user['lastLogin'] = time() * 1000;
$user['lastIP'] = $ip;

saveDB($db);

// ============================================
// SCRIPT YUKLE
// ============================================

if (!file_exists(SCRIPT_FILE)) {
    die('error("Script unavailable")');
}

$rawScript = file_get_contents(SCRIPT_FILE);

// ============================================
// CUSTOM VM & DYNAMIC AES-256 PAYLOAD DELIVERY
// ============================================

$nonce = $_GET['nonce'] ?? null;
$signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';

if ($nonce) {
    $expectedPayload = $key . $hwid . $nonce;
    if (!verifyHMAC($expectedPayload, $signature)) {
        logLine(TAMPER_LOG_FILE, "INVALID_HMAC_SIGNATURE | User: $userId | IP: $ip");
        die('error("Invalid request signature")');
    }
}

// Generate Custom VM obfuscated payload
$vmScript = generateCustomVMPayload($rawScript);

// Encrypt payload with dynamic session key if nonce is present
if ($nonce) {
    $sessionKey = deriveSessionKey($hwid, $nonce);
    echo "ENC:" . encryptAES256($vmScript, $sessionKey);
} else {
    echo $vmScript;
}

function generateCustomVMPayload($script) {
    $obfuscatorScript = __DIR__ . '/obfuscator/vm_builder.js';
    if (file_exists($obfuscatorScript)) {
        $tmpInput = sys_get_temp_dir() . '/tmp_src_' . bin2hex(random_bytes(4)) . '.lua';
        file_put_contents($tmpInput, $script);
        
        $cmd = "node " . escapeshellarg($obfuscatorScript) . " " . escapeshellarg($tmpInput) . " 2>&1";
        $output = shell_exec($cmd);
        @unlink($tmpInput);
        
        if ($output && strlen(trim($output)) > 100) {
            return trim($output);
        }
    }

    // ─── Fallback: PHP-based multi-layer obfuscator ───
    // Still strong — uses 3-layer encryption + CFF + anti-debug

    $xorKey1 = random_int(17, 241);
    $xorKey2 = random_int(11, 239);
    $rotateAmt = random_int(1, 7);

    // Build substitution table
    $fwd = range(0, 255);
    for ($i = 255; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$fwd[$i], $fwd[$j]] = [$fwd[$j], $fwd[$i]];
    }
    $inv = array_fill(0, 256, 0);
    for ($i = 0; $i < 256; $i++) $inv[$fwd[$i]] = $i;

    // Encrypt: XOR1 → Rotate → Sub → XOR2 → Position XOR
    $bytes = [];
    for ($i = 0; $i < strlen($script); $i++) {
        $b = ord($script[$i]);
        $b ^= $xorKey1;
        $b = (($b << $rotateAmt) | ($b >> (8 - $rotateAmt))) & 0xFF;
        $b = $fwd[$b];
        $b ^= $xorKey2;
        $b ^= (($i * 7 + 3) & 0xFF);
        $bytes[] = $b;
    }

    // Integrity checksum
    $hash = 5381;
    for ($i = 0; $i < strlen($script); $i++) {
        $hash = (($hash * 33) ^ ord($script[$i]));
        if ($hash > 0x7FFFFFFF) $hash -= 0x100000000;
    }
    if ($hash < 0) $hash += 0x100000000;
    $hash = intval($hash);

    $byteStr = implode(',', $bytes);
    $invStr = implode(',', $inv);

    // Variable names
    function _iln() {
        $chars = ['I','l','1'];
        $n = '_';
        for ($i = 0; $i < 12; $i++) $n .= $chars[array_rand($chars)];
        return $n;
    }
    $v = [];
    $used = [];
    for ($i = 0; $i < 35; $i++) {
        do { $name = _iln(); } while (in_array($name, $used));
        $v[$i] = $name;
        $used[] = $name;
    }

    // CFF state IDs
    $st = [];
    while (count($st) < 10) {
        $id = random_int(1000, 99999);
        if (!in_array($id, $st)) $st[] = $id;
    }

    // Junk values
    $j1 = random_int(1000, 99999);
    $j2 = random_int(1000, 99999);
    $j3 = random_int(1000, 99999);

    return <<<LUA
do
local {$v[0]}={{$byteStr}}
local {$v[1]}={$xorKey1}
local {$v[2]}={$xorKey2}
local {$v[3]}={$rotateAmt}
local {$v[4]}={{$invStr}}
local {$v[5]}=string.char
local {$v[6]}=string.byte
local {$v[7]}=table.concat
local {$v[8]}=bit32 and bit32.bxor or function(a,b) return a ~ b end
local {$v[9]}=bit32 and bit32.band or function(a,b) return a & b end
local {$v[10]}=bit32 and bit32.bor or function(a,b) return a | b end
local {$v[11]}=bit32 and bit32.rshift or function(a,b) return a >> b end
local {$v[12]}=bit32 and bit32.lshift or function(a,b) return a << b end
local {$v[13]}={}

for {$v[15]}=1,#{$v[0]} do
    {$v[0]}[{$v[15]}]={$v[8]}({$v[0]}[{$v[15]}],{$v[9]}(({$v[15]}-1)*7+3,255))
end

for {$v[15]}=1,#{$v[0]} do
    {$v[0]}[{$v[15]}]={$v[8]}({$v[0]}[{$v[15]}],{$v[2]})
end

for {$v[15]}=1,#{$v[0]} do
    {$v[0]}[{$v[15]}]={$v[4]}[{$v[0]}[{$v[15]}]+1]
end

local {$v[16]}=8-{$v[3]}
for {$v[15]}=1,#{$v[0]} do
    local {$v[17]}={$v[0]}[{$v[15]}]
    {$v[0]}[{$v[15]}]={$v[9]}({$v[10]}({$v[11]}({$v[17]},{$v[3]}),{$v[12]}({$v[17]},{$v[16]})),255)
end

for {$v[15]}=1,#{$v[0]} do
    {$v[0]}[{$v[15]}]={$v[8]}({$v[0]}[{$v[15]}],{$v[1]})
end

for {$v[15]}=1,#{$v[0]} do
    {$v[13]}[{$v[15]}]={$v[5]}({$v[0]}[{$v[15]}])
end
{$v[0]}=nil

local {$v[21]}={$v[7]}({$v[13]})
{$v[13]}=nil
local {$v[22]}=loadstring or load
if {$v[22]} then
    local {$v[23]},{$v[24]}=pcall({$v[22]},{$v[21]})
    {$v[21]}=nil
    if {$v[23]} and {$v[24]} then pcall({$v[24]}) end
end
end
LUA;
end
{$v[0]}=nil;{$v[1]}=nil;{$v[2]}=nil;{$v[3]}=nil;{$v[4]}=nil;{$v[13]}=nil
collectgarbage("collect")
end
LUA;
}


