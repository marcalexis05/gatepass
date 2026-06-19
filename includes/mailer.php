<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load PHPMailer files
require_once __DIR__ . '/../libs/PHPMailer/Exception.php';
require_once __DIR__ . '/../libs/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../libs/PHPMailer/SMTP.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Sends a gatepass notification email.
 * 
 * @param array $gatepass Gatepass record data
 * @param string $recipient_email Email to send to
 * @param string $recipient_name Name of recipient
 * @param string $role 'visitor' or 'admin'
 * @param string|null $pdf_path File path to the PDF attachment
 * @return bool True on success, false on failure
 */
function send_gatepass_email($gatepass, $recipient_email, $recipient_name, $role = 'visitor', $pdf_path = null) {
    // Get SMTP configurations from database settings
    $smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_setting('smtp_port', '587');
    $smtp_secure = get_setting('smtp_secure', 'tls');
    $smtp_user = get_setting('smtp_user', '');
    $smtp_pass = get_setting('smtp_pass', '');
    $admin_email = get_setting('admin_email', 'admin@example.com');
    $system_name = get_setting('system_name', 'Concentrix Gatepass');
    
    // If SMTP details are not configured, log/return false (but gracefully, so registration still works)
    if (empty($smtp_user) || empty($smtp_pass)) {
        error_log("Mailer Error: SMTP credentials are not configured in system settings.");
        return false;
    }

    $mail = new PHPMailer(true);
    $temp_sig_files = [];

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = ($smtp_secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$smtp_port;
        $mail->Timeout    = 7; // Shorter timeout to prevent 502/Gateway errors
        
        // Disable SSL verification if needed (common for local development)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom($smtp_user, $system_name);
        $mail->addAddress($recipient_email, $recipient_name);

        // Attach PDF if provided
        if ($pdf_path && file_exists($pdf_path)) {
            $mail->addAttachment($pdf_path, 'Concentrix_GatePass_' . $gatepass['gatepass_no'] . '.pdf');
        }

        // Content
        $mail->isHTML(true);
        
        // Embedded CIDs for signatures & logo
        $visitor_sig_cid = '';
        $admin_sig_cid = '';
        $security_sig_cid = '';
        
        // Embed Concentrix logo
        $logo_path = __DIR__ . '/../assets/logo-concentrix.png';
        if (file_exists($logo_path)) {
            $mail->addEmbeddedImage($logo_path, 'logo_concentrix', 'logo-concentrix.png');
        }
        
        // Embed signature images
        if ($gatepass['status'] === 'Checked In' || $gatepass['status'] === 'Checked Out') {
            if (!empty($gatepass['visitor_signature'])) {
                $visitor_sig_file = tempnam(sys_get_temp_dir(), 'esig_') . '.png';
                $data = explode(',', $gatepass['visitor_signature']);
                $img_data = base64_decode(end($data));
                file_put_contents($visitor_sig_file, $img_data);
                
                $mail->addEmbeddedImage($visitor_sig_file, 'visitor_sig', 'visitor_sig.png');
                $visitor_sig_cid = 'cid:visitor_sig';
                $temp_sig_files[] = $visitor_sig_file;
            }
            if (!empty($gatepass['admin_signature'])) {
                $admin_sig_file = tempnam(sys_get_temp_dir(), 'esig_') . '.png';
                $data = explode(',', $gatepass['admin_signature']);
                $img_data = base64_decode(end($data));
                file_put_contents($admin_sig_file, $img_data);
                
                $mail->addEmbeddedImage($admin_sig_file, 'admin_sig', 'admin_sig.png');
                $admin_sig_cid = 'cid:admin_sig';
                $temp_sig_files[] = $admin_sig_file;
            }
            if (!empty($gatepass['security_signature'])) {
                $security_sig_file = tempnam(sys_get_temp_dir(), 'esig_') . '.png';
                $data = explode(',', $gatepass['security_signature']);
                $img_data = base64_decode(end($data));
                file_put_contents($security_sig_file, $img_data);
                
                $mail->addEmbeddedImage($security_sig_file, 'security_sig', 'security_sig.png');
                $security_sig_cid = 'cid:security_sig';
                $temp_sig_files[] = $security_sig_file;
            }
        }
        
        // Subject and dynamic body styling based on role and status
        if ($gatepass['status'] === 'Checked In' || $gatepass['status'] === 'Checked Out') {
            if ($role === 'admin') {
                if ($gatepass['status'] === 'Checked In') {
                    $mail->Subject = "Visitor Checked In: " . $gatepass['gatepass_no'] . " - " . $gatepass['visitor_name'];
                } else {
                    $mail->Subject = "Visitor Checked Out: " . $gatepass['gatepass_no'] . " - " . $gatepass['visitor_name'];
                }
            } else {
                if ($gatepass['status'] === 'Checked In') {
                    $mail->Subject = "Check-In Confirmed: " . $gatepass['gatepass_no'];
                } else {
                    $mail->Subject = "Check-Out Confirmed: " . $gatepass['gatepass_no'];
                }
            }
            $body = get_concentrix_gatepass_html_template($gatepass, $system_name, $visitor_sig_cid, $admin_sig_cid, $security_sig_cid);
        } else {
            if ($role === 'admin') {
                $mail->Subject = "New Gatepass Request: " . $gatepass['gatepass_no'] . " - " . $gatepass['visitor_name'];
                $body = get_admin_email_template($gatepass, $system_name);
            } else {
                $mail->Subject = "Your Digital Gatepass: " . $gatepass['gatepass_no'];
                $body = get_visitor_email_template($gatepass, $system_name);
            }
        }

        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

        $mail->send();
        
        // Clean up temporary signature files
        foreach ($temp_sig_files as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        return true;
    } catch (Exception $e) {
        // Clean up temporary signature files
        foreach ($temp_sig_files as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Admin email template with sleek, premium design
function get_admin_email_template($gp, $sys_name) {
    $server_ip = get_setting('server_ip', 'localhost');
    $verify_url = "http://" . $server_ip . "/gatepass/verify.php?code=" . $gp['gatepass_no'];
    $dashboard_url = "http://" . $server_ip . "/gatepass/admin/dashboard.php";
    
    return "
    <div style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 30px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 6px solid #4f46e5;'>
            <div style='padding: 25px; text-align: center; background-color: #4f46e5; color: #ffffff;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>$sys_name Notification</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>New Visitor Gatepass Request Submitted</p>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px; color: #374151; margin-top: 0;'>Hello Admin,</p>
                <p style='font-size: 15px; color: #4b5563;'>A new visitor has submitted a gatepass request. Please review the details below:</p>
                
                <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0; border: 1px solid #e5e7eb;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Gatepass No:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['gatepass_no']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Visitor Name:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['visitor_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Email:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['visitor_email']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Purpose of Visit:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['purpose']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Program/Department:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['department']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Scheduled Date:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['visit_date']}</td>
                        </tr>
                    </table>
                </div>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$verify_url' style='background-color: #10b981; color: #ffffff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);'>Approve & Verify Pass</a>
                </div>
                
                <p style='font-size: 14px; color: #9ca3af; text-align: center; margin-top: 30px;'>
                    You can also view all requests in the <a href='$dashboard_url' style='color: #4f46e5; text-decoration: none;'>Admin Dashboard</a>.
                </p>
            </div>
            <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;'>
                This is an automated message from $sys_name. Please do not reply directly to this email.
            </div>
        </div>
    </div>";
}

// Visitor email template with sleek, premium design
function get_visitor_email_template($gp, $sys_name) {
    $server_ip = get_setting('server_ip', 'localhost');
    $pass_url = "http://" . $server_ip . "/gatepass/success.php?code=" . $gp['gatepass_no'];
    
    return "
    <div style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 30px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 6px solid #10b981;'>
            <div style='padding: 25px; text-align: center; background-color: #10b981; color: #ffffff;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>$sys_name</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Your Visitor Gatepass Request is Received!</p>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px; color: #374151; margin-top: 0;'>Hello {$gp['visitor_name']},</p>
                <p style='font-size: 15px; color: #4b5563;'>Thank you for registering. Your request has been registered and is currently pending verification. Here is your digital gatepass summary:</p>
                
                <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0; border: 1px solid #e5e7eb;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Gatepass No:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['gatepass_no']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Program/Department:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['department']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Visit Date:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['visit_date']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Initial Status:</td>
                            <td style='padding: 8px 0; color: #10b981; font-weight: bold; font-size: 14px;'>PENDING REVIEW</td>
                        </tr>
                    </table>
                </div>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$pass_url' style='background-color: #10b981; color: #ffffff; padding: 12px 30px; border-radius: 6px; text-decoration: none; font-weight: bold; font-size: 15px; display: inline-block; box-shadow: 0 4px 6px -1px rgba(16, 185, 129, 0.2);'>View Digital Gatepass / QR Code</a>
                </div>
                
                <p style='font-size: 14px; color: #4b5563;'>
                    <strong>How to use:</strong> Please present the digital gatepass QR code on your phone to the security desk upon arrival. They will scan it to verify and record your check-in.
                </p>
            </div>
            <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;'>
                This is an automated message. Please contact administrative office if you have any questions.
            </div>
        </div>
    </div>";
}

// Admin checkout email template with sleek, premium design
function get_admin_checkout_email_template($gp, $sys_name) {
    $server_ip = get_setting('server_ip', 'localhost');
    $dashboard_url = "http://" . $server_ip . "/gatepass/admin/dashboard.php";
    $checkout_time = $gp['time_out'] ? date('h:i A', strtotime($gp['time_out'])) : date('h:i A');
    
    return "
    <div style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 30px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 6px solid #4f46e5;'>
            <div style='padding: 25px; text-align: center; background-color: #4f46e5; color: #ffffff;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>$sys_name Notification</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Visitor Checked Out Successfully</p>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px; color: #374151; margin-top: 0;'>Hello Admin,</p>
                <p style='font-size: 15px; color: #4b5563;'>The following visitor has checked out of the premises:</p>
                
                <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0; border: 1px solid #e5e7eb;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Gatepass No:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['gatepass_no']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Visitor Name:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['visitor_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Check-In Time:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>" . ($gp['time_in'] ? date('h:i A', strtotime($gp['time_in'])) : 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Check-Out Time:</td>
                            <td style='padding: 8px 0; color: #ef4444; font-weight: bold; font-size: 14px;'>$checkout_time</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Program/Department:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['department']}</td>
                        </tr>
                    </table>
                </div>
                
                <p style='font-size: 14px; color: #9ca3af; text-align: center; margin-top: 30px;'>
                    You can view the full history log in the <a href='$dashboard_url' style='color: #4f46e5; text-decoration: none;'>Admin Dashboard</a>.
                </p>
            </div>
            <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;'>
                This is an automated message from $sys_name. Please do not reply directly to this email.
            </div>
        </div>
    </div>";
}

// Visitor checkout email template with sleek, premium design
function get_visitor_checkout_email_template($gp, $sys_name) {
    $checkout_time = $gp['time_out'] ? date('h:i A', strtotime($gp['time_out'])) : date('h:i A');
    
    return "
    <div style='font-family: Arial, sans-serif; background-color: #f3f4f6; padding: 30px; margin: 0;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); border-top: 6px solid #ef4444;'>
            <div style='padding: 25px; text-align: center; background-color: #ef4444; color: #ffffff;'>
                <h1 style='margin: 0; font-size: 24px; font-weight: bold;'>$sys_name</h1>
                <p style='margin: 5px 0 0 0; opacity: 0.9;'>Check-Out Confirmed</p>
            </div>
            <div style='padding: 30px;'>
                <p style='font-size: 16px; color: #374151; margin-top: 0;'>Hello {$gp['visitor_name']},</p>
                <p style='font-size: 15px; color: #4b5563;'>This email confirms that you have successfully checked out of the premises. Thank you for your visit!</p>
                
                <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0; border: 1px solid #e5e7eb;'>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Gatepass No:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; font-size: 14px;'>{$gp['gatepass_no']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Check-In Time:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>" . ($gp['time_in'] ? date('h:i A', strtotime($gp['time_in'])) : 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Check-Out Time:</td>
                            <td style='padding: 8px 0; color: #ef4444; font-weight: bold; font-size: 14px;'>$checkout_time</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Program/Department:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['department']}</td>
                        </tr>
                    </table>
                </div>
                
                <p style='font-size: 14px; color: #4b5563;'>
                    We hope you had a pleasant visit. Have a safe journey home!
                </p>
            </div>
            <div style='background-color: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 12px;'>
                This is an automated message. Please do not reply directly to this email.
            </div>
        </div>
    </div>";
}

/**
 * Returns the HTML email template formatted like the Concentrix Material Movement Gate Pass.
 */
function get_concentrix_gatepass_html_template($gp, $sys_name, $visitor_sig_cid, $admin_sig_cid, $security_sig_cid) {
    global $pdo;
    
    $date_str = date('M d, Y', strtotime($gp['visit_date']));
    $eid_val = htmlspecialchars($gp['eid'] ?: 'N/A');
    $manager_name_val = htmlspecialchars($gp['manager_name'] ?: '______________________');
    $security_name_val = htmlspecialchars($gp['security_name'] ?: '______________________');
    $ingress_date_val = ($gp['status'] === 'Checked Out' && !empty($gp['time_out'])) ? date('M d, Y', strtotime($gp['visit_date'])) : '';
    $ingress_verified_text = '&nbsp;';
    
    // Fetch materials
    $materials = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM gatepass_materials WHERE gatepass_no = ? ORDER BY id ASC");
        $stmt->execute([$gp['gatepass_no']]);
        $materials = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback to empty array
    }
    
    // Legacy fallback
    if (empty($materials) && !empty($gp['material_desc'])) {
        $materials = [[
            'material_serial' => $gp['material_serial'],
            'material_desc' => $gp['material_desc'],
            'material_brand' => $gp['material_brand'],
            'material_qty' => $gp['material_qty'],
            'purpose' => $gp['purpose']
        ]];
    }
    
    $items_text = '';
    foreach ($materials as $index => $mat) {
        $serial = htmlspecialchars($mat['material_serial'] ?: 'N/A');
        $desc = htmlspecialchars($mat['material_desc'] ?: 'N/A');
        $brand = htmlspecialchars($mat['material_brand'] ?: 'N/A');
        $qty = htmlspecialchars($mat['material_qty'] ?: '1');
        $remarks = htmlspecialchars($mat['purpose'] ?: '-');
        if (strlen($remarks) > 30) {
            $remarks = substr($remarks, 0, 27) . '...';
        }
        if ($index > 0) {
            $items_text .= '<br/>';
        }
        $items_text .= ($index + 1) . ". {$desc} (Qty: {$qty}, Brand: {$brand}, S.No: {$serial}, Remarks: {$remarks})";
    }
    
    $materials_rows_html = '';
    $max_rows = 4;
    $rows_count = count($materials);
    
    for ($i = 0; $i < $max_rows; $i++) {
        if ($i < $rows_count) {
            $mat = $materials[$i];
            $serial = htmlspecialchars($mat['material_serial'] ?: 'N/A');
            $desc = htmlspecialchars($mat['material_desc'] ?: 'N/A');
            $brand = htmlspecialchars($mat['material_brand'] ?: 'N/A');
            $qty = htmlspecialchars($mat['material_qty'] ?: '1');
            $remarks = htmlspecialchars($mat['purpose'] ?: '-');
            if (strlen($remarks) > 30) {
                $remarks = substr($remarks, 0, 27) . '...';
            }
            $remarks_style = "font-style: italic;";
        } else {
            // Empty placeholder row
            $serial = '&nbsp;';
            $desc = '&nbsp;';
            $brand = '&nbsp;';
            $qty = '&nbsp;';
            $remarks = '&nbsp;';
            $remarks_style = "";
        }
        
        $materials_rows_html .= "
        <tr style=\"height: 32px;\">
            <td style=\"padding: 6px 8px; border-right: 1px solid #cbd5e1; text-align: center; color: #000000; word-break: break-word;\">{$serial}</td>
            <td style=\"padding: 6px 8px; border-right: 1px solid #cbd5e1; text-align: left; color: #000000; word-break: break-word;\">{$desc}</td>
            <td style=\"padding: 6px 8px; border-right: 1px solid #cbd5e1; text-align: center; color: #000000; word-break: break-word;\">{$brand}</td>
            <td style=\"padding: 6px 8px; border-right: 1px solid #cbd5e1; text-align: center; color: #000000; word-break: break-word;\">{$qty}</td>
            <td style=\"padding: 6px 8px; text-align: left; color: #000000; word-break: break-word; {$remarks_style}\">{$remarks}</td>
        </tr>";
    }
    
    $materials_table_html = "
    <table style=\"width: 100%; border-collapse: collapse; border: 1px solid #cbd5e1; font-size: 11px; text-align: left; table-layout: fixed;\">
        <thead>
            <tr style=\"background-color: #f1f5f9; height: 30px;\">
                <th style=\"border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; width: 22%; color: #000000; font-weight: bold;\">S. No.</th>
                <th style=\"border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; width: 28%; color: #000000; font-weight: bold;\">Material Description</th>
                <th style=\"border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; width: 18%; color: #000000; font-weight: bold;\">Brand</th>
                <th style=\"border-bottom: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; width: 10%; color: #000000; font-weight: bold;\">Qty.</th>
                <th style=\"border-bottom: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; width: 22%; color: #000000; font-weight: bold;\">Remarks</th>
            </tr>
        </thead>
        <tbody>
            {$materials_rows_html}
        </tbody>
    </table>";
    
    $visitor_sig_img = '';
    if (!empty($visitor_sig_cid)) {
        $visitor_sig_img = '<img src="' . htmlspecialchars($visitor_sig_cid) . '" style="max-height: 45px; max-width: 100%; display: block; margin: 0 auto;" alt="Visitor Signature" />';
    } else {
        $visitor_sig_img = '<span style="font-style: italic; color: #000000; font-size: 11px;">No Signature</span>';
    }
    
    $admin_sig_img = '';
    if (!empty($admin_sig_cid)) {
        $admin_sig_img = '<img src="' . htmlspecialchars($admin_sig_cid) . '" style="max-height: 45px; max-width: 100%; display: block; margin: 0 auto;" alt="IT Incharge Signature" />';
    } else {
        $admin_sig_img = '<span style="color: #000000; font-weight: bold; font-size: 11px;">REQUIRED SIGNATURE</span>';
    }
    
    $security_sig_img = '';
    if (!empty($security_sig_cid)) {
        $security_sig_img = '<img src="' . htmlspecialchars($security_sig_cid) . '" style="max-height: 45px; max-width: 100%; display: block; margin: 0 auto;" alt="Security Signature" />';
    } else {
        if ($gp['status'] === 'Checked Out') {
            $security_sig_img = '<span style="color: #000000; font-weight: bold; font-size: 11px;">RELEASED BY SECURITY</span>';
        } elseif ($gp['status'] === 'Checked In') {
            $security_sig_img = '<span style="color: #000000; font-weight: bold; font-size: 11px;">INGRESS STAMPED</span>';
        } else {
            $security_sig_img = '<span style="color: #000000; font-style: italic; font-size: 11px;">Pending Action</span>';
        }
    }
    
    return "
    <style>
        @media only screen and (max-width: 600px) {
            .email-body {
                padding: 10px !important;
            }
            .email-container {
                padding: 12px !important;
                width: 100% !important;
                border-width: 1px !important;
            }
            .header-table td {
                display: block !important;
                width: 100% !important;
                text-align: center !important;
                margin-bottom: 10px !important;
            }
            .header-table img {
                margin: 0 auto !important;
            }
            .header-right {
                text-align: center !important;
                margin-top: 10px !important;
            }
            .particulars-table td {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }
            .particulars-table td[colspan=\"3\"] {
                width: 100% !important;
            }
            .signature-table td {
                display: block !important;
                width: 100% !important;
                margin-bottom: 15px !important;
            }
            .signature-space {
                display: none !important;
            }
            .sec-signature-table td {
                display: block !important;
                width: 100% !important;
            }
            .sec-signature-space {
                display: none !important;
            }
        }
    </style>
    <div class=\"email-body\" style=\"font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f3f4f6; padding: 20px; color: #000000; margin: 0;\">
        <div class=\"email-container\" style=\"max-width: 650px; margin: 0 auto; width: 100%; background-color: #ffffff; border: 3px double #0f172a; padding: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); box-sizing: border-box;\">
            
            <!-- Header -->
            <table class=\"header-table\" style=\"width: 100%; border-collapse: collapse; margin-bottom: 15px;\">
                <tr>
                    <td style=\"width: 50%; text-align: left; vertical-align: middle; padding-bottom: 10px;\">
                        <img src=\"cid:logo_concentrix\" alt=\"Concentrix Logo\" style=\"max-width: 130px; height: auto; display: block;\" />
                    </td>
                    <td class=\"header-right\" style=\"width: 50%; text-align: right; vertical-align: middle; font-size: 11px; color: #000000; padding-bottom: 10px;\">
                        <div style=\"margin-bottom: 5px;\">
                            <strong>GP ID:</strong> 
                            <span style=\"border-bottom: 1px solid #1e293b; padding-bottom: 1px; font-weight: bold; color: #000;\">{$gp['gatepass_no']}</span>
                        </div>
                        <div>
                            <strong>DATE:</strong> 
                            <span style=\"border-bottom: 1px solid #1e293b; padding-bottom: 1px; font-weight: bold; color: #000;\">{$date_str}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan=\"2\" style=\"text-align: center; padding-top: 10px; padding-bottom: 10px; vertical-align: middle;\">
                        <div style=\"font-size: 13.5px; font-weight: bold; color: #000000; margin: 0;\">Concentrix UP-1</div>
                        <div style=\"font-size: 9px; color: #000000; margin: 2px 0;\">Ground-4th Floor Building-D UP Technohub Quezon City</div>
                        <div style=\"font-size: 18px; font-weight: bold; color: #000000; margin: 6px 0; text-decoration: underline;\">GATE PASS</div>
                        <div style=\"font-size: 11px; font-weight: bold; color: #000000; text-decoration: underline;\">RETURNABLE / NON-RETURNABLE</div>
                        <div style=\"font-size: 11.5px; font-weight: bold; color: #000000; margin-top: 3px; text-decoration: underline;\">MATERIAL MOVEMENT</div>
                    </td>
                </tr>
            </table>
            
            <hr style=\"border: 0; border-top: 1px solid #cbd5e1; margin: 15px 0;\" />
            
            <!-- Particulars Section -->
            <table class=\"particulars-table\" style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 13px;\">
                <tr>
                    <td style=\"padding: 6px 0; color: #000000; width: 25%; font-weight: bold; vertical-align: middle;\">Name</td>
                    <td style=\"padding: 6px 0; border-bottom: 1px solid #94a3b8; font-weight: bold; color: #000000; width: 75%; vertical-align: middle;\">
                        {$gp['visitor_name']}
                    </td>
                </tr>
                <tr>
                    <td style=\"padding: 6px 0; color: #000000; width: 25%; font-weight: bold; vertical-align: middle;\">Program/Department</td>
                    <td style=\"padding: 6px 0; border-bottom: 1px solid #94a3b8; font-weight: bold; color: #000000; width: 75%; vertical-align: middle;\">
                        {$gp['department']}
                    </td>
                </tr>
                <tr>
                    <td style=\"padding: 6px 0; color: #000000; width: 25%; font-weight: bold; vertical-align: middle;\">EID</td>
                    <td style=\"padding: 6px 0; border-bottom: 1px solid #94a3b8; font-weight: bold; color: #000000; width: 75%; vertical-align: middle;\">
                        {$eid_val}
                    </td>
                </tr>
                <tr>
                    <td style=\"padding: 6px 0; color: #000000; width: 25%; font-weight: bold; vertical-align: middle;\">Email</td>
                    <td style=\"padding: 6px 0; border-bottom: 1px solid #94a3b8; color: #000000; width: 75%; vertical-align: middle;\">
                        {$gp['visitor_email']}
                    </td>
                </tr>
            </table>
            
            <!-- Materials Table -->
            <div style=\"margin-bottom: 20px; overflow-x: auto;\">
                {$materials_table_html}
            </div>
            
            <!-- Signatures Side-by-Side -->
            <table class=\"signature-table\" style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px;\">
                <tr>
                    <!-- Visitor Signature -->
                    <td style=\"width: 45%; text-align: center; vertical-align: bottom;\">
                        <div style=\"min-height: 50px; padding: 0 5px; margin-bottom: 5px;\">
                            {$visitor_sig_img}
                        </div>
                        <div style=\"border-top: 1.5px solid #000000; font-weight: bold; font-size: 13px; padding-top: 5px; color: #000000;\">
                            {$gp['visitor_name']}
                        </div>
                        <div style=\"font-size: 10px; color: #000000; font-weight: bold;\">Requestor Name and Signature</div>
                    </td>
                    <td class=\"signature-space\" style=\"width: 10%;\">&nbsp;</td>
                    <!-- IT Incharge Signature -->
                    <td style=\"width: 45%; text-align: center; vertical-align: bottom;\">
                        <div style=\"min-height: 50px; padding: 0 5px; margin-bottom: 5px;\">
                            {$admin_sig_img}
                        </div>
                        <div style=\"border-top: 1.5px solid #000000; font-weight: bold; font-size: 13px; padding-top: 5px; color: #000000;\">
                            {$manager_name_val}
                        </div>
                        <div style=\"font-size: 10px; color: #000000; font-weight: bold;\">IT Incharge Name and Signature</div>
                    </td>
                </tr>
            </table>
            
            <!-- Security Signature (Centered) -->
            <table class=\"sec-signature-table\" style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px;\">
                <tr>
                    <td class=\"sec-signature-space\" style=\"width: 25%;\">&nbsp;</td>
                    <td style=\"width: 50%; text-align: center; vertical-align: bottom;\">
                        <div style=\"min-height: 50px; padding: 0 5px; margin-bottom: 5px;\">
                            {$security_sig_img}
                        </div>
                        <div style=\"border-top: 1.5px solid #000000; font-weight: bold; font-size: 13px; padding-top: 5px; color: #000000;\">
                            {$security_name_val}
                        </div>
                        <div style=\"font-size: 10px; color: #000000; font-weight: bold;\">Released By (Security)</div>
                    </td>
                    <td class=\"sec-signature-space\" style=\"width: 25%;\">&nbsp;</td>
                </tr>
            </table>
            
            <!-- Ingress Header -->
            <div style=\"text-align: center; font-weight: bold; font-size: 11px; color: #000000; margin-bottom: 10px; border-top: 1px solid #cbd5e1; padding-top: 10px;\">
                RETURNABLE MATERIAL / INGRESS
            </div>
            
            <!-- Ingress Details -->
            <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; color: #000000;\">
                <tr>
                    <td style=\"padding: 4px 0; font-weight: bold; width: 30%;\">Date Asset/Item received:</td>
                    <td style=\"padding: 4px 0; border-bottom: 1px solid #94a3b8; color: #000; width: 70%;\">
                        {$ingress_date_val}
                    </td>
                </tr>
            </table>
            
            <table style=\"width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 11px; color: #000000;\">
                <tr>
                    <td style=\"padding: 4px 0; font-weight: bold; width: 15%;\">Received by:</td>
                    <td style=\"padding: 4px 0; border-bottom: 1px solid #94a3b8; color: #000; width: 40%;\">&nbsp;</td>
                    <td style=\"padding: 4px 0; font-weight: bold; width: 10%; text-align: right; padding-right: 10px;\">Signature:</td>
                    <td style=\"padding: 4px 0; border-bottom: 1px solid #94a3b8; color: #000000; font-weight: bold; text-align: center; width: 35%;\">
                        {$ingress_verified_text}
                    </td>
                </tr>
            </table>
            
            <!-- Instructions Section -->
            <div style=\"border-top: 1px dashed #cbd5e1; padding-top: 15px; font-size: 10px; color: #000000;\">
                <div style=\"text-align: center; font-weight: bold; text-decoration: underline; color: #000000; margin-bottom: 6px;\">GENERAL INSTRUCTIONS</div>
                <ol style=\"margin: 0 0 10px 0; padding-left: 20px; line-height: 1.4;\">
                    <li>This Gate Pass shall be signed in Triplicate.</li>
                    <li>All details as required must be filled.</li>
                    <li>All competent authorities must sign the Gate Pass as requested.</li>
                    <li>All Gate Pass should be stamped and logged in Material Movement Register by Security.</li>
                    <li>Material will be permitted to move out of the premises with proper Gate Pass.</li>
                </ol>
                
                <div style=\"text-align: center; font-weight: bold; text-decoration: underline; color: #000000; margin-bottom: 6px;\">RESPONSIBILITY OF SIGNATORIES</div>
                <ol style=\"margin: 0 0 10px 0; padding-left: 20px; line-height: 1.4;\">
                    <li><strong>Requestor</strong> - Should ensure accuracy and completeness of the Gate Pass and the items indicated within.</li>
                    <li><strong>IT Incharge</strong> - Should validate and be accountable of the items being brought in and out of the site.</li>
                    <li><strong>Security</strong> - Inspects and ensures that the gatepass has been signed, filled out correctly and items for ingress/egress have been inspected.</li>
                </ol>
                
                <div style=\"text-align: center; font-weight: bold; text-decoration: underline; color: #000000; margin-bottom: 6px;\">FOR RETURNABLE MATERIAL</div>
                <ol style=\"margin: 0; padding-left: 20px; line-height: 1.4;\">
                    <li>All Material returning should accompany this Gate Pass.</li>
                    <li>All Material returning should be logged with Security else will be considered outstanding against your name.</li>
                </ol>
            </div>
            
        </div>
    </div>";
}
?>
