<?php
$page_title = "Verify Visitor Pass";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

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

// Handle administrative actions if user is logged in
if (is_logged_in() && !empty($action)) {
    try {
        $allowed_actions = ['approve', 'reject', 'check_in', 'check_out'];
        if (in_array($action, $allowed_actions)) {
            if ($action === 'approve') {
                $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Approved' WHERE gatepass_no = ?");
                $stmt->execute([$gatepass_no]);
                $message = "Gatepass successfully APPROVED.";
            } elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Rejected' WHERE gatepass_no = ?");
                $stmt->execute([$gatepass_no]);
                $message = "Gatepass successfully REJECTED.";
                $message_type = 'warning';
            } elseif ($action === 'check_in') {
                $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked In', time_in = CURRENT_TIME() WHERE gatepass_no = ?");
                $stmt->execute([$gatepass_no]);
                $message = "Visitor has been CHECKED IN at " . date('h:i A');
            } elseif ($action === 'check_out') {
                $stmt = $pdo->prepare("UPDATE gatepasses SET status = 'Checked Out', time_out = CURRENT_TIME() WHERE gatepass_no = ?");
                $stmt->execute([$gatepass_no]);
                $message = "Visitor has been CHECKED OUT at " . date('h:i A');
                $message_type = 'neutral';
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

// Map status configs
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/30', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/30', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/30', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/30', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/30', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];
$cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];
?>

<div class="max-w-xl mx-auto py-4">
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

    <!-- Gatepass Scanner Panel -->
    <div class="glass-card rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-500"></div>

        <div class="p-6 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/40">
            <div class="flex items-center space-x-2">
                <i class="fa-solid fa-shield-halved text-indigo-400"></i>
                <span class="text-xs font-bold text-slate-300 tracking-widest uppercase">Verification Terminal</span>
            </div>
            <!-- Status Badge -->
            <div class="px-3 py-1 rounded-full text-xs font-bold border <?php echo $cfg['bg'] . ' ' . $cfg['border'] . ' ' . $cfg['text']; ?> flex items-center space-x-1.5">
                <i class="fa-solid <?php echo $cfg['icon']; ?>"></i>
                <span><?php echo strtoupper($gp['status']); ?></span>
            </div>
        </div>

        <div class="p-6 sm:p-8 space-y-6">
            <div class="text-center pb-4 border-b border-slate-800/60">
                <span class="block text-[10px] text-indigo-400 font-extrabold uppercase tracking-widest mb-1">Gatepass Code</span>
                <h2 class="text-2xl font-black text-white tracking-wider select-all"><?php echo htmlspecialchars($gp['gatepass_no']); ?></h2>
            </div>

            <!-- Detail Grid -->
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Visitor Name</span>
                    <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($gp['visitor_name']); ?></span>
                </div>
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Company/Org</span>
                    <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($gp['company_org'] ?: 'N/A'); ?></span>
                </div>
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Contact Number</span>
                    <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($gp['visitor_phone']); ?></span>
                </div>
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Email Address</span>
                    <span class="font-semibold text-slate-200 break-all"><?php echo htmlspecialchars($gp['visitor_email']); ?></span>
                </div>
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Scheduled Date</span>
                    <span class="font-semibold text-slate-200"><?php echo date('F d, Y', strtotime($gp['visit_date'])); ?></span>
                </div>
                <div>
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Host Person</span>
                    <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($gp['host_name']); ?> (<?php echo htmlspecialchars($gp['department']); ?>)</span>
                </div>
                <div class="col-span-2">
                    <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Purpose of Visit</span>
                    <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($gp['purpose']); ?></span>
                </div>
                <?php if ($gp['time_in']): ?>
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Time In</span>
                        <span class="font-semibold text-emerald-400"><?php echo date('h:i A', strtotime($gp['time_in'])); ?></span>
                    </div>
                <?php endif; ?>
                <?php if ($gp['time_out']): ?>
                    <div>
                        <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Time Out</span>
                        <span class="font-semibold text-slate-400"><?php echo date('h:i A', strtotime($gp['time_out'])); ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Administration Control Panel -->
            <div class="pt-6 border-t border-slate-800/80">
                <?php if (is_logged_in()): ?>
                    <span class="block text-[10px] text-indigo-400 font-extrabold uppercase tracking-widest mb-4 text-center">Admin Controls</span>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <!-- Pending / Approve / Reject controls -->
                        <?php if ($gp['status'] === 'Pending'): ?>
                            <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=approve"
                               class="py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all">
                                <i class="fa-solid fa-circle-check"></i>
                                <span>Approve Request</span>
                            </a>
                            <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=reject"
                               class="py-2.5 bg-rose-600 hover:bg-rose-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all">
                                <i class="fa-solid fa-circle-xmark"></i>
                                <span>Reject Request</span>
                            </a>
                        <?php endif; ?>

                        <!-- Check In Control -->
                        <?php if ($gp['status'] === 'Approved'): ?>
                            <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=check_in"
                               class="col-span-2 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all">
                                <i class="fa-solid fa-right-to-bracket"></i>
                                <span>Check In Visitor</span>
                            </a>
                        <?php endif; ?>

                        <!-- Check Out Control -->
                        <?php if ($gp['status'] === 'Checked In'): ?>
                            <a href="verify.php?code=<?php echo urlencode($gatepass_no); ?>&action=check_out"
                               class="col-span-2 py-2.5 bg-slate-700 hover:bg-slate-600 active:scale-[0.98] text-white font-bold text-xs rounded-xl flex items-center justify-center space-x-1.5 transition-all">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                <span>Check Out Visitor</span>
                            </a>
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
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
