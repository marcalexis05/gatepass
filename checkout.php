<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$gatepass_no = trim($_GET['code'] ?? '');
$error = '';
$success_message = '';
$gp = null;
$trigger_email = false;
$show_validation_modal = false;

// Handle security checkout form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'check_out') {
    $security_signature = $_POST['security_signature'] ?? '';
    $security_name = trim($_POST['security_name'] ?? '');
    
    if (empty($security_signature)) {
        $error = "Security Signature is required to complete check-out.";
    } elseif (empty($security_name)) {
        $error = "Security Guard Name is required.";
    } else {
        // Fetch gatepass first to verify state
        $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
        $stmt->execute([$gatepass_no]);
        $check_gp = $stmt->fetch();
        
        if (!$check_gp) {
            $error = "Gatepass number not found.";
        } elseif (empty($check_gp['admin_signature']) || empty($check_gp['manager_name'])) {
            $error = "This gatepass cannot be checked out because it has not been signed by the IT Incharge yet.";
        } elseif ($check_gp['status'] === 'Checked In') {
            try {
                $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked Out', time_out = CURRENT_TIME(), security_signature = ?, security_name = ?, checked_out_by = 'Security' WHERE gatepass_no = ?");
                $stmt->execute([$security_signature, $security_name, $gatepass_no]);
                $success_message = "Visitor has been successfully CHECKED OUT.";
                $trigger_email = true;
            } catch (Exception $e) {
                $error = "Failed to update checkout log: " . $e->getMessage();
            }
        } else {
            $error = "This gatepass is not in a Checked In state and cannot be checked out.";
        }
    }
}

// Handle manual search form submit
if (isset($_GET['search']) && !empty($_GET['gatepass_no'])) {
    $gatepass_no = trim($_GET['gatepass_no']);
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
    $stmt->execute([$gatepass_no]);
    $check_gp = $stmt->fetch();
    
    if (!$check_gp) {
        $error = "Gatepass number not found. Please double check.";
        $show_validation_modal = true;
        $gatepass_no = '';
    } else {
        // Check if IT Incharge did not sign (admin_signature or manager_name is empty)
        if (empty($check_gp['admin_signature']) || empty($check_gp['manager_name'])) {
            $error = "This gatepass cannot be checked out because it has not been signed and approved by the IT Incharge yet. The IT Incharge must sign and complete the verification.";
            $show_validation_modal = true;
            $gatepass_no = ''; // Remain in search mode to display the validation modal
        } else {
            header("Location: checkout.php?code=" . urlencode($gatepass_no));
            exit;
        }
    }
}

// Load gatepass details if code is provided
$gp_materials = [];
if (!empty($gatepass_no)) {
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
    $stmt->execute([$gatepass_no]);
    $gp = $stmt->fetch();
    
    if (!$gp) {
        $error = "Gatepass number not found. Please double check.";
    } else {
        // Load materials
        $materials_stmt = $pdo->prepare("SELECT * FROM gatepass_materials WHERE gatepass_no = ? ORDER BY id ASC");
        $materials_stmt->execute([$gp['gatepass_no']]);
        $gp_materials = $materials_stmt->fetchAll();
        
        // Legacy fallback
        if (empty($gp_materials) && !empty($gp['material_desc'])) {
            $gp_materials = [[
                'purpose' => $gp['purpose'],
                'material_desc' => $gp['material_desc'],
                'material_brand' => $gp['material_brand'],
                'material_serial' => $gp['material_serial'],
                'material_qty' => $gp['material_qty']
            ]];
        }
    }
}

// Define map configs for status styling (aligned with success.php)
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

<div class="w-full md:max-w-[210mm] mx-auto px-4 py-4 min-w-0">
    <!-- Breadcrumb -->
    <a href="<?php echo is_logged_in() ? 'admin/dashboard.php' : 'index.php'; ?>" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors flex items-center space-x-1.5 mb-6 group no-print">
        <i class="fa-solid fa-chevron-left group-hover:-translate-x-1 transition-transform"></i>
        <span>Back to <?php echo is_logged_in() ? 'Dashboard' : 'Welcome Page'; ?></span>
    </a>

    <?php if ($success_message): ?>
        <!-- Success Alert Notification -->
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm flex items-start shadow-lg no-print">
            <i class="fa-solid fa-circle-check mt-0.5 mr-3 text-lg text-emerald-400"></i>
            <div class="flex-grow">
                <h4 class="font-bold text-white">Checkout Confirmed!</h4>
                <p class="text-xs text-emerald-400/90 mt-0.5">
                    <?php echo htmlspecialchars($success_message); ?> Email logs were sent to the Administrator and your email address.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error && empty($gp) && !$show_validation_modal): ?>
        <!-- Search Error Notification -->
        <div class="mb-6 p-4 rounded-2xl bg-rose-500/10 border border-rose-500/25 text-rose-300 text-sm flex items-center shadow-lg no-print">
            <i class="fa-solid fa-circle-exclamation mr-3 text-lg text-rose-400"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>

    <?php if ($gp): ?>
        <!-- Detail Mode -->
        <?php $cfg = $status_configs[$gp['status']] ?? $status_configs['Pending']; ?>
        
        <!-- Gatepass Ticket Layout -->
        <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6 p-6 sm:p-8 w-full md:w-[210mm] md:min-h-[297mm] mx-auto min-w-0" id="gatepass-card">
            
            <!-- Outer Box wrapping the entire form to replicate physical paper -->
            <div class="border border-slate-700 p-6 rounded-2xl bg-slate-900/10 text-slate-300 font-sans print-border-black" style="min-height: 100%;">
                
                <!-- Header Grid: Logo, Center Titles, Right Serial/Date -->
                <div class="grid grid-cols-12 gap-2 items-start border-b border-slate-855 pb-4 mb-4 print-border-black">
                    <!-- Left: Concentrix Logo -->
                    <div class="col-span-12 sm:col-span-3 flex items-start justify-center sm:justify-start pt-1">
                        <img src="assets/logo-concentrix.png" alt="Concentrix Logo" class="concentrix-logo h-6 sm:h-7 w-auto object-contain">
                    </div>
                    
                    <!-- Center: Titles -->
                    <div class="col-span-12 sm:col-span-6 text-center space-y-1">
                        <h3 class="text-sm font-extrabold text-slate-200">Concentrix UP-1</h3>
                        <p class="text-[10px] text-slate-500 tracking-wider">Ground-4th Floor Building-D UP Technohub Quezon City</p>
                        
                        <h1 class="text-lg font-black text-white tracking-widest uppercase pt-2 underline">GATE PASS</h1>
                        <h2 class="text-[11px] text-slate-400 font-bold tracking-wider underline">RETURNABLE / NON-RETURNABLE</h2>
                        <h2 class="text-[11px] text-rose-400 font-extrabold tracking-widest uppercase underline">MATERIAL MOVEMENT</h2>
                    </div>

                    <!-- Right: ID & Date -->
                    <div class="col-span-12 sm:col-span-3 text-center sm:text-right text-xs pt-2 space-y-2">
                        <div><span class="text-slate-550 uppercase tracking-widest font-bold text-[9px]">GP ID:</span> <span class="font-bold text-slate-300 border-b border-dashed border-slate-700 pb-0.5 px-2"><?php echo htmlspecialchars($gp['gatepass_no']); ?></span></div>
                        <div><span class="text-slate-550 uppercase tracking-widest font-bold text-[9px]">Date:</span> <span class="font-bold text-slate-300 border-b border-dashed border-slate-700 pb-0.5 px-2"><?php echo date('F j, Y', strtotime($gp['visit_date'])); ?></span></div>
                    </div>
                </div>

                <!-- Particulars Section -->
                <div class="space-y-3 pb-6 text-xs">
                    <div class="flex items-end">
                        <span class="text-slate-400 font-extrabold tracking-wider mr-2 whitespace-nowrap">Name</span>
                        <span class="flex-grow border-b border-dotted border-slate-700 pb-0.5 text-slate-200 font-bold text-sm px-2">
                            <?php echo htmlspecialchars($gp['visitor_name']); ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-end">
                            <span class="text-slate-450 font-extrabold tracking-wider mr-2 whitespace-nowrap">Program/Department</span>
                            <span class="flex-grow border-b border-dotted border-slate-700 pb-0.5 text-slate-200 font-semibold px-2">
                                <?php echo htmlspecialchars($gp['department']); ?>
                            </span>
                        </div>
                        <div class="flex items-end">
                            <span class="text-slate-455 font-extrabold tracking-wider mr-2 whitespace-nowrap">EID</span>
                            <span class="flex-grow border-b border-dotted border-slate-700 pb-0.5 text-slate-200 font-semibold font-mono px-2">
                                <?php echo htmlspecialchars($gp['eid'] ?: 'N/A'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-end">
                        <span class="text-slate-500 font-extrabold tracking-wider mr-2 whitespace-nowrap">Email</span>
                        <span class="flex-grow border-b border-dotted border-slate-700 pb-0.5 text-slate-400 font-semibold px-2">
                            <?php echo htmlspecialchars($gp['visitor_email']); ?>
                        </span>
                    </div>
                </div>

                <!-- Materials Table (with vertical borders extending down) -->
                <div class="border border-slate-800 rounded-lg overflow-hidden mb-6 print-border-black">
                    <table class="w-full text-left text-xs border-collapse table-fixed">
                        <thead>
                            <tr class="bg-slate-900/60 text-slate-400 font-extrabold tracking-wider print-border-black">
                                <th class="p-3 border-r border-b border-slate-800 text-center w-28 print-border-black">S. No.</th>
                                <th class="p-3 border-r border-b border-slate-800 print-border-black">Material Description</th>
                                <th class="p-3 border-r border-b border-slate-800 text-center w-28 print-border-black">Brand</th>
                                <th class="p-3 border-r border-b border-slate-800 text-center w-20 print-border-black">Qty.</th>
                                <th class="p-3 border-b border-slate-800">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-300">
                            <?php if (!empty($gp_materials)): ?>
                                <?php foreach ($gp_materials as $index => $mat): ?>
                                    <tr class="bg-slate-900/10">
                                        <td class="p-3 border-r border-b border-slate-800 font-mono text-center text-slate-355 print-border-black align-top">
                                            <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                                <?php echo htmlspecialchars($mat['material_serial'] ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="p-3 border-r border-b border-slate-800 font-semibold text-slate-200 print-border-black align-top">
                                            <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                                <?php echo htmlspecialchars($mat['material_desc'] ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="p-3 border-r border-b border-slate-800 text-center text-slate-200 print-border-black align-top">
                                            <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                                <?php echo htmlspecialchars($mat['material_brand'] ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td class="p-3 border-r border-b border-slate-800 text-center font-bold print-border-black align-top">
                                            <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                                <?php echo htmlspecialchars($mat['material_qty'] ?: '1'); ?>
                                            </div>
                                        </td>
                                        <td class="p-3 border-b border-slate-800/50 text-slate-400 italic align-top">
                                            <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                                <?php echo htmlspecialchars($mat['purpose'] ?: '-'); ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="bg-slate-900/10">
                                    <td colspan="5" class="p-4 border-b border-slate-800/50 text-center text-slate-500 italic">No materials declared</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Signatures and Security Release Block (Matching Concentrix Paper Form Layout) -->
                <div class="text-[10px] font-bold tracking-wider pt-6 space-y-6">
                    <!-- First Row: Signatures side by side -->
                    <div class="grid grid-cols-2 gap-8 text-center">
                        <!-- Requestor Name and Signature (Left) -->
                        <div class="flex flex-col items-center">
                            <div class="h-16 flex items-end justify-center relative mb-1">
                                <?php if ($gp['visitor_signature']): ?>
                                    <img src="<?php echo $gp['visitor_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="Visitor Signature">
                                <?php else: ?>
                                    <span class="text-slate-600 text-[11px] italic font-semibold">No Signature</span>
                                <?php endif; ?>
                            </div>
                            <div class="w-full max-w-[280px] border-t border-dotted border-slate-700 pt-1 print-border-black">
                                <span class="block text-slate-200 text-[11px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['visitor_name']); ?></span>
                                <span class="text-slate-450 font-bold text-[9px]">Requestor Name and Signature</span>
                            </div>
                        </div>

                        <!-- IT Incharge Name and Signature (Right) -->
                        <div class="flex flex-col items-center">
                            <div class="h-16 flex items-end justify-center relative mb-1">
                                <?php if (!empty($gp['admin_signature'])): ?>
                                    <img src="<?php echo $gp['admin_signature']; ?>" class="max-h-16 max-w-full object-contain signature-img" alt="IT Incharge Signature">
                                <?php else: ?>
                                    <span class="text-rose-500 text-[10px] font-extrabold tracking-widest uppercase">Required</span>
                                <?php endif; ?>
                            </div>
                            <div class="w-full max-w-[280px] border-t border-dotted border-slate-700 pt-1 print-border-black">
                                <span class="block text-slate-200 text-[11px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['manager_name'] ?: '______________________'); ?></span>
                                <span class="text-slate-455 font-bold text-[9px]">IT Incharge Name and Signature</span>
                            </div>
                        </div>
                    </div>

                    <!-- Second Row: Released By Security -->
                    <div class="flex justify-center pt-4">
                        <div class="flex flex-col items-center text-center">
                            <div class="h-16 flex items-end justify-center relative mb-1 w-full max-w-[250px]">
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
                            <div class="w-full max-w-[250px] border-t border-dotted border-slate-700 pt-1 print-border-black">
                                <span class="block text-slate-200 text-[10px] font-extrabold tracking-wide mb-0.5"><?php echo htmlspecialchars($gp['security_name'] ?: '______________________'); ?></span>
                                <span class="text-slate-400 font-bold text-[9px]">Released By (Security)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Returnable Material Title (Centered on new line) -->
                    <div class="text-center pt-2 pb-1">
                        <span class="text-xs font-black text-rose-400 tracking-wider underline">
                            RETURNABLE MATERIAL / INGRESS
                        </span>
                    </div>

                    <!-- Third Row: Date Received & Received By Details -->
                    <div class="grid grid-cols-1 gap-4 pt-4">
                        <div class="flex flex-wrap items-end">
                            <span class="text-slate-500 text-[9px] font-bold tracking-wider mr-2">Date Asset/Item received:</span>
                            <span class="flex-grow border-b border-solid border-slate-700 pb-0.5 text-slate-350 font-semibold px-2 font-mono">
                                &nbsp;
                            </span>
                        </div>
                    </div>

                    <!-- Fourth Row: Received By & Signature side by side -->
                    <div class="grid grid-cols-2 gap-8 pt-2">
                        <div class="flex items-end">
                            <span class="text-slate-500 text-[9px] font-bold tracking-wider mr-2 whitespace-nowrap">Received by</span>
                            <span class="flex-grow border-b border-solid border-slate-700 pb-0.5 text-slate-350 font-semibold px-2 text-center">
                                &nbsp;
                            </span>
                        </div>
                        <div class="flex items-end">
                            <span class="text-slate-500 text-[9px] font-bold tracking-wider mr-2 whitespace-nowrap">Signature</span>
                            <span class="flex-grow border-b border-solid border-slate-700 pb-0.5 text-slate-350 font-mono text-[9px] tracking-widest text-emerald-450 font-bold px-2 text-center font-mono">
                                <?php echo '&nbsp;'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Instructions Section -->
                <div class="instructions-section pt-6 text-[10px] text-slate-500 space-y-6 border-t border-slate-800/60 print-border-black">
                    <div class="text-center font-bold tracking-wider text-slate-400 uppercase underline mb-2">General Instructions</div>
                    <div class="text-left space-y-4">
                        <div>
                            <ul class="list-none pl-0 space-y-2">
                                <li>1. This Gate Pass shall be signed in Triplicate</li>
                                <li>2. All details as required must be filled</li>
                                <li>3. All competent authorities must sign the Gate Pass as requested.</li>
                                <li>4. All Gate Pass should be stamped and logged in Material Movement Register by Security.</li>
                                <li>5. Material will be permitted to move out of the premises with proper Gate Pass.</li>
                            </ul>
                        </div>
                        
                        <div>
                            <div class="text-center font-bold tracking-wider text-slate-400 uppercase underline mb-1">Responsibility of Signatories</div>
                            <ul class="list-none pl-0 space-y-2">
                                <li>1. <strong>Requestor</strong> – Should ensure accuracy and completeness of the Gate Pass and the items indicated within.</li>
                                <li>2. <strong>IT Incharge</strong> – Should validate and be accountable of the items being brought in and out of the site.</li>
                                <li>3. <strong>Security</strong> – Inspects and ensures that the gatepass has been fully signed, filled out correctly and items for ingress/egress have been inspected.</li>
                            </ul>
                        </div>

                        <div>
                            <div class="text-center font-bold tracking-wider text-slate-400 uppercase underline mb-1">For Returnable Material:</div>
                            <ul class="list-none pl-0 space-y-2">
                                <li>4. All Material returning should accompany this Gate Pass.</li>
                                <li>5. All Material returning should be logged with Security else will be considered outstanding against your name.</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Checkout Interactive Area -->
        <?php if ($gp['status'] === 'Checked In'): ?>
            <?php if (empty($gp['admin_signature']) || empty($gp['manager_name'])): ?>
                <!-- Checkout Denied Form -->
                <div class="glass-card p-6 sm:p-8 rounded-3xl glow-brand-orange border border-brand-orange/10 mb-6 text-center no-print">
                    <p class="text-rose-500 font-bold mb-2 flex items-center justify-center space-x-2">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Check-Out Denied</span>
                    </p>
                    <p class="text-slate-350 text-xs leading-relaxed max-w-md mx-auto">
                        This gatepass cannot be checked out because it has not been signed and approved by the <strong>IT Incharge</strong> yet. The IT Incharge must sign and complete the verification.
                    </p>
                </div>
            <?php else: ?>
                <!-- Ready for Checkout Form (Security Signoff) -->
                <form action="checkout.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" method="POST" id="admin-checkout-form" class="glass-card p-6 sm:p-8 rounded-3xl glow-brand-teal border border-brand-teal/10 mb-6 text-left no-print space-y-4">
                    <input type="hidden" name="action" value="check_out">
                    
                    <p class="text-emerald-450 font-bold mb-2 flex items-center justify-start space-x-2 font-display">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>Security Check-Out Verification</span>
                    </p>

                    
                    <div class="space-y-4">
                        <!-- Name Input Field -->
                        <div class="space-y-2">
                            <label for="security_name" class="block text-xs font-bold text-slate-355 uppercase tracking-wide font-display">
                                Security Guard Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="security_name" id="security_name" placeholder="Enter Security Name" required
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs">
                        </div>

                        <!-- Signature Canvas Box -->
                        <div class="space-y-2">
                            <label class="block text-xs font-bold text-slate-355 uppercase tracking-wide font-display">
                                Security Signature <span class="text-rose-500">*</span>
                            </label>
                             <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                                  <canvas id="admin-checkout-signature-pad" class="w-full h-32 cursor-crosshair bg-transparent block"></canvas>
                                 <button type="button" id="clear-admin-checkout-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-700 shadow transition-all">
                                     <i class="fa-solid fa-eraser mr-1"></i> Clear
                                 </button>
                            </div>
                            <input type="hidden" id="security_signature" name="security_signature" required>
                        </div>
                    </div>

                    <button type="submit" id="admin-checkout-btn" disabled
                            class="w-full py-2.5 bg-gradient-to-r from-emerald-600 to-emerald-500 hover:from-emerald-500 hover:to-emerald-400 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all shadow-lg shadow-emerald-600/15 opacity-50 cursor-not-allowed pointer-events-none">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span>Verify & Check Out Visitor</span>
                    </button>
                </form>
            <?php endif; ?>
        <?php endif; ?>

        <div class="text-center no-print">
            <a href="checkout.php" class="text-xs font-semibold text-slate-500 hover:text-slate-300 transition-colors">
                <i class="fa-solid fa-arrow-left mr-1"></i> Check Out Another Pass
            </a>
        </div>
        
        <style>
        img.signature-img {
            background: transparent !important;
            filter: invert(1) !important;
        }
        /* ===================================================
           PRINT: Force single A4 page — checkout.php
           =================================================== */
        @page {
            size: A4 portrait;
            margin: 0; /* Remove margin to hide browser headers/footers */
        }
        @media print {
            /* 1. Kill ALL body direct children */
            body > * {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                min-height: unset !important;
                height: auto !important;
                display: block !important;
                overflow: visible !important;
            }
            /* 2. Show <main> wrapper from header.php */
            body > main {
                all: unset !important;
                display: block !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            /* 3. Show the content div inside main */
            body > main > div {
                all: unset !important;
                display: block !important;
                margin: 0 !important;
                padding: 15mm 10mm !important; /* Move margin here to keep content centered */
            }
            /* 4. Show only the card */
            #gatepass-card {
                display: block !important;
                position: relative !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                min-height: unset !important;
                max-height: unset !important;
                margin: 0 !important;
                padding: 4px !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
                color: #000000 !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                backdrop-filter: none !important;
                page-break-inside: avoid !important;
                overflow: visible !important;
            }
            /* 5. Hide everything NOT the card inside the content div */
            body > main > div > *:not(#gatepass-card),
            header, footer, nav,
            .no-print {
                display: none !important;
            }
            /* 6. Inner card wrapper */
            #gatepass-card > div {
                border: 1px solid #000 !important;
                padding: 15px !important;
                box-sizing: border-box !important;
                background: #ffffff !important;
                height: auto !important;
                border-radius: 0 !important;
            }
            /* 7. All elements inside card: white bg, black text */
            #gatepass-card, #gatepass-card div, #gatepass-card table,
            #gatepass-card tr,
            #gatepass-card span, #gatepass-card p, #gatepass-card ul,
            #gatepass-card li, #gatepass-card h1, #gatepass-card h2,
            #gatepass-card h3, #gatepass-card strong {
                background: #ffffff !important;
                background-color: #ffffff !important;
                color: #000000 !important;
                border-color: #000000 !important;
            }
            #gatepass-card th, #gatepass-card td {
                background: transparent !important;
                background-color: transparent !important;
                color: #000000 !important;
                border-color: #000000 !important;
            }
            /* 8. Grid layout fixes */
            #gatepass-card .grid-cols-12 {
                display: grid !important;
                grid-template-columns: repeat(12, minmax(0, 1fr)) !important;
            }
            #gatepass-card .grid {
                display: grid !important;
            }
            #gatepass-card .grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
            }
            #gatepass-card .col-span-12 { grid-column: span 12 !important; }
            #gatepass-card .sm\:col-span-3 { grid-column: span 3 !important; }
            #gatepass-card .sm\:col-span-6 { grid-column: span 6 !important; }
            /* 9. Flex fixes */
            #gatepass-card .flex { display: flex !important; }
            #gatepass-card .flex-grow { flex-grow: 1 !important; }
            #gatepass-card .items-end { align-items: flex-end !important; }
            #gatepass-card .items-center { align-items: center !important; }
            #gatepass-card .flex-col { flex-direction: column !important; }
            #gatepass-card .justify-between { justify-content: space-between !important; }
            /* 10. Logo */
            #gatepass-card img.concentrix-logo {
                height: 26px !important;
                max-height: 26px !important;
                width: auto !important;
                display: block !important;
            }
            /* 11. Typography compression */
            #gatepass-card * {
                font-size: 10px !important;
                line-height: 1.3 !important;
            }
            #gatepass-card h1 {
                font-size: 17px !important;
                font-weight: 900 !important;
                letter-spacing: 0.08em !important;
            }
            #gatepass-card h2 {
                font-size: 11px !important;
                font-weight: 700 !important;
            }
            #gatepass-card h3 {
                font-size: 10px !important;
                font-weight: 800 !important;
            }
            /* 12. Spacing compression */
            #gatepass-card .p-6, #gatepass-card .p-8, #gatepass-card .p-4,
            #gatepass-card .p-5, #gatepass-card .p-3 {
                padding: 8px !important;
            }
            #gatepass-card .space-y-8 > :not([hidden]) ~ :not([hidden]),
            #gatepass-card .space-y-6 > :not([hidden]) ~ :not([hidden]),
            #gatepass-card .space-y-4 > :not([hidden]) ~ :not([hidden]),
            #gatepass-card .space-y-3 > :not([hidden]) ~ :not([hidden]),
            #gatepass-card .space-y-2 > :not([hidden]) ~ :not([hidden]) {
                margin-top: 5px !important;
            }
            #gatepass-card .py-4, #gatepass-card .py-6 {
                padding-top: 5px !important;
                padding-bottom: 5px !important;
            }
            #gatepass-card .mb-6, #gatepass-card .mb-4 { margin-bottom: 8px !important; }
            #gatepass-card .pb-6, #gatepass-card .pb-4 { padding-bottom: 8px !important; }
            #gatepass-card .pt-6, #gatepass-card .pt-4 { padding-top: 8px !important; }
            #gatepass-card .gap-2 { gap: 6px !important; }
            #gatepass-card .gap-4 { gap: 8px !important; }
            #gatepass-card .gap-8 { gap: 12px !important; }
            /* 13. Table & signatures */
            #gatepass-card .h-16 { height: 35px !important; }
            #gatepass-card img.signature-img { max-height: 35px !important; }
            #gatepass-card tbody td {
                height: <?php echo count($gp_materials) === 1 ? '120px' : 'auto'; ?> !important;
                vertical-align: top !important;
            }
            #gatepass-card table {
                border-collapse: collapse !important;
                border-spacing: 0 !important;
                width: 100% !important;
            }
            #gatepass-card td, #gatepass-card th {
                padding: 6px 8px !important;
            }
            #gatepass-card li { margin-bottom: 0 !important; }
            /* 14. Borders */
            .print-border-black { border-color: #000000 !important; }
            .border-dotted { border-style: dotted !important; }
            .border-b { border-bottom: 1px solid #000 !important; }
            .border-t { border-top: 1px solid #000 !important; }
            .border-r { border-right: 1px solid #000 !important; }
            .border { border: 1px solid #000 !important; }
            /* 15. Invert signature drawings during print */
            img.signature-img { filter: none !important; }
            /* 16. Instructions override */
            #gatepass-card .instructions-section * {
                line-height: 1.5 !important;
            }
            #gatepass-card .instructions-section li {
                margin-bottom: 6px !important;
            }
        }
        </style>
    <?php else: ?>
        <!-- Search Mode -->
        <div class="text-center mb-8 no-print">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-orange/10 border border-brand-orange/20 text-brand-orange text-3xl mb-4 shadow-lg shadow-brand-orange/5">
                <i class="fa-solid fa-building-circle-xmark"></i>
            </div>
            <h1 class="text-3xl font-black text-white tracking-tight font-display">Check-Out Portal</h1>
            <p class="text-slate-400 text-sm mt-1">Scan the Exit QR or input your unique code to check out of the building</p>
        </div>

        <div class="glass-card p-6 sm:p-8 rounded-3xl glow-brand-orange border border-brand-orange/10 relative overflow-hidden transition-all duration-300 no-print">
            <div class="absolute top-0 right-0 w-24 h-24 bg-brand-orange/5 rounded-bl-full pointer-events-none"></div>
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 rounded-2xl bg-brand-orange/10 border border-brand-orange/20 flex items-center justify-center text-brand-orange text-xl flex-shrink-0">
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
                            <input type="text" name="gatepass_no" placeholder="e.g. CNX-20260610-0001" required
                                   class="w-full pl-9 pr-4 py-3 bg-dark-900 border border-dark-700/80 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-brand-orange focus:ring-1 focus:ring-brand-orange transition-all text-sm font-mono">
                        </div>
                        <button type="submit" name="search" value="1"
                                class="px-6 py-3 bg-brand-orange hover:bg-[#f67a33] active:scale-95 transition-all text-white font-semibold text-sm rounded-xl shadow-lg shadow-brand-orange/10 flex items-center justify-center space-x-2">
                            <span>Find Pass</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($show_validation_modal && $error): ?>
            <!-- Warning Modal Notification -->
            <div id="validation-modal" class="custom-modal-overlay show">
                <div class="custom-modal-card">
                    <div class="custom-modal-icon" style="background: rgba(232, 110, 37, 0.15); border-color: rgba(232, 110, 37, 0.3); color: #e86e25;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                    </div>
                    <h3 class="custom-modal-title font-display font-bold">Verification Required</h3>
                    <p class="custom-modal-message text-xs mt-2 text-slate-300"><?php echo htmlspecialchars($error); ?></p>
                    <div class="custom-modal-actions mt-6">
                        <button type="button" class="custom-modal-btn custom-modal-btn-confirm" onclick="document.getElementById('validation-modal').remove()">Okay</button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Signature Pad logic for Security Guard checkout
    const checkoutCanvas = document.getElementById('admin-checkout-signature-pad');
    if (checkoutCanvas) {
        const ctx = checkoutCanvas.getContext('2d');
        const clearBtn = document.getElementById('clear-admin-checkout-sig');
        const sigInput = document.getElementById('security_signature');
        const form = document.getElementById('admin-checkout-form');
        let drawing = false;

        let lastWidth = 0;
        let lastHeight = 0;
        function resizeCanvas() {
            const currentWidth = checkoutCanvas.offsetWidth;
            const currentHeight = checkoutCanvas.offsetHeight;
            
            if (currentWidth === lastWidth && currentHeight === lastHeight) {
                return;
            }
            
            let tempCanvas = null;
            if (lastWidth > 0 && lastHeight > 0) {
                tempCanvas = document.createElement('canvas');
                tempCanvas.width = checkoutCanvas.width;
                tempCanvas.height = checkoutCanvas.height;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.drawImage(checkoutCanvas, 0, 0);
            }
            
            checkoutCanvas.width = currentWidth;
            checkoutCanvas.height = currentHeight;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#ffffff';
            
            if (tempCanvas) {
                ctx.drawImage(tempCanvas, 0, 0, currentWidth, currentHeight);
                sigInput.value = window.getInvertedDataURL(checkoutCanvas);
            } else if (sigInput.value) {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, checkoutCanvas.width, checkoutCanvas.height);
                    ctx.drawImage(img, 0, 0, checkoutCanvas.width, checkoutCanvas.height);
                    
                    // Invert dark/black signature to white ink for screen display
                    try {
                        const imgData = ctx.getImageData(0, 0, checkoutCanvas.width, checkoutCanvas.height);
                        const data = imgData.data;
                        let inverted = false;
                        for (let i = 0; i < data.length; i += 4) {
                            if (data[i + 3] > 0) {
                                const isDark = (data[i] + data[i+1] + data[i+2]) / 3 < 128;
                                if (isDark) {
                                    data[i] = 255 - data[i];
                                    data[i+1] = 255 - data[i+1];
                                    data[i+2] = 255 - data[i+2];
                                    inverted = true;
                                }
                            }
                        }
                        if (inverted) {
                            ctx.putImageData(imgData, 0, 0);
                        }
                    } catch (e) {
                        console.error("Error inverting signature loaded into checkout canvas:", e);
                    }
                };
                img.src = sigInput.value;
            }
            
            lastWidth = currentWidth;
            lastHeight = currentHeight;
        }
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        function getPos(e) {
            const rect = checkoutCanvas.getBoundingClientRect();
            const clientX = e.touches && e.touches.length > 0 ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches && e.touches.length > 0 ? e.touches[0].clientY : e.clientY;
            return { x: clientX - rect.left, y: clientY - rect.top };
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
                sigInput.value = window.getInvertedDataURL(checkoutCanvas);
            }
        }

        checkoutCanvas.addEventListener('mousedown', startDrawing);
        checkoutCanvas.addEventListener('mousemove', draw);
        checkoutCanvas.addEventListener('mouseup', stopDrawing);
        checkoutCanvas.addEventListener('mouseleave', stopDrawing);

        checkoutCanvas.addEventListener('touchstart', startDrawing, { passive: false });
        checkoutCanvas.addEventListener('touchmove', draw, { passive: false });
        checkoutCanvas.addEventListener('touchend', stopDrawing);

        const updateBtn = () => {
            const btn = document.getElementById('admin-checkout-btn');
            if (btn) {
                if (sigInput.value) {
                    btn.removeAttribute('disabled');
                    btn.classList.remove('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                } else {
                    btn.setAttribute('disabled', 'true');
                    btn.classList.add('opacity-50', 'cursor-not-allowed', 'pointer-events-none');
                }
            }
        };

        checkoutCanvas.addEventListener('mouseup', updateBtn);
        checkoutCanvas.addEventListener('touchend', updateBtn);
        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, checkoutCanvas.width, checkoutCanvas.height);
            sigInput.value = '';
            updateBtn();
        });
        sigInput.addEventListener('change', () => {
            if (sigInput.value) {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, checkoutCanvas.width, checkoutCanvas.height);
                    ctx.drawImage(img, 0, 0, checkoutCanvas.width, checkoutCanvas.height);
                    
                    // Invert dark/black signature to white ink for screen display
                    try {
                        const imgData = ctx.getImageData(0, 0, checkoutCanvas.width, checkoutCanvas.height);
                        const data = imgData.data;
                        let inverted = false;
                        for (let i = 0; i < data.length; i += 4) {
                            if (data[i + 3] > 0) {
                                const isDark = (data[i] + data[i+1] + data[i+2]) / 3 < 128;
                                if (isDark) {
                                    data[i] = 255 - data[i];
                                    data[i+1] = 255 - data[i+1];
                                    data[i+2] = 255 - data[i+2];
                                    inverted = true;
                                }
                            }
                        }
                        if (inverted) {
                            ctx.putImageData(imgData, 0, 0);
                        }
                    } catch (e) {
                        console.error("Error inverting signature loaded from modal into checkout canvas:", e);
                    }
                };
                img.src = sigInput.value;
            } else {
                ctx.clearRect(0, 0, checkoutCanvas.width, checkoutCanvas.height);
            }
            updateBtn();
        });
        updateBtn();

        form.addEventListener('submit', (e) => {
            const nameInput = document.getElementById('security_name');
            if (!nameInput || !nameInput.value.trim()) {
                e.preventDefault();
                alert("Security Guard Name is required to complete check-out.");
                return;
            }
            if (!sigInput.value) {
                e.preventDefault();
                alert("Signature is required to complete check-out.");
            }
        });
    }

    <?php if ($trigger_email): ?>
    // Asynchronously dispatch checkout confirmation emails in background
    fetch("send_emails_ajax.php?code=<?php echo urlencode($gatepass_no); ?>")
        .then(response => response.text())
        .then(data => console.log("Asynchronous checkout email dispatch response:", data))
        .catch(error => console.error("Asynchronous checkout email dispatch failed:", error));
    <?php endif; ?>
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
