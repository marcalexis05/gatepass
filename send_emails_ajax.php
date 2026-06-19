<?php
/**
 * GatePass Pro - Asynchronous Email Dispatcher
 * 
 * Sends notification emails in the background to prevent user interface timeouts.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/pdf_generator.php';

$gatepass_no = trim($_GET['code'] ?? '');
if (empty($gatepass_no)) {
    http_response_code(400);
    exit('No code provided');
}

// Fetch gatepass details
$stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
$stmt->execute([$gatepass_no]);
$gatepass_data = $stmt->fetch();

if ($gatepass_data) {
    $admin_email = get_setting('admin_email', 'admin@example.com');
    
    // Generate PDF if status is Checked In or Checked Out
    $pdf_path = null;
    if ($gatepass_data['status'] === 'Checked In' || $gatepass_data['status'] === 'Checked Out') {
        $pdf_path = generate_gatepass_pdf($gatepass_data);
    }
    
    // Attempt to send to visitor
    send_gatepass_email($gatepass_data, $gatepass_data['visitor_email'], $gatepass_data['visitor_name'], 'visitor', $pdf_path);
    // Attempt to send to admin
    send_gatepass_email($gatepass_data, $admin_email, 'Administrator', 'admin', $pdf_path);
    
    // Send notification to badilla.princearvy@concentrix.com if status is Checked In or Checked Out
    if ($gatepass_data['status'] === 'Checked In' || $gatepass_data['status'] === 'Checked Out') {
        send_gatepass_email($gatepass_data, 'badilla.princearvy@concentrix.com', 'Prince Arvy Badilla', 'admin', $pdf_path);
    }
    
    // Clean up temporary PDF file if it exists
    if ($pdf_path && file_exists($pdf_path)) {
        unlink($pdf_path);
    }
    
    echo 'Emails dispatched successfully';
} else {
    http_response_code(404);
    exit('Invalid code');
}
?>
