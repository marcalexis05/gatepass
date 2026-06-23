<?php
/**
 * Security Team Email Notification — Test Script
 *
 * Sends a PREVIEW of the Security Team Check-In and Check-Out
 * notification emails to the IT Team (admin) email instead of
 * phupd.reception@concentrix.com.
 *
 * ⚠️  This file is for TESTING PURPOSES ONLY.
 *     Access is blocked via .htaccess — run via CLI or temporary
 *     direct include if needed.
 *
 * CLI usage:  php tests/test_security_email.php
 */

// Adjust paths since this file is inside tests/
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/mailer.php';

$admin_email = get_setting('admin_email', 'admin@example.com');

// --- Dummy gatepass data that mimics a real record ---
$dummy_gatepass = [
    'gatepass_no'        => 'GP-TEST-' . strtoupper(substr(md5(time()), 0, 6)),
    'visitor_name'       => 'Juan Dela Cruz',
    'visitor_email'      => 'juan.delacruz@concentrix.com',
    'department'         => 'IT Infrastructure',
    'eid'                => 'EID-00123',
    'purpose'            => 'Equipment Delivery',
    'visit_date'         => date('Y-m-d'),
    'time_in'            => date('H:i:s'),
    'time_out'           => date('H:i:s', strtotime('+2 hours')),
    'manager_name'       => 'Marc Alexis Evangelista',
    'security_name'      => 'Test Security Guard',
    'admin_signature'    => '',
    'visitor_signature'  => '',
    'security_signature' => '',
    'status'             => 'Checked In',
    'material_desc'      => 'Laptop Computer',
    'material_brand'     => 'Dell',
    'material_serial'    => 'SN-DELL-2024-XYZ',
    'material_qty'       => '1',
];

$results = [];

// TEST 1: Check-In → IT Admin email using Security template
$checkin_gp = $dummy_gatepass;
$checkin_gp['status'] = 'Checked In';

$sent_checkin = send_gatepass_email(
    $checkin_gp,
    $admin_email,
    'IT Team (Security Test)',
    'security',
    null
);

$results[] = [
    'test'    => '📩 Check-In Notification (Security Team Template)',
    'sent_to' => $admin_email,
    'status'  => $sent_checkin ? '✅ Sent Successfully' : '❌ Failed — Check PHP error log',
    'note'    => 'Mimics what phupd.reception@concentrix.com will receive on Check-In (no PDF)',
];

// TEST 2: Check-Out → IT Admin email using Security template
$checkout_gp = $dummy_gatepass;
$checkout_gp['status'] = 'Checked Out';

$sent_checkout = send_gatepass_email(
    $checkout_gp,
    $admin_email,
    'IT Team (Security Test)',
    'security',
    null
);

$results[] = [
    'test'    => '📩 Check-Out Notification (Security Team Template)',
    'sent_to' => $admin_email,
    'status'  => $sent_checkout ? '✅ Sent Successfully' : '❌ Failed — Check PHP error log',
    'note'    => 'Mimics what phupd.reception@concentrix.com will receive on Check-Out (with PDF in production)',
];

// CLI-friendly output
if (php_sapi_name() === 'cli') {
    echo "\n=== Security Team Email Test Results ===\n";
    foreach ($results as $r) {
        echo "\n{$r['test']}\n";
        echo "  Sent To : {$r['sent_to']}\n";
        echo "  Result  : {$r['status']}\n";
        echo "  Note    : {$r['note']}\n";
    }
    echo "\nDone.\n";
} else {
    // Should not be accessible via browser (blocked by .htaccess)
    http_response_code(403);
    exit('Access denied. Run this script from the command line: php tests/test_security_email.php');
}
