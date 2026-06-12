<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success = false;

// Default values
$visitor_name = '';
$visitor_email = '';
$visitor_phone = 'N/A';
$eid = '';
$company_org = 'N/A';
$vehicle_no = 'N/A';
$purpose = '';
$material_desc = '';
$material_serial = '';
$material_qty = 1;
$host_name = 'N/A';
$department = '';
$visit_date = date('Y-m-d'); // Default to today
$visitor_signature = '';

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $visitor_email = trim($_POST['visitor_email'] ?? '');
    $eid = trim($_POST['eid'] ?? '');
    $company_org = 'N/A';
    $purpose = trim($_POST['purpose'] ?? '');
    if ($purpose === 'Others') {
        $purpose = trim($_POST['custom_purpose'] ?? '');
    }
    $material_desc = trim($_POST['material_desc'] ?? '');
    if ($material_desc === 'Others') {
        $material_desc = trim($_POST['custom_material_desc'] ?? '');
    }
    $material_serial = trim($_POST['material_serial'] ?? '');
    $material_qty = (int)($_POST['material_qty'] ?? 1);
    $department = trim($_POST['department'] ?? '');
    if ($department === 'Others') {
        $department = trim($_POST['custom_department'] ?? '');
    }
    $visit_date = trim($_POST['visit_date'] ?? '');
    $visitor_signature = $_POST['visitor_signature'] ?? '';

    // Form Validations
    if (empty($visitor_name)) $errors['visitor_name'] = "Full Name is required.";
    
    if (empty($visitor_email)) {
        $errors['visitor_email'] = "Email Address is required.";
    } elseif (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
        $errors['visitor_email'] = "Please enter a valid email address.";
    }
    
    if (empty($eid)) $errors['eid'] = "Employee ID (EID) / ID is required.";
    
    if (empty($purpose)) $errors['purpose'] = "Purpose of visit is required.";
    if (empty($department)) $errors['department'] = "Program/Department is required.";
    
    if (empty($visit_date)) {
        $errors['visit_date'] = "Scheduled date is required.";
    } elseif (strtotime($visit_date) < strtotime(date('Y-m-d'))) {
        $errors['visit_date'] = "Visit date cannot be in the past.";
    }

    if (empty($material_desc)) $errors['material_desc'] = "Material / Asset Description is required.";
    if (empty($material_serial)) $errors['material_serial'] = "Material Serial / S. No. is required.";
    if (empty($material_qty) || $material_qty < 1) $errors['material_qty'] = "Quantity must be 1 or greater.";

    if (empty($visitor_signature)) {
        $errors['visitor_signature'] = "Visitor signature is required when checking in.";
    }

    // Process registration if there are no validation errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Generate unique Gatepass Number: GP-YYYYMMDD-XXXX
            $today_prefix = 'GP-' . date('Ymd') . '-';
            
            // Count passes today to make sequential number
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM gatepasses WHERE gatepass_no LIKE ?");
            $stmt->execute([$today_prefix . '%']);
            $count = $stmt->fetchColumn();
            
            // Find unique serial (just in case count has concurrency collisions, check if number exists)
            $serial = $count + 1;
            do {
                $gatepass_no = $today_prefix . str_pad($serial, 4, '0', STR_PAD_LEFT);
                $stmt_chk = $pdo->prepare("SELECT COUNT(*) FROM gatepasses WHERE gatepass_no = ?");
                $stmt_chk->execute([$gatepass_no]);
                $exists = $stmt_chk->fetchColumn() > 0;
                $serial++;
            } while ($exists);

            // Insert into Database (automatically Checked In with current time)
            $insert_sql = "INSERT INTO gatepasses (gatepass_no, visitor_name, visitor_email, visitor_phone, eid, company_org, vehicle_no, purpose, material_desc, material_serial, material_qty, host_name, department, visit_date, status, time_in, visitor_signature)
                           VALUES (:gatepass_no, :visitor_name, :visitor_email, :visitor_phone, :eid, :company_org, :vehicle_no, :purpose, :material_desc, :material_serial, :material_qty, :host_name, :department, :visit_date, 'Checked In', CURRENT_TIME(), :visitor_signature)";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([
                'gatepass_no' => $gatepass_no,
                'visitor_name' => $visitor_name,
                'visitor_email' => $visitor_email,
                'visitor_phone' => $visitor_phone,
                'eid' => $eid ?: null,
                'company_org' => $company_org ?: null,
                'vehicle_no' => $vehicle_no ?: null,
                'purpose' => $purpose,
                'material_desc' => $material_desc ?: null,
                'material_serial' => $material_serial ?: null,
                'material_qty' => $material_qty ?: 1,
                'host_name' => $host_name,
                'department' => $department,
                'visit_date' => $visit_date,
                'visitor_signature' => $visitor_signature ?: null
            ]);

            // Commit transaction
            $pdo->commit();

            // Fetch the inserted record for mailing
            $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
            $stmt->execute([$gatepass_no]);
            $gatepass_data = $stmt->fetch();

            // Redirect to success screen (emails will be sent asynchronously via background fetch in success.php)
            header("Location: success.php?code=" . urlencode($gatepass_no) . "&new=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "An error occurred while saving your registration. Please try again. Info: " . $e->getMessage();
        }
    }
}

$page_title = "Register Visitor Pass";
require_once __DIR__ . '/includes/header.php';
?>

<div class="max-w-2xl mx-auto py-4">
    <!-- Breadcrumb -->
    <a href="index.php" class="text-sm font-semibold text-slate-400 hover:text-white transition-colors flex items-center space-x-1.5 mb-6 group">
        <i class="fa-solid fa-chevron-left group-hover:-translate-x-1 transition-transform"></i>
        <span>Back to Welcome Page</span>
    </a>

    <!-- Header Card -->
    <div class="text-center mb-8">
        <h1 class="text-3xl font-black text-white tracking-tight mb-2">Visitor Request Form</h1>
        <p class="text-slate-400 text-sm">Please fill out all required details accurately to generate your digital gatepass.</p>
    </div>

    <!-- Registration Card -->
    <div class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-500"></div>
        
        <?php if (isset($errors['db'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-start">
                <i class="fa-solid fa-circle-exclamation mt-0.5 mr-2 text-base flex-shrink-0"></i>
                <div><?php echo htmlspecialchars($errors['db']); ?></div>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-6" id="registration-form">
            <!-- Section 1: Visitor Information -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400 mb-4 border-b border-slate-800 pb-2">
                    1. Visitor Particulars
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name -->
                    <div class="space-y-1.5">
                        <label for="visitor_name" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Full Name <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-user text-xs"></i></span>
                            <input type="text" id="visitor_name" name="visitor_name" required value="<?php echo htmlspecialchars($visitor_name); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visitor_name']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. John Doe">
                        </div>
                        <?php if (isset($errors['visitor_name'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['visitor_name']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- EID / ID Number -->
                    <div class="space-y-1.5">
                        <label for="eid" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Employee ID (EID) / ID <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-id-badge text-xs"></i></span>
                            <input type="text" id="eid" name="eid" required value="<?php echo htmlspecialchars($eid); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['eid']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. 101917108">
                        </div>
                        <?php if (isset($errors['eid'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['eid']; ?></p>
                        <?php endif; ?>
                    </div>


                    <!-- Email Address -->
                    <div class="space-y-1.5 md:col-span-2">
                        <label for="visitor_email" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Email Address <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-envelope text-xs"></i></span>
                            <input type="email" id="visitor_email" name="visitor_email" required value="<?php echo htmlspecialchars($visitor_email); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visitor_email']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. johndoe@gmail.com">
                        </div>
                        <?php if (isset($errors['visitor_email'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['visitor_email']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 2: Visit Details -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400 mb-4 border-b border-slate-800 pb-2">
                    2. Visit Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                    <!-- Program/Department -->
                    <div class="space-y-1.5" id="department-container">
                        <label for="department" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Program/Department <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-users-gear text-xs"></i></span>
                            <select id="department" name="department" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['department']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($department === '') ? 'selected' : ''; ?>>Select Program/Department</option>
                                <option value="FUBO" <?php echo $department === 'FUBO' ? 'selected' : ''; ?>>FUBO</option>
                                <option value="BCBS" <?php echo $department === 'BCBS' ? 'selected' : ''; ?>>BCBS</option>
                                <option value="DTV" <?php echo $department === 'DTV' ? 'selected' : ''; ?>>DTV</option>
                                <option value="DISNEY" <?php echo $department === 'DISNEY' ? 'selected' : ''; ?>>DISNEY</option>
                                <option value="AETNA" <?php echo $department === 'AETNA' ? 'selected' : ''; ?>>AETNA</option>
                                <option value="Others" <?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
                        </div>

                        <!-- Custom Department Text Input (Shown when 'Others' is selected) -->
                        <div id="custom-department-wrapper" class="relative mt-2 <?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_department" name="custom_department" 
                                   value="<?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? htmlspecialchars($department) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="Please specify program/department name">
                        </div>

                        <?php if (isset($errors['department'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['department']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Visit Date -->
                    <div class="space-y-1.5">
                        <label for="visit_date" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Visit Date <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-calendar-day text-xs"></i></span>
                            <input type="date" id="visit_date" name="visit_date" required value="<?php echo htmlspecialchars($visit_date); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visit_date']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>
                        <?php if (isset($errors['visit_date'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['visit_date']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Purpose of Visit -->
                    <div class="space-y-1.5" id="purpose-container">
                        <label for="purpose" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Purpose of Visit <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-circle-question text-xs"></i></span>
                            <select id="purpose" name="purpose" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['purpose']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($purpose === '') ? 'selected' : ''; ?>>Select Purpose</option>
                                <option value="Re-image" <?php echo $purpose === 'Re-image' ? 'selected' : ''; ?>>Re-image</option>
                                <option value="Replacement" <?php echo $purpose === 'Replacement' ? 'selected' : ''; ?>>Replacement</option>
                                <option value="Others" <?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
                        </div>

                        <!-- Custom Purpose Text Input (Shown when 'Others' is selected) -->
                        <div id="custom-purpose-wrapper" class="relative mt-2 <?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_purpose" name="custom_purpose"
                                   value="<?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? htmlspecialchars($purpose) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="Please specify purpose of visit">
                        </div>

                        <?php if (isset($errors['purpose'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['purpose']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 3: Material Movement & Signature -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400 mb-4 border-b border-slate-800 pb-2 font-display">
                    3. Material Movement & Signature
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Material Description -->
                    <div class="space-y-1.5 md:col-span-1" id="material-desc-container">
                        <label for="material_desc" class="text-xs font-bold text-slate-300 uppercase tracking-wide md:h-12 flex items-end pb-1">Material / Asset Description <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-laptop text-xs"></i></span>
                            <select id="material_desc" name="material_desc" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_desc']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($material_desc === '') ? 'selected' : ''; ?>>Select Asset Type</option>
                                <option value="CPU" <?php echo $material_desc === 'CPU' ? 'selected' : ''; ?>>CPU</option>
                                <option value="Laptop" <?php echo $material_desc === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                                <option value="Headset" <?php echo $material_desc === 'Headset' ? 'selected' : ''; ?>>Headset</option>
                                <option value="Others" <?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
                        </div>

                        <!-- Custom Material Desc Input (Shown when 'Others' is selected) -->
                        <div id="custom-material-desc-wrapper" class="relative mt-2 <?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_material_desc" name="custom_material_desc"
                                   value="<?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? htmlspecialchars($material_desc) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="Please specify asset description">
                        </div>

                        <?php if (isset($errors['material_desc'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['material_desc']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Material Serial No (S. No.) -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label for="material_serial" class="text-xs font-bold text-slate-300 uppercase tracking-wide font-display md:h-12 flex items-end pb-1">Material Serial / S. No. <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-barcode text-xs"></i></span>
                            <input type="text" id="material_serial" name="material_serial" required value="<?php echo htmlspecialchars($material_serial); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_serial']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. 5CD5266N9G">
                        </div>
                        <?php if (isset($errors['material_serial'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['material_serial']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Material Qty -->
                    <div class="space-y-1.5 md:col-span-1">
                        <label for="material_qty" class="text-xs font-bold text-slate-300 uppercase tracking-wide md:h-12 flex items-end pb-1">Quantity <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-arrow-up-1-9 text-xs"></i></span>
                            <input type="number" id="material_qty" name="material_qty" min="1" required value="<?php echo htmlspecialchars($material_qty); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_qty']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>
                        <?php if (isset($errors['material_qty'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['material_qty']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Visitor Signature <span class="text-rose-500">*</span></label>
                    <div class="relative bg-dark-950 border border-slate-800 rounded-xl overflow-hidden shadow-inner">
                        <canvas id="signature-pad" class="w-full h-40 cursor-crosshair bg-slate-950 block"></canvas>
                        <button type="button" id="clear-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-850 shadow transition-all">
                            <i class="fa-solid fa-eraser mr-1"></i> Clear
                        </button>
                    </div>
                    <?php if (isset($errors['visitor_signature'])): ?>
                        <p class="text-rose-400 text-[11px]"><?php echo $errors['visitor_signature']; ?></p>
                    <?php endif; ?>
                    <input type="hidden" id="visitor_signature" name="visitor_signature" value="<?php echo htmlspecialchars($visitor_signature); ?>">
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-800/80">
                <button type="submit" id="submit-btn"
                        class="w-full py-3 bg-gradient-to-r from-indigo-600 to-indigo-500 hover:from-indigo-500 hover:to-indigo-400 active:scale-[0.99] text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/15 flex items-center justify-center space-x-2 transition-all">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Submit Request & Generate Pass</span>
                </button>
                <p class="text-[10px] text-center text-slate-500 mt-3">
                    By submitting, your details will be logged in accordance with system privacy and security guidelines.
                </p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Toggle Custom Department Input visibility
    const deptSelect = document.getElementById('department');
    const customDeptWrapper = document.getElementById('custom-department-wrapper');
    const customDeptInput = document.getElementById('custom_department');

    function toggleCustomDepartment() {
        if (deptSelect.value === 'Others') {
            customDeptWrapper.classList.remove('hidden');
            customDeptInput.required = true;
        } else {
            customDeptWrapper.classList.add('hidden');
            customDeptInput.required = false;
        }
    }

    if (deptSelect && customDeptWrapper && customDeptInput) {
        deptSelect.addEventListener('change', toggleCustomDepartment);
        toggleCustomDepartment();
    }

    // Toggle Custom Purpose Input visibility
    const purposeSelect = document.getElementById('purpose');
    const customPurposeWrapper = document.getElementById('custom-purpose-wrapper');
    const customPurposeInput = document.getElementById('custom_purpose');

    function toggleCustomPurpose() {
        if (purposeSelect.value === 'Others') {
            customPurposeWrapper.classList.remove('hidden');
            customPurposeInput.required = true;
        } else {
            customPurposeWrapper.classList.add('hidden');
            customPurposeInput.required = false;
        }
    }

    if (purposeSelect && customPurposeWrapper && customPurposeInput) {
        purposeSelect.addEventListener('change', toggleCustomPurpose);
        toggleCustomPurpose();
    }

    // Toggle Custom Material Desc Input visibility
    const materialDescSelect = document.getElementById('material_desc');
    const customMaterialDescWrapper = document.getElementById('custom-material-desc-wrapper');
    const customMaterialDescInput = document.getElementById('custom_material_desc');

    function toggleCustomMaterialDesc() {
        if (materialDescSelect.value === 'Others') {
            customMaterialDescWrapper.classList.remove('hidden');
            customMaterialDescInput.required = true;
        } else {
            customMaterialDescWrapper.classList.add('hidden');
            customMaterialDescInput.required = false;
        }
    }

    if (materialDescSelect && customMaterialDescWrapper && customMaterialDescInput) {
        materialDescSelect.addEventListener('change', toggleCustomMaterialDesc);
        toggleCustomMaterialDesc();
    }

    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');
    const clearBtn = document.getElementById('clear-sig');
    const sigInput = document.getElementById('visitor_signature');
    const form = document.getElementById('registration-form');
    let drawing = false;

    // Set canvas dimensions relative to display size
    function resizeCanvas() {
        // Save temporary canvas content before resize
        const tempImage = canvas.toDataURL();
        canvas.width = canvas.offsetWidth;
        canvas.height = canvas.offsetHeight;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#ffffff';
        
        // Restore signature if it was already drawn
        if (sigInput.value) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0);
            img.src = sigInput.value;
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

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

    clearBtn.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        sigInput.value = '';
    });

    // Capture signature on form submit just to be safe
    form.addEventListener('submit', (e) => {
        // Only allow submit if signature is drawn
        if (!sigInput.value) {
            e.preventDefault();
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
