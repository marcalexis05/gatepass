<?php
require 'config/database.php';
require 'includes/pdf_generator.php';

$stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
$stmt->execute(['CNX-20260623-0011']);
$gp = $stmt->fetch();

if ($gp) {
    $pdf_path = generate_gatepass_pdf($gp);
    if ($pdf_path && file_exists($pdf_path)) {
        copy($pdf_path, 'test_output.pdf');
        unlink($pdf_path);
        echo "Generated test_output.pdf successfully from CNX-20260623-0011\n";
    } else {
        echo "Failed to generate PDF path\n";
    }
} else {
    echo "Gatepass CNX-20260623-0011 not found\n";
}
