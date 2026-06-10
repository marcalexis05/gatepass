<?php
$page_title = "Digital Gatepass";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$gatepass_no = trim($_GET['code'] ?? '');
$is_new = isset($_GET['new']);

if (empty($gatepass_no)) {
    header("Location: index.php");
    exit;
}

// Fetch gatepass details
$stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
$stmt->execute([$gatepass_no]);
$gp = $stmt->fetch();

if (!$gp) {
    echo "
    <div class='max-w-md mx-auto text-center py-12'>
        <div class='text-rose-500 text-5xl mb-4'><i class='fa-solid fa-circle-exclamation'></i></div>
        <h2 class='text-2xl font-bold text-white mb-2'>Invalid Pass Code</h2>
        <p class='text-slate-400 mb-6'>The gatepass code provided does not exist or has been deleted.</p>
        <a href='index.php' class='px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 rounded-xl text-sm font-semibold transition-all'>Return Home</a>
    </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Map status colors & icons
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/30', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/30', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];

$cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];

// Dynamic URL for Verification scanned by guards (using settings IP)
$server_ip = get_setting('server_ip', 'localhost');
$verify_url = "http://" . $server_ip . "/gatepass/verify.php?code=" . $gp['gatepass_no'];
?>

<div class="max-w-xl mx-auto py-2">
    <?php if ($is_new): ?>
        <!-- Success Alert Notification -->
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm flex items-start alert-dismissible shadow-lg">
            <i class="fa-solid fa-circle-check mt-0.5 mr-3 text-lg text-emerald-400"></i>
            <div class="flex-grow">
                <h4 class="font-bold text-white">Registration Successful!</h4>
                <p class="text-xs text-emerald-400/90 mt-0.5">
                    Your request was recorded. A notification was sent to <strong><?php echo htmlspecialchars($gp['visitor_email']); ?></strong> and the Administrator.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <!-- Gatepass Ticket Layout -->
    <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6" id="gatepass-card">
        <!-- Top Banner Gradient -->
        <div class="h-2 bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-500"></div>

        <!-- Ticket Header -->
        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 animate-pulse"></span>
                <span class="text-xs font-bold text-slate-400 tracking-widest uppercase">Digital Ticket</span>
            </div>
            <!-- Status Badge -->
            <div class="px-3 py-1 rounded-full text-xs font-bold border <?php echo $cfg['bg'] . ' ' . $cfg['border'] . ' ' . $cfg['text']; ?> flex items-center space-x-1.5">
                <i class="fa-solid <?php echo $cfg['icon']; ?>"></i>
                <span><?php echo strtoupper($gp['status']); ?></span>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-8">
            <!-- Ticket Info Panel Grid -->
            <div class="grid grid-cols-2 gap-y-6 gap-x-4">
                <div class="col-span-2">
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Gatepass Number</span>
                    <span class="text-2xl font-black text-white tracking-wider select-all"><?php echo htmlspecialchars($gp['gatepass_no']); ?></span>
                </div>
                
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Visitor Name</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['visitor_name']); ?></span>
                </div>

                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Company / Organization</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['company_org'] ?: 'N/A'); ?></span>
                </div>

                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Contact Number</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['visitor_phone']); ?></span>
                </div>

                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Scheduled Date</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo date('M d, Y', strtotime($gp['visit_date'])); ?></span>
                </div>

                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Host Person</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['host_name']); ?></span>
                </div>

                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Department</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['department']); ?></span>
                </div>

                <div class="col-span-2">
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Purpose of Visit</span>
                    <span class="text-sm font-semibold text-slate-200"><?php echo htmlspecialchars($gp['purpose']); ?></span>
                </div>

                <?php if ($gp['time_in'] || $gp['time_out']): ?>
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Checked In</span>
                        <span class="text-sm font-semibold text-emerald-400"><?php echo $gp['time_in'] ? date('h:i A', strtotime($gp['time_in'])) : '--:--'; ?></span>
                    </div>
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold mb-1">Checked Out</span>
                        <span class="text-sm font-semibold text-slate-400"><?php echo $gp['time_out'] ? date('h:i A', strtotime($gp['time_out'])) : '--:--'; ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Dynamic Verification QR Code (Center) -->
            <div class="flex flex-col items-center justify-center border-t border-dashed border-slate-800/80 pt-8 mt-4">
                <p class="text-xs text-slate-400 font-medium text-center mb-4 max-w-xs">
                    Please present this QR code to the Security Guard at the gate entrance for status verification.
                </p>
                <div class="p-3 bg-white rounded-2xl shadow-lg inline-block">
                    <div id="ticket-qrcode"></div>
                </div>
                <span class="text-[10px] text-slate-500 mt-2 select-all break-all text-center max-w-xs">
                    Verify URL: <?php echo htmlspecialchars($verify_url); ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Print & Navigation Actions -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center mb-10">
        <button onclick="window.print()"
                class="px-6 py-3 bg-slate-800 hover:bg-slate-700 active:scale-95 transition-all text-white font-bold text-sm rounded-xl border border-slate-700/80 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-print"></i>
            <span>Print or Save PDF</span>
        </button>
        <a href="index.php"
           class="px-6 py-3 bg-indigo-600 hover:bg-indigo-500 active:scale-95 transition-all text-white font-bold text-sm rounded-xl text-center shadow-lg shadow-indigo-600/10 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-house"></i>
            <span>Return to Home</span>
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Generate verify QR code for Security Guard scan
    const verifyUrl = "<?php echo $verify_url; ?>";
    new QRCode(document.getElementById("ticket-qrcode"), {
        text: verifyUrl,
        width: 140,
        height: 140,
        colorDark : "#0b0f19",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.M
    });
});
</script>

<style>
/* Print Stylesheet overrides for ticket download compatibility */
@media print {
    header, footer, nav, button, a {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        background-image: none !important;
    }
    #gatepass-card {
        border: 2px solid #ccc !important;
        background: white !important;
        box-shadow: none !important;
        color: black !important;
        margin: 0 auto;
        max-width: 100%;
        backdrop-filter: none !important;
    }
    span.text-slate-500, span.text-slate-400 {
        color: #555555 !important;
    }
    span.text-slate-200, span.text-white {
        color: black !important;
    }
    #ticket-qrcode {
        border: 1px solid #000;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
