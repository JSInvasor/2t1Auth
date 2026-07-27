<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Epsilon Hub — Setup Guide</title>
    <meta name="description" content="Step-by-step installation and setup guide for Epsilon Hub Roblox script platform.">
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

        /* ── Ambient Background ── */
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
            width: 600px; height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31,35,64,0.12) 0%, transparent 70%);
            top: -200px; right: -100px;
            animation: float1 20s ease-in-out infinite;
        }
        .ambient::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(31,35,64,0.08) 0%, transparent 70%);
            bottom: -150px; left: -100px;
            animation: float2 25s ease-in-out infinite;
        }
        @keyframes float1 {
            0%, 100% { transform: translate(0,0) scale(1); }
            50% { transform: translate(-50px,50px) scale(1.1); }
        }
        @keyframes float2 {
            0%, 100% { transform: translate(0,0) scale(1); }
            50% { transform: translate(30px,-40px) scale(1.05); }
        }
        .grid-overlay {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(31,35,64,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(31,35,64,0.03) 1px, transparent 1px);
            background-size: 60px 60px;
        }
        .noise {
            position: fixed; inset: 0; z-index: 0; opacity: 0.03; pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)'/%3E%3C/svg%3E");
        }

        /* ── Top Nav ── */
        .topnav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 999;
            display: flex; justify-content: center; padding: 14px 0;
            background: rgba(8,9,12,0.92); backdrop-filter: blur(14px);
            border-bottom: 1px solid #1a1c24;
        }
        .topnav-inner { display: flex; align-items: center; gap: 8px; }
        .topnav-btn {
            padding: 8px 24px; border-radius: 8px;
            font-family: 'Outfit', sans-serif; font-size: 13px; font-weight: 500;
            text-decoration: none; color: #6b6f80;
            background: transparent; border: 1px solid #1a1c24;
            transition: all 0.3s;
        }
        .topnav-btn.active {
            font-weight: 600; color: #e8e9ed;
            background: rgba(31,35,64,0.3); border-color: #3d4a7a;
        }
        .topnav-btn:not(.active):hover { color: #e8e9ed; border-color: #252836; }
        .topnav-spacer { height: 60px; }

        /* ── Container ── */
        .container {
            position: relative; z-index: 10;
            max-width: 820px; margin: 0 auto;
            padding: 32px 24px 60px;
            animation: fadeUp 0.6s ease-out;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ── Page Header ── */
        .page-header {
            text-align: center;
            margin-bottom: 48px;
            padding-bottom: 32px;
            border-bottom: 1px solid var(--border);
        }
        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }
        .page-header h1 i {
            color: var(--accent-glow);
            margin-right: 12px;
            font-size: 28px;
        }
        .page-header p {
            font-size: 15px;
            color: var(--text-secondary);
            line-height: 1.7;
            max-width: 500px;
            margin: 0 auto;
        }

        /* ── Timeline Steps ── */
        .steps {
            position: relative;
            padding-left: 40px;
        }
        .steps::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--accent-glow), var(--border) 90%, transparent);
            border-radius: 2px;
        }

        .step {
            position: relative;
            margin-bottom: 32px;
            animation: fadeUp 0.6s ease-out;
        }
        .step:last-child { margin-bottom: 0; }

        .step-number {
            position: absolute;
            left: -40px;
            top: 0;
            width: 30px; height: 30px;
            border-radius: 50%;
            background: var(--bg-card);
            border: 2px solid var(--accent-glow);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Space Mono', monospace;
            font-size: 12px; font-weight: 700;
            color: var(--accent-bright);
            z-index: 2;
            box-shadow: 0 0 12px rgba(61,74,122,0.2);
        }
        .step.completed .step-number {
            background: var(--success);
            border-color: var(--success);
            color: #fff;
        }

        .step-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            overflow: hidden;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .step-card:hover {
            border-color: var(--border-hover);
            box-shadow: 0 4px 24px rgba(0,0,0,0.2);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            user-select: none;
        }
        .step-header i.icon {
            color: var(--accent-glow);
            font-size: 16px;
            width: 20px;
            text-align: center;
        }
        .step-header h3 {
            flex: 1;
            font-size: 15px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .step-header .badge {
            font-size: 9px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge-required {
            background: rgba(224,64,88,0.1);
            color: var(--error);
            border: 1px solid rgba(224,64,88,0.15);
        }
        .badge-optional {
            background: rgba(56,201,122,0.08);
            color: var(--success);
            border: 1px solid rgba(56,201,122,0.15);
        }
        .badge-important {
            background: rgba(232,163,60,0.1);
            color: var(--warning);
            border: 1px solid rgba(232,163,60,0.15);
        }

        .step-header .chevron {
            color: var(--text-muted);
            font-size: 12px;
            transition: transform 0.3s;
        }
        .step-card.open .step-header .chevron {
            transform: rotate(90deg);
        }

        .step-body {
            padding: 0 24px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.4s ease, padding 0.4s ease;
        }
        .step-card.open .step-body {
            padding: 24px;
            max-height: 2000px;
        }

        .step-body p {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.8;
            margin-bottom: 16px;
        }
        .step-body p:last-child { margin-bottom: 0; }

        /* ── Code Block ── */
        .code-block {
            position: relative;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin: 12px 0 16px;
            overflow: hidden;
        }
        .code-block-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 14px;
            border-bottom: 1px solid var(--border);
            background: rgba(31,35,64,0.06);
        }
        .code-block-lang {
            font-family: 'Space Mono', monospace;
            font-size: 10px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .code-copy-btn {
            display: flex; align-items: center; gap: 6px;
            background: none; border: 1px solid var(--border);
            border-radius: 6px; padding: 4px 12px;
            font-family: 'Outfit', sans-serif; font-size: 11px;
            color: var(--text-muted); cursor: pointer;
            transition: all 0.3s;
        }
        .code-copy-btn:hover { color: var(--text-primary); border-color: var(--accent-glow); }
        .code-copy-btn.copied { color: var(--success); border-color: var(--success); }

        .code-block pre {
            padding: 16px;
            font-family: 'Space Mono', monospace;
            font-size: 12px;
            line-height: 1.7;
            color: var(--text-primary);
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        .code-block pre .comment { color: var(--text-muted); }
        .code-block pre .keyword { color: var(--accent-bright); }
        .code-block pre .string { color: var(--success); }
        .code-block pre .url { color: var(--warning); }

        /* ── Alerts ── */
        .alert {
            display: flex; gap: 12px; align-items: flex-start;
            padding: 14px 18px;
            border-radius: 10px;
            margin: 12px 0 16px;
            font-size: 13px;
            line-height: 1.6;
        }
        .alert i { margin-top: 2px; font-size: 14px; }
        .alert-info {
            background: rgba(61,74,122,0.08);
            border: 1px solid rgba(61,74,122,0.15);
            color: var(--accent-bright);
        }
        .alert-warning {
            background: rgba(232,163,60,0.08);
            border: 1px solid rgba(232,163,60,0.15);
            color: var(--warning);
        }
        .alert-success {
            background: rgba(56,201,122,0.08);
            border: 1px solid rgba(56,201,122,0.15);
            color: var(--success);
        }
        .alert-danger {
            background: rgba(224,64,88,0.08);
            border: 1px solid rgba(224,64,88,0.15);
            color: var(--error);
        }

        /* ── Inline list ── */
        .check-list {
            list-style: none;
            margin: 8px 0 16px;
        }
        .check-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 1px solid rgba(26,28,36,0.5);
        }
        .check-list li:last-child { border-bottom: none; }
        .check-list li i {
            font-size: 11px;
            color: var(--success);
            width: 16px;
            text-align: center;
        }
        .check-list li i.fa-xmark { color: var(--error); }

        /* ── Executor Grid ── */
        .executor-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 12px 0;
        }
        .executor-card {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 16px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: border-color 0.3s;
        }
        .executor-card:hover { border-color: var(--accent-glow); }
        .executor-card .exec-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--accent), var(--accent-glow));
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; color: #fff;
        }
        .executor-card .exec-info { display: flex; flex-direction: column; gap: 2px; }
        .executor-card .exec-name { font-size: 13px; font-weight: 600; }
        .executor-card .exec-status {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .exec-status.supported { color: var(--success); }
        .exec-status.partial { color: var(--warning); }

        /* ── FAQ accordion inside steps ── */
        .faq-item {
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 8px;
            overflow: hidden;
        }
        .faq-q {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 16px;
            cursor: pointer; user-select: none;
            font-size: 13px; font-weight: 500;
            color: var(--text-primary);
            transition: background 0.2s;
        }
        .faq-q:hover { background: rgba(31,35,64,0.06); }
        .faq-q i { color: var(--accent-glow); font-size: 11px; transition: transform 0.3s; }
        .faq-item.open .faq-q i { transform: rotate(90deg); }
        .faq-a {
            max-height: 0; overflow: hidden;
            padding: 0 16px;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        .faq-item.open .faq-a {
            max-height: 500px;
            padding: 0 16px 14px;
        }
        .faq-a p {
            font-size: 12px !important;
            color: var(--text-secondary) !important;
            line-height: 1.7;
        }

        /* ── Footer ── */
        .footer {
            text-align: center;
            padding: 32px 24px;
            font-size: 11px;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            border-top: 1px solid var(--border);
            margin-top: 48px;
        }

        /* ── Responsive ── */
        @media (max-width: 640px) {
            .container { padding: 20px 16px 40px; }
            .page-header h1 { font-size: 24px; }
            .steps { padding-left: 32px; }
            .step-number { left: -32px; width: 26px; height: 26px; font-size: 10px; }
            .executor-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Top Nav -->
    <div class="topnav">
        <div class="topnav-inner">
            <a href="/" class="topnav-btn">Main</a>
            <a href="/setup.php" class="topnav-btn active">Setup</a>
            <a href="/demo.php" class="topnav-btn">Demo</a>
        </div>
    </div>
    <div class="topnav-spacer"></div>

    <div class="ambient"></div>
    <div class="grid-overlay"></div>
    <div class="noise"></div>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fa-solid fa-rocket"></i>Kurulum Rehberi</h1>
            <p>Epsilon Hub'ı kullanmaya başlamak için aşağıdaki adımları sırasıyla takip edin. Tüm işlem 2 dakikadan kısa sürer.</p>
        </div>

        <!-- Timeline Steps -->
        <div class="steps">

            <!-- Step 1: Executor -->
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-card open">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-download"></i>
                        <h3>Executor İndirin</h3>
                        <span class="badge badge-required">Gerekli</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <p>Epsilon Hub'ı çalıştırabilmek için bir Roblox script executor'üne ihtiyacınız var. Aşağıdaki executor'ler test edilmiş ve desteklenmektedir:</p>

                        <div class="executor-grid">
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-bolt"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Solara</span>
                                    <span class="exec-status supported">Tam Destek</span>
                                </div>
                            </div>
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-star"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Wave</span>
                                    <span class="exec-status supported">Tam Destek</span>
                                </div>
                            </div>
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-fire"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Fluxus</span>
                                    <span class="exec-status supported">Tam Destek</span>
                                </div>
                            </div>
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-shield"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Synapse Z</span>
                                    <span class="exec-status supported">Tam Destek</span>
                                </div>
                            </div>
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-wand-sparkles"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Arceus X</span>
                                    <span class="exec-status partial">Kısmi Destek</span>
                                </div>
                            </div>
                            <div class="executor-card">
                                <div class="exec-icon"><i class="fa-solid fa-code"></i></div>
                                <div class="exec-info">
                                    <span class="exec-name">Diğerleri</span>
                                    <span class="exec-status partial">UNC Gerekli</span>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fa-solid fa-circle-info"></i>
                            <span>Executor'ünüzün <strong>loadstring</strong>, <strong>HttpGet</strong> ve <strong>clonefunction</strong> desteğine sahip olması gerekmektedir. UNC uyumlu executor'ler önerilir.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Purchase & Key -->
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-card">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-key"></i>
                        <h3>Lisans Anahtarı Alın</h3>
                        <span class="badge badge-required">Gerekli</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <p>Epsilon Hub'a erişim için bir lisans anahtarına ihtiyacınız var. Discord sunucumuza katılarak satın alabilirsiniz:</p>

                        <ul class="check-list">
                            <li><i class="fa-solid fa-check"></i>Discord sunucumuza katılın</li>
                            <li><i class="fa-solid fa-check"></i><strong>#purchase</strong> kanalından plan seçin (1 Hafta / 1 Ay / Lifetime)</li>
                            <li><i class="fa-solid fa-check"></i>Ödeme sonrası lisans anahtarınız DM ile gönderilir</li>
                            <li><i class="fa-solid fa-check"></i>Key formatı: <code style="color:var(--accent-bright);background:var(--bg-input);padding:2px 8px;border-radius:4px;font-family:'Space Mono',monospace;font-size:11px">XXXXX-XXXXX-XXXXX</code></li>
                        </ul>

                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Anahtarınızı asla kimseyle paylaşmayın! Her key tek bir HWID'ye kilitlenir.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Web Panel Login -->
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-card">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-right-to-bracket"></i>
                        <h3>Web Paneline Giriş Yapın</h3>
                        <span class="badge badge-optional">Opsiyonel</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <p>Web paneliniz üzerinden loader script'inizi kopyalayabilir, HWID durumunuzu görüntüleyebilir ve HWID reset talebi gönderebilirsiniz.</p>

                        <ul class="check-list">
                            <li><i class="fa-solid fa-check"></i><a href="/" style="color:var(--accent-bright);text-decoration:none">Ana sayfaya</a> gidin ve <strong>Sign In</strong> sekmesinden giriş yapın</li>
                            <li><i class="fa-solid fa-check"></i>Kullanıcı adı ve şifrenizi kullanın (Discord bot tarafından verilir)</li>
                            <li><i class="fa-solid fa-check"></i>Dashboard'dan <strong>Loader Script</strong>'inizi kopyalayın</li>
                        </ul>

                        <div class="alert alert-success">
                            <i class="fa-solid fa-lightbulb"></i>
                            <span>Web paneli opsiyoneldir. Loader script'inizi doğrudan aşağıdaki adımda da kullanabilirsiniz.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 4: Execute -->
            <div class="step">
                <div class="step-number">4</div>
                <div class="step-card">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-play"></i>
                        <h3>Script'i Çalıştırın</h3>
                        <span class="badge badge-required">Gerekli</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <p>Roblox oyununu açın, executor'ünüzü başlatın ve inject edin. Ardından aşağıdaki script'i executor'ünüzün kod kutusuna yapıştırıp <strong>Execute</strong> butonuna basın:</p>

                        <div class="code-block">
                            <div class="code-block-header">
                                <span class="code-block-lang">Lua</span>
                                <button class="code-copy-btn" onclick="copyCode(this, 'loader-code')">
                                    <i class="fa-solid fa-copy"></i> Kopyala
                                </button>
                            </div>
                            <pre id="loader-code"><span class="keyword">loadstring</span>(<span class="keyword">game</span>:HttpGet(<span class="string">"https://2t1.online/loader"</span>))()(<span class="string">"KEY-BURAYA"</span>)</pre>
                        </div>

                        <div class="alert alert-danger">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span><strong>KEY-BURAYA</strong> kısmını kendi lisans anahtarınızla değiştirin. Dashboard'dan tam loader script'inizi kopyalayabilirsiniz.</span>
                        </div>

                        <p>İlk çalıştırmada HWID'niz otomatik olarak kaydedilir. Sonraki tüm çalıştırmalarda aynı bilgisayardan giriş yapmanız gerekecektir.</p>
                    </div>
                </div>
            </div>

            <!-- Step 5: HWID -->
            <div class="step">
                <div class="step-number">5</div>
                <div class="step-card">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-fingerprint"></i>
                        <h3>HWID Sistemi Hakkında</h3>
                        <span class="badge badge-important">Önemli</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <p>Güvenlik amacıyla her lisans anahtarı tek bir donanım kimliğine (HWID) kilitlenir. Bu, anahtarınızın sadece sizin bilgisayarınızda çalışmasını sağlar.</p>

                        <ul class="check-list">
                            <li><i class="fa-solid fa-check"></i>İlk çalıştırmada HWID otomatik kilitlenir</li>
                            <li><i class="fa-solid fa-check"></i>Farklı bilgisayardan giriş denemesi → Otomatik engelleme</li>
                            <li><i class="fa-solid fa-check"></i>HWID reset talebi → Dashboard veya Discord üzerinden</li>
                            <li><i class="fa-solid fa-xmark"></i>Anahtarı başkasıyla paylaşma → Kalıcı ban</li>
                        </ul>

                        <div class="alert alert-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>Format, PC değişikliği veya donanım güncellemesi yaptıysanız Discord sunucumuzdan HWID reset talebinde bulunun.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 6: Troubleshooting -->
            <div class="step">
                <div class="step-number">6</div>
                <div class="step-card">
                    <div class="step-header" onclick="toggleStep(this)">
                        <i class="icon fa-solid fa-wrench"></i>
                        <h3>Sorun Giderme & SSS</h3>
                        <span class="badge badge-optional">Yardım</span>
                        <i class="chevron fa-solid fa-chevron-right"></i>
                    </div>
                    <div class="step-body">
                        <div class="faq-item">
                            <div class="faq-q" onclick="toggleFaq(this)">
                                <i class="fa-solid fa-chevron-right"></i>
                                Script çalıştırdığımda hiçbir şey olmuyor
                            </div>
                            <div class="faq-a">
                                <p>Executor'ünüzün inject edildiğinden emin olun. <strong>HttpGet</strong> desteği olmayan bazı executor'lerde bu sorun yaşanabilir. Ayrıca key'inizin doğru olduğunu ve süresinin dolmadığını kontrol edin.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-q" onclick="toggleFaq(this)">
                                <i class="fa-solid fa-chevron-right"></i>
                                "HWID Mismatch" hatası alıyorum
                            </div>
                            <div class="faq-a">
                                <p>Anahtarınız farklı bir bilgisayara kilitlenmiş. Dashboard'dan veya Discord sunucumuzdan HWID reset talebi gönderin. Ortalama yanıt süresi 1-2 saattir.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-q" onclick="toggleFaq(this)">
                                <i class="fa-solid fa-chevron-right"></i>
                                "Access denied" hatası alıyorum
                            </div>
                            <div class="faq-a">
                                <p>Script'i tarayıcıdan veya curl/Postman gibi araçlardan çalıştırmaya çalışıyorsunuz. Script'i sadece bir Roblox executor içinden çalıştırın. Ayrıca IP veya HWID banlanmış olabilir — Discord'dan destek alın.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-q" onclick="toggleFaq(this)">
                                <i class="fa-solid fa-chevron-right"></i>
                                Hangi oyunlarda çalışıyor?
                            </div>
                            <div class="faq-a">
                                <p>Epsilon Hub, desteklenen Roblox oyunlarında çalışmaktadır. Desteklenen oyun listesi için Discord sunucumuzdaki <strong>#supported-games</strong> kanalına bakın.</p>
                            </div>
                        </div>
                        <div class="faq-item">
                            <div class="faq-q" onclick="toggleFaq(this)">
                                <i class="fa-solid fa-chevron-right"></i>
                                Web paneline giriş yapamıyorum
                            </div>
                            <div class="faq-a">
                                <p>Web panel giriş bilgileriniz Discord bot tarafından oluşturulur. Şifrenizi unuttuysanız Discord sunucumuzda <strong>/resetpassword</strong> komutunu kullanın.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- .steps -->

        <div class="footer">
            Epsilon Hub &copy; <?php echo date('Y'); ?> &mdash; @Invasor / @Drawat
        </div>
    </div>

    <script>
        function toggleStep(header) {
            const card = header.closest('.step-card');
            const wasOpen = card.classList.contains('open');
            // Close all
            document.querySelectorAll('.step-card').forEach(c => c.classList.remove('open'));
            // Toggle current
            if (!wasOpen) card.classList.add('open');
        }

        function toggleFaq(el) {
            const item = el.closest('.faq-item');
            item.classList.toggle('open');
        }

        function copyCode(btn, id) {
            const code = document.getElementById(id).innerText;
            navigator.clipboard.writeText(code).then(() => {
                btn.classList.add('copied');
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Kopyalandı';
                setTimeout(() => {
                    btn.classList.remove('copied');
                    btn.innerHTML = '<i class="fa-solid fa-copy"></i> Kopyala';
                }, 2000);
            });
        }
    </script>
</body>
</html>
