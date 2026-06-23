<?php
require_once __DIR__ . '/config/database.php';

$error = '';
$search_result = null;
$found_passes = [];
$search_eid = '';

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

// Handle Employee ID search
if (isset($_GET['eid']) && !empty(trim($_GET['eid']))) {
    $search_eid = trim($_GET['eid']);
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE eid = ? ORDER BY visit_date DESC, created_at DESC");
    $stmt->execute([$search_eid]);
    $found_passes = $stmt->fetchAll();
    
    if (empty($found_passes)) {
        $error = "No gatepass found for this Employee ID.";
    } elseif (count($found_passes) === 1) {
        $search_result = $found_passes[0];
        $gatepass_no = $search_result['gatepass_no'];
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

<div class="py-8 sm:py-12 lg:py-20 relative z-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-8 items-center mb-16 lg:mb-20">
        <!-- Left Hero Column -->
        <div class="lg:col-span-7 space-y-6 text-left">
            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-light tracking-tight text-white leading-tight font-display">
                Turning visitor access <br>
                <span class="font-normal text-brand-teal">into operational reality</span>
            </h1>
            <p class="text-sm sm:text-base lg:text-lg text-slate-400 font-medium max-w-xl leading-relaxed">
                Orchestrating the technology, security, and integrations transforming visitor access in modern workplaces. Today.
            </p>
            <div class="pt-2">
                <a href="register.php" class="btn-concentrix-pill">
                    <span>How we register</span>
                    <span class="circle-arrow"><i class="fa-solid fa-arrow-right"></i></span>
                </a>
            </div>
        </div>

        <!-- Right News-Card Column -->
        <div class="lg:col-span-5">
            <div class="glass-card p-5 sm:p-8 rounded-[24px] sm:rounded-[32px] w-full max-w-lg mx-auto">
                <h2 class="text-xs font-semibold tracking-wider text-slate-400 uppercase mb-5">Latest Updates &amp; Actions</h2>
                
                <!-- Retrieve Pass Action -->
                <div class="news-item-link">
                    <div class="news-title">Track &amp; Retrieve Your Gatepass</div>
                    
                    <?php if ($error): ?>
                        <div id="validation-modal" class="custom-modal-overlay show">
                            <div class="custom-modal-card">
                                <div class="custom-modal-icon" style="background: rgba(232, 110, 37, 0.15); border-color: rgba(232, 110, 37, 0.3); color: #e86e25;">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </div>
                                <h3 class="custom-modal-title font-display">Verification Required</h3>
                                <p class="custom-modal-message text-xs"><?php echo htmlspecialchars($error); ?></p>
                                <div class="custom-modal-actions">
                                    <button type="button" class="custom-modal-btn custom-modal-btn-confirm" onclick="document.getElementById('validation-modal').remove()">Okay</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($found_passes)): ?>
                        <div id="found-passes-modal" class="custom-modal-overlay show">
                            <div class="custom-modal-card max-w-md w-full">
                                <div class="custom-modal-icon" style="background: rgba(37, 226, 204, 0.15); border-color: rgba(37, 226, 204, 0.3); color: #25e2cc;">
                                    <i class="fa-solid fa-id-card-clip"></i>
                                </div>
                                <h3 class="custom-modal-title font-display">Multiple Passes Found</h3>
                                <p class="custom-modal-message text-[11px] mb-4 text-slate-400">Multiple gatepasses were found associated with Employee ID <strong><?php echo htmlspecialchars($search_eid); ?></strong>. Please select the pass you want to retrieve:</p>
                                
                                <div class="max-h-48 overflow-y-auto space-y-2 custom-scrollbar pr-1 mb-5 text-left">
                                    <?php foreach ($found_passes as $p): 
                                        $p_no = $p['gatepass_no'];
                                        $p_date = date('M d, Y', strtotime($p['visit_date']));
                                        $p_status = $p['status'];
                                        $is_retrievable = !empty($p['admin_signature']) && !empty($p['manager_name']) && !empty($p['security_signature']) && !empty($p['security_name']);
                                    ?>
                                        <a href="index.php?gatepass_no=<?php echo urlencode($p_no); ?>&search=1" 
                                           class="block p-3 rounded-xl border border-white/05 bg-dark-900/60 hover:bg-dark-700/80 hover:border-brand-teal/40 transition-all group">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <span class="block text-xs font-bold text-white group-hover:text-brand-teal transition-colors font-mono truncate"><?php echo htmlspecialchars($p_no); ?></span>
                                                    <span class="block text-[9px] text-slate-500 mt-0.5 truncate"><?php echo htmlspecialchars($p_date); ?></span>
                                                </div>
                                                <div class="text-right flex-shrink-0">
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-[8px] font-bold border 
                                                        <?php 
                                                            if ($p_status === 'Checked In') echo 'bg-brand-teal/10 border-brand-teal/20 text-brand-teal';
                                                            elseif ($p_status === 'Checked Out') echo 'bg-slate-700/20 border-slate-700/25 text-slate-400';
                                                            elseif ($p_status === 'Approved') echo 'bg-emerald-500/10 border-emerald-500/25 text-emerald-400';
                                                            elseif ($p_status === 'Rejected') echo 'bg-rose-500/10 border-rose-500/25 text-rose-400';
                                                            else echo 'bg-amber-500/10 border-amber-500/25 text-amber-400';
                                                        ?>">
                                                        <?php echo strtoupper($p_status); ?>
                                                    </span>
                                                    <?php if (!$is_retrievable): ?>
                                                        <span class="block text-[7px] text-amber-400 font-medium mt-0.5"><i class="fa-solid fa-lock text-[6px] mr-0.5"></i> Pending</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="custom-modal-actions pt-2 border-t border-white/5">
                                    <button type="button" class="custom-modal-btn custom-modal-btn-confirm w-full" onclick="document.getElementById('found-passes-modal').remove()">Close</button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="space-y-4">
                        <!-- Search by Gatepass No -->
                        <div>
                            <p class="text-slate-400 text-xs mb-2">Already registered? Enter your unique Gatepass number to view and download your pass.</p>
                            <form action="index.php" method="GET" class="flex gap-2">
                                <input type="text" name="gatepass_no" placeholder="e.g. CNX-20260610-0001" required
                                       class="flex-1 min-w-0 px-3 py-2 bg-dark-900/60 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal text-xs sm:text-sm">
                                <button type="submit" name="search" value="1"
                                        class="flex-shrink-0 px-3 sm:px-4 py-2 bg-brand-teal hover:bg-[#1fd4be] text-dark-900 font-bold text-xs rounded-xl flex items-center gap-1 transition-all">
                                    <span>Retrieve</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Separator -->
                        <div class="relative flex py-1 items-center">
                            <div class="flex-grow border-t border-white/5"></div>
                            <span class="flex-shrink mx-3 text-[10px] text-slate-500 uppercase tracking-widest font-bold">Or</span>
                            <div class="flex-grow border-t border-white/5"></div>
                        </div>

                        <!-- Search by Employee ID -->
                        <div>
                            <p class="text-slate-400 text-xs mb-2">Find your digital gatepass using your Employee ID (EID) / ID number.</p>
                            <form action="index.php" method="GET" class="flex gap-2">
                                <input type="text" name="eid" placeholder="e.g. 101917108" required
                                       class="flex-1 min-w-0 px-3 py-2 bg-dark-900/60 border border-white/10 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-brand-teal text-xs sm:text-sm">
                                <button type="submit"
                                        class="flex-shrink-0 px-3 sm:px-4 py-2 bg-brand-teal hover:bg-[#1fd4be] text-dark-900 font-bold text-xs rounded-xl flex items-center gap-1 transition-all">
                                    <span>Find Pass</span>
                                    <i class="fa-solid fa-magnifying-glass text-[10px]"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Entrance QR -->
                <div class="news-item-link">
                    <div class="news-title">Scan Entrance QR Code</div>
                    <p class="text-slate-400 text-xs mb-3">Arrived at our lobby? Point your camera at the code or click below to fill the entry form.</p>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                        <div class="p-2 bg-white rounded-xl shadow-md inline-block flex-shrink-0">
                            <div id="entrance-qrcode"></div>
                        </div>
                        <div class="min-w-0">
                            <a href="register.php" class="click-here">
                                <span>Open Form</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <div class="text-[9px] text-slate-500 mt-1 break-all line-clamp-2"><?php echo htmlspecialchars($register_url); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Checkout QR -->
                <div class="news-item-link">
                    <div class="news-title">Scan Check-Out QR Code</div>
                    <p class="text-slate-400 text-xs mb-3">Leaving the premises? Scan to check out and release your visitor pass.</p>
                    <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                        <div class="p-2 bg-white rounded-xl shadow-md inline-block flex-shrink-0">
                            <div id="checkout-qrcode"></div>
                        </div>
                        <div class="min-w-0">
                            <a href="checkout.php" class="click-here">
                                <span>Check Out Now</span>
                                <i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <div class="text-[9px] text-slate-500 mt-1 break-all line-clamp-2"><?php echo htmlspecialchars($checkout_url); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Steps Flow -->
    <div class="border-t border-white/5 pt-12 sm:pt-16">
        <h3 class="text-center text-lg sm:text-xl font-bold text-white mb-8 sm:mb-12 tracking-tight">How the Gatepass Flow Works</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 sm:gap-8">
            <div class="text-center relative">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-dark-900 border border-white/10 flex items-center justify-center text-brand-teal font-extrabold text-sm sm:text-base mx-auto mb-3 sm:mb-4 relative z-10 shadow-md">1</div>
                <h4 class="text-white font-bold mb-1 sm:mb-2 text-sm sm:text-base">Scan QR</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Visitor scans the lobby QR code with their mobile phone camera.</p>
            </div>
            <div class="text-center relative">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-dark-900 border border-white/10 flex items-center justify-center text-brand-teal font-extrabold text-sm sm:text-base mx-auto mb-3 sm:mb-4 relative z-10 shadow-md">2</div>
                <h4 class="text-white font-bold mb-1 sm:mb-2 text-sm sm:text-base">Fill Form</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Visitor fills out visitor particulars and purpose of visit in their phone browser.</p>
            </div>
            <div class="text-center relative">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-dark-900 border border-white/10 flex items-center justify-center text-brand-teal font-extrabold text-sm sm:text-base mx-auto mb-3 sm:mb-4 relative z-10 shadow-md">3</div>
                <h4 class="text-white font-bold mb-1 sm:mb-2 text-sm sm:text-base">Get Email &amp; Pass</h4>
                <p class="text-slate-400 text-xs max-w-xs mx-auto">Both Visitor and Admin receive email copies, and visitor gets a digital gatepass.</p>
            </div>
            <div class="text-center relative">
                <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-dark-900 border border-white/10 flex items-center justify-center text-brand-teal font-extrabold text-sm sm:text-base mx-auto mb-3 sm:mb-4 relative z-10 shadow-md">4</div>
                <h4 class="text-white font-bold mb-1 sm:mb-2 text-sm sm:text-base">Verify &amp; Entry</h4>
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
