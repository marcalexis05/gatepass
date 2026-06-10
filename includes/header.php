<?php
require_once __DIR__ . '/../config/database.php';
$system_name = get_setting('system_name', 'GatePass Pro');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " | " . $system_name : $system_name; ?></title>
    
    <!-- Meta Descriptions for SEO -->
    <meta name="description" content="Secure and modern visitor management gatepass system. Register, verify, and track visitor logs with QR code technology.">
    
    <!-- Tailwind CSS Play CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        dark: {
                            900: '#0b0f19',
                            800: '#0f172a',
                            700: '#1e293b',
                            600: '#334155',
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
    <link rel="stylesheet" href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../assets/css/style.css' : 'assets/css/style.css'; ?>">
    
    <!-- QRCode.js Library CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
</head>
<body class="bg-dark-900 text-slate-100 min-h-screen flex flex-col bg-grid-pattern relative overflow-x-hidden">
    <!-- Background glow elements -->
    <div class="absolute top-[-20%] left-[-10%] w-[50vw] h-[50vw] rounded-full bg-indigo-900/20 blur-[120px] pointer-events-none animate-pulse-slow"></div>
    <div class="absolute bottom-[-10%] right-[-10%] w-[50vw] h-[50vw] rounded-full bg-emerald-900/10 blur-[120px] pointer-events-none animate-pulse-slow"></div>

    <header class="w-full glass-panel border-b border-slate-800/80 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <!-- Branding -->
            <a href="<?php echo (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) ? '../admin/dashboard.php' : 'index.php'; ?>" class="flex items-center space-x-3 group">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-emerald-500 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition-all duration-300">
                    <i class="fa-solid fa-id-card-clip"></i>
                </div>
                <div>
                    <span class="text-xl font-extrabold bg-gradient-to-r from-white via-slate-100 to-indigo-400 bg-clip-text text-transparent tracking-tight"><?php echo $system_name; ?></span>
                    <span class="block text-[10px] text-slate-400 font-medium tracking-widest uppercase">Digital Security</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="flex items-center space-x-4">
                <?php if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false): ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="dashboard.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'text-indigo-400 bg-slate-800/60' : 'text-slate-300 hover:text-white hover:bg-slate-800/40'; ?> transition-all">
                            <i class="fa-solid fa-chart-line mr-1.5"></i> Dashboard
                        </a>
                        <a href="history.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'text-indigo-400 bg-slate-800/60' : 'text-slate-300 hover:text-white hover:bg-slate-800/40'; ?> transition-all">
                            <i class="fa-solid fa-history mr-1.5"></i> History Logs
                        </a>
                        <a href="qr-generator.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'qr-generator.php' ? 'text-indigo-400 bg-slate-800/60' : 'text-slate-300 hover:text-white hover:bg-slate-800/40'; ?> transition-all">
                            <i class="fa-solid fa-qrcode mr-1.5"></i> Entrance QR
                        </a>
                        <a href="settings.php" class="px-3 py-2 rounded-lg text-sm font-medium <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'text-indigo-400 bg-slate-800/60' : 'text-slate-300 hover:text-white hover:bg-slate-800/40'; ?> transition-all">
                            <i class="fa-solid fa-sliders mr-1.5"></i> Settings
                        </a>
                        <a href="logout.php" class="px-3 py-2 rounded-lg text-sm font-medium text-rose-400 hover:text-rose-300 hover:bg-rose-950/20 transition-all border border-rose-900/30">
                            <i class="fa-solid fa-right-from-bracket mr-1.5"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="../index.php" class="text-slate-300 hover:text-white text-sm font-medium">
                            <i class="fa-solid fa-arrow-left mr-1.5"></i> Back to Main
                        </a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="register.php" class="px-4 py-2 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-md shadow-indigo-600/10 hover:shadow-indigo-600/30 hover:scale-[1.02] active:scale-[0.98] transition-all">
                        <i class="fa-solid fa-pen-to-square mr-1.5"></i> Register Pass
                    </a>
                    <a href="admin/login.php" class="px-4 py-2 rounded-xl text-sm font-semibold border border-slate-700 text-slate-300 hover:text-white hover:border-slate-500 transition-all">
                        <i class="fa-solid fa-user-lock mr-1.5"></i> Admin Login
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="flex-grow w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col justify-center">
