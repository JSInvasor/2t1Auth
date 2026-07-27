<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: text/plain');

$type = $_GET['type'] ?? 'unknown';
$hwid = $_GET['hwid'] ?? 'unknown';
$user = $_GET['user'] ?? 'unknown';
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

logLine(TAMPER_LOG_FILE, "$type | $user | $hwid | $ip");

sendWebhook('🚨 Script Theft Attempt', [
    'Type' => $type,
    'User' => $user,
    'HWID' => substr($hwid, 0, 20),
    'IP' => $ip,
]);

echo "ok";
