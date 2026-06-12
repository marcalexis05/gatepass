<?php
/**
 * GatePass Pro - Public Web Tunnel Utility (Serveo Edition)
 * 
 * Exposes local web server to the public internet using a static Serveo.net subdomain.
 */

require_once __DIR__ . '/config/database.php';

// Single instance lock to prevent duplicate tunnel processes
$lock_file = __DIR__ . '/tunnel.lock';
$lock_fp = fopen($lock_file, 'c');
if (!$lock_fp || !flock($lock_fp, LOCK_EX | LOCK_NB)) {
    echo "⚠️ WARNING: Another instance of the tunnel is already running!\n";
    echo "We will not start a duplicate tunnel.\n";
    exit(0);
}

// Prevent Windows from sleeping while tunnel is running
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $awake_script = __DIR__ . '/includes/keep_awake.ps1';
    if (file_exists($awake_script)) {
        $php_pid = getmypid();
        pclose(popen('start /B powershell -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($awake_script) . ' -ParentPid ' . $php_pid, 'r'));
        echo "💤 Sleep Prevention active: system sleep is suppressed while tunnel is running.\n";
    }
}

// Clear console screen depending on OS
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    system('cls');
} else {
    system('clear');
}

echo "=================================================================\n";
echo "           GATEPASS PRO - SECURE PUBLIC INTERNET TUNNEL          \n";
echo "=================================================================\n";
echo "Initializing secure reverse proxy tunnel via Serveo...\n";
echo "Please keep this window open while using the system externally.\n";
echo "-----------------------------------------------------------------\n\n";

$subdomain = getenv('SERVEO_SUBDOMAIN') ?: 'digital-gatepass';
echo "🔑 Requesting static subdomain: $subdomain.serveo.net\n";

// Open a command channel to serveo.net
$cmd = 'ssh -i C:\\Users\\Pia\\.ssh\\id_rsa -o StrictHostKeyChecking=no -R ' . escapeshellarg($subdomain) . ':80:127.0.0.1:80 serveo.net 2>&1';
$descriptorspec = [
    0 => ["pipe", "r"], // stdin
    1 => ["pipe", "w"], // stdout
    2 => ["pipe", "w"]  // stderr
];

$process = proc_open($cmd, $descriptorspec, $pipes);

if (is_resource($process)) {
    // Disable blocking on stdout to allow smooth reading
    stream_set_blocking($pipes[1], 0);
    
    $tunnel_established = false;
    
    while (true) {
        // Check if process has terminated
        $status = proc_get_status($process);
        if (!$status['running']) {
            echo "\n[ERROR] Serveo tunnel process closed unexpectedly.\n";
            break;
        }
        
        // Read a line of output
        $line = fgets($pipes[1]);
        if ($line !== false && !empty(trim($line))) {
            // Echo raw line output to console for feedback
            echo ">> " . trim($line) . "\n";
            
            // Parse Serveo URL from console output
            if (preg_match('/Forwarding HTTP traffic from https:\/\/([a-zA-Z0-9.-]+\.serveo(?:usercontent)?\.(?:net|com|org))/i', $line, $matches)) {
                $public_domain = $matches[1];
                
                // If it matches the configured static subdomain, normalize it to serveousercontent.com
                $configured_subdomain = getenv('SERVEO_SUBDOMAIN');
                if (!empty($configured_subdomain) && strpos($public_domain, $configured_subdomain) !== false) {
                    $public_domain = $configured_subdomain . '.serveousercontent.com';
                }
                
                $tunnel_established = true;
                
                echo "\n-----------------------------------------------------------------\n";
                echo "🎉 SUCCESS: Static Tunnel is active and public!\n";
                echo "-----------------------------------------------------------------\n";
                echo "🌐 Public URL:  https://" . $public_domain . "/gatepass/\n";
                echo "🔑 Admin URL:   https://" . $public_domain . "/gatepass/admin/\n";
                echo "-----------------------------------------------------------------\n";
                echo "Updating database server_ip to: " . $public_domain . "...\n";
                
                if (update_setting('server_ip', $public_domain)) {
                    echo "✅ Settings table updated. All QR codes are now dynamically live!\n";
                } else {
                    echo "❌ Failed to update settings table in the database.\n";
                }
                echo "-----------------------------------------------------------------\n";
                echo "Press Ctrl+C inside this terminal to terminate the public tunnel.\n\n";
            }
        }
        
        // Small sleep to avoid CPU spinning
        usleep(100000); // 100ms
    }
    
    // Cleanup pipes
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
} else {
    echo "[ERROR] Failed to start SSH client process.\n";
}
?>
