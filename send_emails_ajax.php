<?php
/**
 * GatePass Pro - Asynchronous Email Dispatcher
 * 
 * Sends notification emails in the background to prevent user interface timeouts.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

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
    
    // Attempt to send to visitor
    send_gatepass_email($gatepass_data, $gatepass_data['visitor_email'], $gatepass_data['visitor_name'], 'visitor');
    // Attempt to send to admin
    send_gatepass_email($gatepass_data, $admin_email, 'Administrator', 'admin');
    
    echo 'Emails dispatched successfully';
} else {
    http_response_code(404);
    exit('Invalid code');
}
