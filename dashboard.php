<?php
require_once 'config.php';
require_once 'includes/auth.php';

$user = requireLogin();
$userId = $_SESSION['user_id'];

$now = time() * 1000;
$daysLeft = floor(($user['expiry'] - $now) / (24 * 60 * 60 * 1000));
$hoursLeft = floor((($user['expiry'] - $now) % (24 * 60 * 60 * 1000)) / (60 * 60 * 1000));

$resetMessage = '';
$resetError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'reset_request') {
        $reason = trim($_POST['reason'] ?? '');
        
        if (strlen($reason) < 10) {
            $resetError = 'Please provide a detailed reason (min 10 characters).';
        } elseif (!$user['hwid']) {
            $resetError = 'Your HWID is not locked yet.';
        } else {
            $postData = json_encode([
                'discordId' => $userId,
                'reason' => $reason
            ]);
            
            $ch = curl_init(API_URL . '/api/reset-request');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            if ($result && $result['success']) {
                $resetMessage = 'Request submitted! ID: ' . $result['requestId'];
            } else {
                $resetError = $result['error'] ?? 'Failed to submit request.';
            }
        }
    }
}

$resetRequests = [];
$ch = curl_init(API_URL . '/api/reset-status/' . $userId);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if ($result && isset($result['requests'])) {
    $resetRequests = $result['requests'];
}

// Kullanicinin key'ini bul
$db_all = loadDB();
$userKey = '';
foreach ($db_all['keys'] as $k => $kdata) {
    if (isset($kdata['usedBy']) && $kdata['usedBy'] === $userId && $kdata['used']) {
        $userKey = $k;
    }
}

$loader = 'loadstring(game:HttpGet("https://2t1.online/loader"))()("' . $userKey . '")';

// Expiry yuzde hesapla (max 365 gun)
$totalDays = $daysLeft + ($hoursLeft / 24);
$expiryPercent = min(100, max(0, ($totalDays / 365) * 100));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epsilon Hub — Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --accent: #1f2340;
            --accent-light: #2a3158;
            --accent-glow: #3d4a7a;
            --accent-bright: #5a6aaa;
            --bg-primary: #08090c;
            --bg-secondary: #0d0f14;
            --bg-card: #111318;
            --bg-input: #0a0b10;
            --border: #1a1c24;
            --border-hover: #252836;
            --text-primary: #e8e9ed;
            --text-secondary: #6b6f80;
            --text-muted: #3d4050;
            --error: #e04058;
            --success: #38c97a;
            --warning: #e8a33c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--bg-primary);
            font-family: 'Outfit', sans-serif;
            color: var(--text-primary);
        }

        /* Ambient */
        .ambient {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .ambient::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 35, 64, 0.12) 0%, transparent 70%);
            top: -200px;
            right: -100px;
            animation: float1 20s ease-in-out infinite;
        }

        .ambient::after {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 35, 64, 0.08) 0%, transparent 70%);
            bottom: -150px;
            left: -100px;
            animation: float2 25s ease-in-out infinite;
        }

        @keyframes float1 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-50px, 50px) scale(1.1); }
        }

        @keyframes float2 {
            0%, 100% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(30px, -40px) scale(1.05); }
        }

        .grid-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-image: 
                linear-gradient(rgba(31, 35, 64, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(31, 35, 64, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Container */
        .container {
            position: relative;
            z-index: 10;
            max-width: 860px;
            margin: 0 auto;
            padding: 32px 24px 60px;
            animation: fadeUp 0.6s ease-out;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 36px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .header-logo {
            width: 36px;
            height: 36px;
            border-radius: 8px;
        }

        .header-title {
            font-family: 'Space Mono', monospace;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .user-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-secondary);
        }

        .user-badge i {
            color: var(--accent-glow);
            font-size: 12px;
        }

        .btn-logout {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: transparent;
            border: 1px solid rgba(224, 64, 88, 0.2);
            border-radius: 8px;
            color: var(--error);
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: rgba(224, 64, 88, 0.08);
            border-color: rgba(224, 64, 88, 0.3);
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            transition: border-color 0.3s;
        }

        .stat-card:hover {
            border-color: var(--border-hover);
        }

        .stat-label {
            font-size: 10px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-family: 'Space Mono', monospace;
            font-size: 22px;
            font-weight: 700;
        }

        .stat-value.green { color: var(--success); }
        .stat-value.warning { color: var(--warning); }

        /* Expiry bar */
        .expiry-bar {
            width: 100%;
            height: 3px;
            background: var(--border);
            border-radius: 3px;
            margin-top: 10px;
            overflow: hidden;
        }

        .expiry-bar-fill {
            height: 100%;
            border-radius: 3px;
            background: linear-gradient(90deg, var(--accent-glow), var(--accent-bright));
            transition: width 1s ease;
        }

        /* Section cards */
        .section {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
        }

        .section-header i {
            color: var(--accent-glow);
            font-size: 14px;
        }

        .section-header h2 {
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .section-body {
            padding: 24px;
        }

        .section-hint {
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 16px;
        }

        /* Script box */
        .script-container {
            position: relative;
        }

        .script-box {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--text-secondary);
            line-height: 1.6;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
            max-height: 120px;
            overflow-y: auto;
        }

        .btn-copy {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border: 1px solid var(--accent-glow);
            border-radius: 8px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 12px;
        }

        .btn-copy:hover {
            background: linear-gradient(135deg, var(--accent-light), var(--accent-glow));
            transform: translateY(-1px);
        }

        .btn-copy.copied {
            background: var(--success);
            border-color: var(--success);
        }

        /* HWID status */
        .hwid-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 16px;
        }

        .hwid-badge.locked {
            background: rgba(224, 64, 88, 0.08);
            border: 1px solid rgba(224, 64, 88, 0.15);
            color: var(--error);
        }

        .hwid-badge.unlocked {
            background: rgba(56, 201, 122, 0.08);
            border: 1px solid rgba(56, 201, 122, 0.15);
            color: var(--success);
        }

        /* Info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-item {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px 16px;
        }

        .info-item label {
            display: block;
            font-size: 10px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }

        .info-item span {
            font-size: 13px;
            color: var(--text-primary);
        }

        /* Form elements */
        textarea {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            padding: 14px 16px;
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            resize: vertical;
            min-height: 80px;
            margin-bottom: 12px;
            outline: none;
            transition: border-color 0.3s;
        }

        textarea::placeholder {
            color: var(--text-muted);
        }

        textarea:focus {
            border-color: var(--accent-glow);
            box-shadow: 0 0 0 3px rgba(31, 35, 64, 0.2);
        }

        .btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--warning), #cc8a2e);
            border: none;
            border-radius: 8px;
            color: #000;
            font-family: 'Outfit', sans-serif;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(232, 163, 60, 0.2);
        }

        /* Messages */
        .msg {
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .msg.success {
            background: rgba(56, 201, 122, 0.08);
            border: 1px solid rgba(56, 201, 122, 0.15);
            color: var(--success);
        }

        .msg.error {
            background: rgba(224, 64, 88, 0.08);
            border: 1px solid rgba(224, 64, 88, 0.15);
            color: var(--error);
        }

        .msg.pending {
            background: rgba(232, 163, 60, 0.08);
            border: 1px solid rgba(232, 163, 60, 0.15);
            color: var(--warning);
        }

        /* Request items */
        .request-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 8px;
        }

        .req-badge {
            font-size: 10px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .req-badge.pending {
            background: rgba(232, 163, 60, 0.15);
            color: var(--warning);
        }

        .req-badge.approved {
            background: rgba(56, 201, 122, 0.15);
            color: var(--success);
        }

        .req-badge.denied {
            background: rgba(224, 64, 88, 0.15);
            color: var(--error);
        }

        .req-id {
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Changelog */
        .cl-release {
            padding: 16px 0;
            border-bottom: 1px solid var(--border);
        }

        .cl-release:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .cl-release.latest .cl-version {
            background: rgba(56, 201, 122, 0.12);
            color: var(--success);
            border: 1px solid rgba(56, 201, 122, 0.2);
        }

        .cl-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .cl-version {
            font-family: 'Space Mono', monospace;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 6px;
            background: rgba(61, 74, 122, 0.15);
            color: var(--accent-glow);
            border: 1px solid rgba(61, 74, 122, 0.2);
        }

        .cl-date {
            font-size: 11px;
            color: var(--text-muted);
        }

        .cl-changes {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-left: 4px;
        }

        .cl-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cl-badge {
            font-size: 8px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.5px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 4px;
            min-width: 75px;
            justify-content: center;
        }

        .cl-badge i {
            font-size: 7px;
        }

        .cl-new {
            background: rgba(56, 201, 122, 0.1);
            color: var(--success);
        }

        .cl-improve {
            background: rgba(61, 74, 122, 0.15);
            color: var(--accent-bright);
        }

        .cl-fix {
            background: rgba(232, 163, 60, 0.1);
            color: var(--warning);
        }

        .cl-text {
            font-size: 12px;
            color: var(--text-secondary);
        }

        /* Footer */
        .footer {
            text-align: center;
            padding: 24px;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        /* Responsive */
        @media (max-width: 640px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }
        }
    </style>
</head>
<body>
    <div class="ambient"></div>
    <div class="grid-overlay"></div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <img src="/assets/logo.png" alt="Epsilon" class="header-logo">
                <span class="header-title">EPSILON</span>
            </div>
            <div class="header-right">
                <div class="user-badge">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($user['discordTag'] ?? 'User'); ?>
                </div>
                <a href="/logout.php" class="btn-logout">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    Sign Out
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Status</div>
                <div class="stat-value green">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Time Left</div>
                <div class="stat-value <?php echo $daysLeft < 3 ? 'warning' : ''; ?>"><?php echo $daysLeft; ?>d <?php echo $hoursLeft; ?>h</div>
                <div class="expiry-bar">
                    <div class="expiry-bar-fill" style="width: <?php echo $expiryPercent; ?>%"></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">HWID</div>
                <div class="stat-value" style="font-size:14px"><?php echo $user['hwid'] ? 'Locked' : 'Open'; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Executions</div>
                <div class="stat-value"><?php echo $user['logins'] ?? 0; ?></div>
            </div>
        </div>

        <!-- Loader Script -->
        <div class="section">
            <div class="section-header">
                <i class="fa-solid fa-terminal"></i>
                <h2>Loader Script</h2>
            </div>
            <div class="section-body">
                <p class="section-hint">Copy and execute in your Roblox executor.</p>
                <div class="script-container">
                    <div class="script-box" id="script"><?php echo htmlspecialchars($loader); ?></div>
                    <button class="btn-copy" onclick="copyScript()">
                        <i class="fa-solid fa-copy"></i>
                        <span>Copy Script</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- HWID Management -->
        <div class="section">
            <div class="section-header">
                <i class="fa-solid fa-fingerprint"></i>
                <h2>HWID Management</h2>
            </div>
            <div class="section-body">
                <?php if ($user['hwid']): ?>
                    <div class="hwid-badge locked">
                        <i class="fa-solid fa-lock"></i>
                        HWID Locked
                    </div>

                    <?php if ($resetMessage): ?>
                        <div class="msg success"><i class="fa-solid fa-check-circle"></i> <?php echo htmlspecialchars($resetMessage); ?></div>
                    <?php endif; ?>

                    <?php if ($resetError): ?>
                        <div class="msg error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($resetError); ?></div>
                    <?php endif; ?>

                    <?php
                    $hasPending = false;
                    foreach ($resetRequests as $req) {
                        if ($req['status'] === 'pending') { $hasPending = true; break; }
                    }
                    ?>

                    <?php if (!$hasPending): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="reset_request">
                            <textarea name="reason" placeholder="Why do you need an HWID reset?" required></textarea>
                            <button type="submit" class="btn-submit">
                                <i class="fa-solid fa-paper-plane"></i>
                                Submit Request
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="msg pending">
                            <i class="fa-solid fa-clock"></i>
                            You have a pending request. Please wait for review.
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="hwid-badge unlocked">
                        <i class="fa-solid fa-lock-open"></i>
                        HWID Not Locked
                    </div>
                    <p class="section-hint">Your HWID will be locked on first script execution.</p>
                <?php endif; ?>

                <?php if (!empty($resetRequests)): ?>
                    <div style="margin-top: 20px;">
                        <div class="stat-label" style="margin-bottom: 10px;">Recent Requests</div>
                        <?php foreach (array_reverse($resetRequests) as $req): ?>
                            <div class="request-item">
                                <span class="req-badge <?php echo $req['status']; ?>"><?php echo $req['status']; ?></span>
                                <span class="req-id"><?php echo $req['id']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account Info -->
        <div class="section">
            <div class="section-header">
                <i class="fa-solid fa-user"></i>
                <h2>Account</h2>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Discord</label>
                        <span><?php echo htmlspecialchars($user['discordTag'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Roblox</label>
                        <span><?php echo htmlspecialchars($user['robloxName'] ?? 'Not linked'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Expires</label>
                        <span><?php echo date('M d, Y', $user['expiry'] / 1000); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Registered</label>
                        <span><?php echo date('M d, Y', $user['createdAt'] / 1000); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Changelog -->
        <div class="section">
            <div class="section-header">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <h2>Changelog</h2>
            </div>
            <div class="section-body">
                <?php
                $changelog = [
                    ['date' => '2026-02-15', 'version' => 'v3.0', 'changes' => [
                        ['type' => 'new', 'text' => 'New loader with crack effect animation'],
                        ['type' => 'new', 'text' => 'Game-specific script loading (Blade Ball, Fisch, Blox Fruits)'],
                        ['type' => 'improve', 'text' => 'Website redesign — minimal dark theme'],
                        ['type' => 'improve', 'text' => 'Anti-tamper system with Discord alerts'],
                        ['type' => 'fix', 'text' => 'HWID detection consistency across executors'],
                    ]],
                    ['date' => '2026-02-14', 'version' => 'v2.5', 'changes' => [
                        ['type' => 'new', 'text' => 'Key-based authentication system'],
                        ['type' => 'new', 'text' => 'Polymorphic XOR obfuscation'],
                        ['type' => 'improve', 'text' => 'Rate limiting (15 req/min)'],
                        ['type' => 'fix', 'text' => 'Loader compatibility with all executors'],
                    ]],
                    ['date' => '2026-02-13', 'version' => 'v2.0', 'changes' => [
                        ['type' => 'new', 'text' => 'Epsilon Hub rebranded from 2T1 Hub'],
                        ['type' => 'new', 'text' => 'Flow UI library integration'],
                        ['type' => 'new', 'text' => 'Silent Aim + ESP framework'],
                    ]],
                ];
                
                foreach ($changelog as $i => $release): ?>
                    <div class="cl-release <?php echo $i === 0 ? 'latest' : ''; ?>">
                        <div class="cl-header">
                            <span class="cl-version"><?php echo $release['version']; ?></span>
                            <span class="cl-date"><?php echo date('M d, Y', strtotime($release['date'])); ?></span>
                        </div>
                        <div class="cl-changes">
                            <?php foreach ($release['changes'] as $change): ?>
                                <div class="cl-item">
                                    <?php
                                    $badge = 'cl-new';
                                    $icon = 'fa-plus';
                                    $label = 'NEW';
                                    if ($change['type'] === 'improve') { $badge = 'cl-improve'; $icon = 'fa-arrow-up'; $label = 'IMPROVED'; }
                                    if ($change['type'] === 'fix') { $badge = 'cl-fix'; $icon = 'fa-wrench'; $label = 'FIX'; }
                                    ?>
                                    <span class="cl-badge <?php echo $badge; ?>"><i class="fa-solid <?php echo $icon; ?>"></i> <?php echo $label; ?></span>
                                    <span class="cl-text"><?php echo htmlspecialchars($change['text']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Epsilon Hub &copy; <?php echo date('Y'); ?> &mdash; @Invasor / @Drawat
        </div>
    </div>

    <script>
        function copyScript() {
            const text = document.getElementById('script').innerText;
            navigator.clipboard.writeText(text);
            const btn = document.querySelector('.btn-copy');
            btn.classList.add('copied');
            btn.innerHTML = '<i class="fa-solid fa-check"></i><span>Copied!</span>';
            setTimeout(() => {
                btn.classList.remove('copied');
                btn.innerHTML = '<i class="fa-solid fa-copy"></i><span>Copy Script</span>';
            }, 2000);
        }
    </script>
</body>
</html>
