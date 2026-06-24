<?php
$page_title = "Analytics Dashboard";
require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

// Fetch statistics
try {
    // 1. Status Breakdown
    $status_data = $pdo->query("SELECT status, COUNT(*) as count FROM gatepasses GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Department Breakdown
    $dept_data = $pdo->query("SELECT department, COUNT(*) as count FROM gatepasses GROUP BY department ORDER BY count DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Weekly visitor trend (last 7 days)
    $weekly_data = $pdo->query("SELECT visit_date, COUNT(*) as count FROM gatepasses GROUP BY visit_date ORDER BY visit_date DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);
    $weekly_data = array_reverse($weekly_data); // Show chronologically
    
    // 4. Overall counts
    $total_all_time = $pdo->query("SELECT COUNT(*) FROM gatepasses")->fetchColumn();
    $total_checked_in = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked In'")->fetchColumn();
    $total_checked_out = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Checked Out'")->fetchColumn();
    $total_rejected = $pdo->query("SELECT COUNT(*) FROM gatepasses WHERE status = 'Rejected'")->fetchColumn();

} catch (PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>

<div class="space-y-8 pt-0 pb-4">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-black text-white tracking-tight">Access Analytics</h1>
            <p class="text-slate-400 text-sm">Visualize visitor traffic, status breakdowns, and departmental volumes.</p>
        </div>
        <div class="flex gap-3">
            <a href="dashboard.php" class="px-4 py-2 rounded-xl text-xs font-semibold border border-white/10 text-slate-350 hover:text-white hover:border-brand-teal/30 transition-all flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Command Center
            </a>
        </div>
    </div>

    <!-- Analytics Summary Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Registered -->
        <div class="glass-card p-5 rounded-2xl border border-white/05 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest font-display mb-1">Total Registered</span>
            <span class="block text-2xl font-black text-white font-display"><?php echo number_format($total_all_time); ?></span>
        </div>

        <!-- Currently Checked In -->
        <div class="glass-card p-5 rounded-2xl border border-white/05 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest font-display mb-1">Checked In</span>
            <span class="block text-2xl font-black text-brand-teal font-display"><?php echo number_format($total_checked_in); ?></span>
        </div>

        <!-- Total Checked Out -->
        <div class="glass-card p-5 rounded-2xl border border-white/05 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest font-display mb-1">Checked Out</span>
            <span class="block text-2xl font-black text-slate-300 font-display"><?php echo number_format($total_checked_out); ?></span>
        </div>

        <!-- Total Rejected -->
        <div class="glass-card p-5 rounded-2xl border border-white/05 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest font-display mb-1">Rejected Passes</span>
            <span class="block text-2xl font-black text-rose-400 font-display"><?php echo number_format($total_rejected); ?></span>
        </div>
    </div>

    <!-- Charts Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Status Donut Chart -->
        <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-brand-teal"></i> Gatepass Status Distribution
            </h2>
            <div id="status-3d-pie" style="height: 380px;"></div>
        </div>

        <!-- Department Column Chart -->
        <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-brand-teal"></i> Top Departments by Visitor Count
            </h2>
            <div id="dept-3d-column" style="height: 380px;"></div>
        </div>
    </div>

    <!-- Trend Chart -->
    <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
        <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-brand-teal"></i> Visitor Arrival Trend (Last 7 Days)
        </h2>
        <div id="trend-3d-spline" style="height: 400px;"></div>
    </div>
</div>

<!-- Highcharts Script Loader (Local Fallback for Offline / Sandbox environments) -->
<script src="../assets/js/highcharts.js"></script>
<script src="../assets/js/highcharts-3d.js"></script>
<script src="../assets/js/highcharts-exporting.js"></script>

<script>
// Gradient helper function for premium theme
const createGradient = (color1, color2) => {
    return {
        linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
        stops: [
            [0, color1],
            [1, color2]
        ]
    };
};

Highcharts.setOptions({
    chart: {
        backgroundColor: 'transparent',
        style: {
            fontFamily: 'Outfit, sans-serif'
        }
    },
    title: {
        text: null
    },
    credits: {
        enabled: false
    },
    tooltip: {
        backgroundColor: 'rgba(1, 21, 26, 0.95)',
        borderColor: 'rgba(37, 226, 204, 0.15)',
        borderRadius: 12,
        borderWidth: 1,
        shadow: false,
        useHTML: true,
        style: {
            color: '#e2e8f0',
            fontSize: '12px'
        }
    }
});

// Define modern gradient colors for statuses
const statusColorMap = {
    'Pending': createGradient('#f59e0b', '#d97706'),      // Amber
    'Approved': createGradient('#25e2cc', '#0284c7'),     // Cyan/Sky
    'Checked In': createGradient('#10b981', '#047857'),   // Emerald
    'Checked Out': createGradient('#94a3b8', '#475569'),  // Slate
    'Rejected': createGradient('#f43f5e', '#be123c')      // Rose
};

// 1. Status Donut Chart
Highcharts.chart('status-3d-pie', {
    chart: {
        type: 'pie'
    },
    plotOptions: {
        pie: {
            innerSize: '65%',
            borderWidth: 2,
            borderColor: '#01151a',
            allowPointSelect: true,
            cursor: 'pointer',
            dataLabels: {
                enabled: true,
                format: '<b>{point.name}</b>: {point.y}',
                style: {
                    color: '#cbd5e1',
                    fontSize: '11px',
                    textOutline: 'none'
                }
            }
        }
    },
    series: [{
        name: 'Passes',
        data: [
            <?php foreach ($status_data as $row): ?>
            { 
                name: '<?php echo htmlspecialchars($row['status']); ?>', 
                y: <?php echo (int)$row['count']; ?>,
                color: statusColorMap['<?php echo htmlspecialchars($row['status']); ?>'] || createGradient('#25e2cc', '#0284c7')
            },
            <?php endforeach; ?>
        ]
    }]
});

// Department color palette with glowing modern gradients
const deptColors = [
    createGradient('#25e2cc', '#0e9488'), // Teal
    createGradient('#3b82f6', '#1d4ed8'), // Blue
    createGradient('#8b5cf6', '#6d28d9'), // Purple
    createGradient('#ec4899', '#be185d'), // Pink
    createGradient('#f59e0b', '#d97706'), // Amber
    createGradient('#10b981', '#047857'), // Emerald
    createGradient('#06b6d4', '#0891b2'), // Cyan
    createGradient('#6366f1', '#4f46e5')  // Indigo
];

// 2. Department Column Chart
Highcharts.chart('dept-3d-column', {
    chart: {
        type: 'column'
    },
    legend: {
        enabled: false
    },
    colors: deptColors,
    xAxis: {
        categories: [
            <?php foreach ($dept_data as $row): ?>
            '<?php echo htmlspecialchars($row['department']); ?>',
            <?php endforeach; ?>
        ],
        gridLineColor: 'rgba(255, 255, 255, 0.02)',
        lineColor: 'rgba(255, 255, 255, 0.08)',
        labels: {
            style: {
                color: '#94a3b8',
                fontSize: '10px'
            }
        }
    },
    yAxis: {
        title: {
            text: 'Number of Visitors',
            style: {
                color: '#94a3b8',
                fontSize: '11px'
            }
        },
        gridLineColor: 'rgba(255, 255, 255, 0.04)',
        lineColor: 'rgba(255, 255, 255, 0.08)',
        labels: {
            style: {
                color: '#94a3b8'
            }
        }
    },
    plotOptions: {
        column: {
            borderRadius: 6,
            borderWidth: 0,
            colorByPoint: true
        }
    },
    series: [{
        name: 'Visitors',
        data: [
            <?php foreach ($dept_data as $row): ?>
            <?php echo (int)$row['count']; ?>,
            <?php endforeach; ?>
        ]
    }]
});

// 3. Trend Areaspline Chart
Highcharts.chart('trend-3d-spline', {
    chart: {
        type: 'areaspline'
    },
    legend: {
        enabled: false
    },
    colors: ['#25e2cc'],
    xAxis: {
        categories: [
            <?php foreach ($weekly_data as $row): ?>
            '<?php echo date('M d', strtotime($row['visit_date'])); ?>',
            <?php endforeach; ?>
        ],
        gridLineColor: 'rgba(255, 255, 255, 0.02)',
        lineColor: 'rgba(255, 255, 255, 0.08)',
        labels: {
            style: {
                color: '#94a3b8'
            }
        }
    },
    yAxis: {
        title: {
            text: 'Total Visits',
            style: {
                color: '#94a3b8',
                fontSize: '11px'
            }
        },
        gridLineColor: 'rgba(255, 255, 255, 0.04)',
        lineColor: 'rgba(255, 255, 255, 0.08)',
        labels: {
            style: {
                color: '#94a3b8'
            }
        }
    },
    plotOptions: {
        areaspline: {
            fillColor: {
                linearGradient: { x1: 0, y1: 0, x2: 0, y2: 1 },
                stops: [
                    [0, 'rgba(37, 226, 204, 0.25)'],
                    [1, 'rgba(37, 226, 204, 0.01)']
                ]
            },
            marker: {
                lineWidth: 2,
                lineColor: '#25e2cc',
                fillColor: '#01151A',
                radius: 4,
                states: {
                    hover: {
                        radius: 6
                    }
                }
            },
            lineWidth: 3
        }
    },
    series: [{
        name: 'Daily Arrivals',
        data: [
            <?php foreach ($weekly_data as $row): ?>
            <?php echo (int)$row['count']; ?>,
            <?php endforeach; ?>
        ]
    }]
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
