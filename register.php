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
    if (empty($visit_date)) {
        $visit_date = date('Y-m-d');
    }
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
    <div class="glass-card p-6 sm:p-8 rounded-3xl border border-dark-800 shadow-2xl relative">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-brand-blue via-brand-teal to-brand-blue"></div>
        
        <?php if (isset($errors['db'])): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-sm flex items-start">
                <i class="fa-solid fa-circle-exclamation mt-0.5 mr-2 text-base flex-shrink-0"></i>
                <div><?php echo htmlspecialchars($errors['db']); ?></div>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" class="space-y-6" id="registration-form" novalidate>
            <!-- Section 1: Visitor Information -->
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-brand-teal mb-4 border-b border-dark-800 pb-2">
                    1. Visitor Particulars
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Full Name -->
                    <div class="space-y-1.5">
                        <label for="visitor_name" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Full Name <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-user text-xs"></i></span>
                            <input type="text" id="visitor_name" name="visitor_name" required value="<?php echo htmlspecialchars($visitor_name); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visitor_name']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['eid']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visitor_email']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                <h3 class="text-xs font-bold uppercase tracking-wider text-brand-teal mb-4 border-b border-dark-800 pb-2">
                    2. Visit Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                    <!-- Program/Department -->
                    <div class="space-y-1.5" id="department-container">
                        <label for="department" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Program/Department <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-users-gear text-xs"></i></span>
                            <select id="department" name="department" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['department']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($department === '') ? 'selected' : ''; ?>>Select Program/Department</option>
                                <option value="FUBO" <?php echo $department === 'FUBO' ? 'selected' : ''; ?>>FUBO</option>
                                <option value="BCBS" <?php echo $department === 'BCBS' ? 'selected' : ''; ?>>BCBS</option>
                                <option value="DTV" <?php echo $department === 'DTV' ? 'selected' : ''; ?>>DTV</option>
                                <option value="DISNEY" <?php echo $department === 'DISNEY' ? 'selected' : ''; ?>>DISNEY</option>
                                <option value="AETNA" <?php echo $department === 'AETNA' ? 'selected' : ''; ?>>AETNA</option>
                                <option value="Others" <?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                        </div>

                        <!-- Custom Department Text Input (Shown when 'Others' is selected) -->
                        <div id="custom-department-wrapper" class="relative mt-2 <?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_department" name="custom_department" 
                                   value="<?php echo ($department && !in_array($department, ['FUBO', 'BCBS', 'DTV', 'DISNEY', 'AETNA'])) ? htmlspecialchars($department) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="Please specify program/department name">
                        </div>

                        <?php if (isset($errors['department'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['department']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Visit Date (locked to today) -->
                    <div class="space-y-1.5">
                        <label for="visit_date_display" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Visit Date <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-calendar-day text-xs"></i></span>
                            <input type="text" id="visit_date_display" readonly
                                   value="<?php echo date('M d, Y'); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900/50 border border-dark-800 rounded-xl text-slate-300 text-sm cursor-not-allowed select-none"
                                   title="Visit date is automatically set to today">
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                <i class="fa-solid fa-lock text-slate-600 text-[10px]"></i>
                            </span>
                        </div>
                        <!-- Hidden actual date value submitted with the form -->
                        <input type="hidden" id="visit_date" name="visit_date" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <!-- Purpose of Visit -->
                    <div class="space-y-1.5" id="purpose-container">
                        <label for="purpose" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Purpose of Visit <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-circle-question text-xs"></i></span>
                            <select id="purpose" name="purpose" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['purpose']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($purpose === '') ? 'selected' : ''; ?>>Select Purpose</option>
                                <option value="Re-image" <?php echo $purpose === 'Re-image' ? 'selected' : ''; ?>>Re-image</option>
                                <option value="Replacement" <?php echo $purpose === 'Replacement' ? 'selected' : ''; ?>>Replacement</option>
                                <option value="Others" <?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                        </div>

                        <!-- Custom Purpose Text Input (Shown when 'Others' is selected) -->
                        <div id="custom-purpose-wrapper" class="relative mt-2 <?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_purpose" name="custom_purpose"
                                   value="<?php echo ($purpose && !in_array($purpose, ['Re-image', 'Replacement'])) ? htmlspecialchars($purpose) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                <h3 class="text-xs font-bold uppercase tracking-wider text-brand-teal mb-4 border-b border-dark-800 pb-2 font-display">
                    3. Material Movement & Signature
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <!-- Material Description -->
                    <div class="space-y-1.5 md:col-span-1" id="material-desc-container">
                        <label for="material_desc" class="text-xs font-bold text-slate-300 uppercase tracking-wide md:h-12 flex items-end pb-1">Material / Asset Description <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-laptop text-xs"></i></span>
                            <select id="material_desc" name="material_desc" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_desc']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo ($material_desc === '') ? 'selected' : ''; ?>>Select Asset Type</option>
                                <option value="CPU" <?php echo $material_desc === 'CPU' ? 'selected' : ''; ?>>CPU</option>
                                <option value="Laptop" <?php echo $material_desc === 'Laptop' ? 'selected' : ''; ?>>Laptop</option>
                                <option value="Headset" <?php echo $material_desc === 'Headset' ? 'selected' : ''; ?>>Headset</option>
                                <option value="Others" <?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? 'selected' : ''; ?>>Others</option>
                            </select>
                        </div>

                        <!-- Custom Material Desc Input (Shown when 'Others' is selected) -->
                        <div id="custom-material-desc-wrapper" class="relative mt-2 <?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? '' : 'hidden'; ?>">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                            <input type="text" id="custom_material_desc" name="custom_material_desc"
                                   value="<?php echo ($material_desc && !in_array($material_desc, ['CPU', 'Laptop', 'Headset'])) ? htmlspecialchars($material_desc) : ''; ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_serial']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
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
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_qty']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>
                        <?php if (isset($errors['material_qty'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['material_qty']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Visitor Signature <span class="text-rose-500">*</span></label>
                    <div class="relative bg-dark-950 border border-dark-800 rounded-xl overflow-hidden shadow-inner">
                        <canvas id="signature-pad" class="w-full h-40 cursor-crosshair bg-slate-950 block"></canvas>
                        <button type="button" id="clear-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-dark-800 hover:bg-dark-700 text-slate-300 text-xs font-bold rounded-lg border border-dark-700 shadow transition-all">
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
            <div class="pt-4 border-t border-dark-800/85">
                <button type="submit" id="submit-btn"
                        class="w-full py-3 bg-brand-teal hover:bg-[#1fd4be] active:scale-[0.99] text-dark-900 font-bold text-sm rounded-xl shadow-lg shadow-brand-teal/15 flex items-center justify-center space-x-2 transition-all">
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

<!-- Custom Validation Modal -->
<div id="validation-modal" class="fixed inset-0 z-[150] hidden items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm transition-all duration-300">
    <div class="w-full max-w-md bg-[#01222A] rounded-[28px] border border-rose-500/30 shadow-2xl p-6 sm:p-8 text-left transform scale-95 transition-transform duration-300 relative overflow-hidden">
        <!-- Accent Glow background decoration -->
        <div class="absolute -top-12 -left-12 w-24 h-24 bg-rose-500/10 rounded-full blur-xl pointer-events-none"></div>
        
        <div class="flex items-center space-x-3 mb-4 border-b border-dark-800 pb-3">
            <div class="w-10 h-10 rounded-full bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400 text-lg flex-shrink-0">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <div>
                <h3 class="text-sm font-bold text-white font-display">Incomplete Request</h3>
                <p class="text-slate-400 text-[11px]">Please fill in the required field(s):</p>
            </div>
        </div>
        
        <!-- Error List -->
        <div class="max-h-48 overflow-y-auto mb-6 pr-2 custom-scrollbar">
            <ul id="validation-errors-list" class="space-y-2 text-xs text-slate-300 pl-4 list-disc">
                <!-- Errors dynamically injected here -->
            </ul>
        </div>
        
        <div class="flex justify-end pt-3 border-t border-dark-800/80">
            <button type="button" id="validation-modal-close-btn" class="w-full sm:w-auto px-6 py-2.5 bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 font-bold text-xs rounded-xl transition-all shadow-md">
                <span>Go Back & Fix</span>
            </button>
        </div>
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

    // Set canvas dimensions relative to display size without flickering
    let lastWidth = 0;
    let lastHeight = 0;
    function resizeCanvas() {
        const currentWidth = canvas.offsetWidth;
        const currentHeight = canvas.offsetHeight;
        
        // Prevent unnecessary resets if dimensions haven't actually changed
        if (currentWidth === lastWidth && currentHeight === lastHeight) {
            return;
        }
        
        // Use a temporary canvas to save and redraw synchronously
        let tempCanvas = null;
        if (lastWidth > 0 && lastHeight > 0) {
            tempCanvas = document.createElement('canvas');
            tempCanvas.width = canvas.width;
            tempCanvas.height = canvas.height;
            const tempCtx = tempCanvas.getContext('2d');
            tempCtx.drawImage(canvas, 0, 0);
        }
        
        canvas.width = currentWidth;
        canvas.height = currentHeight;
        ctx.lineWidth = 3;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#ffffff';
        
        // Restore signature content synchronously if possible, fallback to image load
        if (tempCanvas) {
            ctx.drawImage(tempCanvas, 0, 0, currentWidth, currentHeight);
            sigInput.value = canvas.toDataURL();
        } else if (sigInput.value) {
            const img = new Image();
            img.onload = () => ctx.drawImage(img, 0, 0);
            img.src = sigInput.value;
        }
        
        lastWidth = currentWidth;
        lastHeight = currentHeight;
    }
    
    // Initial size
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Listen to changes from full screen modal to ensure local canvas is synchronised
    sigInput.addEventListener('change', () => {
        if (sigInput.value) {
            const img = new Image();
            img.onload = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
            };
            img.src = sigInput.value;
        } else {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
        }
    });

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

    // Custom Professional Validation Modal logic
    const validationModal = document.getElementById('validation-modal');
    const errorsList = document.getElementById('validation-errors-list');
    const closeValidationBtn = document.getElementById('validation-modal-close-btn');

    function showValidationModal(errors) {
        if (!validationModal || !errorsList) return;
        errorsList.innerHTML = '';
        errors.forEach(err => {
            const li = document.createElement('li');
            li.textContent = err;
            errorsList.appendChild(li);
        });
        validationModal.classList.remove('hidden');
        validationModal.classList.add('flex');
        
        const card = validationModal.querySelector('.transform');
        if (card) {
            setTimeout(() => {
                card.classList.remove('scale-95');
                card.classList.add('scale-100');
            }, 10);
        }
    }

    if (closeValidationBtn && validationModal) {
        closeValidationBtn.addEventListener('click', () => {
            const card = validationModal.querySelector('.transform');
            if (card) {
                card.classList.remove('scale-100');
                card.classList.add('scale-95');
            }
            setTimeout(() => {
                validationModal.classList.add('hidden');
                validationModal.classList.remove('flex');
            }, 150);
        });
    }

    // Intercept form submit and perform custom validation
    form.addEventListener('submit', (e) => {
        const errors = [];
        
        const nameVal = document.getElementById('visitor_name').value.trim();
        const eidVal = document.getElementById('eid').value.trim();
        const emailVal = document.getElementById('visitor_email').value.trim();
        
        const deptVal = deptSelect.value;
        const customDeptVal = customDeptInput.value.trim();
        
        const purpVal = purposeSelect.value;
        const customPurpVal = customPurposeInput.value.trim();
        
        const matDescVal = materialDescSelect.value;
        const customMatDescVal = customMaterialDescInput.value.trim();
        
        const serialVal = document.getElementById('material_serial').value.trim();
        const qtyVal = parseInt(document.getElementById('material_qty').value);
        const signatureVal = sigInput.value.trim();

        if (!nameVal) errors.push("Full Name is required.");
        if (!eidVal) errors.push("Employee ID (EID) / ID is required.");
        
        if (!emailVal) {
            errors.push("Email Address is required.");
        } else {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(emailVal)) {
                errors.push("Please enter a valid email address.");
            }
        }
        
        if (!deptVal) {
            errors.push("Program/Department selection is required.");
        } else if (deptVal === 'Others' && !customDeptVal) {
            errors.push("Please specify the program/department name.");
        }
        
        if (!purpVal) {
            errors.push("Purpose of Visit selection is required.");
        } else if (purpVal === 'Others' && !customPurpVal) {
            errors.push("Please specify the purpose of visit.");
        }
        
        if (!matDescVal) {
            errors.push("Material / Asset Description selection is required.");
        } else if (matDescVal === 'Others' && !customMatDescVal) {
            errors.push("Please specify the asset description.");
        }
        
        if (!serialVal) errors.push("Material Serial / S. No. is required.");
        
        if (isNaN(qtyVal) || qtyVal < 1) {
            errors.push("Quantity must be 1 or greater.");
        }
        
        if (!signatureVal) {
            errors.push("Visitor Signature is required. Please sign in the box below.");
        }

        if (errors.length > 0) {
            e.preventDefault();
            showValidationModal(errors);
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
