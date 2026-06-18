<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$gatepass_no = trim($_GET['code'] ?? '');
$error = '';
$success_message = '';
$gp = null;
$trigger_email = false;

// Handle manual search form submit
if (isset($_GET['search']) && !empty($_GET['gatepass_no'])) {
    $gatepass_no = trim($_GET['gatepass_no']);
    header("Location: checkout.php?code=" . urlencode($gatepass_no));
    exit;
}

// Load gatepass details if code is provided
if (!empty($gatepass_no)) {
    $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
    $stmt->execute([$gatepass_no]);
    $gp = $stmt->fetch();
    
    $gp_materials = [];
    if ($gp) {
        $materials_stmt = $pdo->prepare("SELECT * FROM gatepass_materials WHERE gatepass_no = ? ORDER BY id ASC");
        $materials_stmt->execute([$gp['gatepass_no']]);
        $gp_materials = $materials_stmt->fetchAll();
        
        // Legacy fallback
        if (empty($gp_materials) && !empty($gp['material_desc'])) {
            $gp_materials = [[
                'purpose' => $gp['purpose'],
                'material_desc' => $gp['material_desc'],
                'material_brand' => $gp['material_brand'],
                'material_serial' => $gp['material_serial'],
                'material_qty' => $gp['material_qty']
            ]];
        }
    }
    
    if (!$gp) {
        $error = "Gatepass nu

        function getPos(e) {
            const rect = canvas.getBoundingClientRect();
            const clientX = e.touches ? e.touches[0].clientX : e.clientX;
            const clientY = e.touches ? e.touches[0].clientY : e.clientY;
            return {
                x: clientX - rect.left,
                y: clientY - rect.top
            };
        }

        function startDrawing(e) {
            drawing = true;
            const pos = getPos(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }

        function draw(e) {
            if (!drawing) return;
            const pos = getPos(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            e.preventDefault();
        }

        function stopDrawing() {
            if (drawing) {
                drawing = false;
                sigInput.value = canvas.toDataURL();
            }
        }

        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseleave', stopDrawing);

        canvas.addEventListener('touchstart', startDrawing, { passive: false });
        canvas.addEventListener('touchmove', draw, { passive: false });
        canvas.addEventListener('touchend', stopDrawing);

        const updateBtn = () => {
            const btn = document.getElementById('checkout-btn');
            if (btn) {
                if (sigInput.value) {
                    btn.removeAttribute('dis