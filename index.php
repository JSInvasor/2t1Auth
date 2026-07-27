<?php
require_once 'config.php';
require_once 'includes/auth.php';

if (isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username && $password) {
        $db = loadDB();
        
        foreach ($db['users'] as $odaRobloxId => $user) {
            if (isset($user['webUser']) && strtolower($user['webUser']) === strtolower($username)) {
                if (isset($user['webPass']) && $user['webPass'] === $password) {
                    if ($user['expiry'] > (time() * 1000)) {
                        $_SESSION['user_id'] = $odaRobloxId;
                        header('Location: /dashboard.php');
                        exit;
                    } else {
                        $error = 'License expired.';
                    }
                } else {
                    $error = 'Invalid credentials.';
                }
                break;
            }
        }
        
        if (!$error) $error = 'User not found.';
    } else {
        $error = 'Please fill all fields.';
    }
}

if (isset($_GET['error']) && $_GET['error'] === 'expired') {
    $error = 'Your license has expired.';
}

// Aktif kullanici sayisi
$db = loadDB();
$totalUsers = count($db['users'] ?? []);
$activeUsers = 0;
$totalExecs = 0;
$now = time() * 1000;
foreach ($db['users'] as $u) {
    if (($u['expiry'] ?? 0) > $now) $activeUsers++;
    $totalExecs += ($u['logins'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epsilon Hub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --accent: #1f2340;
            --accent-light: #2a3158;
            --accent-glow: #3d4a7a;
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
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: var(--bg-primary);
            font-family: 'Outfit', sans-serif;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Ambient background */
        .ambient {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .ambient::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31, 35, 64, 0.15) 0%, transparent 70%);
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
            background: radial-gradient(circle, rgba(31, 35, 64, 0.1) 0%, transparent 70%);
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

        /* Grid lines */
        .grid-overlay {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-image: 
                linear-gradient(rgba(31, 35, 64, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(31, 35, 64, 0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }

        /* Noise texture */
        .noise {
            position: fixed;
            inset: 0;
            z-index: 0;
            opacity: 0.03;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
        }

        /* Main container */
        .main {
            position: relative;
            z-index: 10;
            display: flex;
            gap: 80px;
            align-items: center;
            max-width: 1000px;
            width: 100%;
            padding: 40px;
            animation: fadeUp 0.8s ease-out;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Left side - branding */
        .brand {
            flex: 1;
            max-width: 380px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 32px;
        }

        .brand-logo img {
            width: 44px;
            height: 44px;
            border-radius: 10px;
        }

        .brand-logo-placeholder {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-glow));
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Space Mono', monospace;
            font-weight: 700;
            font-size: 16px;
            color: #fff;
        }

        .brand-name {
            font-family: 'Space Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 2px;
        }

        .brand h1 {
            font-size: 36px;
            font-weight: 700;
            line-height: 1.2;
            margin-bottom: 16px;
            letter-spacing: -0.5px;
        }

        .brand h1 span {
            color: var(--accent-glow);
        }

        .brand p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 40px;
        }

        /* Stats */
        .stats {
            display: flex;
            gap: 32px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .stat-value {
            font-family: 'Space Mono', monospace;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Right side - card */
        .card {
            width: 400px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 
                0 0 0 1px rgba(31, 35, 64, 0.1),
                0 20px 60px rgba(0, 0, 0, 0.4),
                0 0 120px rgba(31, 35, 64, 0.05);
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
        }

        .tab {
            flex: 1;
            padding: 16px;
            text-align: center;
            font-size: 13px;
            font-weight: 500;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            text-decoration: none;
            letter-spacing: 0.5px;
        }

        .tab:hover {
            color: var(--text-secondary);
        }

        .tab.active {
            color: var(--text-primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 20%;
            width: 60%;
            height: 2px;
            background: var(--accent-glow);
            border-radius: 2px;
        }

        /* Tab content */
        .tab-content {
            padding: 32px;
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Error */
        .error-msg {
            background: rgba(224, 64, 88, 0.08);
            border: 1px solid rgba(224, 64, 88, 0.15);
            color: var(--error);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-msg i {
            font-size: 14px;
        }

        /* Form */
        .field {
            margin-bottom: 16px;
        }

        .field label {
            display: block;
            font-size: 11px;
            font-weight: 500;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 8px;
        }

        .field input {
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border);
            padding: 14px 16px;
            border-radius: 10px;
            color: var(--text-primary);
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
            outline: none;
        }

        .field input::placeholder {
            color: var(--text-muted);
        }

        .field input:focus {
            border-color: var(--accent-glow);
            box-shadow: 0 0 0 3px rgba(31, 35, 64, 0.2);
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border: 1px solid var(--accent-glow);
            border-radius: 10px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--accent-light), var(--accent-glow));
            box-shadow: 0 4px 20px rgba(31, 35, 64, 0.3);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Purchase tab */
        .pricing {
            text-align: center;
        }

        .pricing-title {
            font-size: 11px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 24px;
        }

        .price-cards {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .price-card {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.3s;
            position: relative;
        }

        .price-card:hover {
            border-color: var(--accent-glow);
        }

        .price-card.popular {
            border-color: var(--accent-glow);
            background: rgba(31, 35, 64, 0.12);
        }

        .popular-tag {
            position: absolute;
            top: -9px;
            right: 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-glow));
            color: #fff;
            font-size: 9px;
            font-weight: 600;
            padding: 2px 10px;
            border-radius: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .price-left {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .price-card .duration {
            font-size: 14px;
            font-weight: 500;
        }

        .price-card .duration-sub {
            font-size: 11px;
            color: var(--text-muted);
        }

        .price-right {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }

        .price-card .price {
            font-family: 'Space Mono', monospace;
            font-size: 16px;
            color: var(--accent-glow);
            font-weight: 700;
        }

        .price-robux {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: var(--text-muted);
            font-family: 'Space Mono', monospace;
        }

        .robux-icon {
            color: #38c97a;
        }

        .btn-discord {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px;
            background: #5865F2;
            border: none;
            border-radius: 10px;
            color: #fff;
            font-family: 'Outfit', sans-serif;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-discord:hover {
            background: #4752c4;
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(88, 101, 242, 0.3);
        }

        /* Footer */
        .card-footer {
            padding: 16px 32px;
            border-top: 1px solid var(--border);
            text-align: center;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        /* Status dot */
        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(56, 201, 122, 0.4); }
            50% { opacity: 0.8; box-shadow: 0 0 0 6px rgba(56, 201, 122, 0); }
        }

        /* Responsive */
        @media (max-width: 860px) {
            .main {
                flex-direction: column;
                gap: 40px;
                padding: 24px;
            }

            .brand {
                text-align: center;
                max-width: 100%;
            }

            .brand h1 { font-size: 28px; }

            .stats { justify-content: center; }

            .card { width: 100%; max-width: 400px; }
        }
    </style>
</head>
<body>
    <!-- Top Nav -->
    <style>
        .topnav{position:fixed;top:0;left:0;right:0;z-index:999;display:flex;justify-content:center;padding:14px 0;background:rgba(8,9,12,0.92);backdrop-filter:blur(14px);border-bottom:1px solid #1a1c24}
        .topnav-inner{display:flex;align-items:center;gap:8px}
        .topnav-btn{padding:8px 24px;border-radius:8px;font-family:'Outfit',sans-serif;font-size:13px;font-weight:500;text-decoration:none;color:#6b6f80;background:transparent;border:1px solid #1a1c24;transition:all 0.3s}
        .topnav-btn.active{font-weight:600;color:#e8e9ed;background:rgba(31,35,64,0.3);border-color:#3d4a7a}
        .topnav-btn:not(.active):hover{color:#e8e9ed;border-color:#252836}
        .topnav-arrow{opacity:0;transition:all 0.4s ease;filter:drop-shadow(0 0 8px rgba(61,74,122,0.9)) drop-shadow(0 0 16px rgba(61,74,122,0.4))}
        .topnav-arrow.show{opacity:1}
        .topnav-spacer{height:52px}
    </style>
    <div class="topnav">
        <div class="topnav-inner">
            <a href="/" class="topnav-btn active">Main</a>

            <svg class="topnav-arrow" id="topArrow" width="22" height="22" viewBox="0 0 22 22">
                <path d="M8 4l7 7-7 7" stroke="#3d4a7a" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
                    <animate attributeName="stroke" values="#3d4a7a;#5a6aaa;#3d4a7a" dur="1.5s" repeatCount="indefinite"/>
                </path>
            </svg>
            <a href="/demo.php" class="topnav-btn" id="demoBtn"
               onmouseenter="document.getElementById('topArrow').classList.add('show')"
               onmouseleave="document.getElementById('topArrow').classList.remove('show')">Demo</a>
        </div>
    </div>
    <div class="topnav-spacer"></div>

    <div class="ambient"></div>
    <div class="grid-overlay"></div>
    <div class="noise"></div>

    <div class="main">
        <div class="brand">
            <div class="brand-logo">
                <img src="/logo.png?v=2" alt="2t1 Studio" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" />
                <div class="brand-logo-placeholder" style="display:none">2</div>
                <span class="brand-name">2T1 STUDIO</span>
            </div>

            <h1>Enjoy<br><span>The Power.</span></h1>
            <p>Made By @0worry , @Drawat</p>

            <div class="status">
                <span class="status-dot"></span>
                All systems operational
            </div>

            <div class="stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo $totalUsers; ?></span>
                    <span class="stat-label">Users</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo number_format($totalExecs); ?></span>
                    <span class="stat-label">Executions</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $activeUsers; ?></span>
                    <span class="stat-label">Active</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('login')">Sign In</div>
                <div class="tab" onclick="switchTab('purchase')">Purchase</div>
                <a href="https://discord.com/invite/VsghrHFVbx" target="_blank" class="tab">
                    <i class="fa-brands fa-discord"></i> Discord
                </a>
            </div>

            <div id="login" class="tab-content active">
                <?php if ($error): ?>
                    <div class="error-msg">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="field">
                        <label>Username</label>
                        <input type="text" name="username" placeholder="Enter your username" required autocomplete="off">
                    </div>
                    <div class="field">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" class="btn-primary">Sign In</button>
                </form>
            </div>

            <div id="purchase" class="tab-content">
                <div class="pricing">
                    <div class="pricing-title">Select a plan</div>

                    <div class="price-cards">
                        <div class="price-card">
                            <div class="price-left">
                                <span class="duration">1 Week</span>
                                <span class="duration-sub">7 days access</span>
                            </div>
                            <div class="price-right">
                                <span class="price">$10</span>
                                <span class="price-robux"><svg class="robux-icon" viewBox="0 0 24 24" width="12" height="12"><path fill="currentColor" d="M2.014 12.003c0-1.34.263-2.629.78-3.855a10.074 10.074 0 0 1 2.15-3.222A10.08 10.08 0 0 1 8.164.78 9.932 9.932 0 0 1 12.003 0c1.34 0 2.629.263 3.855.78a10.074 10.074 0 0 1 3.222 2.15 10.08 10.08 0 0 1 2.147 3.22 9.932 9.932 0 0 1 .78 3.853c0 1.34-.263 2.629-.78 3.855a10.074 10.074 0 0 1-2.15 3.222 10.08 10.08 0 0 1-3.22 2.147 9.932 9.932 0 0 1-3.854.78c-1.34 0-2.629-.263-3.855-.78a10.074 10.074 0 0 1-3.222-2.15 10.08 10.08 0 0 1-2.147-3.22 9.932 9.932 0 0 1-.78-3.854zm3.87 0c0 .83.157 1.628.471 2.394a6.198 6.198 0 0 0 1.315 1.997 6.198 6.198 0 0 0 1.997 1.315A6.015 6.015 0 0 0 12.06 18.18c.83 0 1.628-.157 2.394-.471a6.198 6.198 0 0 0 1.997-1.315 6.198 6.198 0 0 0 1.315-1.997A6.015 6.015 0 0 0 18.238 12c0-.83-.157-1.628-.471-2.394A6.198 6.198 0 0 0 16.452 7.61a6.198 6.198 0 0 0-1.997-1.315A6.015 6.015 0 0 0 12.06 5.824c-.83 0-1.628.157-2.394.471A6.198 6.198 0 0 0 7.67 7.61a6.198 6.198 0 0 0-1.315 1.997A6.015 6.015 0 0 0 5.884 12zm3.312-2.27h1.69v-1.69h2.27v1.69h1.69v2.27h-1.69v1.69h-2.27V12h-1.69v-2.27z"/></svg> 300 R$</span>
                            </div>
                        </div>
                        <div class="price-card popular">
                            <div class="popular-tag">Popular</div>
                            <div class="price-left">
                                <span class="duration">1 Month</span>
                                <span class="duration-sub">30 days access</span>
                            </div>
                            <div class="price-right">
                                <span class="price">$25</span>
                                <span class="price-robux"><svg class="robux-icon" viewBox="0 0 24 24" width="12" height="12"><path fill="currentColor" d="M2.014 12.003c0-1.34.263-2.629.78-3.855a10.074 10.074 0 0 1 2.15-3.222A10.08 10.08 0 0 1 8.164.78 9.932 9.932 0 0 1 12.003 0c1.34 0 2.629.263 3.855.78a10.074 10.074 0 0 1 3.222 2.15 10.08 10.08 0 0 1 2.147 3.22 9.932 9.932 0 0 1 .78 3.853c0 1.34-.263 2.629-.78 3.855a10.074 10.074 0 0 1-2.15 3.222 10.08 10.08 0 0 1-3.22 2.147 9.932 9.932 0 0 1-3.854.78c-1.34 0-2.629-.263-3.855-.78a10.074 10.074 0 0 1-3.222-2.15 10.08 10.08 0 0 1-2.147-3.22 9.932 9.932 0 0 1-.78-3.854zm3.87 0c0 .83.157 1.628.471 2.394a6.198 6.198 0 0 0 1.315 1.997 6.198 6.198 0 0 0 1.997 1.315A6.015 6.015 0 0 0 12.06 18.18c.83 0 1.628-.157 2.394-.471a6.198 6.198 0 0 0 1.997-1.315 6.198 6.198 0 0 0 1.315-1.997A6.015 6.015 0 0 0 18.238 12c0-.83-.157-1.628-.471-2.394A6.198 6.198 0 0 0 16.452 7.61a6.198 6.198 0 0 0-1.997-1.315A6.015 6.015 0 0 0 12.06 5.824c-.83 0-1.628.157-2.394.471A6.198 6.198 0 0 0 7.67 7.61a6.198 6.198 0 0 0-1.315 1.997A6.015 6.015 0 0 0 5.884 12zm3.312-2.27h1.69v-1.69h2.27v1.69h1.69v2.27h-1.69v1.69h-2.27V12h-1.69v-2.27z"/></svg> 1,250 R$</span>
                            </div>
                        </div>
                        <div class="price-card">
                            <div class="price-left">
                                <span class="duration">Lifetime</span>
                                <span class="duration-sub">Unlimited access</span>
                            </div>
                            <div class="price-right">
                                <span class="price">$60</span>
                                <span class="price-robux"><svg class="robux-icon" viewBox="0 0 24 24" width="12" height="12"><path fill="currentColor" d="M2.014 12.003c0-1.34.263-2.629.78-3.855a10.074 10.074 0 0 1 2.15-3.222A10.08 10.08 0 0 1 8.164.78 9.932 9.932 0 0 1 12.003 0c1.34 0 2.629.263 3.855.78a10.074 10.074 0 0 1 3.222 2.15 10.08 10.08 0 0 1 2.147 3.22 9.932 9.932 0 0 1 .78 3.853c0 1.34-.263 2.629-.78 3.855a10.074 10.074 0 0 1-2.15 3.222 10.08 10.08 0 0 1-3.22 2.147 9.932 9.932 0 0 1-3.854.78c-1.34 0-2.629-.263-3.855-.78a10.074 10.074 0 0 1-3.222-2.15 10.08 10.08 0 0 1-2.147-3.22 9.932 9.932 0 0 1-.78-3.854zm3.87 0c0 .83.157 1.628.471 2.394a6.198 6.198 0 0 0 1.315 1.997 6.198 6.198 0 0 0 1.997 1.315A6.015 6.015 0 0 0 12.06 18.18c.83 0 1.628-.157 2.394-.471a6.198 6.198 0 0 0 1.997-1.315 6.198 6.198 0 0 0 1.315-1.997A6.015 6.015 0 0 0 18.238 12c0-.83-.157-1.628-.471-2.394A6.198 6.198 0 0 0 16.452 7.61a6.198 6.198 0 0 0-1.997-1.315A6.015 6.015 0 0 0 12.06 5.824c-.83 0-1.628.157-2.394.471A6.198 6.198 0 0 0 7.67 7.61a6.198 6.198 0 0 0-1.315 1.997A6.015 6.015 0 0 0 5.884 12zm3.312-2.27h1.69v-1.69h2.27v1.69h1.69v2.27h-1.69v1.69h-2.27V12h-1.69v-2.27z"/></svg> 3,000 R$</span>
                            </div>
                        </div>
                    </div>

                    <a href="https://discord.com/invite/VsghrHFVbx" target="_blank" class="btn-discord">
                        <i class="fa-brands fa-discord"></i>
                        Purchase via Discord
                    </a>
                </div>
            </div>

            <div class="card-footer">
                2t1 Studio &copy; <?php echo date('Y'); ?> &mdash; Made By @0worry , @Drawat
            </div>
        </div>
    </div>

    <script>
        function switchTab(id) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            event.currentTarget.classList.add('active');
        }
    </script>
</body>
</html>
