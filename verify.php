<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$gatepass_no = trim($_GET['code'] ?? '');
$action = trim($_GET['action'] ?? '');
$message = '';
$message_type = 'success';

if (empty($gatepass_no)) {
    header("Location: index.php");
    exit;
}

// Fetch gatepass details
$stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
$stmt->execute([$gatepass_no]);
$gp = $stmt->fetch();

if (!$gp) {
    $page_title = "Verify Visitor Pass";
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

$trigger_email = false;

// Handle administrative actions if user is logged in or accessing via QR scanner
if (true) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = trim($_POST['action'] ?? '');
        if ($post_action === 'check_out') {
            $security_signature = $_POST['security_signature'] ?? '';
            $security_name = trim($_POST['security_name'] ?? '');
            if (empty($security_signature)) {
                $message = "Security Signature is required to complete check-out.";
                $message_type = 'error';
            } elseif (empty($gp['admin_signature'])) {
                $message = "This gatepass cannot be checked out because it has not been signed by the IT Incharge yet.";
                $message_type = 'error';
            } elseif ($gp['status'] === 'Checked In') {
                try {
                    $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked Out', time_out = CURRENT_TIME(), security_signature = ?, security_name = ?, checked_out_by = 'Security' WHERE gatepass_no = ?");
                    $stmt->execute([$security_signature, $security_name, $gatepass_no]);
                    $message = "Visitor has been CHECKED OUT at " . date('h:i A');
                    $message_type = 'success';
                    $trigger_email = true;
                    
                    // Reload gatepass record
                    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
                    $stmt->execute([$gatepass_no]);
                    $gp = $stmt->fetch();
                } catch (Exception $e) {
                    $message = "Failed to update checkout log: " . $e->getMessage();
                    $message_type = 'error';
                }
            } else {
                $message = "This gatepass is not in a Checked In state and cannot be checked out.";
                $message_type = 'error';
            }
        } elseif ($post_action === 'approve') {
            $manager_name = trim($_POST['manager_name'] ?? '');
            $manager_signature = $_POST['manager_signature'] ?? '';
            if (empty($manager_name) || empty($manager_signature)) {
                $message = "IT Incharge Name and Signature are required to approve the request.";
                $message_type = 'error';
            } elseif ($gp['status'] === 'Pending') {
                try {
                    $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Approved', manager_name = ?, admin_signature = ? WHERE gatepass_no = ?");
                    $stmt->execute([$manager_name, $manager_signature, $gatepass_no]);
                    $message = "Gatepass successfully APPROVED and Signed.";
                    $message_type = 'success';
                    
                    // Reload gatepass record
                    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
                    $stmt->execute([$gatepass_no]);
                    $gp = $stmt->fetch();
                } catch (Exception $e) {
                    $message = "Failed to approve gatepass: " . $e->getMessage();
                    $message_type = 'error';
                }
            } else {
                $message = "This gatepass is not in a Pending state.";
                $message_type = 'error';
            }
        } elseif ($post_action === 'sign_manager') {
            $manager_name = trim($_POST['manager_name'] ?? '');
            $manager_signature = $_POST['manager_signature'] ?? '';
            if (empty($manager_name) || empty($manager_signature)) {
                $message = "IT Incharge Name and Signature are required.";
                $message_type = 'error';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE gatepasses SET manager_name = ?, admin_signature = ? WHERE gatepass_no = ?");
                    $stmt->execute([$manager_name, $manager_signature, $gatepass_no]);
                    $message = "IT Incharge signature saved successfully.";
                    $message_type = 'success';
                    
                    // Reload gatepass record
                    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
                    $stmt->execute([$gatepass_no]);
                    $gp = $stmt->fetch();
                } catch (Exception $e) {
                    $message = "Failed to save manager signature: " . $e->getMessage();
                    $message_type = 'error';
                }
            }
        }
    } elseif (!empty($action)) {
        try {
            $allowed_actions = ['reject', 'check_in'];
            if (in_array($action, $allowed_actions)) {
                if ($action === 'reject') {
                    $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Rejected' WHERE gatepass_no = ?");
                    $stmt->execute([$gatepass_no]);
                    $message = "Gatepass successfully REJECTED.";
                    $message_type = 'warning';
                } elseif ($action === 'check_in') {
                    if (empty($gp['admin_signature'])) {
                        $message = "Visitor cannot be checked in because the gatepass has not been signed by the IT Incharge yet.";
                        $message_type = 'error';
                    } else {
                        $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked In', time_in = CURRENT_TIME() WHERE gatepass_no = ?");
                        $stmt->execute([$gatepass_no]);
                        $message = "Visitor has been CHECKED IN at " . date('h:i A');
                        $trigger_email = true;
                    }
                }
                
                // Reload the gatepass record
                $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
                $stmt->execute([$gatepass_no]);
                $gp = $stmt->fetch();
            }
        } catch (Exception $e) {
            $message = "Failed to update gatepass: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Map status configs
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/30', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/30', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];
$cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];

// Load materials
$gp_materials = [];
if ($gp) {
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

$page_title = "Verify Visitor Pass";
require_once __DIR__ . '/includes/header.php';
?>

<div class="w-full md:max-w-[210mm] mx-auto px-4 py-4 min-w-0">
    <!-- Breadcrumb -->
    <a href="<?php echo is_logged_in() ? 'admin/dashboard.php' : 'index.php'; ?>" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors flex items-center space-x-1.5 mb-6 group">
        <i class="fa-solid fa-chevron-left group-hover:-translate-x-1 transition-transform"></i>
        <span>Back to <?php echo is_logged_in() ? 'Dashboard' : 'Welcome Page'; ?></span>
    </a>

    <?php if ($message): ?>
        <!-- Alert Notification -->
        <div class="mb-6 p-4 rounded-2xl alert-dismissible shadow-lg flex items-center border
            <?php 
                if ($message_type === 'success') echo 'bg-emerald-500/10 border-emerald-500/25 text-emerald-300';
                elseif ($message_type === 'warning') echo 'bg-amber-500/10 border-amber-500/25 text-amber-300';
                elseif ($message_type === 'error') echo 'bg-rose-500/10 border-rose-500/25 text-rose-300';
                else echo 'bg-slate-800 border-slate-700 text-slate-300';
            ?>">
            <i class="fa-solid <?php 
                if ($message_type === 'success') echo 'fa-circle-check text-emerald-400';
                elseif ($message_type === 'warning') echo 'fa-circle-exclamation text-amber-400';
                elseif ($message_type === 'error') echo 'fa-circle-xmark text-rose-400';
                else echo 'fa-info-circle text-slate-400';
            ?> mr-3 text-lg"></i>
            <span class="text-sm font-medium"><?php echo htmlspecialchars($message); ?></span>
        </div>
    <?php endif; ?>

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
                                    <td class="p-3 border-r border-slate-800 font-mono text-center text-slate-350 print-border-black align-top">
                                        <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                            <?php echo htmlspecialchars($mat['material_serial'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-slate-800 font-semibold text-slate-200 print-border-black align-top">
                                        <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                            <?php echo htmlspecialchars($mat['material_desc'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-slate-800 text-center text-slate-200 print-border-black align-top">
                                        <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                            <?php echo htmlspecialchars($mat['material_brand'] ?: 'N/A'); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 border-r border-slate-800 text-center font-bold print-border-black align-top">
                                        <div style="min-height: <?php echo count($gp_materials) === 1 ? '150px' : '40px'; ?>;">
                                            <?php echo htmlspecialchars($mat['material_qty'] ?: '1'); ?>
                                        </div>
                                    </td>
                                    <td class="p-3 text-slate-400 italic align-top">
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
                        <span class="text-slate-500 text-[9px] font-bold tracking-wider mr-2">Date Asset received:</span>
                        <span class="flex-grow border-b border-solid border-slate-700 pb-0.5 text-slate-355 font-semibold px-2 font-mono">
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
                        <span class="flex-grow border-b border-solid border-slate-700 pb-0.5 text-slate-355 font-mono text-[9px] tracking-widest text-emerald-450 font-bold px-2 text-center font-mono">
                            <?php echo '&nbsp;'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Instructions Section -->
            <div class="instructions-section pt-6 text-[11.5px] text-slate-500 space-y-6 border-t border-slate-800/60 print-border-black">
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
                            <li>1. <strong>Requestor</strong> – Should ensure accuracy and completeness of the Gate Pass and the assets indicated within.</li>
                            <li>2. <strong>IT Incharge</strong> – Should validate and be accountable of the assets being brought in and out of the site.</li>
                            <li>3. <strong>Security</strong> – Inspects and ensures that the gatepass has been fully signed, filled out correctly and assets for ingress/egress have been inspected.</li>
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

    <!-- Print Actions for Admins -->
    <div class="flex flex-col sm:flex-row gap-3 justify-center mt-6 mb-6 no-print">
        <a href="download_pdf.php?code=<?php echo urlencode($gatepass_no); ?>" target="_blank"
           class="px-6 py-2.5 bg-slate-800 hover:bg-slate-700 active:scale-95 transition-all text-white font-bold text-sm rounded-xl border border-slate-700/80 flex items-center justify-center space-x-2">
            <i class="fa-solid fa-print"></i>
            <span>Print or Save PDF</span>
        </a>
    </div>

        <!-- Administration Control Panel -->
        <?php if ($gp['status'] !== 'Checked Out'): ?>
        <div class="pt-6 border-t border-slate-800/80">
            <?php if (true): // Allow actions directly via QR verification link ?>
                <span class="block text-[10px] text-indigo-400 font-extrabold uppercase tracking-widest mb-4 text-center">Admin Controls</span>
                
                <div class="grid grid-cols-2 gap-3">
                    <!-- Pending / Approve / Reject controls -->
                    <?php if ($gp['status'] === 'Pending'): ?>
                        <div class="col-span-2 space-y-4">
                            <!-- Reject action -->
                            <div class="flex justify-end">
                                <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=reject"
                                   class="px-4 py-2 bg-rose-950/20 hover:bg-rose-900/30 text-rose-400 border border-rose-900/30 text-xs font-bold rounded-xl transition-all flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-xmark"></i>
                                    <span>Reject Request</span>
                                </a>
                            </div>

                            <!-- Approval Form with Signature -->
                            <form action="verify.php?code=<?php echo urlencode($gatepass_no); ?>" method="POST" id="admin-approve-form" class="space-y-4 text-left border border-slate-800/80 p-5 rounded-2xl bg-slate-900/10">
                                <input type="hidden" name="action" value="approve">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-2 border-b border-slate-800/60 pb-1.5 font-display">IT Incharge Approval</h4>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <label for="manager_name" class="block text-xs font-bold text-slate-350 uppercase tracking-wide">Manager Full Name <span class="text-rose-500">*</span></label>
                                        <input type="hidden" name="manager_name" id="manager_name">
                                        <select id="manager_name_select" required
                                                class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs cursor-pointer">
                                            <option value="" disabled selected class="bg-dark-900 text-white">Select</option>
                                            <option value="Aero Yabes" class="bg-dark-900 text-white">Aero Yabes</option>
                                            <option value="Bernie Jabon" class="bg-dark-900 text-white">Bernie Jabon</option>
                                            <option value="Carlos Guinto" class="bg-dark-900 text-white">Carlos Guinto</option>
                                            <option value="Dominic Carreon" class="bg-dark-900 text-white">Dominic Carreon</option>
                                            <option value="Feliz Lauta" class="bg-dark-900 text-white">Feliz Lauta</option>
                                            <option value="Ian Ocampo" class="bg-dark-900 text-white">Ian Ocampo</option>
                                            <option value="Mark Relano" class="bg-dark-900 text-white">Mark Relano</option>
                                            <option value="Paul Michael Aguas" class="bg-dark-900 text-white">Paul Michael Aguas</option>
                                            <option value="Prince Arvy Padilla" class="bg-dark-900 text-white">Prince Arvy Padilla</option>
                                            <option value="Richard Cheing" class="bg-dark-900 text-white">Richard Cheing</option>
                                            <option value="Ronald Omega" class="bg-dark-900 text-white">Ronald Omega</option>
                                            <option value="Sophia Abes" class="bg-dark-900 text-white">Sophia Abes</option>
                                            <option value="Other" class="bg-dark-900 text-white">Other (Please specify)</option>
                                        </select>
                                        <div id="custom_manager_name_container" class="hidden mt-2">
                                            <input type="text" id="manager_name_custom" placeholder="Enter IT Incharge Name"
                                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs">
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-355 tracking-wide uppercase">Manager Signature <span class="text-rose-500">*</span></label>
                                        <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                                             <canvas id="admin-approve-signature-pad" class="w-full h-32 cursor-crosshair bg-transparent block"></canvas>
                                             <button type="button" id="clear-admin-approve-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
                                                 <i class="fa-solid fa-eraser mr-1"></i> Clear
                                             </button>
                                        </div>
                                        <input type="hidden" id="manager_signature" name="manager_signature" required>
                                    </div>
                                </div>

                                <button type="submit" id="admin-approve-btn" disabled
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all opacity-50 cursor-not-allowed pointer-events-none">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span>Approve & Sign Request</span>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Check In Control -->
                    <?php if ($gp['status'] === 'Approved'): ?>
                        <?php if (empty($gp['admin_signature'])): ?>
                            <form action="verify.php?code=<?php echo urlencode($gatepass_no); ?>" method="POST" id="admin-approve-form" class="col-span-2 space-y-4 text-left border border-slate-800/80 p-5 rounded-2xl bg-slate-900/10">
                                <input type="hidden" name="action" value="sign_manager">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-2 border-b border-slate-800/60 pb-1.5 font-display">IT Incharge Signature Required</h4>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <label for="manager_name" class="block text-xs font-bold text-slate-355 uppercase tracking-wide">Manager Full Name <span class="text-rose-500">*</span></label>
                                        <input type="hidden" name="manager_name" id="manager_name">
                                        <select id="manager_name_select" required
                                                class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs cursor-pointer">
                                            <option value="" disabled selected class="bg-dark-900 text-white">Select</option>
                                            <option value="Aero Yabes" class="bg-dark-900 text-white">Aero Yabes</option>
                                            <option value="Bernie Jabon" class="bg-dark-900 text-white">Bernie Jabon</option>
                                            <option value="Carlos Guinto" class="bg-dark-900 text-white">Carlos Guinto</option>
                                            <option value="Dominic Carreon" class="bg-dark-900 text-white">Dominic Carreon</option>
                                            <option value="Feliz Lauta" class="bg-dark-900 text-white">Feliz Lauta</option>
                                            <option value="Ian Ocampo" class="bg-dark-900 text-white">Ian Ocampo</option>
                                            <option value="Mark Relano" class="bg-dark-900 text-white">Mark Relano</option>
                                            <option value="Paul Michael Aguas" class="bg-dark-900 text-white">Paul Michael Aguas</option>
                                            <option value="Prince Arvy Padilla" class="bg-dark-900 text-white">Prince Arvy Padilla</option>
                                            <option value="Richard Cheing" class="bg-dark-900 text-white">Richard Cheing</option>
                                            <option value="Ronald Omega" class="bg-dark-900 text-white">Ronald Omega</option>
                                            <option value="Sophia Abes" class="bg-dark-900 text-white">Sophia Abes</option>
                                            <option value="Other" class="bg-dark-900 text-white">Other (Please specify)</option>
                                        </select>
                                        <div id="custom_manager_name_container" class="hidden mt-2">
                                            <input type="text" id="manager_name_custom" placeholder="Enter IT Incharge Name"
                                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs">
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-355 tracking-wide uppercase">Manager Signature <span class="text-rose-500">*</span></label>
                                        <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                                             <canvas id="admin-approve-signature-pad" class="w-full h-32 cursor-crosshair bg-transparent block"></canvas>
                                             <button type="button" id="clear-admin-approve-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
                                                 <i class="fa-solid fa-eraser mr-1"></i> Clear
                                             </button>
                                        </div>
                                        <input type="hidden" id="manager_signature" name="manager_signature" required>
                                    </div>
                                </div>

                                <button type="submit" id="admin-approve-btn" disabled
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all opacity-50 cursor-not-allowed pointer-events-none">
                                    <i class="fa-solid fa-signature"></i>
                                    <span>Sign Gatepass</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=check_in"
                               class="col-span-2 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span>Check In Visitor</span>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Check Out Form with Signature Pad -->
                    <?php if ($gp['status'] === 'Checked In'): ?>
                        <?php if (empty($gp['admin_signature'])): ?>
                            <form action="verify.php?code=<?php echo urlencode($gatepass_no); ?>" method="POST" id="admin-approve-form" class="col-span-2 space-y-4 text-left border border-slate-800/80 p-5 rounded-2xl bg-slate-900/10">
                                <input type="hidden" name="action" value="sign_manager">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-2 border-b border-slate-800/60 pb-1.5 font-display">IT Incharge Signature Required</h4>
                                
                                <div class="space-y-3">
                                    <div class="space-y-1.5">
                                        <label for="manager_name" class="block text-xs font-bold text-slate-355 uppercase tracking-wide">Manager Full Name <span class="text-rose-500">*</span></label>
                                        <input type="hidden" name="manager_name" id="manager_name">
                                        <select id="manager_name_select" required
                                                class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs cursor-pointer">
                                            <option value="" disabled selected class="bg-dark-900 text-white">Select</option>
                                            <option value="Aero Yabes" class="bg-dark-900 text-white">Aero Yabes</option>
                                            <option value="Bernie Jabon" class="bg-dark-900 text-white">Bernie Jabon</option>
                                            <option value="Carlos Guinto" class="bg-dark-900 text-white">Carlos Guinto</option>
                                            <option value="Dominic Carreon" class="bg-dark-900 text-white">Dominic Carreon</option>
                                            <option value="Feliz Lauta" class="bg-dark-900 text-white">Feliz Lauta</option>
                                            <option value="Ian Ocampo" class="bg-dark-900 text-white">Ian Ocampo</option>
                                            <option value="Mark Relano" class="bg-dark-900 text-white">Mark Relano</option>
                                            <option value="Paul Michael Aguas" class="bg-dark-900 text-white">Paul Michael Aguas</option>
                                            <option value="Prince Arvy Padilla" class="bg-dark-900 text-white">Prince Arvy Padilla</option>
                                            <option value="Richard Cheing" class="bg-dark-900 text-white">Richard Cheing</option>
                                            <option value="Ronald Omega" class="bg-dark-900 text-white">Ronald Omega</option>
                                            <option value="Sophia Abes" class="bg-dark-900 text-white">Sophia Abes</option>
                                            <option value="Other" class="bg-dark-900 text-white">Other (Please specify)</option>
                                        </select>
                                        <div id="custom_manager_name_container" class="hidden mt-2">
                                            <input type="text" id="manager_name_custom" placeholder="Enter IT Incharge Name"
                                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition-all text-xs">
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-xs font-bold text-slate-355 uppercase tracking-wide">Manager Signature <span class="text-rose-500">*</span></label>
                                        <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                                             <canvas id="admin-approve-signature-pad" class="w-full h-32 cursor-crosshair bg-transparent block"></canvas>
                                             <button type="button" id="clear-admin-approve-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
                                                 <i class="fa-solid fa-eraser mr-1"></i> Clear
                                             </button>
                                        </div>
                                        <input type="hidden" id="manager_signature" name="manager_signature" required>
                                    </div>
                                </div>

                                <button type="submit" id="admin-approve-btn" disabled
                                        class="w-full py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all opacity-50 cursor-not-allowed pointer-events-none">
                                    <i class="fa-solid fa-signature"></i>
                                    <span>Sign Gatepass</span>
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="verify.php?code=<?php echo urlencode($gatepass_no); ?>" method="POST" id="admin-checkout-form" class="col-span-2 space-y-4 text-left">
                                <input type="hidden" name="action" value="check_out">
                                
                                <div class="space-y-4">
                                    <!-- Name Input Field -->
                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-355 uppercase tracking-wide font-display">
                                            Security Guard Name <span class="text-rose-500 md:inline hidden">*</span>
                                        </label>
                                        <input type="hidden" name="security_name" id="security_name">
                                        <select id="security_name_select" required
                                                class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-all text-xs cursor-pointer">
                                            <option value="" disabled selected class="bg-dark-900 text-white">Select</option>
                                            <option value="Aisara, Nadzmil" class="bg-dark-900 text-white">Aisara, Nadzmil</option>
                                            <option value="Dela Cruz, Jobelson" class="bg-dark-900 text-white">Dela Cruz, Jobelson</option>
                                            <option value="Ilao, Andrew" class="bg-dark-900 text-white">Ilao, Andrew</option>
                                            <option value="Mallo, Mark Anthony" class="bg-dark-900 text-white">Mallo, Mark Anthony</option>
                                            <option value="Mejia, Raymart" class="bg-dark-900 text-white">Mejia, Raymart</option>
                                            <option value="Ong, Jeffry" class="bg-dark-900 text-white">Ong, Jeffry</option>
                                            <option value="Rico, Francisco" class="bg-dark-900 text-white">Rico, Francisco</option>
                                            <option value="Santos, Jayson" class="bg-dark-900 text-white">Santos, Jayson</option>
                                            <option value="Tarrago, Niel Bryan" class="bg-dark-900 text-white">Tarrago, Niel Bryan</option>
                                            <option value="Tumangil, John Paul" class="bg-dark-900 text-white">Tumangil, John Paul</option>
                                            <option value="Villas, Cristian" class="bg-dark-900 text-white">Villas, Cristian</option>
                                            <option value="Other" class="bg-dark-900 text-white">Other (Please specify)</option>
                                        </select>
                                        <div id="custom_security_name_container" class="hidden mt-2">
                                            <input type="text" id="security_name_custom" placeholder="Enter Security Name"
                                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 rounded-xl text-white placeholder-slate-500 focus:outline-none focus:border-rose-500 focus:ring-1 focus:ring-rose-500 transition-all text-xs">
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="block text-xs font-bold text-slate-355 uppercase tracking-wide font-display">
                                            Security Signature <span class="text-rose-500">*</span>
                                        </label>
                                         <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                                              <canvas id="admin-checkout-signature-pad" class="w-full h-32 cursor-crosshair bg-transparent block"></canvas>
                                             <button type="button" id="clear-admin-checkout-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
                                                 <i class="fa-solid fa-eraser mr-1"></i> Clear
                                             </button>
                                        </div>
                                        <input type="hidden" id="security_signature" name="security_signature" required>
                                    </div>
                                </div>

                                <button type="submit" id="admin-checkout-btn" disabled
                                        class="w-full py-2.5 bg-gradient-to-r from-rose-600 to-rose-500 hover:from-rose-500 hover:to-rose-400 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all shadow-lg shadow-rose-600/15 opacity-50 cursor-not-allowed pointer-events-none">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span>Verify & Check Out Visitor</span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Terminal status closed states -->
                    <?php if ($gp['status'] === 'Rejected'): ?>
                        <div class="col-span-2 p-3 text-center text-xs font-semibold bg-rose-950/20 border border-rose-900/30 text-rose-400 rounded-xl">
                            <i class="fa-solid fa-circle-xmark mr-1.5"></i> This request has been rejected. Entry denied.
                        </div>
                    <?php endif; ?>

                    <?php if ($gp['status'] === 'Checked Out'): ?>
                        <div class="col-span-2 p-3 text-center text-xs font-semibold bg-slate-800/40 border border-slate-700/50 text-slate-400 rounded-xl">
                            <i class="fa-solid fa-circle-info mr-1.5"></i> This pass is archived (Checked Out).
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Security warning & login prompt -->
                <div class="p-4 rounded-2xl bg-indigo-950/20 border border-indigo-900/30 text-center">
                    <i class="fa-solid fa-lock text-indigo-400 text-lg mb-2"></i>
                    <p class="text-xs text-slate-300 font-medium mb-3">
                        You are viewing this pass details. Login as Administrator to update entry status (Approve, Check In/Out).
                    </p>
                    <a href="admin/login.php?redirect=<?php echo urlencode('../verify.php?code=' . $gatepass_no); ?>"
                       class="inline-block px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold rounded-lg transition-all active:scale-[0.97]">
                        <i class="fa-solid fa-user-lock mr-1.5"></i> Admin Login
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>



<?php if (true): // Allow signature script execution directly via QR verification link ?>
<script>
function initVerifySignatures() {
    // Setup for Manager Approval Signature Pad
    const approveCanvas = document.getElementById('admin-approve-signature-pad');
    if (approveCanvas) {
        const ctx = approveCanvas.getContext('2d');
        const clearBtn = document.getElementById('clear-admin-approve-sig');
        const sigInput = document.getElementById('manager_signature');
        const form = document.getElementById('admin-approve-form');
        let drawing = false;

        // Setup dropdown logic for IT Incharge Name
        const selectEl = document.getElementById('manager_name_select');
        const customContainer = document.getElementById('custom_manager_name_container');
        const customEl = document.getElementById('manager_name_custom');
        const hiddenEl = document.getElementById('manager_name');

        if (selectEl && customContainer && customEl && hiddenEl) {
            function updateManagerName() {
                if (selectEl.value === 'Other') {
                    customContainer.classList.remove('hidden');
                    customEl.required = true;
                    hiddenEl.value = customEl.value.trim();
                } else {
                    customContainer.classList.add('hidden');
                    customEl.required = false;
                    hiddenEl.value = selectEl.value;
                }
            }

            selectEl.addEventListener('change', updateManagerName);
            customEl.addEventListener('input', updateManagerName);
            updateManagerName();
        }

        let lastWidth = 0;
        let lastHeight = 0;
        function resizeCanvas() {
            const currentWidth = approveCanvas.offsetWidth;
            const currentHeight = approveCanvas.offsetHeight;
            
            if (currentWidth === lastWidth && currentHeight === lastHeight) {
                return;
            }
            
            let tempCanvas = null;
            if (lastWidth > 0 && lastHeight > 0) {
                tempCanvas = document.createElement('canvas');
                tempCanvas.width = approveCanvas.width;
                tempCanvas.height = approveCanvas.height;
                const tempCtx = tempCanvas.getContext('2d');
                tempCtx.drawImage(approveCanvas, 0, 0);
            }
            
            approveCanvas.width = currentWidth;
            approveCanvas.height = currentHeight;
            ctx.lineWidth = 3;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#ffffff';
            
            if (tempCanvas) {
                ctx.drawImage(tempCanvas, 0, 0, currentWidth, currentHeight);
                sigInput.value = window.getInvertedDataURL(approveCanvas);
            } else if (sigInput.value) {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, approveCanvas.width, approveCanvas.height);
                    ctx.drawImage(img, 0, 0, approveCanvas.width, approveCanvas.height);
                    
                    // Invert dark/black signature to white ink for screen display
                    try {
                        const imgData = ctx.getImageData(0, 0, approveCanvas.width, approveCanvas.height);
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
                        console.error("Error inverting signature loaded into approve canvas:", e);
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
            const rect = approveCanvas.getBoundingClientRect();
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
                sigInput.value = window.getInvertedDataURL(approveCanvas);
            }
        }

        approveCanvas.addEventListener('mousedown', startDrawing);
        approveCanvas.addEventListener('mousemove', draw);
        approveCanvas.addEventListener('mouseup', stopDrawing);
        approveCanvas.addEventListener('mouseleave', stopDrawing);

        approveCanvas.addEventListener('touchstart', startDrawing, { passive: false });
        approveCanvas.addEventListener('touchmove', draw, { passive: false });
        approveCanvas.addEventListener('touchend', stopDrawing);

        const updateBtn = () => {
            const btn = document.getElementById('admin-approve-btn');
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

        approveCanvas.addEventListener('mouseup', updateBtn);
        approveCanvas.addEventListener('touchend', updateBtn);
        clearBtn.addEventListener('click', () => {
            ctx.clearRect(0, 0, approveCanvas.width, approveCanvas.height);
            sigInput.value = '';
            updateBtn();
        });
        sigInput.addEventListener('change', () => {
            if (sigInput.value) {
                const img = new Image();
                img.onload = () => {
                    ctx.clearRect(0, 0, approveCanvas.width, approveCanvas.height);
                    ctx.drawImage(img, 0, 0, approveCanvas.width, approveCanvas.height);
                    
                    // Invert dark/black signature to white ink for screen display
                    try {
                        const imgData = ctx.getImageData(0, 0, approveCanvas.width, approveCanvas.height);
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
                        console.error("Error inverting signature loaded from modal into approve canvas:", e);
                    }
                };
                img.src = sigInput.value;
            } else {
                ctx.clearRect(0, 0, approveCanvas.width, approveCanvas.height);
            }
            updateBtn();
        });
        updateBtn();

        form.addEventListener('submit', (e) => {
            const nameInput = document.getElementById('manager_name');
            if (!nameInput || !nameInput.value.trim()) {
                e.preventDefault();
                alert("Manager Name is required.");
                return;
            }
            if (!sigInput.value) {
                e.preventDefault();
                alert("Signature is required for approval.");
            }
        });
    }

    // Setup for Security Checkout Signature Pad
    const checkoutCanvas = document.getElementById('admin-checkout-signature-pad');
    if (checkoutCanvas) {
        const ctx = checkoutCanvas.getContext('2d');
        const clearBtn = document.getElementById('clear-admin-checkout-sig');
        const sigInput = document.getElementById('security_signature');
        const form = document.getElementById('admin-checkout-form');
        let drawing = false;

        // Setup dropdown logic for Security Guard Name
        const selectEl = document.getElementById('security_name_select');
        const customContainer = document.getElementById('custom_security_name_container');
        const customEl = document.getElementById('security_name_custom');
        const hiddenEl = document.getElementById('security_name');

        if (selectEl && customContainer && customEl && hiddenEl) {
            function updateSecurityName() {
                if (selectEl.value === 'Other') {
                    customContainer.classList.remove('hidden');
                    customEl.required = true;
                    hiddenEl.value = customEl.value.trim();
                } else {
                    customContainer.classList.add('hidden');
                    customEl.required = false;
                    hiddenEl.value = selectEl.value;
                }
            }

            selectEl.addEventListener('change', updateSecurityName);
            customEl.addEventListener('input', updateSecurityName);
            updateSecurityName();
        }

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
            if (window.innerWidth >= 768 && (!nameInput || !nameInput.value.trim())) {
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
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVerifySignatures);
} else {
    initVerifySignatures();
}
</script>
<?php endif; ?>

<?php if ($trigger_email): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Asynchronously dispatch checkout confirmation emails in background
    fetch("send_emails_ajax.php?code=<?php echo urlencode($gatepass_no); ?>")
        .then(response => response.text())
        .then(data => console.log("Asynchronous checkout email dispatch response:", data))
        .catch(error => console.error("Asynchronous checkout email dispatch failed:", error));
});
</script>
<?php endif; ?>
</div>

<style>
img.signature-img {
    background: transparent !important;
    filter: invert(1) !important;
}
/* ===================================================
   PRINT: Force single A4 page — verify.php
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
        font-size: 11.5px !important;
        line-height: 1.5 !important;
    }
    #gatepass-card .instructions-section li {
        margin-bottom: 6px !important;
    }
}
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
