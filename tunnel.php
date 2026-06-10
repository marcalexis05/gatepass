<?php
/**
 * GatePass Pro - Public Web Tunnel Utility
 * 
 * Exposes local web server to the public internet using Cloudflare Tunnel.
 * Supports both custom Zero Trust tunnels (token) and free Quick Tunnels.
 */

require_once __DIR__ . '/config/database.php';

// Clear console screen depending on OS
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    system('cls');
} else {
    system('clear');
}

echo "=================================================================\n";
echo "           GATEPASS PRO - SECURE PUBLIC INTERNET TUNNEL          \n";
echo "=================================================================\n";
echo "Initializing secure reverse proxy tunnel...\n";
echo "Please keep this window open while using the system externally.\n";
echo "-----------------------------------------------------------------\n\n";

$token = getenv('CLOUDFLARE_TUNNEL_TOKEN');
$system_url = getenv('SYSTEM_URL');

if (!empty($token)) {
    echo "🔑 Found Cloudflare Token in .env configuration.\n";
    echo "Connecting using your Named Tunnel...\n";
    // escapeshellarg handles quotes properly on Windows too
    $cmd = '"' . __DIR__ . '/cloudflared.exe" tunnel run --token ' . escapeshellarg($token) . ' 2>&1';
} else {
    echo "💡 No Cloudflare Token found. Launching a free Quick Tunnel...\n";
    $cmd = '"' . __DIR__ . '/cloudflared.exe" tunnel --url http://127.0.0.1:80 2>&1';
}

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
            echo "\n[ERROR] Cloudflare tunnel process closed unexpectedly.\n";
            break;
        }
        
        // Read a line of output
        $line = fgets($pipes[1]);
        if ($line !== false && !empty(trim($line))) {
            // Echo raw line output to console for feedback
            echo ">> " . trim($line) . "\n";
            
            // Scenario A: Custom Tunnel Token (look for connection registered log)
            if (!empty($token) && !$tunnel_established) {
                if (stripos($line, 'Registered tunnel connection') !== false) {
                    $tunnel_established = true;
                    
                    $display_url = !empty($system_url) ? $system_url : 'localhost';
                    
                    echo "\n-----------------------------------------------------------------\n";
                    echo "🎉 SUCCESS: Named Tunnel connection is active!\n";
                    echo "-----------------------------------------------------------------\n";
                    echo "🌐 Public URL:  https://" . $display_url . "/gatepass/\n";
                    echo "🔑 Admin URL:   https://" . $display_url . "/gatepass/admin/\n";
                    echo "-----------------------------------------------------------------\n";
                    
                    if (!empty($system_url)) {
                        echo "Updating database server_ip to: " . $system_url . "...\n";
                        if (update_setting('server_ip', $system_url)) {
                            echo "✅ Settings table updated. All QR codes are now dynamically live!\n";
                        } else {
                            echo "❌ Failed to update settings table in the database.\n";
                        }
                    } else {
                        echo "⚠️ Warning: SYSTEM_URL is empty in .env. Database server_ip not updated.\n";
                    }
                    echo "-----------------------------------------------------------------\n";
                    echo "Press Ctrl+C inside this terminal to terminate the public tunnel.\n\n";
                }
            }
            
            // Scenario B: Quick Tunnel (parse URL from console)
            if (empty($token) && !$tunnel_established) {
                if (preg_match('/https:\/\/([a-zA-Z0-9.-]+\.trycloudflare\.com)/i', $line, $matches)) {
                    $public_domain = $matches[1];
                    $tunnel_established = true;
                    
                    echo "\n-----------------------------------------------------------------\n";
                    echo "🎉 SUCCESS: Quick Tunnel is active and public!\n";
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
    echo "[ERROR] Failed to start Cloudflare Tunnel process.\n";
}
?>
