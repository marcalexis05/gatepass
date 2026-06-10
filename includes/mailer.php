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
 * @return bool True on success, false on failure
 */
function send_gatepass_email($gatepass, $recipient_email, $recipient_name, $role = 'visitor') {
    // Get SMTP configurations from database settings
    $smtp_host = get_setting('smtp_host', 'smtp.gmail.com');
    $smtp_port = get_setting('smtp_port', '587');
    $smtp_secure = get_setting('smtp_secure', 'tls');
    $smtp_user = get_setting('smtp_user', '');
    $smtp_pass = get_setting('smtp_pass', '');
    $admin_email = get_setting('admin_email', 'admin@example.com');
    $system_name = get_setting('system_name', 'GatePass Pro');
    
    // If SMTP details are not configured, log/return false (but gracefully, so registration still works)
    if (empty($smtp_user) || empty($smtp_pass)) {
        error_log("Mailer Error: SMTP credentials are not configured in system settings.");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $smtp_host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp_user;
        $mail->Password   = $smtp_pass;
        $mail->SMTPSecure = ($smtp_secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)$smtp_port;
        
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

        // Content
        $mail->isHTML(true);
        
        // Subject and dynamic body styling based on role
        if ($role === 'admin') {
            $mail->Subject = "New Gatepass Request: " . $gatepass['gatepass_no'] . " - " . $gatepass['visitor_name'];
            $body = get_admin_email_template($gatepass, $system_name);
        } else {
            $mail->Subject = "Your Digital Gatepass: " . $gatepass['gatepass_no'];
            $body = get_visitor_email_template($gatepass, $system_name);
        }

        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace('<br>', "\n", $body));

        $mail->send();
        return true;
    } catch (Exception $e) {
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
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Email / Phone:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['visitor_email']} / {$gp['visitor_phone']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Company/Org:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>" . ($gp['company_org'] ?: 'N/A') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Purpose of Visit:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['purpose']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Host / Dept:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['host_name']} ({$gp['department']})</td>
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
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Host Person:</td>
                            <td style='padding: 8px 0; color: #111827; font-size: 14px;'>{$gp['host_name']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Department:</td>
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
?>
