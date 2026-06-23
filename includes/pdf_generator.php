<?php
/**
 * GatePass Pro - PDF Generator
 * 
 * Generates a high-quality, professional PDF document matching the physical 
 * Concentrix Gate Pass template (Material Movement).
 */

require_once __DIR__ . '/../libs/fpdf/fpdf.php';
require_once __DIR__ . '/../config/database.php';

class ConcentrixGatePassPDF extends FPDF {
    function RoundedRect($x, $y, $w, $h, $r, $style = '', $angle = '1234')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k));

        $xc = $x+$w-$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k));
        if (strpos($angle, '2')===false)
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$y)*$k));
        else
            $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);

        $xc = $x+$w-$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        if (strpos($angle, '3')===false)
            $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);

        $xc = $x+$r;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        if (strpos($angle, '4')===false)
            $this->_out(sprintf('%.2F %.2F l',$x*$k,($hp-($y+$h))*$k));
        else
            $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);

        $xc = $x+$r;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',$x*$k,($hp-$yc)*$k));
        if (strpos($angle, '1')===false) {
            $this->_out(sprintf('%.2F %.2F l',$x*$k,($hp-$y)*$k));
            $this->_out(sprintf('%.2F %.2F l',($x+$r)*$k,($hp-$y)*$k));
        } else
            $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    function DottedLine($x1, $y1, $x2, $y2, $width = 0.4, $dash = 1.3)
    {
        $this->SetLineWidth($width);
        $this->_out('1 J'); // Round line cap style
        $this->_out(sprintf('[0 %.2F] 0 d', $dash * $this->k));
        $this->Line($x1, $y1, $x2, $y2);
        $this->_out('2 J'); // Restore square line cap style
        $this->_out('[] 0 d'); // Reset dash pattern
    }
    
    function CellWithDottedUnderline($w, $h, $txt, $align = 'C')
    {
        $x = $this->GetX();
        $y = $this->GetY();
        $this->Cell($w, $h, $txt, 0, 1, $align);
        
        $txt_w = $this->GetStringWidth($txt);
        if ($align === 'C') {
            $line_x1 = $x + ($w - $txt_w) / 2;
        } else {
            $line_x1 = $x;
        }
        $line_x2 = $line_x1 + $txt_w;
        
        $underline_y = $y + 0.5 * $h + 0.35 * $this->FontSize;
        $this->DottedLine($line_x1, $underline_y, $line_x2, $underline_y);
    }
}

/**
 * Generates the Gatepass PDF and returns the path to the temporary file.
 * 
 * @param array $gp Gatepass record array
 * @return string File path to the generated PDF
 */
function generate_gatepass_pdf($gp) {
    global $pdo;
    
    // Fetch materials
    $materials = [];
    try {
        $stmt = $pdo->prepare("SELECT * FROM gatepass_materials WHERE gatepass_no = ? ORDER BY id ASC");
        $stmt->execute([$gp['gatepass_no']]);
        $materials = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback to empty array
    }
    
    // Legacy fallback if materials list is empty but single fields exist
    if (empty($materials) && !empty($gp['material_desc'])) {
        $materials = [[
            'material_serial' => $gp['material_serial'],
            'material_desc' => $gp['material_desc'],
            'material_brand' => $gp['material_brand'],
            'material_qty' => $gp['material_qty'],
            'purpose' => $gp['purpose']
        ]];
    }

    // Initialize FPDF
    $pdf = new ConcentrixGatePassPDF('P', 'mm', 'A4');
    $pdf->SetTitle('Concentrix Gatepass | Download PDF');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(false); // We handle pagination/fitting on one A4 page manually
    $pdf->AddPage();
    
    // 1. Draw outer boundary box (replicates the card outline)
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(0, 15, 19); // Deep dark border matching theme
    $pdf->Rect(10, 10, 190, 277);
    
    // 2. Draw Concentrix logo
    $logo_path = __DIR__ . '/../assets/logo-concentrix.png';
    if (file_exists($logo_path)) {
        $pdf->Image($logo_path, 15, 14, 45, 0, 'PNG');
    }
    
    // 3. Header Titles (Centered)
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY(60, 13);
    $pdf->Cell(90, 4, 'Concentrix UP-1', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetX(60);
    $pdf->Cell(90, 3, 'Ground-4th Floor Building-D UP Technohub Quezon City', 0, 1, 'C');
    
    $pdf->Ln(1.5);
    
    // Centered and Underlined GATE PASS
    $txt = 'GATE PASS';
    $pdf->SetFont('Arial', 'BU', 13);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 5, $txt, 0, 1, 'C');
    
    $pdf->Ln(0.5);
    
    // Centered and Underlined RETURNABLE / NON-RETURNABLE
    $txt = 'RETURNABLE / NON-RETURNABLE';
    $pdf->SetFont('Arial', 'BU', 9);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 4, $txt, 0, 1, 'C');
    
    $pdf->Ln(0.5);
    
    // Centered and Underlined MATERIAL MOVEMENT
    $txt = 'MATERIAL MOVEMENT';
    $pdf->SetFont('Arial', 'BU', 9.5);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 4, $txt, 0, 1, 'C');
    
    // 4. ID & Date (Right aligned in header area, ending at X=198)
    $pdf->SetXY(150, 14);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(11, 4, 'GP ID: ', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(37, 4, $gp['gatepass_no'], 0, 1, 'L');
    $pdf->DottedLine(161, 18, 198, 18);
    
    $pdf->SetXY(150, 20);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(11, 4, 'DATE: ', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(37, 4, date('M d, Y', strtotime($gp['visit_date'])), 0, 1, 'L');
    $pdf->DottedLine(161, 24, 198, 24);
    
    // 5. Particulars Section (4 full-width rows with dotted lines under values and colons)
    $pdf->SetTextColor(0, 0, 0);

    // Row 1: Name
    $pdf->SetXY(12, 44);
    $pdf->SetFont('Arial', 'B', 8.5);
    $label = 'Name:';
    $label_w = $pdf->GetStringWidth($label);
    $pdf->Cell($label_w, 4, $label, 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $val_x = 12 + $label_w + 3;
    $pdf->SetX($val_x);
    $pdf->Cell(198 - $val_x, 4, $gp['visitor_name'], 0, 1, 'L');
    $pdf->DottedLine($val_x, 48.5, 198, 48.5);

    // Row 2: Program/Department
    $pdf->SetXY(12, 50);
    $pdf->SetFont('Arial', 'B', 8.5);
    $label = 'Program/Department:';
    $label_w = $pdf->GetStringWidth($label);
    $pdf->Cell($label_w, 4, $label, 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $val_x = 12 + $label_w + 3;
    $pdf->SetX($val_x);
    $pdf->Cell(198 - $val_x, 4, $gp['department'], 0, 1, 'L');
    $pdf->DottedLine($val_x, 54.5, 198, 54.5);

    // Row 3: EID
    $pdf->SetXY(12, 56);
    $pdf->SetFont('Arial', 'B', 8.5);
    $label = 'EID:';
    $label_w = $pdf->GetStringWidth($label);
    $pdf->Cell($label_w, 4, $label, 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 9);
    $val_x = 12 + $label_w + 3;
    $pdf->SetX($val_x);
    $pdf->Cell(198 - $val_x, 4, $gp['eid'] ?: 'N/A', 0, 1, 'L');
    $pdf->DottedLine($val_x, 60.5, 198, 60.5);

    // Row 4: Email
    $pdf->SetXY(12, 62);
    $pdf->SetFont('Arial', 'B', 8.5);
    $label = 'Email:';
    $label_w = $pdf->GetStringWidth($label);
    $pdf->Cell($label_w, 4, $label, 0, 0, 'L');
    $pdf->SetFont('Arial', '', 9);
    $val_x = 12 + $label_w + 3;
    $pdf->SetX($val_x);
    $pdf->Cell(198 - $val_x, 4, $gp['visitor_email'], 0, 1, 'L');
    $pdf->DottedLine($val_x, 66.5, 198, 66.5);
    
    // 6. Materials Table (Rounded border with only vertical column borders in the body)
    $table_y = 70;
    $table_h = 45; // Table height
    $hdr_h = 7;    // Header height
    
    $w_serial = 30;
    $w_desc = 60;
    $w_brand = 35;
    $w_qty = 15;
    $w_remarks = 46;
    
    // Draw rounded border around the table
    $pdf->SetLineWidth(0.35);
    $pdf->RoundedRect(12, $table_y, 186, $table_h, 2.5, 'D');
    
    // Draw horizontal divider under the header
    $pdf->Line(12, $table_y + $hdr_h, 198, $table_y + $hdr_h);
    
    // Draw vertical dividers inside the table going all the way down
    $pdf->Line(12 + $w_serial, $table_y, 12 + $w_serial, $table_y + $table_h);
    $pdf->Line(12 + $w_serial + $w_desc, $table_y, 12 + $w_serial + $w_desc, $table_y + $table_h);
    $pdf->Line(12 + $w_serial + $w_desc + $w_brand, $table_y, 12 + $w_serial + $w_desc + $w_brand, $table_y + $table_h);
    $pdf->Line(12 + $w_serial + $w_desc + $w_brand + $w_qty, $table_y, 12 + $w_serial + $w_desc + $w_brand + $w_qty, $table_y + $table_h);
    
    // Print Header Labels
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetXY(12, $table_y);
    $pdf->Cell($w_serial, $hdr_h, 'S. No.', 0, 0, 'C');
    $pdf->Cell($w_desc, $hdr_h, 'Material Description', 0, 0, 'C');
    $pdf->Cell($w_brand, $hdr_h, 'Brand', 0, 0, 'C');
    $pdf->Cell($w_qty, $hdr_h, 'Qty.', 0, 0, 'C');
    $pdf->Cell($w_remarks, $hdr_h, 'Remarks', 0, 1, 'C');
    
    // Print Table Body
    $pdf->SetFont('Arial', '', 8);
    $row_height = 7;
    $max_rows = 5;
    $rows_printed = 0;
    
    foreach ($materials as $mat) {
        $row_y = $table_y + $hdr_h + ($rows_printed * $row_height);
        $pdf->SetXY(12, $row_y);
        
        $pdf->Cell($w_serial, $row_height, $mat['material_serial'] ?: 'N/A', 0, 0, 'C');
        $pdf->Cell($w_desc, $row_height, ' ' . ($mat['material_desc'] ?: 'N/A'), 0, 0, 'L');
        $pdf->Cell($w_brand, $row_height, $mat['material_brand'] ?: 'N/A', 0, 0, 'C');
        $pdf->Cell($w_qty, $row_height, $mat['material_qty'] ?: '1', 0, 0, 'C');
        
        $pdf->SetFont('Arial', 'I', 8); // Italic for remarks
        $remarks = $mat['purpose'] ?: '-';
        if (strlen($remarks) > 30) {
            $remarks = substr($remarks, 0, 27) . '...';
        }
        $pdf->Cell($w_remarks, $row_height, ' ' . $remarks, 0, 1, 'L');
        $pdf->SetFont('Arial', '', 8); // Restore regular
        
        $rows_printed++;
        if ($rows_printed >= $max_rows) break;
    }
    
    $end_body_y = $table_y + $table_h;
    
    // 7. Signatures section
    $sig_y = $end_body_y + 4;
    $pdf->SetXY(10, $sig_y);
    
    // We will decode signature base64 images and write them to temp files, then load them in FPDF
    $temp_sig_files = [];
    
    // Visitor Signature
    $visitor_sig_path = '';
    if (!empty($gp['visitor_signature'])) {
        $visitor_sig_path = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
        $raw_data = explode(',', $gp['visitor_signature']);
        $decoded_data = base64_decode(end($raw_data));
        file_put_contents($visitor_sig_path, $decoded_data);
        $temp_sig_files[] = $visitor_sig_path;
    }
    
    // IT Incharge Signature
    $admin_sig_path = '';
    if (!empty($gp['admin_signature'])) {
        $admin_sig_path = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
        $raw_data = explode(',', $gp['admin_signature']);
        $decoded_data = base64_decode(end($raw_data));
        file_put_contents($admin_sig_path, $decoded_data);
        $temp_sig_files[] = $admin_sig_path;
    }
    
    // Security Signature
    $security_sig_path = '';
    if (!empty($gp['security_signature'])) {
        $security_sig_path = tempnam(sys_get_temp_dir(), 'sig_') . '.png';
        $raw_data = explode(',', $gp['security_signature']);
        $decoded_data = base64_decode(end($raw_data));
        file_put_contents($security_sig_path, $decoded_data);
        $temp_sig_files[] = $security_sig_path;
    }
    
    // Render Signatures Side by Side (Left: Requestor, Right: IT Incharge)
    $sig_box_h = 14;
    
    // Requestor Signature (Left)
    $pdf->SetXY(25, $sig_y);
    if ($visitor_sig_path && file_exists($visitor_sig_path)) {
        $pdf->Image($visitor_sig_path, 40, $sig_y - 2, 35, $sig_box_h, 'PNG');
    } else {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(65, $sig_box_h, 'No Signature', 0, 0, 'C');
    }
    
    // IT Incharge Signature (Right)
    $pdf->SetXY(120, $sig_y);
    if ($admin_sig_path && file_exists($admin_sig_path)) {
        $pdf->Image($admin_sig_path, 135, $sig_y - 2, 35, $sig_box_h, 'PNG');
    } else {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(65, $sig_box_h, 'REQUIRED SIGNATURE', 0, 0, 'C');
    }
    
    $pdf->SetTextColor(0, 0, 0); // Restore
    
    // Underline text for Requestor & IT Incharge
    $pdf->SetFont('Arial', 'B', 8.5);
    
    $pdf->SetXY(25, $sig_y + $sig_box_h + 1);
    $pdf->Cell(65, 4, $gp['visitor_name'], 'T', 0, 'C');
    
    $pdf->SetXY(120, $sig_y + $sig_box_h + 1);
    $pdf->Cell(65, 4, $gp['manager_name'] ?: '______________________', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(25, $sig_y + $sig_box_h + 5);
    $pdf->Cell(65, 3, 'Requestor Name and Signature', 0, 0, 'C');
    
    $pdf->SetXY(120, $sig_y + $sig_box_h + 5);
    $pdf->Cell(65, 3, 'IT Incharge Name and Signature', 0, 1, 'C');
    
    // Row 2: Security Release Signature (Centered)
    $sec_sig_y = $sig_y + $sig_box_h + 9;
    $pdf->SetXY(72.5, $sec_sig_y);
    
    if ($security_sig_path && file_exists($security_sig_path)) {
        $pdf->Image($security_sig_path, 87.5, $sec_sig_y - 2, 35, $sig_box_h, 'PNG');
    } else {
        $pdf->SetFont('Arial', 'B', 8);
        if ($gp['status'] === 'Checked Out') {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(65, $sig_box_h, 'RELEASED BY SECURITY', 0, 0, 'C');
        } elseif ($gp['status'] === 'Checked In') {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(65, $sig_box_h, 'INGRESS STAMPED', 0, 0, 'C');
        } else {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(65, $sig_box_h, 'Pending Action', 0, 0, 'C');
        }
    }
    
    $pdf->SetTextColor(0, 0, 0); // Restore
    
    $pdf->SetXY(72.5, $sec_sig_y + $sig_box_h + 1);
    $pdf->SetFont('Arial', 'B', 8.5);
    $pdf->Cell(65, 4, $gp['security_name'] ?: '______________________', 'T', 1, 'C');
    
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY(72.5, $sec_sig_y + $sig_box_h + 5);
    $pdf->Cell(65, 3, 'Released By (Security)', 0, 1, 'C');
    
    // Ingress Section
    $pdf->SetXY(12, 166);
    $txt = 'RETURNABLE MATERIAL / INGRESS';
    $pdf->SetFont('Arial', 'BU', 9);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 4, $txt, 0, 1, 'C');
    
    // Received Info
    $pdf->SetXY(12, 172);
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(34, 4, 'Date Asset/Item received:', 0, 0, 'L');
    $pdf->Cell(152, 4, '', 0, 1, 'L');
    $pdf->DottedLine(46, 176, 198, 176);
    
    $pdf->SetXY(12, 178);
    $pdf->SetFont('Arial', 'B', 7.5);
    $pdf->Cell(18, 4, 'Received by', 0, 0, 'L');
    $pdf->Cell(70, 4, '', 0, 0, 'C');
    $pdf->DottedLine(30, 182, 100, 182);
    
    $pdf->SetXY(112, 178);
    $pdf->Cell(15, 4, 'Signature', 0, 0, 'L');
    $pdf->Cell(71, 4, '', 0, 1, 'C');
    $pdf->DottedLine(127, 182, 198, 182);
    
    // 8. General Instructions Section
    $inst_y = 186;
    $pdf->SetXY(12, $inst_y);
    
    // Thin border line separating instructions
    $pdf->SetLineWidth(0.2);
    $pdf->Line(10, $inst_y - 1.5, 200, $inst_y - 1.5);
    
    $txt = 'GENERAL INSTRUCTIONS';
    $pdf->SetFont('Arial', 'BU', 9.5);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 5, $txt, 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(0, 0, 0);
    
    // Add text points
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '1. This Gate Pass shall be signed in Triplicate', 0, 'L');
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '2. All details as required must be filled', 0, 'L');
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '3. All competent authorities must sign the Gate Pass as requested.', 0, 'L');
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '4. All Gate Pass should be stamped and logged in Material Movement Register by Security.', 0, 'L');
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '5. Material will be permitted to move out of the premises with proper Gate Pass.', 0, 'L');
    
    $pdf->Ln(1.5);
    $txt = 'RESPONSIBILITY OF SIGNATORIES';
    $pdf->SetFont('Arial', 'BU', 9.5);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 5, $txt, 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetTextColor(0, 0, 0);
    
    // Requestor
    $pdf->SetX(15);
    $pdf->Cell(5, 4.5, '1. ', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $label_w = $pdf->GetStringWidth('Requestor');
    $pdf->Cell($label_w, 4.5, 'Requestor', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8.5);
    $current_x = $pdf->GetX();
    $pdf->MultiCell(190 - $current_x, 4.5, ' - Should ensure accuracy and completeness of the Gate Pass and the items indicated within.', 0, 'L');
    
    // IT Incharge
    $pdf->SetX(15);
    $pdf->Cell(5, 4.5, '2. ', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $label_w = $pdf->GetStringWidth('IT Incharge');
    $pdf->Cell($label_w, 4.5, 'IT Incharge', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8.5);
    $current_x = $pdf->GetX();
    $pdf->MultiCell(190 - $current_x, 4.5, ' - Should validate and be accountable of the items being brought in and out of the site.', 0, 'L');
    
    // Security
    $pdf->SetX(15);
    $pdf->Cell(5, 4.5, '3. ', 0, 0, 'L');
    $pdf->SetFont('Arial', 'B', 8.5);
    $label_w = $pdf->GetStringWidth('Security');
    $pdf->Cell($label_w, 4.5, 'Security', 0, 0, 'L');
    $pdf->SetFont('Arial', '', 8.5);
    $current_x = $pdf->GetX();
    $pdf->MultiCell(190 - $current_x, 4.5, ' - Inspects and ensures that the gatepass has been signed, filled out correctly and items for ingress/egress have been inspected.', 0, 'L');
    
    $pdf->Ln(1.5);
    $txt = 'FOR RETURNABLE MATERIAL:';
    $pdf->SetFont('Arial', 'BU', 9.5);
    $w = $pdf->GetStringWidth($txt);
    $pdf->SetX(105 - ($w / 2));
    $pdf->Cell($w, 5, $txt, 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8.5);
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '4. All Material returning should accompany this Gate Pass.', 0, 'L');
    $pdf->SetX(15);
    $pdf->MultiCell(180, 4.5, '5. All Material returning should be logged with Security else will be considered outstanding against your name.', 0, 'L');
    
    // Clean up temporary signature files
    foreach ($temp_sig_files as $f) {
        if (file_exists($f)) {
            unlink($f);
        }
    }
    
    // Save to temp file and return path
    $temp_pdf_path = tempnam(sys_get_temp_dir(), 'gp_pdf_') . '.pdf';
    $pdf->Output('F', $temp_pdf_path);
    
    return $temp_pdf_path;
}
?>
