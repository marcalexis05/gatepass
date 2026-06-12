<?php
$page_title = "Admin Dashboard";
require_once __DIR__ . '/../includes/auth.php';
// Secure the page
require_login();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Check if SMTP is configured
$smtp_user = get_setting('smtp_user');
$smtp_pass = get_setting('smtp_pass');
$smtp_configured = !empty($smtp_user) && !empty($smtp_pass);

// Fetch stats for today
$today = date('Y-m-d');
try {
    // 1. Total visitor requests today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gatepasses WHERE visit_date = ?");
    $stmt->execute([$today]);
    $stats_today = $stmt->fetchColumn();

    // 2. Pending approval requests (all-time)
    $stats_pending = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Pending'")->fetchColumn();

    // 3. Checked In currently (active visitors inside building)
    $stats_checked_in = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked In'")->fetchColumn();

    // 4. Checked out today
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked Out' AND visit_date = ?");
    $stmt->execute([$today]);
    $stats_checked_out = $stmt->fetchColumn();
    
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Handle search/filters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$where_clauses = ["status != 'Checked Out' AND status != 'Rejected'"]; // Default to active passes only (pending, approved, checked_in)
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(visitor_name LIKE :search OR gatepass_no LIKE :search OR host_name LIKE :search OR department LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    // Override default active only filter if user specifically chooses a filter
    $where_clauses = ["status = :status"];
    $params['status'] = $status_filter;
} elseif (isset($_GET['status']) && $_GET['status'] === 'all') {
    // Show all records
    $where_clauses = ["1=1"];
}

$where_sql = implode(' AND ', $where_clauses);
$query_sql = "SELECT * FROM gatepasses WHERE $where_sql ORDER BY created_at DESC LIMIT 50";

try {
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $gatepasses = $stmt->fetchAll();
} catch (PDOException $e) {
    $gatepasses = [];
    $error = "Error executing query: " . $e->getMessage();
}

// Map status labels
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/25', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/25', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/25', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/25', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/25', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];
?>

<div class="space-y-8 py-4">
    <!-- Header Summary Block -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Security Command Center</h1>
            <p class="text-slate-400 text-sm">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>. Monitor and authorize visitor access.</p>
        </div>
        <div class="flex gap-3">
            <a href="qr-generator.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition-all flex items-center gap-2">
                <i class="fa-solid fa-qrcode"></i> Print Lobby QR
            </a>
            <a href="../register.php" target="_blank" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/10 transition-all flex items-center gap-2">
                <i class="fa-solid fa-user-plus"></i> New Pass
            </a>
        </div>
    </div>

    <!-- SMTP Warning Alert -->
    <?php if (!$smtp_configured): ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-300 text-xs flex items-center justify-between shadow-md">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-400 text-lg flex-shrink-0"></i>
                <p>
                    <strong>Email dispatch is currently offline:</strong> SMTP details are not configured. Notifications will not be sent to admins and visitors.
                </p>
            </div>
            <a href="settings.php#smtp-settings" class="px-3 py-1.5 bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 font-bold rounded-lg border border-amber-500/30 transition-all text-[11px] whitespace-nowrap">
                Configure SMTP
            </a>
        </div>
    <?php endif; ?>

    <!-- KPI Stats Counters -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Card 1: Today's Requests -->
        <div class="glass-card p-5 rounded-3xl border border-slate-800 flex items-center justify-between relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Today's Requests</span>
                <span class="text-3xl font-black text-white"><?php echo $stats_today; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-600/10 border border-indigo-500/20 text-indigo-400 flex items-center justify-center text-xl shadow shadow-indigo-600/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
        </div>

        <!-- Card 2: Pending Authorizations -->
        <div class="glass-card p-5 rounded-3xl border border-slate-800 flex items-center justify-between relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Pending Approval</span>
                <span class="text-3xl font-black text-amber-400"><?php echo $stats_pending; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-amber-400 flex items-center justify-center text-xl shadow shadow-amber-500/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
        </div>

        <!-- Card 3: Inside Building (Checked In) -->
        <div class="glass-card p-5 rounded-3xl border border-slate-800 flex items-center justify-between relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Active Visitors</span>
                <span class="text-3xl font-black text-emerald-400"><?php echo $stats_checked_in; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl shadow shadow-emerald-500/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-right-to-bracket font-bold"></i>
            </div>
        </div>

        <!-- Card 4: Checked Out Today -->
        <div class="glass-card p-5 rounded-3xl border border-slate-800 flex items-center justify-between relative overflow-hidden group hover:border-slate-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Checked Out Today</span>
                <span class="text-3xl font-black text-slate-400"><?php echo $stats_checked_out; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-slate-700/20 border border-slate-700/30 text-slate-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
        </div>
    </div>

    <!-- Active Passes Management Console -->
    <div class="glass-card rounded-3xl border border-slate-800/80 overflow-hidden shadow-2xl">
        <!-- Panel Toolbar Controls -->
        <div class="p-6 border-b border-slate-800 bg-slate-900/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h3 class="text-lg font-black text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-indigo-400"></i> Active Access Passes
            </h3>
            
            <form action="dashboard.php" method="GET" class="flex flex-wrap items-center gap-3">
                <!-- Status Filter Options -->
                <div class="flex rounded-xl bg-dark-900 p-1 border border-slate-800 text-xs">
                    <a href="dashboard.php" class="px-3 py-1.5 rounded-lg font-bold <?php echo (empty($status_filter) && (!isset($_GET['status']) || $_GET['status'] !== 'all')) ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'; ?> transition-all">Active</a>
                    <a href="dashboard.php?status=Pending" class="px-3 py-1.5 rounded-lg font-bold <?php echo $status_filter === 'Pending' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'; ?> transition-all">Pending</a>
                    <a href="dashboard.php?status=Approved" class="px-3 py-1.5 rounded-lg font-bold <?php echo $status_filter === 'Approved' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'; ?> transition-all">Approved</a>
                    <a href="dashboard.php?status=all" class="px-3 py-1.5 rounded-lg font-bold <?php echo (isset($_GET['status']) && $_GET['status'] === 'all') ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white'; ?> transition-all">All Logs</a>
                </div>

                <!-- Search Input box -->
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-search text-xs"></i></span>
                    <input type="text" name="search" placeholder="Search visitors..." value="<?php echo htmlspecialchars($search); ?>"
                           class="pl-8 pr-4 py-1.5 w-60 bg-dark-900 border border-slate-800 rounded-xl text-white text-xs focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all placeholder-slate-500">
                    <?php if (!empty($search) || !empty($status_filter)): ?>
                        <a href="dashboard.php" class="absolute inset-y-0 right-0 pr-3 flex items-center text-rose-400 hover:text-rose-300 text-xs">
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table Display -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-800/80 bg-slate-900/10 text-slate-400 text-xs uppercase tracking-wider font-bold">
                        <th class="py-4 px-6">Gatepass No</th>
                        <th class="py-4 px-6">Visitor Details</th>
                        <th class="py-4 px-6">Program/Department & Purpose</th>
                        <th class="py-4 px-6">Transaction Details</th>
                        <th class="py-4 px-6">Date & Entry Logs</th>
                        <th class="py-4 px-6">Status</th>
                        <th class="py-4 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-dark-900/10">
                    <?php if (empty($gatepasses)): ?>
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                <div class="text-4xl mb-3"><i class="fa-solid fa-box-archive"></i></div>
                                <span class="font-medium text-xs">No active gatepass requests found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gatepasses as $gp): 
                            $cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];
                        ?>
                            <tr class="hover:bg-slate-800/20 transition-all duration-150">
                                <!-- Pass Number -->
                                <td class="py-4 px-6 font-bold text-white tracking-wider text-xs whitespace-nowrap">
                                    <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" class="hover:underline flex items-center gap-1.5 text-indigo-400">
                                        <i class="fa-solid fa-id-card-clip"></i>
                                        <span><?php echo htmlspecialchars($gp['gatepass_no']); ?></span>
                                    </a>
                                </td>
                                
                                <!-- Visitor Details -->
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="font-bold text-slate-200"><?php echo htmlspecialchars($gp['visitor_name']); ?></div>
                                    <div class="text-slate-400 text-xs flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-envelope text-[9px]"></i> <?php echo htmlspecialchars($gp['visitor_email']); ?>
                                    </div>
                                </td>

                                <!-- Department & Purpose -->
                                <td class="py-4 px-6">
                                    <div class="font-semibold text-slate-300"><?php echo htmlspecialchars($gp['department']); ?></div>
                                    <div class="text-slate-400 text-xs mt-1 italic max-w-xs truncate" title="<?php echo htmlspecialchars($gp['purpose']); ?>">
                                        "<?php echo htmlspecialchars($gp['purpose']); ?>"
                                    </div>
                                </td>

                                <!-- Transaction Details -->
                                <td class="py-4 px-6 text-xs">
                                    <?php if ($gp['material_desc']): ?>
                                        <div class="font-bold text-slate-200"><?php echo htmlspecialchars($gp['material_desc']); ?></div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            <span class="font-mono text-slate-450">S. No: <?php echo htmlspecialchars($gp['material_serial'] ?: 'N/A'); ?></span>
                                            <span class="text-slate-700">|</span>
                                            <span>Qty: <?php echo htmlspecialchars($gp['material_qty']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-500 italic">No Materials</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Visit Date & Logs -->
                                <td class="py-4 px-6 text-xs whitespace-nowrap">
                                    <div class="font-bold text-slate-300"><?php echo date('M d, Y', strtotime($gp['visit_date'])); ?></div>
                                    <div class="text-[10px] text-slate-400 space-y-0.5 mt-1">
                                        <div class="flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                            <span>In: <?php echo $gp['time_in'] ? date('h:i A', strtotime($gp['time_in'])) : '--:--'; ?></span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                            <span>Out: <?php echo $gp['time_out'] ? date('h:i A', strtotime($gp['time_out'])) : '--:--'; ?></span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold border <?php echo $cfg['bg'] . ' ' . $cfg['border'] . ' ' . $cfg['text']; ?>">
                                        <i class="fa-solid <?php echo $cfg['icon']; ?> text-[10px]"></i>
                                        <?php echo strtoupper($gp['status']); ?>
                                    </span>
                                </td>

                                <!-- Administrative Quick Actions -->
                                <td class="py-4 px-6 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <?php if ($gp['status'] === 'Pending'): ?>
                                            <!-- Approve Button -->
                                            <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=approve" title="Approve Request"
                                               class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 hover:bg-emerald-500 hover:text-white transition-all flex items-center justify-center">
                                                <i class="fa-solid fa-check text-xs"></i>
                                            </a>
                                            <!-- Reject Button -->
                                            <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=reject" title="Reject Request"
                                               class="w-8 h-8 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-400 hover:bg-rose-500 hover:text-white transition-all flex items-center justify-center">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </a>
                                        <?php elseif ($gp['status'] === 'Approved'): ?>
                                            <!-- Check-in Button -->
                                            <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=check_in" title="Authorize Check In"
                                               class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white transition-all text-xs font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-right-to-bracket text-[10px]"></i> Check In
                                            </a>
                                        <?php elseif ($gp['status'] === 'Checked In'): ?>
                                            <!-- Check-out Button -->
                                            <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" title="Authorize Check Out"
                                               class="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 hover:border-slate-500 text-white transition-all text-xs font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Check Out
                                            </a>
                                        <?php else: ?>
                                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Archived</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Footer Info -->
        <div class="p-4 bg-slate-900/10 border-t border-slate-800/80 text-center text-[10px] text-slate-500">
            Showing up to 50 active records. To review checkouts and archives, visit the <a href="history.php" class="text-indigo-400 hover:underline">History Logs</a> page.
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
