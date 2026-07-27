<?php
// ============================================================
// 2T1 / EPSILON HUB — CONFIG
// Central place for every path so nothing drifts out of sync
// ============================================================

define('SITE_NAME', '2T1');
define('SITE_ROOT', __DIR__);

define('DB_FILE', __DIR__ . '/data/database.json');
define('DB_LOCK_FILE', __DIR__ . '/data/database.lock');

define('SCRIPT_FILE', __DIR__ . '/scripts/main.lua');

define('WEBHOOK_FILE', __DIR__ . '/webhook_url.txt');
define('AUTH_LOG_FILE', __DIR__ . '/logs/auth.log');
define('TAMPER_LOG_FILE', __DIR__ . '/logs/tamper.log');

// Local companion service (Discord bot / reset-request API)
define('API_URL', 'http://127.0.0.1:3001');

// Master HMAC Secret Key for Anti-MitM Handshake
define('HMAC_SECRET', 'e7b92f8d4c1a5b3e9d8f7a6c5b4a3f2e1d0c9b8a7f6e5d4c3b2a1f0e9d8c7b6a');

// ============================================
// SECURITY & CRYPTO HELPERS
// ============================================
function verifyHMAC($data, $receivedHmac) {
    $calculated = hash_hmac('sha256', $data, HMAC_SECRET);
    return hash_equals($calculated, $receivedHmac);
}

function deriveSessionKey($hwid, $nonce) {
    return hash('sha256', $hwid . $nonce . HMAC_SECRET);
}

function encryptAES256($plainText, $sessionKey) {
    $key = substr(hash('sha256', $sessionKey, true), 0, 32);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}


if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// ============================================
// DATABASE — flat JSON with lock-based writes
// so two simultaneous requests can't corrupt it
// ============================================
function loadDB() {
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    if (!file_exists(DB_FILE)) {
        $default = ['users' => [], 'keys' => [], 'blacklist' => []];
        @file_put_contents(DB_FILE, json_encode($default, JSON_PRETTY_PRINT));
        return $default;
    }

    $fp = @fopen(DB_FILE, 'r');
    if (!$fp) return ['users' => [], 'keys' => [], 'blacklist' => []];

    flock($fp, LOCK_SH);
    $content = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    $data = json_decode($content, true);
    if (!is_array($data)) $data = [];
    $data['users'] = $data['users'] ?? [];
    $data['keys'] = $data['keys'] ?? [];
    $data['blacklist'] = $data['blacklist'] ?? [];

    return $data;
}

function saveDB($data) {
    $dir = dirname(DB_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    $lockFp = @fopen(DB_LOCK_FILE, 'c');
    if (!$lockFp) {
        // Fallback: write without locking rather than silently losing data
        return @file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }

    flock($lockFp, LOCK_EX);
    $ok = @file_put_contents(DB_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    flock($lockFp, LOCK_UN);
    fclose($lockFp);

    return $ok;
}

// ============================================
// DISCORD WEBHOOK — single implementation,
// both api.php and report.php call this now
// ============================================
function sendWebhook($title, $fields, $color = 16711680) {
    if (!file_exists(WEBHOOK_FILE)) return;
    $url = trim(file_get_contents(WEBHOOK_FILE));
    if (!$url) return;

    $embedFields = [];
    foreach ($fields as $name => $value) {
        $embedFields[] = ['name' => $name, 'value' => (string)$value, 'inline' => true];
    }

    $data = json_encode(['embeds' => [[
        'title' => $title,
        'color' => $color,
        'fields' => $embedFields,
        'timestamp' => date('c'),
    ]]]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

function logLine($file, $line) {
    $dir = dirname($file);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    @file_put_contents($file, date('Y-m-d H:i:s') . " | $line\n", FILE_APPEND | LOCK_EX);
}
