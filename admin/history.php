<?php
$page_title = "Gatepass History Logs";
require_once __DIR__ . '/../includes/auth.php';
// Secure the page
require_login();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Filter Variables
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$dept_filter = trim($_GET['department'] ?? '');
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$where_clauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(visitor_name LIKE :search OR gatepass_no LIKE :search OR host_name LIKE :search OR company_org LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    $where_clauses[] = "status = :status";
    $params['status'] = $status_filter;
}

if (!empty($dept_filter)) {
    $where_clauses[] = "department = :department";
    $params['department'] = $dept_filter;
}

if (!empty($start_date)) {
    $where_clauses[] = "visit_date >= :start_date";
    $params['start_date'] = $start_date;
}

if (!empty($end_date)) {
    $where_clauses[] = "visit_date <= :end_date";
    $params['end_date'] = $end_date;
}

$where_sql = implode(' AND ', $where_clauses);
$query_sql = "SELECT * FROM gatepasses WHERE $where_sql ORDER BY visit_date DESC, created_at DESC";

try {
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $history_records = $stmt->fetchAll();
} catch (PDOException $e) {
    $history_records = [];
    $error = "Error loading history logs: " . $e->getMessage();
}

// Fetch all unique departments for select dropdown filtering
try {
    $departments = $pdo->query("SELECT DISTINCT department FROM gatepasses ORDER BY department ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $departments = [];
}

// Map status configurations
$status_configs = [
    'Pending' => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/25', 'text' => 'text-amber-400', 'icon' => 'fa-hourglass-half'],
    'Approved' => ['bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/25', 'text' => 'text-emerald-400', 'icon' => 'fa-circle-check'],
    'Rejected' => ['bg' => 'bg-rose-500/10', 'border' => 'border-rose-500/25', 'text' => 'text-rose-400', 'icon' => 'fa-circle-xmark'],
    'Checked In' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/25', 'text' => 'text-indigo-400', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/25', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket']
];
?>

<div class="space-y-6 py-4">
    <!-- Breadcrumb & Page Info -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Gatepass History Logs</h1>
            <p class="text-slate-400 text-sm">Review, audit, and print all historical visitor logs and security records.</p>
        </div>
        <button onclick="window.print()" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:text-white hover:border-slate-500 transition-all flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Print Audit Log
        </button>
    </div>

    <!-- Filter Control Card -->
    <div class="glass-card p-5 rounded-3xl border border-slate-800 shadow-xl relative overflow-hidden">
        <form action="history.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end text-xs">
            <!-- Search Text -->
            <div class="space-y-1.5 col-span-1 sm:col-span-2 lg:col-span-1">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Search Term</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="search" placeholder="Visitor, host, pass code..." value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full pl-8 pr-4 py-2 bg-dark-900 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-xs placeholder-slate-500">
                </div>
            </div>

            <!-- Status Filter -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-dark-900 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-xs cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    <option value="Checked In" <?php echo $status_filter === 'Checked In' ? 'selected' : ''; ?>>Checked In</option>
                    <option value="Checked Out" <?php echo $status_filter === 'Checked Out' ? 'selected' : ''; ?>>Checked Out</option>
                </select>
            </div>

            <!-- Department Filter -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Program/Department</label>
                <select name="department" class="w-full px-3 py-2 bg-dark-900 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-xs cursor-pointer">
                    <option value="">All Programs/Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $dept_filter === $dept ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Start Date -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Start Date</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>"
                       class="w-full px-3 py-1.5 bg-dark-900 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-xs">
            </div>

            <!-- End Date -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">End Date</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>"
                       class="w-full px-3 py-1.5 bg-dark-900 border border-slate-800 rounded-xl text-white focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition-all text-xs">
            </div>

            <!-- Submit & Reset Buttons -->
            <div class="col-span-1 sm:col-span-2 lg:col-span-5 flex justify-end gap-2.5 pt-2 border-t border-slate-800/80">
                <?php if (!empty($search) || !empty($status_filter) || !empty($dept_filter) || !empty($start_date) || !empty($end_date)): ?>
                    <a href="history.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-lg transition-all text-center">
                        Reset Filters
                    </a>
                <?php endif; ?>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-lg transition-all shadow-md shadow-indigo-600/10">
                    Apply Filters
                </button>
            </div>
        </form>
    </div>

    <!-- History Log Table -->
    <div class="glass-card rounded-3xl border border-slate-800/80 overflow-hidden shadow-2xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-800/80 bg-slate-900/10 text-slate-400 text-xs uppercase tracking-wider font-bold">
                        <th class="py-4 px-6">Gatepass No</th>
                        <th class="py-4 px-6">Visitor Info</th>
                        <th class="py-4 px-6">Program/Department</th>
                        <th class="py-4 px-6">Transaction Details</th>
                        <th class="py-4 px-6">Scheduled Date</th>
                        <th class="py-4 px-6">Access Logs</th>
                        <th class="py-4 px-6">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-dark-900/10">
                    <?php if (empty($history_records)): ?>
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500">
                                <div class="text-4xl mb-3"><i class="fa-solid fa-box-open"></i></div>
                                <span class="font-medium text-xs">No historical gatepass records match your filters.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_records as $gp): 
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

                                <!-- Department -->
                                <td class="py-4 px-6">
                                    <div class="font-semibold text-slate-300"><?php echo htmlspecialchars($gp['department']); ?></div>
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

                                <!-- Visit Date -->
                                <td class="py-4 px-6 text-xs font-semibold text-slate-300 whitespace-nowrap">
                                    <?php echo date('M d, Y', strtotime($gp['visit_date'])); ?>
                                </td>

                                <!-- Check-in / Check-out Times -->
                                <td class="py-4 px-6 text-xs whitespace-nowrap">
                                    <div class="text-slate-400 space-y-0.5">
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Total Logs Count -->
        <div class="p-4 bg-slate-900/10 border-t border-slate-800/80 text-right text-xs font-bold text-slate-400">
            Total Records Found: <?php echo count($history_records); ?>
        </div>
    </div>
</div>

<style>
/* Print Layout styling overrides for Audit Report */
@media print {
    header, footer, nav, form, button, a {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        background-image: none !important;
    }
    .glass-card {
        border: none !important;
        background: transparent !important;
        box-shadow: none !important;
        color: black !important;
    }
    table {
        color: black !important;
        border: 1px solid #ddd;
    }
    th {
        background-color: #f3f4f6 !important;
        color: black !important;
        border-bottom: 2px solid #aaa !important;
    }
    td {
        border-bottom: 1px solid #eee !important;
        color: black !important;
    }
    .text-white {
        color: black !important;
    }
    .text-slate-400, .text-slate-300, .text-slate-200 {
        color: #333 !important;
    }
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
