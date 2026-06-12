<?php
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
    $page_title = "Digital Gatepass";
    require_once __DIR__ . '/includes/header.php';
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

$page_title = "Digital Gatepass";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full md:max-w-[210mm] mx-auto px-4 py-2 min-w-0">
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
    <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6 p-4 sm:p-8 w-full md:w-[210mm] md:min-h-[297mm] mx-auto min-w-0" id="gatepass-card">
        <!-- Ticket Header (Concentrix Design) -->
        <div class="border-2 border-slate-800 p-4 rounded-t-2xl bg-slate-900/30 text-center relative">
            <div class="flex flex-col sm:flex-row items-center justify-between border-b border-slate-800 pb-4 mb-4 gap-4">
                <!-- Brand logo/name -->
                <div class="text-left flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-indigo-600 to-emerald-500 flex items-center justify-center text-white font-bold text-sm">
                        <i class="fa-solid fa-id-card-clip"></i>
                    </div>
                    <span class="text-lg font-black text-white uppercase tracking-tight font-display">concentrix</span>
                </div>
                
                <!-- Center Info -->
                <div class="text-center">
                    <h3 class="text-sm font-extrabold text-slate-300">Concentrix UP-1</h3>
                    <p class="text-[10px] text-slate-500">Ground-4th Floor Building-D UP Technohub Quezon City</p>
                </div>

                <!-- Right serial/date -->
                <div class="text-right text-xs space-y-1">
                    <div><span class="font-mono font-bold text-indigo-400"><?php echo htmlspecialchars($gp['gatepass_no']); ?></span></div>
                    <div><span class="text-slate-500 uppercase tracking-widest font-bold text-[9px]">Date:</span> <span class="font-bold text-slate-300"><?php echo date('M d, Y', strtotime($gp['visit_date'])); ?></span></div>
                </div>
            </div>

            <!-- Ticket Forms Titles -->
            <h1 class="text-xl font-black text-white tracking-widest uppercase mb-1">GATE PASS</h1>
            <p class="text-xs text-slate-400 font-bold tracking-wider underline mb-1">RETURNABLE / NON-RETURNABLE</p>
            <p class="text-xs text-rose-400 font-extrabold tracking-widest uppercase">MATERIAL MOVEMENT</p>
            
            <!-- Absolute badge for status -->
            <div class="absolute top-4 right-4 sm:top-auto sm:bottom-4 sm:right-4 px-3 py-1 rounded-full text-[10px] font-bold border <?php echo $cfg['bg'] . ' ' . $cfg['border'] . ' ' . $cfg['text']; ?> flex items-center space-x-1">
                <i class="fa-solid <?php echo $cfg['icon']; ?>"></i>
                <span><?php echo strtoupper($gp['status']); ?></span>
            </div>
        </div>

        <!-- Particulars form layout -->
        <div class="border-x-2 border-b-2 border-slate-800 p-4 bg-slate-900/10 text-xs space-y-3">
            <div class="flex flex-wrap items-center">
                <span class="text-slate-450 font-extrabold uppercase tracking-wider mr-2">Name</span>
                <span class="flex-grow border-b border-dashed border-slate-700 pb-0.5 text-slate-200 font-bold text-sm tracking-wide px-2">
                    <?php echo htmlspecialchars($gp['visitor_name']); ?>
                </span>
            </div>
            
            <div class="flex flex-wrap items-center">
                <span class="text-slate-450 font-extrabold uppercase tracking-wider mr-2">Program/Department</span>
                <span class="flex-grow border-b border-dashed border-slate-700 pb-0.5 text-slate-200 font-semibold px-2">
                    <?php echo htmlspecialchars($gp['department']); ?>
                </span>
            </div>

            <div class="flex flex-wrap items-center">
                <span class="text-slate-455 font-extrabold uppercase tracking-wider mr-2">EID</span>
                <span class="flex-grow border-b border-dashed border-slate-700 pb-0.5 text-slate-200 font-semibold font-mono px-2">
                    <?php echo htmlspecialchars($gp['eid'] ?: 'N/A'); ?>
                </span>
            </div>

            <div class="flex flex-wrap items-center">
                <span class="text-slate-500 font-extrabold uppercase tracking-wider mr-2">Email</span>
                <span class="flex-grow border-b border-dashed border-slate-800 pb-0.5 text-slate-450 font-semibold px-2">
                    <?php echo htmlspecialchars($gp['visitor_email']); ?>
                </span>
            </div>
        </div>

        <!-- Materials Table -->
        <div class="border-x-2 border-b-2 border-slate-800 overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-900/60 border-b-2 border-slate-800 text-slate-400 font-extrabold uppercase tracking-wider">
                        <th class="p-3 border-r border-slate-800 text-center w-40">S. No.</th>
                        <th class="p-3 border-r border-slate-800">Material Description</th>
                        <th class="p-3 border-r border-slate-800 text-center w-20">Qty.</th>
                        <th class="p-3">Remarks</th>
                    </tr>
                </thead>
                <tbody class="text-slate-300">
                    <tr class="border-b border-slate-800/50 bg-slate-900/10">
                        <td class="p-3 border-r border-slate-800 font-mono text-center text-slate-350"><?php echo htmlspecialchars($gp['material_serial'] ?: 'N/A'); ?></td>
                        <td class="p-3 border-r border-slate-800 font-semibold text-slate-200"><?php echo htmlspecialchars($gp['material_desc'] ?: 'No material items registered'); ?></td>
                        <td class="p-3 border-r border-slate-800 text-center font-bold"><?php echo htmlspecialchars($gp['material_qty'] ?: '-'); ?></td>
                        <td class="p-3 text-slate-400 italic"><?php echo htmlspecialchars($gp['purpose'] ?: '-'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Signatures and Security Release Block (Matching Concentrix Paper Form) -->
        <div class="border-x-2 border-b-2 border-slate-800 bg-slate-900/20 text-[10px] font-bold uppercase tracking-wider p-4 sm:p-6 space-y-8">
            <!-- First Row: Signatures -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 text-center items-start">
                <!-- Requestor Name and Signature (Left) -->
                <div class="flex flex-col items-center">
                    <div class="h-16 flex items-end justify-center relative mb-1">
                        <?php if ($gp['visitor_signature']): ?>
                            <img src="<?php echo $gp['visitor_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="Visitor Signature">
                        <?php else: ?>
                            <span class="text-slate-600 text-[11px] italic font-semibold">No Signature</span>
                        <?php endif; ?>
                    </div>
                    <div class="w-full max-w-[280px] border-t border-slate-700 pt-1">
                        <span class="block text-slate-200 text-[11px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['visitor_name']); ?></span>
                        <span class="text-slate-400 font-bold text-[9px]">Requestor Name and Signature</span>
                    </div>
                </div>

                <!-- Authorized Manager Name and Signature (Right) -->
                <div class="flex flex-col items-center">
                    <div class="h-16 flex items-end justify-center relative mb-1">
                        <?php if (!empty($gp['admin_signature'])): ?>
                            <img src="<?php echo $gp['admin_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="Authorized Manager Signature">
                        <?php else: ?>
                            <span class="text-rose-500 text-[10px] font-extrabold tracking-widest uppercase">Required</span>
                        <?php endif; ?>
                    </div>
                    <div class="w-full max-w-[280px] border-t border-slate-700 pt-1">
                        <span class="block text-slate-200 text-[11px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['manager_name'] ?: '______________________'); ?></span>
                        <span class="text-slate-400 font-bold text-[9px]">Authorized Manager Name and Signature</span>
                    </div>
                </div>
            </div>

            <!-- Second Row: Released By Security & Returnable Header -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-end pt-4">
                <!-- Released By Security (Left) -->
                <div class="flex flex-col items-start min-h-[60px]">
                    <div class="h-16 flex items-end justify-start pl-8 relative mb-1">
                        <?php if (!empty($gp['security_signature'])): ?>
                            <img src="<?php echo $gp['security_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="Security Signature">
                        <?php elseif ($gp['status'] === 'Checked Out'): ?>
                            <div class="px-2 py-0.5 rounded border border-rose-500/40 text-rose-400 font-black text-[9px] tracking-widest uppercase rotate-2">
                                RELEASED
                            </div>
                        <?php elseif ($gp['status'] === 'Checked In'): ?>
                            <div class="px-2 py-0.5 rounded border border-indigo-500/40 text-indigo-400 font-black text-[9px] tracking-widest uppercase rotate-2">
                                INGRESS
                            </div>
                        <?php else: ?>
                            <span class="text-slate-600 text-[9px] italic">Pending</span>
                        <?php endif; ?>
                    </div>
                    <div class="w-full max-w-[250px] border-t border-slate-700 pt-1">
                        <span class="block text-slate-200 text-[10px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['security_name'] ?: '______________________'); ?></span>
                        <span class="text-slate-400 font-bold text-[9px]">Released By (Security)</span>
                    </div>
                </div>

                <!-- Returnable Material Title (Right) -->
                <div class="text-center md:text-right pb-1">
                    <span class="text-xs font-black text-rose-400 tracking-wider underline block">
                        RETURNABLE MATERIAL / INGRESS
                    </span>
                </div>
            </div>

            <!-- Third Row: Date Received & Received By Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-800/40">
                <div class="flex flex-col sm:flex-row sm:items-center gap-1">
                    <span class="text-slate-500 text-[9px] font-bold uppercase tracking-wider">Date Asset/Item received:</span>
                    <span class="flex-grow border-b border-dashed border-slate-800 pb-0.5 text-slate-350 font-semibold">
                        <?php echo $gp['time_in'] ? date('M d, Y', strtotime($gp['visit_date'])) : '____________________'; ?>
                    </span>
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1">
                    <span class="text-slate-500 text-[9px] font-bold uppercase tracking-wider">Signature:</span>
                    <span class="flex-grow border-b border-dashed border-slate-800 pb-0.5 text-slate-350 font-mono text-[9px] tracking-widest text-emerald-450 font-bold">
                        <?php echo ($gp['status'] === 'Checked Out' && !empty($gp['admin_signature'])) ? '✓ VERIFIED' : '____________________'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Instructions Section -->
        <div class="border-x-2 border-b-2 border-slate-800 p-4 rounded-b-2xl bg-slate-950/20 text-[10px] text-slate-500 space-y-4">
            <div>
                <h4 class="font-bold uppercase tracking-wider text-slate-400 mb-1 border-b border-slate-800 pb-1">General Instructions</h4>
                <ul class="list-decimal pl-4 space-y-0.5">
                    <li>This Gate Pass shall be signed in Triplicate.</li>
                    <li>All details as required must be filled.</li>
                    <li>All competent authorities must sign the Gate Pass as requested.</li>
                    <li>All Gate Pass should be stamped and logged in Material Movement Register by Security.</li>
                    <li>Material will be permitted to move out of the premises with proper Gate Pass.</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold uppercase tracking-wider text-slate-400 mb-1 border-b border-slate-800 pb-1">Responsibility of Signatories</h4>
                <ul class="list-decimal pl-4 space-y-0.5">
                    <li><strong>Requestor:</strong> Should ensure accuracy and completeness of the Gate Pass and the items indicated within.</li>
                    <li><strong>Authorized Manager:</strong> (Manager of requestor) Should validate and be accountable of the items being brought in and out of the site.</li>
                    <li><strong>Security:</strong> Inspects and ensures that the gatepass has been fully signed, filled out correctly and items for ingress/egress have been inspected.</li>
                </ul>
            </div>
        </div>

    </div>

    <!-- Print & Navigation Actions -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center mb-10 no-print">
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

    <?php if ($is_new): ?>
    // Asynchronously trigger email sending in the background to avoid user-facing delays or timeouts
    fetch("send_emails_ajax.php?code=<?php echo urlencode($gatepass_no); ?>")
        .then(response => response.text())
        .then(data => console.log("Asynchronous email dispatcher response:", data))
        .catch(error => console.error("Asynchronous email dispatcher failed:", error));
    <?php endif; ?>
});
</script>

<style>
/* Print Stylesheet overrides for ticket download compatibility */
@page {
    size: A4 portrait;
    margin: 10mm;
}
@media print {
    header, footer, nav, button, a, .no-print {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        background-image: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    #gatepass-card {
        border: 2px solid #000000 !important;
        background: white !important;
        box-shadow: none !important;
        color: black !important;
        margin: 0 auto !important;
        padding: 10mm !important;
        width: 190mm !important;
        max-width: 190mm !important;
        min-height: 277mm !important;
        box-sizing: border-box !important;
        backdrop-filter: none !important;
    }
    /* Compact print layouts */
    #gatepass-card .p-6, 
    #gatepass-card .p-8, 
    #gatepass-card .p-4 {
        padding: 10px !important;
    }
    #gatepass-card .space-y-8 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 14px !important;
    }
    #gatepass-card .space-y-4 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 6px !important;
    }
    #gatepass-card .py-6 {
        padding-top: 8px !important;
        padding-bottom: 8px !important;
    }
    /* Solid black borders for table and grid items in print */
    .border-2 {
        border: 2px solid #000000 !important;
    }
    .border-x-2 {
        border-left: 2px solid #000000 !important;
        border-right: 2px solid #000000 !important;
    }
    .border-b-2 {
        border-bottom: 2px solid #000000 !important;
    }
    .border-b {
        border-bottom: 1px solid #000000 !important;
    }
    .border-t {
        border-top: 1px solid #000000 !important;
    }
    .border-r {
        border-right: 1px solid #000000 !important;
    }
    .bg-slate-900\/30, .bg-slate-900\/10, .bg-slate-900\/60, .bg-slate-900\/20, .bg-slate-950\/20 {
        background-color: transparent !important;
    }
    h1, h2, h3, h4, span, p, th, td, li, strong {
        color: #000000 !important;
    }
    .text-slate-550, .text-slate-500, .text-slate-450, .text-slate-400, .text-slate-300, .text-slate-200 {
        color: #000000 !important;
    }
    .text-indigo-400, .text-rose-400, .text-emerald-400 {
        color: #000000 !important;
    }
    /* Invert signature drawings to print black on white */
    img.signature-img {
        filter: invert(1) !important;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
