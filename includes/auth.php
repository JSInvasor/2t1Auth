<?php
require_once __DIR__ . '/../config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /');
        exit;
    }
    
    $db = loadDB();
    $userId = $_SESSION['user_id'];
    
    if (!isset($db['users'][$userId])) {
        session_destroy();
        header('Location: /');
        exit;
    }
    
    $user = $db['users'][$userId];
    
    if ($user['expiry'] < (time() * 1000)) {
        session_destroy();
        header('Location: /?error=expired');
        exit;
    }
    
    return $user;
}
