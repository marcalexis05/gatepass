<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$gatepass_no = trim($_GET['code'] ?? '');
$error = '';
$success_message = '';
$gp = null;
$trigger_email = false;

// Handle manual search form submit
if (isset($_GET['search']) && !empty($_GET['gatepass_no'])) {
    $gatepass_no = trim($_GET['gatepass_no']);
    header("Location: checkout.php?code=" . urlencode($gatepass_no));
    exit;
}

// Load gatepass details if code is provided
if (!empty($gatepass_no)) {
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
    $stmt->execute([$gatepass_no]);
    $gp = $stmt->fetch();
    
    if (!$gp) {
        $error = "Gatepass number not found. Please double check.";
    } else {
        // Handle checkout post action
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkout_submit') {
            $admin_signature = $_POST['admin_signature'] ?? '';
            if (empty($admin_signature)) {
                $error = "Authorized Manager's signature is required to complete check-out.";
            } elseif ($gp['status'] === 'Checked In') {
                try {
                    $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked Out', time_out = CURRENT_TIME(), admin_signature = ? WHERE gatepass_no = ?");
                    $stmt->execute([$admin_signature, $gatepass_no]);
                    $success_message = "You have successfully checked out of the building. Thank you!";
                    $trigger_email = true;
                    
                    // Reload gatepass details
                    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
                    $stmt->execute([$gatepass_no]);
                    $gp = $stmt->fetch();
                } catch (Exception $e) {
                    $error = "Failed to update checkout log: " . $e->getMessage();
                }
            } else {
                $error = "This gatepass is not in a Checked In state and cannot be checked out.";
            }
        }
    }
}

// Define map configs for status styling
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/30', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/30', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];

$page_title = "Visitor Check-Out Portal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-xl mx-auto py-4">
    <!-- Breadcrumb -->
    <a href="<?php echo is_logged_in() ? 'admin/dashboard.php' : 'index.php'; ?>" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors flex items-center space-x-1.5 mb-6 group">
        <i class="fa-solid fa-chevron-left group-hover:-translate-x-1 transition-transform"></i>
        <span>Back to <?php echo is_logged_in() ? 'Dashboard' : 'Welcome Page'; ?></span>
    </a>

    <?php if ($success_message): ?>
        <!-- Success Alert Notification -->
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm flex items-start shadow-lg">
            <i class="fa-solid fa-circle-check mt-0.5 mr-3 text-lg text-emerald-400"></i>
            <div class="flex-grow">
                <h4 class="font-bold text-white">Checkout Confirmed!</h4>
                <p class="text-xs text-emerald-400/90 mt-0.5">
                    <?php echo htmlspecialchars($success_message); ?> Email logs were sent to the Administrator and your email address.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error && empty($gp)): ?>
        <!-- Search Error Notification -->
        <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/25 text-rose-300 text-sm flex items-center shadow-lg">
            <i class="fa-solid fa-circle-exclamation mr-3 text-lg text-rose-400"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($gp): ?>
        <!-- Detail Mode -->
        <?php $cfg = $status_configs[$gp['status']] ?? $status_configs['Pending']; ?>
        
        <!-- Concentrix Gate Pass Card -->
        <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6 p-6 sm:p-8" id="gatepass-card">
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
                        <div><span class="text-slate-500 uppercase tracking-widest font-bold text-[9px]">S. No.</span> <span class="font-mono font-bold text-indigo-400"><?php echo htmlspecialchars($gp['gatepass_no']); ?></span></div>
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
                    <span class="text-slate-455 font-extrabold uppercase tracking-wider mr-2">Program/Department</span>
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
            <div class="border-x-2 border-b-2 border-slate-800 bg-slate-900/20 text-[10px] font-bold uppercase tracking-wider p-6 space-y-8">
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
                            <?php if ($gp['admin_signature']): ?>
                                <img src="<?php echo $gp['admin_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="Manager Signature">
                            <?php else: ?>
                                <span class="text-slate-600 text-[11px] italic font-extrabold tracking-widest animate-pulse">PENDING CHECK-OUT</span>
                            <?php endif; ?>
                        </div>
                        <div class="w-full max-w-[280px] border-t border-slate-700 pt-1">
                            <span class="block text-slate-200 text-[11px] font-extrabold tracking-wide mb-0.5"><?php echo $gp['admin_signature'] ? 'System Administrator' : '______________________'; ?></span>
                            <span class="text-slate-400 font-bold text-[9px]">Authorized Manager Name and Signature</span>
                        </div>
                    </div>
                </div>

                <!-- Second Row: Released By Security & Returnable Header -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-end pt-4">
                    <!-- Released By Security (Left) -->
                    <div class="flex flex-col items-start min-h-[60px]">
                        <div class="h-8 flex items-center justify-start pl-8 relative mb-1">
                            <?php if ($gp['status'] === 'Checked Out'): ?>
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
                            <span class="text-slate-400 font-bold text-[9px]">Released By (Security)</span>
                        </div>
                    </div>

                    <!-- Returnable Material Title (Right) -->
                    <div class="text-center md:text-right pb-1">
                        <span class="text-xs font-black text-rose-450 tracking-wider underline block">
                            RETURNABLE MATERIAL / INGRESS
                        </span>
                    </div>
                </div>

                <!-- Third Row: Date Received & Received By Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-800/40">
                    <div class="flex items-center">
                        <span class="text-slate-500 mr-2 text-[9px] font-bold uppercase tracking-wider">Date Asset/Item received:</span>
                        <span class="flex-grow border-b border-dashed border-slate-800 pb-0.5 text-slate-355 font-semibold">
                            <?php echo $gp['time_in'] ? date('M d, Y', strtotime($gp['visit_date'])) : '____________________'; ?>
                        </span>
                    </div>
                    <div class="flex items-center">
                        <span class="text-slate-500 mr-2 text-[9px] font-bold uppercase tracking-wider">Signature:</span>
                        <span class="flex-grow border-b border-dashed border-slate-800 pb-0.5 text-slate-355 font-mono text-[9px] tracking-widest text-emerald-450 font-bold">
                            <?php echo ($gp['time_in'] && !empty($gp['visitor_signature']) && $gp['visitor_signature'] !== 'N/A') ? '✓ VERIFIED' : '____________________'; ?>
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

        <!-- Checkout Interactive Area -->
        <?php if ($gp['status'] === 'Checked In'): ?>
            <!-- Checkout Action Form -->
            <div class="glass-card p-6 sm:p-8 rounded-3xl glow-rose border border-rose-500/10 mb-6">
                <form action="checkout.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" method="POST" id="checkout-form" class="space-y-4">
                    <input type="hidden" name="action" value="checkout_submit">
                    
                    <div class="space-y-2 mb-4 text-left">
                        <label class="block text-xs font-bold text-slate-350 uppercase tracking-wide font-display">Authorized Manager Name & Signature <span class="text-rose-500">*</span></label>
                        <div class="relative bg-dark-950 border border-slate-800 rounded-xl overflow-hidden shadow-inner">
                            <canvas id="admin-signature-pad" class="w-full h-32 cursor-crosshair bg-slate-950 block"></canvas>
                            <button type="button" id="clear-admin-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
                                <i class="fa-solid fa-eraser mr-1"></i> Clear
                            </button>
                        </div>
                        <input type="hidden" id="admin_signature" name="admin_signature">
                    </div>

                    <button type="submit"
                            class="w-full py-3 bg-gradient-to-r from-rose-600 to-rose-500 hover:from-rose-500 hover:to-rose-400 active:scale-[0.99] text-white font-bold text-sm rounded-xl shadow-lg shadow-rose-600/15 flex items-center justify-center space-x-2 transition-all">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Verify & Check Out Now</span>
                    </button>
                </form>
            </div>
        <?php elseif ($gp['status'] === 'Checked Out'): ?>
            <!-- Print Actions for Checked Out Visitors -->
            <div class="flex flex-col sm:flex-row gap-3 justify-center mb-6 no-print">
                <button onclick="window.print()"
                        class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 active:scale-95 transition-all text-white font-bold text-xs rounded-xl border border-slate-700/80 flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-print"></i>
                    <span>Print or Save PDF</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="text-center no-print">
            <a href="checkout.php" class="text-xs font-semibold text-slate-500 hover:text-slate-300 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Check Out Another Pass
            </a>
        </div>
        
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
                width: 100% !important;
                max-width: 100% !important;
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
            .border-b, .border-t {
                border-bottom: 1px solid #000000 !important;
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
            img.signature-img {
                filter: invert(1) !important;
            }
        }
        </style>
    <?php else: ?>
        <!-- Search Mode -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-3xl mb-4 shadow-lg shadow-rose-500/5">
                <i class="fa-solid fa-building-circle-xmark"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tight">Check-Out Portal</h1>
            <p class="text-slate-400 text-sm mt-1">Scan the Exit QR or input your unique code to check out of the building</p>
        </div>

        <div class="glass-card p-6 sm:p-8 rounded-3xl glow-rose border border-rose-500/10 relative overflow-hidden transition-all duration-300">
            <div class="absolute top-0 right-0 w-24 h-24 bg-rose-500/5 rounded-bl-full pointer-events-none"></div>
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-xl flex-shrink-0">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div class="flex-grow">
                    <h3 class="text-xl font-bold text-white mb-2">Track & Retrieve Gatepass for Check-Out</h3>
                    <p class="text-slate-400 text-sm mb-4">
                        Already registered? Enter your Unique Gatepass number below to retrieve your pass details and complete your check-out log.
                    </p>

                    <form action="checkout.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                        <div class="relative flex-grow">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
                                <i class="fa-solid fa-hashtag text-xs"></i>
                            </span>
                            <input type="text" name="gatepass_no" placeholder="e.g. GP-20260610-0001" required
                                   class="w-full pl-9 pr-4 py-3 bg-dark-900 border border-slate-700/80 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-all text-sm">
                        </div>
                        <button type="submit" name="search" value="1"
                                class="px-6 py-3 bg-rose-600 hover:bg-rose-500 active:scale-95 transition-all text-white font-semibold text-sm rounded-xl shadow-lg shadow-rose-600/10 flex items-center justify-center space-x-2">
                            <span>Find Pass</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    <?php if ($trigger_email): ?>
    // Asynchronously dispatch checkout confirmation emails in background
    fetch("send_emails_ajax.php?code=<?php echo urlencode($gatepass_no); ?>")
        .then(response => response.text())
        .then(data => console.log("Asynchronous checkout email dispatch response:", data))
        .catch(error => console.error("Asynchronous checkout email dispatch failed:", error));
    <?php endif; ?>

    // Admin signature pad setup
    const canvas = document.getElementById('admin-signature-pad');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const clearBtn = document.getElementById('clear-admin-sig');
        const sigInput = document.getElementById('admin_signature');
        const form = document.getElementById('checkout-form');
        let drawing = false;

        function resizeCanvas() {
            canvas.width = canvas.offsetWidth;
            canvas.height = canvas.offsetHeight;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#ffffff';
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function startDrawing(e) {
            drawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }

        function stopDrawing() {
            if (drawing) {
                drawing = false;
                sigInput.value = canvas.toDataURL();
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            sigInput.value = '';
        });

        form.addEventListener('submit', (e) => {
            if (!sigInput.value) {
                e.preventDefault();
                alert("Manager/Authorized Signature is required to complete check-out.");
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
