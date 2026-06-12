<?php
$page_title = "Entrance QR Poster";
require_once __DIR__ . '/../includes/auth.php';
// Secure the page
require_login();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$system_name = get_setting('system_name', 'Concentrix Gatepass');
$server_ip = get_setting('server_ip', 'localhost');
$register_url = "http://" . $server_ip . "/gatepass/register.php";
?>

<div class="max-w-xl mx-auto py-4 text-center">
    <!-- Breadcrumb (Hide during print) -->
    <a href="dashboard.php" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors flex items-center justify-center space-x-1.5 mb-8 group no-print">
        <i class="fa-solid fa-chevron-left group-hover:-translate-x-1 transition-transform"></i>
        <span>Back to Admin Dashboard</span>
    </a>

    <!-- Print Poster Design -->
    <div class="glass-card p-10 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden mb-6 flex flex-col items-center" id="qr-poster">
        <!-- Top glowing lights -->
        <div class="absolute inset-0 bg-gradient-to-b from-brand-teal/5 via-transparent to-transparent pointer-events-none"></div>
        
        <div class="w-16 h-16 rounded-2xl bg-brand-teal/10 border border-brand-teal/20 flex items-center justify-center text-brand-teal text-3xl mb-6">
            <i class="fa-solid fa-building-shield"></i>
        </div>

        <h1 class="text-3xl font-black text-white tracking-tight uppercase mb-1 font-display"><?php echo htmlspecialchars($system_name); ?></h1>
        <p class="text-brand-teal font-extrabold text-xs uppercase tracking-widest mb-6">Lobby Registration Terminal</p>
        
        <!-- Large QR Display -->
        <div class="p-6 bg-white rounded-3xl shadow-2xl inline-block mb-6 relative">
            <div id="lobby-qrcode" class="p-2"></div>
        </div>

        <div class="space-y-4 max-w-sm">
            <h3 class="text-white font-bold text-lg">Scan to Register Your Visit</h3>
            <p class="text-slate-400 text-xs leading-relaxed">
                Please point your mobile camera at this QR code. You will be redirected to our registration form. Fill out your particulars and submit to generate your entry gatepass ticket.
            </p>
        </div>

        <div class="mt-8 pt-6 border-t border-dashed border-slate-800/80 w-full">
            <span class="block text-[10px] text-slate-500 uppercase tracking-wider font-bold mb-1">Manual Access URL</span>
            <span class="text-xs text-brand-teal font-bold select-all break-all"><?php echo htmlspecialchars($register_url); ?></span>
        </div>
    </div>

    <!-- Actions (Hide during print) -->
    <div class="no-print">
        <button onclick="window.print()"
                class="px-6 py-3 bg-brand-teal hover:bg-[#1fd4be] active:scale-95 transition-all text-dark-900 font-bold text-sm rounded-xl shadow-lg shadow-brand-teal/10 inline-flex items-center space-x-2">
            <i class="fa-solid fa-print"></i>
            <span>Print Registration Poster</span>
        </button>
        <p class="text-[10px] text-slate-500 mt-3 max-w-xs mx-auto">
            *Print this poster and display it at the reception counter or security lobby entrance.
        </p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Generate large lobby entry QR code
    const registerUrl = "<?php echo $register_url; ?>";
    new QRCode(document.getElementById("lobby-qrcode"), {
        text: registerUrl,
        width: 240,
        height: 240,
        colorDark : "#0b0f19",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
});
</script>

<style>
/* Print Layout overrides to optimize the printed poster */
@media print {
    .no-print, header, footer, nav {
        display: none !important;
    }
    body {
        background-color: white !important;
        color: black !important;
        background-image: none !important;
    }
    #qr-poster {
        border: 2px solid #000 !important;
        background: white !important;
        color: black !important;
        box-shadow: none !important;
        margin: 0 auto;
        padding: 40px !important;
        max-width: 100%;
        backdrop-filter: none !important;
        page-break-after: avoid;
    }
    h1, h3, span {
        color: black !important;
    }
    p.text-slate-400 {
        color: #333 !important;
    }
    span.text-brand-teal {
        color: black !important;
    }
    #lobby-qrcode {
        border: 1px solid #000;
    }
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
