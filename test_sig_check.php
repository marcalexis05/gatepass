<?php
require 'config/database.php';
$stmt = $pdo->query("SELECT gatepass_no, visitor_signature, admin_signature, security_signature FROM gatepasses");
$rows = $stmt->fetchAll();
foreach ($rows as $row) {
    echo "Gatepass: " . $row['gatepass_no'] . "\n";
    foreach (['visitor_signature', 'admin_signature', 'security_signature'] as $col) {
        $data = $row[$col];
        if ($data) {
            $raw_data = explode(',', $data);
            $decoded = base64_decode(end($raw_data));
            $im = @imagecreatefromstring($decoded);
            if ($im) {
                $w = imagesx($im);
                $h = imagesy($im);
                $drawn = 0; $white = 0; $black = 0;
                for ($x = 0; $x < $w; $x++) {
                    for ($y = 0; $y < $h; $y++) {
                        $rgb = imagecolorat($im, $x, $y);
                        $colors = imagecolorsforindex($im, $rgb);
                        if ($colors['alpha'] < 127) {
                            $drawn++;
                            $r = $colors['red'];
                            $g = $colors['green'];
                            $b = $colors['blue'];
                            if ($r > 200 && $g > 200 && $b > 200) $white++;
                            elseif ($r < 50 && $g < 50 && $b < 50) $black++;
                        }
                    }
                }
                echo "  $col: drawn=$drawn, white=$white, black=$black\n";
            } else {
                echo "  $col: failed to load image\n";
            }
        }
    }
}
