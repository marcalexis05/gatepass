<?php
require_once __DIR__ . '/config/database.php';

$error = '';
$search_result = null;

// Handle gatepass search
if (isset($_GET['search']) && !empty($_GET['gatepass_no'])) {
    $gatepass_no = trim($_GET['gatepass_no']);
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
    $stmt->execute([$gatepass_no]);
    $search_result = $stmt->fetch();
    
    if (!$search_result) {
        $error = "Gatepass number not found. Please double check.";
    } else {
        // Redirect to success page for that gatepass
        header("Location: success.php?code=" . urlencode($gatepass_no));
        exit;
    }
}

// Generate registration & checkout URLs based on configured Server IP
$server_ip = get_setting('server_ip', 'localhost');
$register_url = "http://" . $server_ip . "/gatepass/register.php";
$checkout_url = "http://" . $server_ip . "/gatepass/checkout.php";

$page_title = "Welcome";
require_once __DIR__ . '/includes/header.php';
?>

<div class="py-6 lg:py-12">
    <!-- Hero Header -->
    <div class="text-center max-w-3xl mx-auto mb-16">
        <span class="px-4 py-1.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 uppercase tracking-widest inline-block mb-4">
            Smart Visitor Management
        </span>
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black tracking-tight text-white mb-6 leading-tight">
            Simplify Your Access with <span class="bg-gradient-to-r from-indigo-400 via-purple-400 to-emerald-400 bg-clip-text text-transparent">Contactless Gatepass</span>
        </h1>
        <p class="text-lg text-slate-400 font-medium">
            Scan. Register. Approve. Experience a secure, seamless, and automated visitor entry system powered by instant email confirmations and verification QR codes.
        </p>
    </div>

    <!-- Main Features Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start mb-16">
        <!-- Interactive Cards -->
        <div class="lg:col-span-6 space-y-6">
            <!-- Card 1: Check Status / Quick Search -->
            <div class="glass-card p-6 sm:p-8 rounded-3xl glow-indigo border border-indigo-500/10 relative overflow-hidden transition-all duration-300">
                <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-500/5 rounded-bl-full pointer-events-none"></div>
                <div class="flex items-start space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl flex-shrink-0">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                    </div>
                    <div class="flex-grow">
                        <h3 class="text-xl font-bold text-white mb-2">Track & Retrieve Gatepass</h3>
                        <p class="text-slate-400 text-sm mb-4">
                            Already registered? Enter your Unique Gatepass number below to check the real-time review status, download, or show your pass to security.
                        </p>
                        
                        <?php if ($error): ?>
                            <div class="mb-4 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/25 text-rose-300 text-xs flex items-center">
                                <i class="fa-solid fa-triangle-exclamation mr-2 text-sm"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <form action="index.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                            <div class="relative flex-grow">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-500">
                                    <i class="fa-solid fa-hashtag"></i>
                                </span>
                                <input type="text" name="gatepass_no" placeholder="e.g. GP-20260610-0001" required
                                       class="w-full pl-10 pr-4 py-3 bg-dark-900 border border-slate-700/80 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-sm">
                            </div>
                            <button type="submit" name="search" value="1"
                                    class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 active:scale-95 transition-all text-white font-semibold text-sm rounded-xl shadow-lg shadow-indigo-600/10 flex items-center justify-center space-x-2">
                                <span>Find Pass</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Card 2: Manual Registration Action -->
            <div class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-6 hover:border-indigo-500/20 transition-all duration-300 group">
                <div class="flex items-center space-x-4 text-center sm:text-left">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-xl flex-shrink-0 mx-auto sm:mx-0">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-white mb-1">Fill Entry Form</h3>
                        <p class="text-slate-400 text-sm">
                            Register your visitor pass details manually before your visit.
                        </p>
                    </div>
                </div>
                <a href="register.php" 
                   class="w-full sm:w-auto px-6 py-3 bg-emerald-600 hover:bg-emerald-500 hover:scale-[1.02] text-white font-semibold text-sm rounded-xl text-center shadow-lg shadow-emerald-600/10 transition-all">
                    Register Gatepass
                </a>
            </div>
        </div>

        <!-- Scan/QR Promo Section -->
        <div class="lg:col-span-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Card 3: Scan Entrance QR -->
            <div class="glass-card p-6 rounded-3xl glow-indigo border border-indigo-500/10 text-center flex flex-col items-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-indigo-500/5 via-transparent to-transparent pointer-events-none"></div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-xl mb-4">
                    <i class="fa-solid fa-qrcode"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2 tracking-tight">Scan Entrance QR</h3>
                <p class="text-slate-400 text-xs mb-4 leading-relaxed">
                    Arrived at our main lobby? Point your cell phone camera at this QR code to load the entry form.
                </p>

                <!-- Generated QR Code Display -->
                <div class="p-3 bg-white rounded-2xl shadow-xl inline-block mb-4 relative group overflow-hidden">
                    <div id="entrance-qrcode" class="p-1"></div>
                    <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all duration-300 cursor-pointer rounded-2xl"
                         onclick="window.print()">
                        <span class="text-xs text-white font-bold bg-indigo-600 px-3 py-1.5 rounded-lg shadow">
                            <i class="fa-solid fa-print mr-1"></i> Print
                        </span>
                    </div>
                </div>
                
                <span class="text-[9px] text-indigo-400 font-semibold tracking-wider uppercase bg-indigo-500/10 px-2 py-0.5 rounded-full border border-indigo-500/20 break-all select-all max-w-full">
                    <?php echo htmlspecialchars($register_url); ?>
                </span>
            </div>

            <!-- Card 4: Scan Check-Out QR -->
            <div class="glass-card p-6 rounded-3xl glow-rose border border-rose-500/10 text-center flex flex-col items-center relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-b from-rose-500/5 via-transparent to-transparent pointer-events-none"></div>
                <div class="w-12 h-12 rounded-2xl bg-rose-600/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-xl mb-4">
                    <i class="fa-solid fa-building-circle-xmark"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2 tracking-tight">Scan Check-Out QR</h3>
                <p class="text-slate-400 text-xs mb-4 leading-relaxed">
                    Leaving the premises? Point your camera at this QR code to retrieve your pass and check out.
                </p>

                <!-- Generated QR Code Display -->
                <div class="p-3 bg-white rounded-2xl shadow-xl inline-block mb-4 relative group overflow-hidden">
                    <div id="checkout-qrcode" class="p-1"></div>
                    <div class="absolute inset-0 bg-slate-950/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-all duration-300 cursor-pointer rounded-2xl"
                         onclick="window.print()">
                        <span class="text-xs text-white font-bold bg-rose-600 px-3 py-1.5 rounded-lg shadow">
                            <i class="fa-solid fa-print mr-1"></i> Print
                        </span>
                    </div>
                </div>
                
                <span class="text-[9px] text-rose-400 font-semibold tracking-wider uppercase bg-rose-500/10 px-2 py-0.5 rounded-full border border-rose-500/20 break-all select-all max-w-full">
                    <?php echo htmlspecialchars($checkout_url); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Steps Flow -->
    <div class="border-t border-slate-800/80 pt-12">
        <h3 class="text-center text-xl font-bold text-white mb-10 tracking-tight">How the Gatepass Flow Works</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="text-center relative">
                <div class="w-12 h-12 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-extrabold text-base mx-auto mb-4 relative z-10 shadow-md">1</div>
                <h4 class="text-white font-bold mb-2">Scan QR</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Visitor scans the lobby QR code with their mobile phone camera.</p>
            </div>
            <div class="text-center relative">
                <div class="w-12 h-12 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-extrabold text-base mx-auto mb-4 relative z-10 shadow-md">2</div>
                <h4 class="text-white font-bold mb-2">Fill Form</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Visitor fills out visitor particulars and purpose of visit in their phone browser.</p>
            </div>
            <div class="text-center relative">
                <div class="w-12 h-12 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-extrabold text-base mx-auto mb-4 relative z-10 shadow-md">3</div>
                <h4 class="text-white font-bold mb-2">Get Email & Pass</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Both Visitor and Admin receive email copies, and visitor gets a digital gatepass.</p>
            </div>
            <div class="text-center relative">
                <div class="w-12 h-12 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-extrabold text-base mx-auto mb-4 relative z-10 shadow-md">4</div>
                <h4 class="text-white font-bold mb-2">Verify & Entry</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Security scans the visitor's digital pass at the entrance to verify and approve check-in.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Generate Entrance QR code
    const entranceUrl = "<?php echo $register_url; ?>";
    new QRCode(document.getElementById("entrance-qrcode"), {
        text: entranceUrl,
        width: 130,
        height: 130,
        colorDark : "#0b0f19",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });

    // Generate Check-Out QR code
    const checkoutUrl = "<?php echo $checkout_url; ?>";
    new QRCode(document.getElementById("checkout-qrcode"), {
        text: checkoutUrl,
        width: 130,
        height: 130,
        colorDark : "#0b0f19",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
