<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth.php';
$system_name = get_setting('system_name', 'Concentrix Gatepass');

$in_admin_dir = (stripos(str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), '/admin/') !== false);

$current_page = strtolower(basename($_SERVER['PHP_SELF']));
$current_script = strtolower(basename($_SERVER['SCRIPT_NAME']));
$hide_nav_buttons = (
    $current_page === 'register.php' || $current_script === 'register.php' ||
    $current_page === 'success.php' || $current_script === 'success.php' ||
    $current_page === 'login.php' || $current_script === 'login.php' ||
    $current_page === 'checkout.php' || $current_script === 'checkout.php' ||
    strpos($current_page, 'register') !== false || strpos($current_script, 'register') !== false ||
    strpos($current_page, 'success') !== false || strpos($current_script, 'success') !== false ||
    strpos($current_page, 'login') !== false || strpos($current_script, 'login') !== false ||
    strpos($current_page, 'checkout') !== false || strpos($current_script, 'checkout') !== false
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $system_name . " | " . $page_title : $system_name; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo $in_admin_dir ? '../assets/favicon.png' : 'assets/favicon.png'; ?>?v=<?php echo time(); ?>">
    
    <!-- Meta Descriptions for SEO -->
    <meta name="description" content="Secure and modern visitor management gatepass system. Register, verify, and track visitor logs with QR code technology.">
    
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            blue: '#003D5B',
                            teal: '#25E2CC',
                            orange: '#E86E25',
                            yellow: '#C4D600',
                        },
                        dark: {
                            900: '#01151A',
                            800: '#01222A',
                            700: '#022F3B',
                            600: '#023E4E',
                        }
                    },
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        display: ['Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="<?php echo $in_admin_dir ? '../assets/css/style.css' : 'assets/css/style.css'; ?>?v=<?php echo time(); ?>">
    
    <!-- QRCode.js Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="text-slate-200 min-h-screen flex flex-col relative overflow-x-clip">

    <!-- Top Announcement Banner -->
    <?php if (!is_logged_in() && !$hide_nav_buttons): ?>
    <div class="top-banner">
        Discover how to go from paper to digital gatepass management.
        <a href="<?php echo $in_admin_dir ? '../register.php' : 'register.php'; ?>">Today. <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i></a>
    </div>
    <?php endif; ?>

    <!-- Organic background glow waves -->
    <div class="concentrix-glow-container">
        <div class="concentrix-glow-wave-1"></div>
        <div class="concentrix-glow-wave-2"></div>
        <!-- Vector ribbon wave matching official site -->
        <svg class="absolute w-full h-full opacity-60 pointer-events-none" viewBox="0 0 1440 800" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <g filter="url(#glow-blur)">
                <!-- Main cyan/teal wave -->
                <path d="M-100 650 Q 300 300 700 450 T 1540 300 L 1540 900 L -100 900 Z" fill="url(#teal-grad)" opacity="0.45"/>
                <!-- Secondary accent wave -->
                <path d="M-100 550 Q 400 250 800 500 T 1540 250 L 1540 900 L -100 900 Z" fill="url(#yellow-grad)" opacity="0.25"/>
                <!-- Fine highlighted edge ribbon -->
                <path d="M-100 600 Q 350 280 750 475 T 1540 270" stroke="url(#edge-grad)" stroke-width="8" stroke-linecap="round" opacity="0.6"/>
            </g>
            <defs>
                <filter id="glow-blur" x="-20%" y="-20%" width="140%" height="140%" filterUnits="userSpaceOnUse">
                    <feGaussianBlur stdDeviation="60" result="blur" />
                </filter>
                <linearGradient id="teal-grad" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#003d5b" stop-opacity="0.8"/>
                    <stop offset="50%" stop-color="#25e2cc" stop-opacity="0.5"/>
                    <stop offset="100%" stop-color="#000f13" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="yellow-grad" x1="0%" y1="100%" x2="100%" y2="0%">
                    <stop offset="30%" stop-color="#25e2cc" stop-opacity="0"/>
                    <stop offset="70%" stop-color="#c4d600" stop-opacity="0.35"/>
                    <stop offset="100%" stop-color="#000f13" stop-opacity="0"/>
                </linearGradient>
                <linearGradient id="edge-grad" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#25e2cc" stop-opacity="0.8"/>
                    <stop offset="50%" stop-color="#00f5d4" stop-opacity="0.9"/>
                    <stop offset="80%" stop-color="#c4d600" stop-opacity="0.7"/>
                    <stop offset="100%" stop-color="#000f13" stop-opacity="0"/>
                </linearGradient>
            </defs>
        </svg>
        
        <!-- Particle dots -->
        <div class="absolute inset-0 opacity-40 pointer-events-none mix-blend-screen" style="background-image: radial-gradient(circle at 45% 55%, rgba(37, 226, 204, 0.4) 1px, transparent 1px), radial-gradient(circle at 35% 65%, rgba(255, 255, 255, 0.3) 1.5px, transparent 1.5px), radial-gradient(circle at 60% 45%, rgba(196, 214, 0, 0.3) 1px, transparent 1px); background-size: 80px 80px, 120px 120px, 90px 90px; background-position: 0 0, 40px 60px, 10px 30px;"></div>
    </div>
 
    <header class="w-full glass-panel sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 sm:h-20 flex items-center justify-between">

            <!-- Branding -->
            <?php 
            $asset_prefix = $in_admin_dir ? '../assets/' : 'assets/'; 
            $logo_url = is_logged_in() 
                ? ($in_admin_dir ? 'dashboard.php' : 'admin/dashboard.php') 
                : ($in_admin_dir ? '../index.php' : 'index.php');
            ?>
            <a href="<?php echo $logo_url; ?>" class="flex items-center space-x-3 group py-1 relative z-10 flex-shrink-0">
                <div class="flex items-center justify-center group-hover:scale-[1.02] transition-all duration-300">
                    <img src="<?php echo $asset_prefix; ?>logo.png?v=<?php echo time(); ?>" alt="Concentrix Logo" class="h-6 sm:h-7 w-auto filter drop-shadow-[0_0_8px_rgba(37,226,204,0.15)]">
                </div>
                <div class="border-l border-white/10 pl-3">
                    <span class="block text-[11px] sm:text-xs font-bold text-brand-teal tracking-widest uppercase font-display">GatePass</span>
                    <span class="block text-[8px] sm:text-[9px] text-slate-400 font-medium tracking-wider uppercase">Digital Security</span>
                </div>
            </a>

            <!-- Desktop Navigation -->
            <nav class="hidden md:flex items-center space-x-1 lg:space-x-2 relative z-10">
                <?php if (is_logged_in()): ?>
                    <?php $admin_prefix = $in_admin_dir ? '' : 'admin/'; ?>
                    <a href="<?php echo $admin_prefix; ?>dashboard.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-chart-line mr-1.5 text-xs"></i>Dashboard
                    </a>
                    <a href="<?php echo $admin_prefix; ?>history.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-history mr-1.5 text-xs"></i>History
                    </a>
                    <a href="<?php echo $admin_prefix; ?>qr-generator.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'qr-generator.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-qrcode mr-1.5 text-xs"></i>Entry QR
                    </a>
                    <a href="<?php echo $admin_prefix; ?>checkout-qr.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'checkout-qr.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-qrcode mr-1.5 text-xs"></i>Exit QR
                    </a>
                    <a href="<?php echo $admin_prefix; ?>analytics.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-chart-pie mr-1.5 text-xs"></i>Analytics
                    </a>
                    <a href="<?php echo $admin_prefix; ?>settings.php" class="px-3 py-2 rounded-lg text-xs font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-brand-teal font-semibold' : 'text-slate-300 hover:text-white'; ?> transition-all">
                        <i class="fa-solid fa-sliders mr-1.5 text-xs"></i>Settings
                    </a>
                    <a href="<?php echo $admin_prefix; ?>logout.php" class="ml-2 px-3 py-2 rounded-full text-xs font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-950/20 transition-all border border-rose-900/30">
                        <i class="fa-solid fa-right-from-bracket mr-1"></i>Logout
                    </a>
                <?php else: ?>
                    <?php if ($in_admin_dir): ?>
                        <a href="../index.php" class="text-slate-300 hover:text-white text-sm font-medium">
                            <i class="fa-solid fa-arrow-left mr-1.5"></i>Back to Main
                        </a>
                    <?php elseif ($hide_nav_buttons): ?>
                        <a href="index.php" class="text-slate-300 hover:text-white text-sm font-medium">
                            <i class="fa-solid fa-arrow-left mr-1.5"></i>Back to Main
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="px-4 py-2 rounded-full text-sm font-semibold bg-brand-teal text-[#000f13] hover:bg-[#1fd4be] hover:scale-[1.02] active:scale-[0.98] transition-all duration-300">
                            <i class="fa-solid fa-pen-to-square mr-1.5"></i>Register Pass
                        </a>
                        <a href="admin/login.php" class="hidden md:inline-flex px-4 py-2 rounded-full text-sm font-semibold border border-white/10 text-slate-300 hover:text-white hover:border-brand-teal/30 hover:scale-[1.02] active:scale-[0.98] transition-all duration-300">
                            <i class="fa-solid fa-user-lock mr-1.5"></i>Admin Login
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <!-- Mobile Hamburger Button -->
            <button id="mobile-menu-btn" class="md:hidden flex items-center justify-center w-10 h-10 rounded-xl bg-dark-800/60 border border-white/08 text-slate-300 hover:text-brand-teal hover:border-brand-teal/30 transition-all z-10" aria-label="Toggle menu">
                <i id="mobile-menu-icon" class="fa-solid fa-bars text-sm"></i>
            </button>
        </div>

        <!-- Mobile Navigation Drawer -->
        <div id="mobile-nav" class="md:hidden overflow-hidden transition-all duration-300 ease-in-out" style="max-height:0;">
            <div class="px-4 pb-4 pt-2 border-t border-white/05 space-y-1">
                <?php if (is_logged_in()): ?>
                    <?php $admin_prefix = $in_admin_dir ? '' : 'admin/'; ?>
                    <a href="<?php echo $admin_prefix; ?>dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-chart-line w-4 text-center"></i>Dashboard
                    </a>
                    <a href="<?php echo $admin_prefix; ?>history.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-history w-4 text-center"></i>History Logs
                    </a>
                    <a href="<?php echo $admin_prefix; ?>qr-generator.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'qr-generator.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-qrcode w-4 text-center"></i>Entrance QR
                    </a>
                    <a href="<?php echo $admin_prefix; ?>checkout-qr.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'checkout-qr.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-qrcode w-4 text-center"></i>Exit QR
                    </a>
                    <a href="<?php echo $admin_prefix; ?>analytics.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-chart-pie w-4 text-center"></i>Analytics
                    </a>
                    <a href="<?php echo $admin_prefix; ?>settings.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-brand-teal/10 text-brand-teal border border-brand-teal/20' : 'text-slate-300 hover:text-white hover:bg-white/05'; ?> transition-all">
                        <i class="fa-solid fa-sliders w-4 text-center"></i>Settings
                    </a>
                    <div class="pt-2 border-t border-white/05">
                        <a href="<?php echo $admin_prefix; ?>logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold text-rose-400 hover:text-rose-300 hover:bg-rose-950/20 transition-all border border-rose-900/20">
                            <i class="fa-solid fa-right-from-bracket w-4 text-center"></i>Logout
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($in_admin_dir): ?>
                        <a href="../index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-white/05 transition-all">
                            <i class="fa-solid fa-arrow-left w-4 text-center"></i>Back to Main
                        </a>
                    <?php elseif ($hide_nav_buttons): ?>
                        <a href="index.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-slate-300 hover:text-white hover:bg-white/05 transition-all">
                            <i class="fa-solid fa-arrow-left w-4 text-center"></i>Back to Main
                        </a>
                    <?php else: ?>
                        <a href="register.php" class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold bg-brand-teal text-[#000f13] hover:bg-[#1fd4be] transition-all">
                            <i class="fa-solid fa-pen-to-square"></i>Register Pass
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <script>
    // Mobile menu toggle
    (function() {
        const btn  = document.getElementById('mobile-menu-btn');
        const nav  = document.getElementById('mobile-nav');
        const icon = document.getElementById('mobile-menu-icon');
        if (!btn || !nav) return;

        btn.addEventListener('click', () => {
            const isOpen = nav.style.maxHeight !== '0px' && nav.style.maxHeight !== '';
            if (isOpen) {
                nav.style.maxHeight = '0';
                icon.className = 'fa-solid fa-bars text-sm';
            } else {
                nav.style.maxHeight = nav.scrollHeight + 'px';
                icon.className = 'fa-solid fa-xmark text-sm';
            }
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!btn.contains(e.target) && !nav.contains(e.target)) {
                nav.style.maxHeight = '0';
                icon.className = 'fa-solid fa-bars text-sm';
            }
        });
    })();
    </script>
 
    <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 flex flex-col justify-start relative z-10">
