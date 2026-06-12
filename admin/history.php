<?php
$page_title = "Gatepass History Logs";
require_once __DIR__ . '/../includes/auth.php';
// Secure the page
require_login();

require_once __DIR__ . '/../config/database.php';

// Handle Archive Action — MUST run before any HTML output (header.php) so JSON response works
if (($_GET['action'] ?? '') === 'archive' && !empty($_GET['id'])) {
    $archive_id = (int)$_GET['id'];
    try {
        $stmt_arch = $pdo->prepare("UPDATE gatepasses SET status = 'Archived' WHERE id = ?");
        $stmt_arch->execute([$archive_id]);
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            header("Location: history.php");
            exit;
        }
    } catch (PDOException $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle Delete Action (Archived records only) — before HTML output
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $delete_id = (int)$_GET['id'];
    try {
        // Only allow deleting records that are already Archived
        $stmt_del = $pdo->prepare("DELETE FROM gatepasses WHERE id = ? AND status = 'Archived'");
        $stmt_del->execute([$delete_id]);
        $deleted = $stmt_del->rowCount();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => $deleted > 0]);
            exit;
        } else {
            header("Location: history.php");
            exit;
        }
    } catch (PDOException $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

// Handle Delete All Archived — before HTML output
if (($_POST['action'] ?? '') === 'delete_all_archived') {
    try {
        $stmt_del_all = $pdo->prepare("DELETE FROM gatepasses WHERE status = 'Archived'");
        $stmt_del_all->execute();
        $deleted_count = $stmt_del_all->rowCount();
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'count' => $deleted_count]);
            exit;
        } else {
            header("Location: history.php?status=Archived");
            exit;
        }
    } catch (PDOException $e) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

// Filter Variables
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$dept_filter = trim($_GET['department'] ?? '');
$visit_date = trim($_GET['visit_date'] ?? '');

$where_clauses = ["1=1"];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(visitor_name LIKE :search OR gatepass_no LIKE :search OR host_name LIKE :search OR company_org LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($status_filter)) {
    if ($status_filter === 'Resolved') {
        $where_clauses[] = "(status = 'Checked Out' OR status = 'Rejected')";
    } else {
        $where_clauses[] = "status = :status";
        $params['status'] = $status_filter;
    }
}

if (!empty($dept_filter)) {
    $where_clauses[] = "department = :department";
    $params['department'] = $dept_filter;
}

if (!empty($visit_date)) {
    $where_clauses[] = "visit_date = :visit_date";
    $params['visit_date'] = $visit_date;
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

    // Fetch paginated history records
    $query_sql = "SELECT * FROM gatepasses WHERE $where_sql ORDER BY visit_date DESC, created_at DESC LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query_sql);
    $stmt->execute($params);
    $history_records = $stmt->fetchAll();
} catch (PDOException $e) {
    $history_records = [];
    $total_records = 0;
    $total_pages = 0;
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
    'Checked In' => ['bg' => 'bg-brand-teal/10', 'border' => 'border-brand-teal/25', 'text' => 'text-brand-teal', 'icon' => 'fa-right-to-bracket'],
    'Checked Out' => ['bg' => 'bg-slate-700/20', 'border' => 'border-slate-700/25', 'text' => 'text-slate-400', 'icon' => 'fa-right-from-bracket'],
    'Archived' => ['bg' => 'bg-indigo-500/10', 'border' => 'border-indigo-500/25', 'text' => 'text-indigo-400', 'icon' => 'fa-box-archive']
];
?>

<div class="space-y-6 py-4">
    <!-- Breadcrumb & Page Info -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Gatepass History Logs</h1>
            <p class="text-slate-400 text-sm">Review, audit, and print all historical visitor logs and security records.</p>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($status_filter === 'Archived'): ?>
            <button id="delete-all-archived-btn"
                class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-rose-950/40 border border-rose-800/50 text-rose-400 hover:bg-rose-900/50 hover:border-rose-600/60 hover:text-rose-300 transition-all flex items-center gap-2">
                <i class="fa-solid fa-trash-can"></i> Delete All Archived
            </button>
            <?php endif; ?>
            <button onclick="window.print()" class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-800 border border-slate-700 text-slate-300 hover:text-white hover:border-slate-500 transition-all flex items-center gap-2">
                <i class="fa-solid fa-print"></i> Print Audit Log
            </button>
        </div>
    </div>

    <!-- Filter Control Card -->
    <div class="glass-card p-5 rounded-3xl border border-dark-800 shadow-xl relative z-20">
        <form action="history.php" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end text-xs">
            <!-- Search Term -->
            <div class="space-y-1.5 col-span-1 sm:col-span-2 lg:col-span-1">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Search Term</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-search"></i></span>
                    <input type="text" name="search" placeholder="Visitor, host, pass code..." value="<?php echo htmlspecialchars($search); ?>"
                            class="w-full pl-8 pr-4 py-2 bg-dark-900 border border-dark-800 rounded-xl text-white focus:outline-none focus:border-brand-teal focus:ring-1 focus:ring-brand-teal transition-all text-xs placeholder-slate-500">
                </div>
            </div>

            <!-- Status Filter -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Status</label>
                <select name="status" class="w-full px-3 py-2 bg-dark-900 border border-dark-800 rounded-xl text-white focus:outline-none focus:border-brand-teal focus:ring-1 focus:ring-brand-teal transition-all text-xs cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="Checked In" <?php echo $status_filter === 'Checked In' ? 'selected' : ''; ?>>Check In</option>
                    <option value="Checked Out" <?php echo $status_filter === 'Checked Out' ? 'selected' : ''; ?>>Check Out</option>
                    <option value="Resolved" <?php echo $status_filter === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                    <option value="Archived" <?php echo $status_filter === 'Archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>

            <!-- Department Filter -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Program/Department</label>
                <select name="department" class="w-full px-3 py-2 bg-dark-900 border border-dark-800 rounded-xl text-white focus:outline-none focus:border-brand-teal focus:ring-1 focus:ring-brand-teal transition-all text-xs cursor-pointer">
                    <option value="">All Programs/Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $dept_filter === $dept ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Date Filter -->
            <div class="space-y-1.5">
                <label class="block font-bold text-slate-400 uppercase tracking-wider">Date</label>
                <input type="date" name="visit_date" value="<?php echo htmlspecialchars($visit_date); ?>"
                       class="w-full px-3 py-1.5 bg-dark-900 border border-dark-800 rounded-xl text-white focus:outline-none focus:border-brand-teal focus:ring-1 focus:ring-brand-teal transition-all text-xs">
            </div>

            <!-- Reset Button (Only visible when filters are active) -->
            <?php if (!empty($search) || !empty($status_filter) || !empty($dept_filter) || !empty($visit_date)): ?>
                <div id="reset-filters-container" class="col-span-1 sm:col-span-2 lg:col-span-4 flex justify-end pt-2 border-t border-slate-800/80">
                    <a href="history.php" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold rounded-lg transition-all text-center">
                        Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- History Log Table -->
    <div id="history-table" class="glass-card rounded-3xl border border-slate-800/80 overflow-hidden shadow-2xl">
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
                        <th class="py-4 px-6 text-center">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 bg-dark-900/10">
                    <?php if (empty($history_records)): ?>
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-500">
                                <div class="text-4xl mb-3"><i class="fa-solid fa-box-open"></i></div>
                                <span class="font-medium text-xs">No historical gatepass records match your filters.</span>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($history_records as $gp): 
                            $cfg = $status_configs[$gp['status']] ?? $status_configs['Pending'];
                        ?>
                            <tr class="hover:bg-slate-800/20 transition-all duration-150 text-slate-350">
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
                                        <i class="fa-solid fa-envelope text-[9px] text-slate-550"></i> <?php echo htmlspecialchars($gp['visitor_email']); ?>
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

                                <!-- Action -->
                                <td class="py-4 px-6 whitespace-nowrap text-center text-xs">
                                    <?php if ($gp['status'] !== 'Archived'): ?>
                                        <a href="?action=archive&id=<?php echo $gp['id']; ?>" 
                                           class="archive-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-dark-900 border border-dark-800 text-slate-400 hover:text-brand-teal hover:border-brand-teal/50 transition-all"
                                           title="Archive Record">
                                            <i class="fa-solid fa-box-archive"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=delete&id=<?php echo $gp['id']; ?>"
                                           class="delete-btn inline-flex items-center justify-center w-8 h-8 rounded-lg bg-dark-900 border border-dark-800 text-slate-500 hover:text-rose-400 hover:border-rose-500/50 hover:bg-rose-950/20 transition-all"
                                           title="Delete Record Permanently">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </a>
                                    <?php endif; ?>
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
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>#history-table" class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 hover:border-brand-teal text-slate-350 hover:text-white transition-all flex items-center justify-center">
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
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>#history-table" class="px-3 py-1.5 rounded-lg border bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white transition-all">1</a>
                        <?php if ($start_range > 2): ?>
                            <span class="px-2 text-slate-600">...</span>
                        <?php endif; ?>
                    <?php endif;

                    for ($i = $start_range; $i <= $end_range; $i++):
                    ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>#history-table" class="px-3 py-1.5 rounded-lg border transition-all <?php echo $i === $page ? 'bg-brand-teal border-brand-teal text-[#000f13] font-black' : 'bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor;

                    if ($end_range < $total_pages): ?>
                        <?php if ($end_range < $total_pages - 1): ?>
                            <span class="px-2 text-slate-600">...</span>
                        <?php endif; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>#history-table" class="px-3 py-1.5 rounded-lg border bg-dark-900 border-dark-800 text-slate-350 hover:border-brand-teal hover:text-white transition-all"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>#history-table" class="px-3 py-1.5 rounded-lg bg-dark-900 border border-dark-800 hover:border-brand-teal text-slate-350 hover:text-white transition-all flex items-center justify-center">
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const tableContainer = document.getElementById('history-table');
    if (!tableContainer) return;

    // Intercept clicks on links inside the table container (like page numbers or archive buttons)
    tableContainer.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (!link) return;

        const url = link.getAttribute('href');
        if (!url) return;

        if (link.classList.contains('archive-btn')) {
            e.preventDefault();
            if (typeof showConfirmModal === 'function') {
                showConfirmModal('Are you sure you want to archive this gatepass?', () => {
                    // Find the row to animate out before fetching
                    const row = link.closest('tr');

                    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show toast immediately
                                showArchivedToast();

                                // Smoothly fade + collapse the row — no reload, no flicker
                                if (row) {
                                    row.style.transition = 'opacity 350ms ease, transform 350ms ease';
                                    row.style.opacity = '0';
                                    row.style.transform = 'translateX(16px)';
                                    setTimeout(() => {
                                        row.style.transition = 'max-height 300ms ease, padding 300ms ease';
                                        row.style.overflow = 'hidden';
                                        row.style.maxHeight = row.offsetHeight + 'px';
                                        requestAnimationFrame(() => {
                                            row.style.maxHeight = '0';
                                            row.style.paddingTop = '0';
                                            row.style.paddingBottom = '0';
                                        });
                                        setTimeout(() => row.remove(), 310);
                                    }, 360);
                                }
                            }
                        })
                        .catch(error => console.error('Error archiving:', error));
                });
            }
        } else if (link.classList.contains('delete-btn')) {
            e.preventDefault();
            showDeleteConfirmModal('This will <strong>permanently delete</strong> this archived record. This action cannot be undone.', () => {
                const row = link.closest('tr');

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showDeletedToast();

                            // Smoothly fade + collapse the row
                            if (row) {
                                row.style.transition = 'opacity 350ms ease, transform 350ms ease';
                                row.style.opacity = '0';
                                row.style.transform = 'translateX(16px)';
                                setTimeout(() => {
                                    row.style.transition = 'max-height 300ms ease, padding 300ms ease';
                                    row.style.overflow = 'hidden';
                                    row.style.maxHeight = row.offsetHeight + 'px';
                                    requestAnimationFrame(() => {
                                        row.style.maxHeight = '0';
                                        row.style.paddingTop = '0';
                                        row.style.paddingBottom = '0';
                                    });
                                    setTimeout(() => row.remove(), 310);
                                }, 360);
                            }
                        }
                    })
                    .catch(error => console.error('Error deleting:', error));
            });
        } else if (url.includes('history.php') || url.startsWith('?')) {
            e.preventDefault();
            loadTableContent(url);
        }
    });

    // Delete All Archived button handler
    const deleteAllBtn = document.getElementById('delete-all-archived-btn');
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', () => {
            const rowCount = tableContainer.querySelectorAll('tbody tr').length;
            showDeleteConfirmModal(
                `This will <strong>permanently delete all ${rowCount} archived record${rowCount !== 1 ? 's' : ''}</strong> on this page. This action cannot be undone.`,
                () => {
                    fetch('history.php', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: 'action=delete_all_archived'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Stagger-animate all rows out
                            const rows = tableContainer.querySelectorAll('tbody tr');
                            rows.forEach((row, i) => {
                                setTimeout(() => {
                                    row.style.transition = 'opacity 300ms ease, transform 300ms ease';
                                    row.style.opacity = '0';
                                    row.style.transform = 'translateX(20px)';
                                }, i * 40);
                            });

                            const totalDuration = rows.length * 40 + 320;
                            setTimeout(() => {
                                rows.forEach(row => row.remove());
                                // Show empty state message
                                const tbody = tableContainer.querySelector('tbody');
                                if (tbody) {
                                    tbody.innerHTML = `
                                        <tr>
                                            <td colspan="8" class="py-16 text-center">
                                                <div class="flex flex-col items-center gap-3 text-slate-500">
                                                    <i class="fa-solid fa-box-open text-4xl opacity-30"></i>
                                                    <p class="text-sm font-medium">No archived records remaining.</p>
                                                </div>
                                            </td>
                                        </tr>`;
                                }
                                // Hide the Delete All button
                                deleteAllBtn.style.transition = 'opacity 200ms ease';
                                deleteAllBtn.style.opacity = '0';
                                setTimeout(() => deleteAllBtn.remove(), 200);
                            }, totalDuration);

                            showDeleteAllToast(data.count || rows.length);
                        }
                    })
                    .catch(error => console.error('Error deleting all:', error));
                }
            );
        });
    }

    // Intercept filter form changes on the page for automatic live updating
    const filterForm = document.querySelector('form[action="history.php"]');
    if (filterForm) {
        const triggerSearch = () => {
            const formData = new FormData(filterForm);
            const searchParams = new URLSearchParams();
            for (const [key, val] of formData.entries()) {
                if (val) searchParams.append(key, val);
            }
            const url = 'history.php?' + searchParams.toString();
            loadTableContent(url);
        };

        // Listen for input on search textbox (with debounce) and change on other controls
        filterForm.querySelectorAll('input, select').forEach(input => {
            if (input.type === 'text') {
                let debounceTimeout;
                input.addEventListener('input', () => {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(triggerSearch, 300);
                });
            } else {
                input.addEventListener('change', triggerSearch);
            }
        });

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            triggerSearch();
        });
    }

    function loadTableContent(url) {
        tableContainer.style.opacity = '0.5';
        tableContainer.style.transition = 'opacity 150ms ease';

        fetch(url)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                // Update table contents
                const newContent = doc.getElementById('history-table');
                if (newContent) {
                    tableContainer.innerHTML = newContent.innerHTML;
                    window.history.pushState(null, '', url);
                    if (typeof initCustomSelects === 'function') {
                        initCustomSelects();
                    }
                    if (typeof initCustomDatePickers === 'function') {
                        initCustomDatePickers();
                    }
                }

                // Update Reset Filters button visibility
                const newReset = doc.getElementById('reset-filters-container');
                const currentReset = document.getElementById('reset-filters-container');
                if (newReset) {
                    if (currentReset) {
                        currentReset.outerHTML = newReset.outerHTML;
                    } else if (filterForm) {
                        filterForm.appendChild(newReset);
                    }
                } else if (currentReset) {
                    currentReset.remove();
                }

                tableContainer.style.opacity = '1';
            })
            .catch(error => {
                console.error('AJAX load error:', error);
                tableContainer.style.opacity = '1';
            });
    }

    window.addEventListener('popstate', () => {
        loadTableContent(window.location.href);
    });
});

function showArchivedToast() {
    // Remove any existing toasts
    document.querySelectorAll('.success-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'success-toast';
    toast.innerHTML = `
        <div class="success-toast-icon">
            <i class="fa-solid fa-box-archive"></i>
        </div>
        <div class="success-toast-body">
            <p class="success-toast-title">Archived!</p>
            <p class="success-toast-message">Gatepass has been archived successfully.</p>
        </div>
        <button class="success-toast-close" onclick="this.closest('.success-toast').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="success-toast-progress"></div>
    `;

    document.body.appendChild(toast);

    // Trigger animation
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });
    });

    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

function showDeleteConfirmModal(message, onConfirm) {
    const overlay = document.createElement('div');
    overlay.className = 'custom-modal-overlay';

    overlay.innerHTML = `
        <div class="custom-modal-card">
            <div class="custom-modal-icon" style="background:rgba(244,63,94,0.1);border-color:rgba(244,63,94,0.25);color:#f43f5e;">
                <i class="fa-solid fa-trash-can"></i>
            </div>
            <h3 class="custom-modal-title">Delete Permanently?</h3>
            <p class="custom-modal-message">${message}</p>
            <div class="custom-modal-actions">
                <button type="button" class="custom-modal-btn custom-modal-btn-cancel" id="dm-cancel">Cancel</button>
                <button type="button" class="custom-modal-btn" id="dm-confirm"
                    style="background:#f43f5e;color:#fff;box-shadow:0 4px 12px rgba(244,63,94,0.25);">
                    Delete
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);
    setTimeout(() => overlay.classList.add('show'), 10);

    overlay.querySelector('#dm-cancel').addEventListener('click', () => {
        overlay.classList.remove('show');
        setTimeout(() => overlay.remove(), 300);
    });

    overlay.querySelector('#dm-confirm').addEventListener('click', () => {
        overlay.classList.remove('show');
        setTimeout(() => overlay.remove(), 300);
        if (typeof onConfirm === 'function') onConfirm();
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.classList.remove('show');
            setTimeout(() => overlay.remove(), 300);
        }
    });
}

function showDeletedToast() {
    document.querySelectorAll('.success-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'success-toast';
    toast.style.borderColor = 'rgba(244,63,94,0.35)';
    toast.innerHTML = `
        <div class="success-toast-icon" style="background:rgba(244,63,94,0.12);border-color:rgba(244,63,94,0.3);color:#f43f5e;">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <div class="success-toast-body">
            <p class="success-toast-title" style="color:#f43f5e;">Deleted!</p>
            <p class="success-toast-message">Record has been permanently deleted.</p>
        </div>
        <button class="success-toast-close" onclick="this.closest('.success-toast').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="success-toast-progress" style="background:linear-gradient(90deg,#f43f5e,rgba(244,63,94,0.3));"></div>
    `;

    document.body.appendChild(toast);
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}

function showDeleteAllToast(count) {
    document.querySelectorAll('.success-toast').forEach(t => t.remove());

    const toast = document.createElement('div');
    toast.className = 'success-toast';
    toast.style.borderColor = 'rgba(244,63,94,0.35)';
    toast.innerHTML = `
        <div class="success-toast-icon" style="background:rgba(244,63,94,0.12);border-color:rgba(244,63,94,0.3);color:#f43f5e;">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <div class="success-toast-body">
            <p class="success-toast-title" style="color:#f43f5e;">All Deleted!</p>
            <p class="success-toast-message">${count} archived record${count !== 1 ? 's' : ''} permanently deleted.</p>
        </div>
        <button class="success-toast-close" onclick="this.closest('.success-toast').remove()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <div class="success-toast-progress" style="background:linear-gradient(90deg,#f43f5e,rgba(244,63,94,0.3));"></div>
    `;

    document.body.appendChild(toast);
    requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('show')));

    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 500);
    }, 4000);
}
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
