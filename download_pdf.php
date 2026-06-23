<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/pdf_generator.php';

$gatepass_no = trim($_GET['code'] ?? '');
$raw = isset($_GET['raw']);

// Simple mobile check to bypass iframe wrapper on phones/tablets for native support
$is_mobile = false;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (preg_match('/(android|iphone|ipad|mobile)/i', $user_agent)) {
    $is_mobile = true;
}

if (empty($gatepass_no)) {
    die("Invalid request");
}

// Fetch gatepass details
$stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
$stmt->execute([$gatepass_no]);
$gp = $stmt->fetch();

if (!$gp) {
    die("Gatepass not found");
}

if ($raw || $is_mobile) {
    // Generate the PDF
    $pdf_path = generate_gatepass_pdf($gp);

    if (file_exists($pdf_path)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $gatepass_no . '.pdf"');
        header('Content-Length: ' . filesize($pdf_path));
        readfile($pdf_path);
        unlink($pdf_path);
        exit;
    } else {
        die("Failed to generate PDF");
    }
} else {
    // Serve HTML Wrapper with custom favicon and title for desktop
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Concentrix Gatepass | Download PDF</title>
        <link rel="icon" type="image/png" href="assets/favicon.png">
        <style>
            html, body {
                margin: 0;
                padding: 0;
                width: 100%;
                height: 100%;
                overflow: hidden;
                background-color: #0f172a;
            }
            iframe {
                width: 100%;
                height: 100%;
                border: none;
            }
        </style>
    </head>
    <body>
        <iframe src="download_pdf.php?code=<?php echo urlencode($gatepass_no); ?>&raw=1"></iframe>
    </body>
    </html>
    <?php
    exit;
}
?>
