<?php
require_once 'config.php';

header('Content-Type: text/plain');
header('Cache-Control: no-cache, no-store, must-revalidate');

$hwid = $_GET['hwid'] ?? null;

if (!$hwid || strlen($hwid) < 5) {
    die('invalid');
}

// User-Agent kontrolÃ¼
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strlen($ua) > 150 || strpos($ua, 'Mozilla') !== false || strpos($ua, 'Chrome') !== false) {
    die('invalid');
}

$db = loadDB();

foreach ($db['users'] as $user) {
    if (isset($user['hwid']) && $user['hwid'] === $hwid) {
        if ($user['expiry'] > (time() * 1000)) {
            die('valid');
        } else {
            die('expired');
        }
    }
}

die('invalid');
