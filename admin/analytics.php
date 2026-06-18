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
        <!-- Status 3D Pie Chart -->
        <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-pie text-brand-teal"></i> Gatepass Status Distribution (3D)
            </h2>
            <div id="status-3d-pie" style="height: 380px;"></div>
        </div>

        <!-- Department 3D Column Chart -->
        <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
            <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
                <i class="fa-solid fa-chart-column text-brand-teal"></i> Top Departments by Visitor Count (3D)
            </h2>
            <div id="dept-3d-column" style="height: 380px;"></div>
        </div>
    </div>

    <!-- Trend Chart -->
    <div class="glass-card p-6 rounded-[24px] border border-white/10 relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
        <h2 class="text-base font-bold text-white tracking-tight mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-brand-teal"></i> Visitor Arrival Trend (Last 7 Days) (3D)
        </h2>
        <div id="trend-3d-spline" style="height: 400px;"></div>
    </div>
</div>

<!-- Highcharts Script Loader -->
<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/highcharts-3d.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>

<script>
Highcharts.setOptions({
    colors: ['#25e2cc', '#c4d600', '#e86e25', '#00f5d4', '#94a3b8', '#7209b7', '#4cc9f0'],
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
        backgroundColor: 'rgba(1, 21, 26, 0.9)',
        borderColor: 'rgba(37, 226, 204, 0.3)',
        borderRadius: 16,
        borderWidth: 1,
        shadow: true,
        style: {
            color: '#e2e8f0',
            fontSize: '12px'
        }
    }
});

// Helper color map for statuses
const statusColorMap = {
    'Pending': '#c4d600',
    'Approved': '#25e2cc',
    'Rejected': '#e86e25',
    'Checked In': '#00f5d4',
    'Checked Out': '#94a3b8'
};

// 1. Status 3D Donut Chart
Highcharts.chart('status-3d-pie', {
    chart: {
        type: 'pie',
        options3d: {
            enabled: true,
            alpha: 55,
            beta: 15,
            depth: 45
        }
    },
    plotOptions: {
        pie: {
            innerSize: '55%',
            depth: 35,
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
                color: statusColorMap['<?php echo htmlspecialchars($row['status']); ?>'] || '#25e2cc'
            },
            <?php endforeach; ?>
        ]
    }]
});

// 2. Department 3D Column Chart
Highcharts.chart('dept-3d-column', {
    chart: {
        type: 'column',
        options3d: {
            enabled: true,
            alpha: 12,
            beta: 10,
            depth: 50,
            viewDistance: 25
        }
    },
    legend: {
        enabled: false
    },
    xAxis: {
        categories: [
            <?php foreach ($dept_data as $row): ?>
            '<?php echo htmlspecialchars($row['department']); ?>',
            <?php endforeach; ?>
        ],
        gridLineColor: 'rgba(255, 255, 255, 0.03)',
        lineColor: 'rgba(255, 255, 255, 0.1)',
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
        gridLineColor: 'rgba(255, 255, 255, 0.05)',
        lineColor: 'rgba(255, 255, 255, 0.1)',
        labels: {
            style: {
                color: '#94a3b8'
            }
        }
    },
    plotOptions: {
        column: {
            depth: 35,
            colorByPoint: true,
            borderRadius: 4
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

// 3. Trend 3D Chart
Highcharts.chart('trend-3d-spline', {
    chart: {
        type: 'column',
        options3d: {
            enabled: true,
            alpha: 10,
            beta: 8,
            depth: 40,
            viewDistance: 25
        }
    },
    legend: {
        enabled: false
    },
    xAxis: {
        categories: [
            <?php foreach ($weekly_data as $row): ?>
            '<?php echo date('M d', strtotime($row['visit_date'])); ?>',
            <?php endforeach; ?>
        ],
        gridLineColor: 'rgba(255, 255, 255, 0.03)',
        lineColor: 'rgba(255, 255, 255, 0.1)',
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
        gridLineColor: 'rgba(255, 255, 255, 0.05)',
        lineColor: 'rgba(255, 255, 255, 0.1)',
        labels: {
            style: {
                color: '#94a3b8'
            }
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
