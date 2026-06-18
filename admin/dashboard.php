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

    // 2. Check In Total (all-time Checked In)
    $stats_check_in_total = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked In'")->fetchColumn();

    // 3. Check Out Total (all-time Checked Out)
    $stats_check_out_total = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked Out'")->fetchColumn();

    // 4. Rejected Total (all-time Rejected)
    $stats_rejected_total = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Rejected'")->fetchColumn();
    
} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Handle search/filters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$where_clauses = ["1=1"]; // Default to show all logs
$params = [];

if (!empty($search)) {
    $words = array_filter(explode(' ', $search), function($w) { return trim($w) !== ''; });
    $sub_clauses = [];
    $word_idx = 0;
    foreach ($words as $word) {
        $cols = ['visitor_name', 'gatepass_no', 'visitor_email', 'visitor_phone', 'eid', 'company_org', 'purpose', 'host_name', 'department', 'material_desc', 'material_brand', 'material_serial'];
        $clauses = [];
        $i = 0;
        foreach ($cols as $col) {
            $clauses[] = "$col LIKE :word_${word_idx}_$i";
            $params["word_${word_idx}_$i"] = '%' . trim($word) . '%';
            $i++;
        }
        
        // Check sub-materials table with unique placeholders for each column check to bypass native statement limitations
        $clauses[] = "EXISTS (SELECT 1 FROM gatepass_materials gm WHERE gm.gatepass_no = gatepasses.gatepass_no AND (gm.material_desc LIKE :word_m_desc_${word_idx} OR gm.material_brand LIKE :word_m_brand_${word_idx} OR gm.material_serial LIKE :word_m_serial_${word_idx} OR gm.purpose LIKE :word_m_purpose_${word_idx}))";
        $params["word_m_desc_${word_idx}"] = '%' . trim($word) . '%';
        $params["word_m_brand_${word_idx}"] = '%' . trim($word) . '%';
        $params["word_m_serial_${word_idx}"] = '%' . trim($word) . '%';
        $params["word_m_purpose_${word_idx}"] = '%' . trim($word) . '%';

        $sub_clauses[] = '(' . implode(' OR ', $clauses) . ')';
        $word_idx++;
    }
    if (!empty($sub_clauses)) {
        $where_clauses[] = '(' . implode(' AND ', $sub_clauses) . ')';
    }
}

if (!empty($status_filter)) {
    if ($status_filter === 'Rejected') {
        $where_clauses[] = "status = 'Rejected'";
    } elseif ($status_filter !== 'all') {
        $where_clauses[] = "status = :status";
        $params['status'] = $status_filter;
    }
}

$where_sql = implode(' AND ', $where_clauses);

// Pagination Settings
$limit = 5;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

try {
    // Get total records count for pagination
    $count_sql = "SELECT COUNT(*) FROM gatepasses WHERE $where_sql";
    $stmt_count = $pdo->prepare($count_sql);
    $stmt_count->execute($params);
    $total_records = (int)$stmt_count->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Fetch paginated dashboard records
    $query_sql = "SELECT * FROM gatepasses WHERE $where_sql ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $gatepasses = $stmt->fetchAll();

    // Fetch related materials
    $all_materials = [];
    if (!empty($gatepasses)) {
        $gatepass_nos = array_column($gatepasses, 'gatepass_no');
        $in_placeholders = implode(',', array_fill(0, count($gatepass_nos), '?'));
        $m_stmt = $pdo->prepare("SELECT * FROM gatepass_materials WHERE gatepass_no IN ($in_placeholders) ORDER BY id ASC");
        $m_stmt->execute($gatepass_nos);
        while ($row = $m_stmt->fetch()) {
            $all_materials[$row['gatepass_no']][] = $row;
        }
    }
} catch (PDOException $e) {
    $gatepasses = [];
    $total_records = 0;
    $total_pages = 0;
    $error = "Error executing query: " . $e->getMessage();
}

// Map status labels
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/25', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/25', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/25', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-brand-teal/10', 'border' => 'border-brand-teal/25', 'text' => 'text-brand-teal', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/25', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];
?>

<div class="space-y-8 pt-0 pb-4">
    <!-- Header Summary Block -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Security Command Center</h1>
            <p class="text-slate-450 text-sm">Welcome back, <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>. Monitor and authorize visitor access.</p>
        </div>
        <div class="flex gap-3">
            <a href="qr-generator.php" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-dark-800 border border-dark-700 text-slate-300 hover:text-white transition-all flex items-center gap-2">
                <i class="fa-solid fa-qrcode text-brand-teal"></i> Print Lobby QR
            </a>
            <a href="../register.php" target="_blank" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-brand-teal text-dark-900 hover:bg-[#1fd4be] shadow-lg shadow-brand-teal/10 transition-all flex items-center gap-2">
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
        <div class="glass-card p-5 rounded-3xl border border-dark-800 flex items-center justify-between relative overflow-hidden group hover:border-dark-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Today's Requests</span>
                <span class="text-3xl font-black text-white"><?php echo $stats_today; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-brand-blue/20 border border-brand-teal/20 text-brand-teal flex items-center justify-center text-xl shadow shadow-brand-teal/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-calendar-day"></i>
            </div>
        </div>

        <!-- Card 2: Check In Total -->
        <div class="glass-card p-5 rounded-3xl border border-dark-800 flex items-center justify-between relative overflow-hidden group hover:border-dark-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Check In Total</span>
                <span class="text-3xl font-black text-brand-teal"><?php echo $stats_check_in_total; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-brand-teal/10 border border-brand-teal/20 text-brand-teal flex items-center justify-center text-xl shadow shadow-brand-teal/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-right-to-bracket font-bold"></i>
            </div>
        </div>

        <!-- Card 3: Check Out Total -->
        <div class="glass-card p-5 rounded-3xl border border-dark-800 flex items-center justify-between relative overflow-hidden group hover:border-dark-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Check Out Total</span>
                <span class="text-3xl font-black text-slate-400"><?php echo $stats_check_out_total; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-slate-700/20 border border-slate-700/30 text-slate-400 flex items-center justify-center text-xl group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-right-from-bracket"></i>
            </div>
        </div>

        <!-- Card 4: Rejected Total -->
        <div class="glass-card p-5 rounded-3xl border border-dark-800 flex items-center justify-between relative overflow-hidden group hover:border-dark-700/60 transition-all duration-300">
            <div class="space-y-1">
                <span class="block text-[10px] text-slate-500 uppercase tracking-widest font-bold">Rejected Total</span>
                <span class="text-3xl font-black text-rose-400"><?php echo $stats_rejected_total; ?></span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-400 flex items-center justify-center text-xl shadow shadow-rose-500/5 group-hover:scale-110 transition-transform">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
        </div>
    </div>

    <!-- Active Passes Management Console -->
    <div id="active-passes-table" class="glass-card rounded-3xl border border-dark-800/80 overflow-hidden shadow-2xl">
        <!-- Panel Toolbar Controls -->
        <div class="p-6 border-b border-dark-800 bg-slate-900/40 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <h3 class="text-lg font-black text-white tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-brand-teal"></i> Active Access Passes
            </h3>
            
            <form id="filter-form" action="dashboard.php" method="GET" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="status" id="status-input" value="<?php echo htmlspecialchars($status_filter); ?>">
                <!-- Search Box -->
                <div class="relative min-w-[240px]">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500">
                        <i class="fa-solid fa-search text-xs"></i>
                    </span>
                    <input type="text" id="search-input" name="search" value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full pl-8 pr-4 py-1.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal focus:ring-1 focus:outline-none rounded-xl text-white placeholder-slate-500 text-xs transition-all"
                           placeholder="Search visitor, EID, brand, serial...">
                </div>
                <!-- Status Filter Options -->
                <div class="flex rounded-xl bg-dark-900 p-1 border border-dark-800 text-xs">
                    <button type="button" data-status="all" class="status-tab px-3 py-1.5 rounded-lg font-bold <?php echo ($status_filter === 'all' || empty($status_filter)) ? 'bg-brand-teal text-dark-900' : 'text-slate-400 hover:text-white'; ?> transition-all">All Logs</button>
                    <button type="button" data-status="Checked In" class="status-tab px-3 py-1.5 rounded-lg font-bold <?php echo $status_filter === 'Checked In' ? 'bg-brand-teal text-dark-900' : 'text-slate-400 hover:text-white'; ?> transition-all">Check In</button>
                    <button type="button" data-status="Checked Out" class="status-tab px-3 py-1.5 rounded-lg font-bold <?php echo $status_filter === 'Checked Out' ? 'bg-brand-teal text-dark-900' : 'text-slate-400 hover:text-white'; ?> transition-all">Check Out</button>
                    <button type="button" data-status="Rejected" class="status-tab px-3 py-1.5 rounded-lg font-bold <?php echo $status_filter === 'Rejected' ? 'bg-brand-teal text-dark-900' : 'text-slate-400 hover:text-white'; ?> transition-all">Rejected</button>
                </div>
            </form>
        </div>

        <div id="active-passes-table-container">
            <!-- Table Display -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-dark-800/80 bg-slate-900/10 text-slate-400 text-xs uppercase tracking-wider font-bold">
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
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <div class="text-4xl mb-3"><i class="fa-solid fa-box-archive text-slate-600"></i></div>
                                <span class="font-medium text-xs">No active gatepass requests found.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($gatepasses as $gp): 
                            $cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];
                        ?>
                            <tr class="hover:bg-dark-800/40 transition-all duration-150 text-slate-300">
                                <!-- Pass Number -->
                                <td class="py-4 px-6 font-bold tracking-wider text-xs whitespace-nowrap">
                                    <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" class="hover:underline flex items-center gap-1.5 text-brand-teal">
                                        <i class="fa-solid fa-id-card-clip"></i>
                                        <span><?php echo htmlspecialchars($gp['gatepass_no']); ?></span>
                                    </a>
                                </td>
                                
                                <!-- Visitor Details -->
                                <td class="py-4 px-6 whitespace-nowrap">
                                    <div class="font-bold text-slate-200"><?php echo htmlspecialchars($gp['visitor_name']); ?></div>
                                    <div class="text-slate-400 text-xs flex items-center gap-1 mt-0.5">
                                        <i class="fa-solid fa-envelope text-[9px] text-slate-500"></i> <?php echo htmlspecialchars($gp['visitor_email']); ?>
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
                                     <?php 
                                     $gp_mats = $all_materials[$gp['gatepass_no']] ?? [];
                                     if (empty($gp_mats) && !empty($gp['material_desc'])) {
                                         $gp_mats = [[
                                             'material_desc' => $gp['material_desc'],
                                             'material_brand' => $gp['material_brand'],
                                             'material_serial' => $gp['material_serial'],
                                             'material_qty' => $gp['material_qty']
                                         ]];
                                     }
                                     ?>
                                     <?php if (!empty($gp_mats)): ?>
                                         <div class="space-y-3">
                                             <?php foreach ($gp_mats as $idx => $m): ?>
                                                 <div class="<?php echo $idx > 0 ? 'border-t border-slate-800/60 pt-2 mt-2' : ''; ?>">
                                                     <div class="font-bold text-slate-200"><?php echo htmlspecialchars($m['material_desc'] ?: 'N/A'); ?></div>
                                                     <div class="text-[10px] text-slate-400 mt-0.5 space-y-0.5">
                                                         <?php if (!empty($m['material_brand'])): ?>
                                                             <div><span class="text-slate-500 font-medium">Brand:</span> <span class="font-semibold text-slate-300"><?php echo htmlspecialchars($m['material_brand']); ?></span></div>
                                                         <?php endif; ?>
                                                         <div class="flex items-center gap-1">
                                                             <span class="font-mono text-slate-450">S. No: <?php echo htmlspecialchars($m['material_serial'] ?: 'N/A'); ?></span>
                                                             <span class="text-slate-700">|</span>
                                                             <span>Qty: <?php echo htmlspecialchars($m['material_qty']); ?></span>
                                                         </div>
                                                     </div>
                                                 </div>
                                             <?php endforeach; ?>
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
                                               class="px-3 py-1.5 rounded-lg bg-emerald-950/20 border border-emerald-900/30 hover:bg-emerald-900/40 text-emerald-400 hover:text-emerald-300 transition-all text-xs font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-check text-[10px]"></i> Approve
                                            </a>
                                            <!-- Reject Button -->
                                            <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=reject" title="Reject Request"
                                               class="px-3 py-1.5 rounded-lg bg-rose-950/20 border border-rose-900/30 hover:bg-rose-900/40 text-rose-400 hover:text-rose-300 transition-all text-xs font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-xmark text-[10px]"></i> Reject
                                            </a>
                                        <?php elseif ($gp['status'] === 'Approved'): ?>
                                            <div class="flex items-center justify-center gap-1.5">
                                                <!-- Check-in Button -->
                                                <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=check_in" title="Authorize Check In"
                                                   class="px-3 py-1.5 rounded-lg bg-brand-teal hover:bg-[#1fd4be] text-dark-900 transition-all text-xs font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-right-to-bracket text-[10px]"></i> Check In
                                                </a>
                                                <!-- Reject Button -->
                                                <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=reject" title="Reject Request"
                                                   class="px-3 py-1.5 rounded-lg bg-rose-950/20 border border-rose-900/30 hover:bg-rose-900/40 text-rose-400 hover:text-rose-300 transition-all text-xs font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-xmark text-[10px]"></i> Reject
                                                </a>
                                            </div>
                                        <?php elseif ($gp['status'] === 'Checked In'): ?>
                                            <div class="flex items-center justify-center gap-1.5">
                                                <!-- Check-out Button -->
                                                <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>" title="Authorize Check Out"
                                                   class="px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 hover:border-slate-500 text-white transition-all text-xs font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-right-from-bracket text-[10px]"></i> Check Out
                                                </a>
                                                <!-- Reject Button -->
                                                <a href="../verify.php?code=<?php echo urlencode($gp['gatepass_no']); ?>&action=reject" title="Reject Request"
                                                   class="px-3 py-1.5 rounded-lg bg-rose-950/20 border border-rose-900/30 hover:bg-rose-900/40 text-rose-400 hover:text-rose-300 transition-all text-xs font-bold flex items-center gap-1">
                                                    <i class="fa-solid fa-xmark text-[10px]"></i> Reject
                                                </a>
                                            </div>
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
        
        <!-- Total Logs Count & Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-5 bg-slate-900/10 border-t border-slate-800/80 text-xs font-bold text-slate-400">
            <div>
                Showing <?php echo $total_records > 0 ? $offset + 1 : 0; ?> to <?php echo min($total_records, $offset + $limit); ?> of <?php echo $total_records; ?> records
            </div>
            
            <?php if ($total_pages > 1): ?>
                <div class="flex items-center gap-1.5">
                    <!-- Previous Button -->
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>#active-passes-table" class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 hover:border-brand-teal text-slate-350 hover:text-white transition-all flex items-center justify-center">
                            <i class="fa-solid fa-angle-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg bg-dark-900/50 border border-dark-800/40 text-slate-600 cursor-not-allowed flex items-center justify-center">
                            <i class="fa-solid fa-angle-left"></i>
                        </span>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_range = max(1, $page - 2);
                    $end_range = min($total_pages, $page + 2);
                    
                    if ($start_range > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>#active-passes-table" class="px-3 py-1.5 rounded-lg border bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white transition-all">1</a>
                        <?php if ($start_range > 2): ?>
                            <span class="px-2 text-slate-600">...</span>
                        <?php endif; ?>
                    <?php endif;

                    for ($i = $start_range; $i <= $end_range; $i++):
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>#active-passes-table" class="px-3 py-1.5 rounded-lg border transition-all <?php echo $i === $page ? 'bg-brand-teal border-brand-teal text-[#000f13] font-black' : 'bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end_range < $total_pages): ?>
                        <?php if ($end_range < $total_pages - 1): ?>
                            <span class="px-2 text-slate-600">...</span>
                        <?php endif; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>#active-passes-table" class="px-3 py-1.5 rounded-lg border bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white transition-all"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>#active-passes-table" class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 hover:border-brand-teal text-slate-350 hover:text-white transition-all flex items-center justify-center">
                            <i class="fa-solid fa-angle-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg bg-dark-900/50 border border-dark-800/40 text-slate-600 cursor-not-allowed flex items-center justify-center">
                            <i class="fa-solid fa-angle-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const filterForm = document.getElementById('filter-form');
    const tableContainer = document.getElementById('active-passes-table-container');
    const statusInput = document.getElementById('status-input');
    const searchInput = document.getElementById('search-input');

    if (!filterForm || !tableContainer) return;

    // Handle status tab clicks
    filterForm.addEventListener('click', (e) => {
        const btn = e.target.closest('.status-tab');
        if (!btn) return;
        e.preventDefault();
        
        // Update hidden input
        const statusVal = btn.getAttribute('data-status');
        statusInput.value = statusVal;

        // Submit form
        filterForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });

    // Intercept search form submission
    filterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const formData = new FormData(filterForm);
        const searchParams = new URLSearchParams();
        for (const [key, val] of formData.entries()) {
            if (val) searchParams.append(key, val);
        }
        const url = 'dashboard.php?' + searchParams.toString();
        loadTableContent(url);
    });

    // Automatic filter on input change (debounced)
    if (searchInput) {
        let debounceTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                filterForm.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
            }, 300);
        });
    }

    // Intercept clicks on links inside the table container (e.g. pagination)
    tableContainer.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        // Skip administrative action buttons
        if (link.getAttribute('title') || link.querySelector('.fa-check') || link.querySelector('.fa-xmark')) {
            return;
        }

        const url = link.getAttribute('href');
        if (url && (url.includes('dashboard.php') || url.startsWith('?'))) {
            e.preventDefault();
            loadTableContent(url);
        }
    });

    function loadTableContent(url) {
        tableContainer.style.opacity = '0.5';
        tableContainer.style.transition = 'opacity 150ms ease';

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newContent = doc.getElementById('active-passes-table-container');
                if (newContent) {
                    tableContainer.innerHTML = newContent.innerHTML;
                    window.history.pushState(null, '', url);

                    // Update status tab buttons in the UI to match the new status
                    const newStatusInput = doc.getElementById('status-input');
                    if (newStatusInput && statusInput) {
                        statusInput.value = newStatusInput.value;
                        const currentStatus = newStatusInput.value || 'all';

                        filterForm.querySelectorAll('.status-tab').forEach(btn => {
                            const btnStatus = btn.getAttribute('data-status');
                            if (btnStatus === currentStatus) {
                                btn.className = 'status-tab px-3 py-1.5 rounded-lg font-bold bg-brand-teal text-dark-900 transition-all';
                            } else {
                                btn.className = 'status-tab px-3 py-1.5 rounded-lg font-bold text-slate-400 hover:text-white transition-all';
                            }
                        });
                    }
                    
                    if (typeof initCustomSelects === 'function') {
                        initCustomSelects();
                    }
                }
                tableContainer.style.opacity = '1';
            })
            .catch(error => {
                console.error('AJAX load error:', error);
                tableContainer.style.opacity = '1';
            });
    }

    window.addEventListener('popstate', () => {
        const urlParams = new URLSearchParams(window.location.search);
        if (searchInput) {
            searchInput.value = urlParams.get('search') || '';
        }
        loadTableContent(window.location.href);
    });
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
