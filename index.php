<?php
session_start();
require_once 'config/config.php';

// If user is logged in, redirect to dashboard
if(isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "letters/dashboard/dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo SITE_NAME; ?> | IDCMS</title>


<!-- Icons -->
<link href="<?php echo BASE_URL; ?>assets/css/all.min.css" rel="stylesheet">

<style>
:root {
    --accent: #7551FF;
    --accent-deep: #4318FF;
    --accent-soft: rgba(117, 81, 255, 0.15);
    --accent-glow: rgba(117, 81, 255, 0.4);
    --bg-primary: #0B1437;
    --bg-secondary: #111C44;
    --text-main: #FFFFFF;
    --text-muted: #A3AED0;
    --border: rgba(255, 255, 255, 0.08);
}

* { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
    height: 100%;
    overflow: hidden;
    background-color: var(--bg-primary);
    color: var(--text-main);
    font-family: 'Plus Jakarta Sans', 'Noto Sans Sinhala', 'Segoe UI', system-ui, -apple-system, sans-serif;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    line-height: 1.6;
}

/* ═══════════════════════════════════════
   SPLASH SCREEN (FLASH SCREEN)
   ═══════════════════════════════════════ */
.splash {
    position: fixed;
    inset: 0;
    background: radial-gradient(circle at 50% 50%, #1a2555 0%, #0B1437 50%, #050a1f 100%);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    transition: opacity 0.7s ease, visibility 0.7s ease, transform 0.7s ease;
    cursor: pointer;
}

.splash.hidden {
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: scale(1.05);
}

.splash-logo {
    width: 110px;
    height: 110px;
    border-radius: 28px;
    background: linear-gradient(135deg, #7551FF 0%, #4318FF 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #fff;
    box-shadow:
        0 0 60px var(--accent-glow),
        0 20px 60px rgba(67, 24, 255, 0.5),
        inset 0 2px 0 rgba(255, 255, 255, 0.2);
    animation: logoPulse 2s ease-in-out infinite;
    position: relative;
    margin-bottom: 30px;
}

.splash-logo::before {
    content: '';
    position: absolute;
    inset: -20px;
    border-radius: 40px;
    background: linear-gradient(135deg, #7551FF, #4318FF);
    opacity: 0.3;
    filter: blur(30px);
    z-index: -1;
    animation: logoGlow 2s ease-in-out infinite;
}

@keyframes logoPulse {
    0%, 100% { transform: scale(1) rotate(0deg); }
    50% { transform: scale(1.05) rotate(-3deg); }
}

@keyframes logoGlow {
    0%, 100% { opacity: 0.3; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.15); }
}

.splash-title {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: 8px;
    color: #fff;
    margin-bottom: 8px;
    display: flex;
    gap: 4px;
}

.splash-title span {
    display: inline-block;
    opacity: 0;
    transform: translateY(20px);
    animation: letterReveal 0.5s ease forwards;
}

.splash-title span:nth-child(1) { animation-delay: 0.2s; }
.splash-title span:nth-child(2) { animation-delay: 0.3s; }
.splash-title span:nth-child(3) { animation-delay: 0.4s; }
.splash-title span:nth-child(4) { animation-delay: 0.5s; }
.splash-title span:nth-child(5) { animation-delay: 0.6s; }

@keyframes letterReveal {
    to { opacity: 1; transform: translateY(0); }
}

.splash-subtitle {
    color: var(--text-muted);
    font-size: 0.85rem;
    letter-spacing: 3px;
    text-transform: uppercase;
    font-weight: 600;
    opacity: 0;
    animation: fadeIn 0.6s ease 0.9s forwards;
    margin-bottom: 40px;
}

@keyframes fadeIn {
    to { opacity: 1; }
}

.splash-loader {
    width: 200px;
    height: 4px;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 4px;
    overflow: hidden;
    position: relative;
    opacity: 0;
    animation: fadeIn 0.4s ease 1s forwards;
}

.splash-loader::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    background: linear-gradient(90deg, transparent, var(--accent), #fff, var(--accent), transparent);
    width: 40%;
    animation: loaderMove 1.4s ease-in-out infinite;
    border-radius: 4px;
}

@keyframes loaderMove {
    0%   { transform: translateX(-100%); }
    100% { transform: translateX(400%); }
}

.splash-footer {
    position: absolute;
    bottom: 30px;
    color: var(--text-muted);
    font-size: 0.72rem;
    letter-spacing: 1.5px;
    opacity: 0;
    text-transform: uppercase;
    animation: fadeIn 0.6s ease 1.2s forwards;
}

.splash-skip {
    position: absolute;
    top: 24px;
    right: 24px;
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 700;
    padding: 8px 16px;
    border: 1px solid var(--border);
    border-radius: 20px;
    opacity: 0.6;
    transition: 0.2s;
    background: transparent;
    cursor: pointer;
    font-family: inherit;
    letter-spacing: 0.5px;
}

.splash-skip:hover {
    opacity: 1;
    border-color: var(--accent);
    color: var(--accent);
}

/* ═══════════════════════════════════════
   LANDING PAGE
   ═══════════════════════════════════════ */
.landing {
    position: fixed;
    inset: 0;
    overflow-y: auto;
    background: radial-gradient(ellipse at top, #1a2555 0%, #0B1437 40%, #050a1f 100%);
    display: flex;
    flex-direction: column;
    opacity: 0;
    transform: scale(1.03);
    transition: opacity 0.8s ease, transform 0.8s ease;
}

.landing.visible {
    opacity: 1;
    transform: scale(1);
}

/* Animated background blobs */
.landing::before,
.landing::after {
    content: '';
    position: fixed;
    border-radius: 50%;
    filter: blur(100px);
    pointer-events: none;
    z-index: 0;
}

.landing::before {
    width: 500px;
    height: 500px;
    background: radial-gradient(circle, #7551FF 0%, transparent 70%);
    top: -200px;
    right: -150px;
    opacity: 0.25;
    animation: blobFloat 15s ease-in-out infinite;
}

.landing::after {
    width: 600px;
    height: 600px;
    background: radial-gradient(circle, #4318FF 0%, transparent 70%);
    bottom: -250px;
    left: -200px;
    opacity: 0.2;
    animation: blobFloat 20s ease-in-out infinite reverse;
}

@keyframes blobFloat {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50%      { transform: translate(50px, 30px) scale(1.1); }
}

/* Header */
.landing-header {
    padding: 24px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 2;
}

.brand-logo {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 800;
    font-size: 1.2rem;
    color: #fff;
}

.brand-logo .icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #7551FF, #4318FF);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1rem;
    box-shadow: 0 4px 20px rgba(117, 81, 255, 0.4);
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-header {
    padding: 9px 22px;
    border-radius: 10px;
    background: var(--accent);
    color: #fff;
    text-decoration: none;
    font-weight: 700;
    font-size: 0.85rem;
    transition: 0.2s;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-header:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(117, 81, 255, 0.4);
    color: #fff;
}

/* Main content */
.landing-main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    position: relative;
    z-index: 2;
    text-align: center;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 18px;
    border-radius: 30px;
    background: rgba(117, 81, 255, 0.15);
    border: 1px solid rgba(117, 81, 255, 0.3);
    color: #a78bfa;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 26px;
    animation: fadeUp 0.8s ease 0.2s both;
}

.hero-badge .pulse-dot {
    width: 6px;
    height: 6px;
    background: #34E27B;
    border-radius: 50%;
    box-shadow: 0 0 0 0 rgba(52, 226, 123, 0.7);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%   { box-shadow: 0 0 0 0 rgba(52, 226, 123, 0.7); }
    70%  { box-shadow: 0 0 0 10px rgba(52, 226, 123, 0); }
    100% { box-shadow: 0 0 0 0 rgba(52, 226, 123, 0); }
}

.hero-title {
    font-size: clamp(2rem, 5vw, 3.5rem);
    font-weight: 800;
    line-height: 1.15;
    color: #fff;
    max-width: 900px;
    margin-bottom: 20px;
    letter-spacing: -1px;
    animation: fadeUp 0.8s ease 0.4s both;
}

.hero-title .accent {
    background: linear-gradient(135deg, #a78bfa, #7551FF, #4318FF);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    color: var(--text-muted);
    font-size: clamp(0.95rem, 1.5vw, 1.1rem);
    max-width: 620px;
    margin-bottom: 40px;
    line-height: 1.7;
    animation: fadeUp 0.8s ease 0.6s both;
}

.hero-cta {
    display: flex;
    gap: 14px;
    flex-wrap: wrap;
    justify-content: center;
    margin-bottom: 60px;
    animation: fadeUp 0.8s ease 0.8s both;
}

.btn-cta-primary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 40px;
    background: linear-gradient(135deg, #7551FF, #4318FF);
    color: #fff;
    text-decoration: none;
    border-radius: 14px;
    font-weight: 800;
    font-size: 1rem;
    transition: 0.25s;
    box-shadow: 0 10px 30px rgba(67, 24, 255, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.1);
    position: relative;
    overflow: hidden;
}

.btn-cta-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(67, 24, 255, 0.6);
    color: #fff;
}

.btn-cta-primary i {
    font-size: 0.95rem;
    transition: transform 0.25s;
}

.btn-cta-primary:hover i {
    transform: translateX(4px);
}

.btn-cta-secondary {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 16px 32px;
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-main);
    text-decoration: none;
    border-radius: 14px;
    font-weight: 700;
    font-size: 0.95rem;
    border: 1px solid var(--border);
    transition: 0.25s;
    backdrop-filter: blur(10px);
    cursor: pointer;
}

.btn-cta-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    border-color: var(--accent);
    color: #fff;
    transform: translateY(-3px);
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Features */
.features {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 16px;
    max-width: 900px;
    width: 100%;
    margin: 0 auto;
    animation: fadeUp 0.8s ease 1s both;
}

.feature {
    background: rgba(17, 28, 68, 0.6);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 22px 20px;
    text-align: left;
    backdrop-filter: blur(10px);
    transition: 0.25s;
    position: relative;
    overflow: hidden;
}

.feature::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent), transparent);
    opacity: 0;
    transition: 0.3s;
}

.feature:hover {
    border-color: rgba(117, 81, 255, 0.4);
    transform: translateY(-4px);
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.feature:hover::before {
    opacity: 1;
}

.feature-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(117, 81, 255, 0.15);
    color: #a78bfa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.05rem;
    margin-bottom: 14px;
}

.feature h3 {
    font-size: 0.95rem;
    font-weight: 800;
    color: #fff;
    margin-bottom: 6px;
}

.feature p {
    font-size: 0.82rem;
    color: var(--text-muted);
    line-height: 1.5;
}

/* Footer */
.landing-footer {
    padding: 20px 40px;
    text-align: center;
    color: var(--text-muted);
    font-size: 0.78rem;
    border-top: 1px solid var(--border);
    position: relative;
    z-index: 2;
    opacity: 0;
    animation: fadeIn 0.8s ease 1.2s forwards;
}

.landing-footer strong {
    color: var(--accent);
    font-weight: 800;
}

/* Responsive */
@media (max-width: 768px) {
    .landing-header { padding: 16px 20px; }
    .brand-logo { font-size: 1rem; }
    .brand-logo .icon { width: 36px; height: 36px; font-size: 0.9rem; }
    .landing-main { padding: 30px 16px; }
    .hero-cta { flex-direction: column; width: 100%; max-width: 320px; }
    .btn-cta-primary, .btn-cta-secondary {
        width: 100%;
        justify-content: center;
        padding: 14px 24px;
    }
    .features { grid-template-columns: 1fr 1fr; }
    .splash-logo { width: 90px; height: 90px; font-size: 2.4rem; }
    .splash-title { font-size: 1.6rem; letter-spacing: 5px; }
    .splash-subtitle { font-size: 0.72rem; letter-spacing: 2px; }
    .splash-skip { display: none; }
    .landing-footer { padding: 16px 20px; font-size: 0.72rem; }
}
@media (max-width: 480px) {
    .features { grid-template-columns: 1fr; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════
     SPLASH SCREEN (FLASH)
     ═══════════════════════════════════════ -->
<div class="splash" id="splashScreen">
    <button type="button" class="splash-skip" id="skipBtn">
        Skip <i class="fa-solid fa-forward ms-1"></i>
    </button>

    <div class="splash-logo">
        <i class="fa-solid fa-shield-halved"></i>
    </div>

    <div class="splash-title">
        <span>I</span><span>D</span><span>C</span><span>M</span><span>S</span>
    </div>

    <div class="splash-subtitle">Letter Dispatching System</div>

    <div class="splash-loader"></div>

    <div class="splash-footer">Initialising Secure Environment</div>
</div>

<!-- ═══════════════════════════════════════
     LANDING PAGE
     ═══════════════════════════════════════ -->
<div class="landing" id="landingPage">

    <!-- Header -->
    <header class="landing-header">
        <div class="brand-logo">
            <div class="icon"><i class="fa-solid fa-shield-halved"></i></div>
            <span>IDCMS</span>
        </div>
        <div class="header-actions">
            <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn-header">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In
            </a>
        </div>
    </header>

    <!-- Hero -->
    <main class="landing-main">
        <div class="hero-badge">
            <span class="pulse-dot"></span>
            Secure &bull; Reliable &bull; Enterprise-Grade
        </div>

        <h1 class="hero-title">  
            Integrated<br>
            <span class="accent"> Document & Correspondence </span><br>
            Management System
        </h1>

        <p class="hero-subtitle">
            A comprehensive platform for managing Inward letters, tasking, and outward correspondence with role-based access and full audit trails.
        </p>

        <div class="hero-cta">
            <a href="<?php echo BASE_URL; ?>auth/login.php" class="btn-cta-primary">
                <i class="fa-solid fa-right-to-bracket"></i>
                Access Your Account
            </a>
            <a href="javascript:void(0)" class="btn-cta-secondary" onclick="showInfo()">
                <i class="fa-solid fa-circle-info"></i>
                Learn More
            </a>
        </div>

        <!-- Features -->
        <div class="features">
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-envelope-open-text"></i></div>
                <h3>Inward Letters</h3>
                <p>Complete registry with minute trails and final disposal tracking.</p>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-list-check"></i></div>
                <h3>Task Management</h3>
                <p>Assign tasks to regiments and track all received replies.</p>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-paper-plane"></i></div>
                <h3>Outward Dispatch</h3>
                <p>Prepare, approve, and dispatch with complete audit trail.</p>
            </div>
            <div class="feature">
                <div class="feature-icon"><i class="fa-solid fa-user-shield"></i></div>
                <h3>Role-Based Access</h3>
                <p>Granular permission system for every user and section.</p>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="landing-footer">
        <strong>IDCMS</strong> &middot; <?php echo SITE_NAME; ?> &middot; &copy; <?php echo date('Y'); ?> All Rights Reserved
    </footer>
</div>

<script>
/* ═══════════════════════════════════════
   SPLASH → LANDING TRANSITION
   ═══════════════════════════════════════ */
(function() {
    var splash  = document.getElementById('splashScreen');
    var landing = document.getElementById('landingPage');
    var skipBtn = document.getElementById('skipBtn');
    var hidden  = false;

    function hideSplash() {
        if (hidden) return;
        hidden = true;

        splash.classList.add('hidden');
        landing.classList.add('visible');

        // Re-enable scrolling
        document.body.style.overflow = 'auto';
        document.documentElement.style.overflow = 'auto';

        // Remove splash from DOM after fade
        setTimeout(function() {
            if (splash && splash.parentNode) splash.remove();
        }, 750);
    }

    // Auto hide after 5 seconds
    var autoTimer = setTimeout(hideSplash, 5000);

    // Skip button
    if (skipBtn) {
        skipBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            clearTimeout(autoTimer);
            hideSplash();
        });
    }

    // Skip on click anywhere on splash
    splash.addEventListener('click', function(e) {
        if (e.target === skipBtn) return;
        clearTimeout(autoTimer);
        hideSplash();
    });

    // Preload Font Awesome icons
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function() { /* icons ready */ });
    }
})();

/* Info placeholder */
function showInfo() {
    alert(
        'IDCMS — Intelligent Letter Dispatching Management System\n\n' +
        'A secure enterprise platform for managing internal letters, tasking, and outward correspondence.\n\n' +
        'Please sign in to access the system.'
    );
}
</script>

</body>
</html>