<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success = false;

// Default values
$visitor_name = '';
$visitor_email = '';
$eid = '';
$purpose = '';
$material_desc = '';
$material_brand = '';
$material_serial = '';
$material_qty = 1;
$department = '';
$visit_date = date('Y-m-d'); // Default to today
$visitor_signature = '';

$is_multiple_materials = false;
$items = [];

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $visitor_email = trim($_POST['visitor_email'] ?? '');
    $eid = trim($_POST['eid'] ?? '');
    $department = trim($_POST['department'] ?? '');
    if ($department === 'Others') {
        $department = trim($_POST['custom_department'] ?? '');
    }
    $visit_date = trim($_POST['visit_date'] ?? '');
    if (empty($visit_date)) {
        $visit_date = date('Y-m-d');
    }
    $visitor_signature = $_POST['visitor_signature'] ?? '';

    $is_multiple_materials = isset($_POST['is_multiple_materials']);

    // Single item inputs (if not multiple)
    if (!$is_multiple_materials) {
        $purpose = trim($_POST['purpose'] ?? '');
        if ($purpose === 'Others') {
            $purpose = trim($_POST['custom_purpose'] ?? '');
        }
        $material_desc = trim($_POST['material_desc'] ?? '');
        if ($material_desc === 'Others') {
            $material_desc = trim($_POST['custom_material_desc'] ?? '');
        }
        $material_brand = trim($_POST['material_brand'] ?? '');
        $material_serial = trim($_POST['material_serial'] ?? '');
        $material_qty = (int)($_POST['material_qty'] ?? 1);

        $items = [[
            'purpose' => $purpose,
            'material_desc' => $material_desc,
            'material_brand' => $material_brand,
            'material_serial' => $material_serial,
            'material_qty' => $material_qty,
            'raw_purpose' => trim($_POST['purpose'] ?? ''),
            'raw_desc' => trim($_POST['material_desc'] ?? ''),
            'custom_purpose' => trim($_POST['custom_purpose'] ?? ''),
            'custom_desc' => trim($_POST['custom_material_desc'] ?? '')
        ]];
    } else {
        $raw_items = $_POST['items'] ?? [];
        if (empty($raw_items) || !is_array($raw_items)) {
            $errors['items'] = "At least one material item must be declared.";
        } else {
            foreach ($raw_items as $index => $raw_item) {
                $item_purpose = trim($raw_item['purpose'] ?? '');
                if ($item_purpose === 'Others') {
                    $item_purpose = trim($raw_item['custom_purpose'] ?? '');
                }
                $item_desc = trim($raw_item['material_desc'] ?? '');
                if ($item_desc === 'Others') {
                    $item_desc = trim($raw_item['custom_material_desc'] ?? '');
                }
                $item_brand = trim($raw_item['material_brand'] ?? '');
                $item_serial = trim($raw_item['material_serial'] ?? '');
                $item_qty = (int)($raw_item['material_qty'] ?? 1);

                if (empty($item_purpose)) {
                    $errors["item_{$index}_purpose"] = "Asset #" . ($index + 1) . ": Purpose of visit is required.";
                }
                if (empty($item_desc)) {
                    $errors["item_{$index}_desc"] = "Asset #" . ($index + 1) . ": Description is required.";
                }
                if (empty($item_brand)) {
                    $errors["item_{$index}_brand"] = "Asset #" . ($index + 1) . ": Brand is required.";
                }
                if (empty($item_serial)) {
                    $errors["item_{$index}_serial"] = "Asset #" . ($index + 1) . ": Serial / S.No. is required.";
                }
                if ($item_qty < 1) {
                    $errors["item_{$index}_qty"] = "Asset #" . ($index + 1) . ": Quantity must be at least 1.";
                }

                $items[] = [
                    'purpose' => $item_purpose,
                    'material_desc' => $item_desc,
                    'material_brand' => $item_brand,
                    'material_serial' => $item_serial,
                    'material_qty' => $item_qty,
                    'raw_purpose' => trim($raw_item['purpose'] ?? ''),
                    'raw_desc' => trim($raw_item['material_desc'] ?? ''),
                    'custom_purpose' => trim($raw_item['custom_purpose'] ?? ''),
                    'custom_desc' => trim($raw_item['custom_material_desc'] ?? '')
                ];
            }
        }
    }

    // Form Validations
    if (empty($visitor_name)) $errors['visitor_name'] = "Full Name is required.";
    
    if (empty($visitor_email)) {
        $errors['visitor_email'] = "Email Address is required.";
    } elseif (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
        $errors['visitor_email'] = "Please enter a valid email address.";
    }
    
    if (empty($eid)) $errors['eid'] = "Employee ID (EID) / ID is required.";
    if (empty($department)) $errors['department'] = "Program/Department is required.";

    if (!$is_multiple_materials) {
        if (empty($purpose)) $errors['purpose'] = "Purpose of visit is required.";
        if (empty($material_desc)) $errors['material_desc'] = "Material / Asset Description is required.";
        if (empty($material_brand)) $errors['material_brand'] = "Material Brand is required.";
        if (empty($material_serial)) $errors['material_serial'] = "Material Serial / S. No. is required.";
        if (empty($material_qty) || $material_qty < 1) $errors['material_qty'] = "Quantity must be 1 or greater.";
    }

    if (empty($visitor_signature)) {
        $errors['visitor_signature'] = "Visitor signature is required when checking in.";
    }

    // Process registration if there are no validation errors
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Generate unique Gatepass Number: CNX-YYYYMMDD-XXXX
            $today_prefix = 'CNX-' . date('Ymd') . '-';
            
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

            // Populate main table columns from the first item
            $first_item = $items[0];

            // Insert into Database (automatically Checked In with current time)
            $insert_sql = "INSERT INTO gatepasses (gatepass_no, visitor_name, visitor_email, eid, purpose, material_desc, material_brand, material_serial, material_qty, department, visit_date, status, time_in, visitor_signature)
                           VALUES (:gatepass_no, :visitor_name, :visitor_email, :eid, :purpose, :material_desc, :material_brand, :material_serial, :material_qty, :department, :visit_date, 'Checked In', CURRENT_TIME(), :visitor_signature)";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([
                'gatepass_no' => $gatepass_no,
                'visitor_name' => $visitor_name,
                'visitor_email' => $visitor_email,
                'eid' => $eid ?: null,
                'purpose' => $first_item['purpose'],
                'material_desc' => $first_item['material_desc'] ?: null,
                'material_brand' => $first_item['material_brand'] ?: null,
                'material_serial' => $first_item['material_serial'] ?: null,
                'material_qty' => $first_item['material_qty'] ?: 1,
                'department' => $department,
                'visit_date' => $visit_date,
                'visitor_signature' => $visitor_signature ?: null
            ]);

            // Insert all items into gatepass_materials
            $insert_m_sql = "INSERT INTO gatepass_materials (gatepass_no, purpose, material_desc, material_brand, material_serial, material_qty)
                             VALUES (:gatepass_no, :purpose, :material_desc, :material_brand, :material_serial, :material_qty)";
            $stmt_m = $pdo->prepare($insert_m_sql);
            foreach ($items as $item) {
                $stmt_m->execute([
                    'gatepass_no' => $gatepass_no,
                    'purpose' => $item['purpose'],
                    'material_desc' => $item['material_desc'] ?: null,
                    'material_brand' => $item['material_brand'] ?: null,
                    'material_serial' => $item['material_serial'] ?: null,
                    'material_qty' => $item['material_qty'] ?: 1
                ]);
            }

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

                <!-- Declare Multiple Items Checkbox Switch -->
                <div class="mb-6 p-4 rounded-xl bg-dark-950/40 border border-dark-800/60 flex items-center justify-between">
                    <div class="space-y-0.5 pr-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Declare Multiple Items</h4>
                        <p class="text-[10px] text-slate-500">Enable this if you have more than one device or asset to log under this pass.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 select-none">
                        <input type="checkbox" id="is_multiple_materials" name="is_multiple_materials" class="sr-only peer" <?php echo $is_multiple_materials ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-dark-900 peer-focus:outline-none rounded-full border border-dark-800 peer-checked:bg-brand-teal/20 peer-checked:border-brand-teal/60 peer-checked:after:bg-brand-teal peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-slate-500 after:rounded-full after:h-4 after:w-4 after:transition-all duration-300"></div>
                    </label>
                </div>

                <!-- Dynamic Multiple Materials Wrapper -->
                <div id="multiple-materials-wrapper" class="hidden space-y-4 mb-6">
                    <div id="materials-list" class="space-y-4"></div>
                    <button type="button" id="add-material-btn" class="w-full py-2.5 bg-dark-900 hover:bg-dark-850 border border-dark-800 hover:border-brand-teal/40 text-slate-350 hover:text-brand-teal font-semibold text-xs rounded-xl flex items-center justify-center space-x-2 transition-all">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>Add Another Material / Asset</span>
                    </button>
                </div>

                <!-- Dynamic Material Item Template -->
                <template id="material-item-template">
                    <div class="material-item-card p-5 rounded-2xl border border-dark-800 bg-dark-950/40 relative space-y-4 transition-all duration-300">
                        <!-- Card Header -->
                        <div class="flex items-center justify-between border-b border-dark-900/60 pb-2">
                            <span class="text-xs font-bold text-brand-teal/80 tracking-wider uppercase item-number-label">Asset #1</span>
                            <button type="button" class="remove-item-btn text-rose-500 hover:text-rose-450 hover:bg-rose-500/10 px-2.5 py-1 rounded-lg text-[10px] font-bold transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-trash-can text-[9px]"></i>
                                <span>Remove</span>
                            </button>
                        </div>
                        
                        <!-- Grid fields -->
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                            <!-- Purpose of Visit -->
                            <div class="space-y-1.5 md:col-span-6">
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Purpose of Visit <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-circle-question text-xs"></i></span>
                                    <select name="items[INDEX][purpose]" class="item-purpose w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white text-sm focus:outline-none transition-all appearance-none cursor-pointer">
                                        <option value="" disabled selected>Select Purpose</option>
                                        <option value="Re-image">Re-image</option>
                                        <option value="Replacement">Replacement</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <!-- Custom Purpose -->
                                <div class="custom-purpose-wrapper relative mt-2 hidden">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                                    <input type="text" name="items[INDEX][custom_purpose]" class="item-custom-purpose w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white text-sm placeholder-slate-500 focus:outline-none transition-all" placeholder="Specify purpose">
                                </div>
                            </div>

                            <!-- Material / Asset Description -->
                            <div class="space-y-1.5 md:col-span-6">
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Description <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-laptop text-xs"></i></span>
                                    <select name="items[INDEX][material_desc]" class="item-desc w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white text-sm focus:outline-none transition-all appearance-none cursor-pointer">
                                        <option value="" disabled selected>Select Asset</option>
                                        <option value="CPU">CPU</option>
                                        <option value="Laptop">Laptop</option>
                                        <option value="Headset">Headset</option>
                                        <option value="Others">Others</option>
                                    </select>
                                </div>
                                <!-- Custom Material Desc -->
                                <div class="custom-material-desc-wrapper relative mt-2 hidden">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-pen text-xs"></i></span>
                                    <input type="text" name="items[INDEX][custom_material_desc]" class="item-custom-desc w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white text-sm placeholder-slate-500 focus:outline-none transition-all" placeholder="Specify asset description">
                                </div>
                            </div>

                            <!-- Brand -->
                            <div class="space-y-1.5 md:col-span-5">
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Brand <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-tag text-xs"></i></span>
                                    <input type="text" name="items[INDEX][material_brand]" class="item-brand w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none transition-all" placeholder="e.g. Dell">
                                </div>
                            </div>

                            <!-- Serial No -->
                            <div class="space-y-1.5 md:col-span-5">
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Serial / S.No. <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-barcode text-xs"></i></span>
                                    <input type="text" name="items[INDEX][material_serial]" class="item-serial w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none transition-all" placeholder="e.g. 5CD52">
                                </div>
                            </div>

                            <!-- Quantity -->
                            <div class="space-y-1.5 md:col-span-2">
                                <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Qty <span class="text-rose-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-arrow-up-1-9 text-xs"></i></span>
                                    <input type="number" name="items[INDEX][material_qty]" min="1" value="1" class="item-qty w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-dark-800 focus:border-brand-teal focus:ring-brand-teal rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none transition-all">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- Single Material Wrapper (Legacy Single Fields) -->
                <div id="single-material-wrapper">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 mb-6">
                        <!-- Material Description -->
                        <div class="space-y-1.5 lg:col-span-4" id="material-desc-container">
                            <label for="material_desc" class="text-xs font-bold text-slate-300 uppercase tracking-wide lg:h-12 flex items-end pb-1">Material / Asset Description <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-laptop text-xs"></i></span>
                                <select id="material_desc" name="material_desc" required
                                        class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_desc']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?>> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                    <option value="" disabled <?php echo ($material_desc === '') ? 'selected' : ''; ?>>Select Asset</option>
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

                        <!-- Material Brand -->
                        <div class="space-y-1.5 lg:col-span-3">
                            <label for="material_brand" class="text-xs font-bold text-slate-300 uppercase tracking-wide lg:h-12 flex items-end pb-1">Material Brand <span class="text-rose-500">*</span></label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-tag text-xs"></i></span>
                                <input type="text" id="material_brand" name="material_brand" required value="<?php echo htmlspecialchars($material_brand); ?>"
                                       class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['material_brand']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-dark-800 focus:border-brand-teal focus:ring-brand-teal'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                       placeholder="e.g. Dell, HP, Apple">
                            </div>
                            <?php if (isset($errors['material_brand'])): ?>
                                <p class="text-rose-400 text-[11px]"><?php echo $errors['material_brand']; ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Material Serial No (S. No.) -->
                        <div class="space-y-1.5 lg:col-span-3">
                            <label for="material_serial" class="text-xs font-bold text-slate-300 uppercase tracking-wide font-display lg:h-12 flex items-end pb-1">Material Serial / S. No. <span class="text-rose-500">*</span></label>
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
                        <div class="space-y-1.5 lg:col-span-2">
                            <label for="material_qty" class="text-xs font-bold text-slate-300 uppercase tracking-wide lg:h-12 flex items-end pb-1">Quantity <span class="text-rose-500">*</span></label>
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
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Visitor Signature <span class="text-rose-500">*</span></label>
                    <div class="relative bg-dark-950/45 border border-dark-800/80 rounded-xl overflow-hidden shadow-inner">
                        <canvas id="signature-pad" class="w-full h-40 cursor-crosshair bg-transparent block"></canvas>
                        <button type="button" id="clear-sig" class="absolute bottom-2 right-2 px-3 py-1 bg-slate-850 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-lg border border-slate-800 shadow transition-all">
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
    // Helper function to restrict material description options if purpose is Re-image
    function filterDescOptions(purposeValue, descSelect) {
        if (!descSelect) return;
        
        const wrapper = descSelect.closest('.custom-select-wrapper');
        const dropdown = wrapper ? wrapper.querySelector('.custom-select-dropdown') : null;
        
        if (purposeValue === 'Re-image') {
            // Automatically set value to CPU if it is not already
            if (descSelect.value !== 'CPU') {
                descSelect.value = 'CPU';
                descSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            
            // Disable native option elements
            Array.from(descSelect.options).forEach(opt => {
                if (opt.value !== 'CPU' && opt.value !== '') {
                    opt.disabled = true;
                } else {
                    opt.disabled = false;
                }
            });
            
            // Hide custom options divs
            if (dropdown) {
                dropdown.querySelectorAll('.custom-select-option').forEach(optDiv => {
                    const val = optDiv.getAttribute('data-value');
                    if (val !== 'CPU' && val !== '') {
                        optDiv.style.display = 'none';
                    } else {
                        optDiv.style.display = '';
                    }
                });
            }
        } else {
            // Restore native options
            Array.from(descSelect.options).forEach(opt => {
                opt.disabled = false;
            });
            
            // Restore custom options divs
            if (dropdown) {
                dropdown.querySelectorAll('.custom-select-option').forEach(optDiv => {
                    optDiv.style.display = '';
                });
            }
        }
    }

    // Dynamic Multi-Materials Scripting
    const multipleCheckbox = document.getElementById('is_multiple_materials');
    const singleMaterialWrapper = document.getElementById('single-material-wrapper');
    const multipleMaterialsWrapper = document.getElementById('multiple-materials-wrapper');
    const purposeContainer = document.getElementById('purpose-container');
    const addMaterialBtn = document.getElementById('add-material-btn');
    const materialsList = document.getElementById('materials-list');

    let materialIndex = 0;

    function addMaterialItem(data = null) {
        const template = document.getElementById('material-item-template');
        if (!template) return;
        const clone = template.content.cloneNode(true);
        
        // Replace INDEX prefix in names
        clone.querySelectorAll('[name*="INDEX"]').forEach(el => {
            el.name = el.name.replace('INDEX', materialIndex);
        });
        
        const card = clone.querySelector('.material-item-card');
        
        const itemPurposeSelect = clone.querySelector('.item-purpose');
        const customPurposeWrapper = clone.querySelector('.custom-purpose-wrapper');
        const customPurposeInput = clone.querySelector('.item-custom-purpose');

        const itemDescSelect = clone.querySelector('.item-desc');
        const customDescWrapper = clone.querySelector('.custom-material-desc-wrapper');
        const customDescInput = clone.querySelector('.item-custom-desc');

        const brandInput = clone.querySelector('.item-brand');
        const serialInput = clone.querySelector('.item-serial');
        const qtyInput = clone.querySelector('.item-qty');
        
        // Populate inputs if data is provided
        if (data) {
            itemPurposeSelect.value = data.raw_purpose || '';
            if (data.raw_purpose === 'Others') {
                customPurposeWrapper.classList.remove('hidden');
                customPurposeInput.value = data.custom_purpose || '';
            }
            
            itemDescSelect.value = data.raw_desc || '';
            if (data.raw_desc === 'Others') {
                customDescWrapper.classList.remove('hidden');
                customDescInput.value = data.custom_desc || '';
            }
            
            brandInput.value = data.material_brand || '';
            serialInput.value = data.material_serial || '';
            qtyInput.value = data.material_qty || 1;
        }

        // Add purpose dropdown change behavior
        itemPurposeSelect.addEventListener('change', () => {
            if (itemPurposeSelect.value === 'Others') {
                customPurposeWrapper.classList.remove('hidden');
                if (multipleCheckbox.checked) {
                    customPurposeInput.required = true;
                }
            } else {
                customPurposeWrapper.classList.add('hidden');
                customPurposeInput.required = false;
                customPurposeInput.value = '';
            }
            // Filter options when purpose is Re-image
            filterDescOptions(itemPurposeSelect.value, itemDescSelect);
        });

        // Add material description dropdown change behavior
        itemDescSelect.addEventListener('change', () => {
            if (itemDescSelect.value === 'Others') {
                customDescWrapper.classList.remove('hidden');
                if (multipleCheckbox.checked) {
                    customDescInput.required = true;
                }
            } else {
                customDescWrapper.classList.add('hidden');
                customDescInput.required = false;
                customDescInput.value = '';
            }
        });

        // Remove button handler
        clone.querySelector('.remove-item-btn').addEventListener('click', () => {
            const cards = materialsList.querySelectorAll('.material-item-card');
            if (cards.length <= 1) {
                alert("At least one material item is required.");
                return;
            }
            card.remove();
            updateItemNumbers();
        });

        materialsList.appendChild(clone);
        
        // Initialize custom selects on dynamically added elements
        if (typeof initCustomSelects === 'function') {
            initCustomSelects();
        }

        // Apply dynamic option filtering on initial card rendering
        filterDescOptions(itemPurposeSelect.value, itemDescSelect);

        materialIndex++;
        updateItemNumbers();
        toggleRequiredFields();
    }

    function updateItemNumbers() {
        const labels = materialsList.querySelectorAll('.item-number-label');
        labels.forEach((label, idx) => {
            label.textContent = `Asset #${idx + 1}`;
        });
    }

    function toggleRequiredFields() {
        const isMultiple = multipleCheckbox.checked;
        
        // Single field requirements
        const purposeSelect = document.getElementById('purpose');
        const customPurposeInput = document.getElementById('custom_purpose');
        const materialDescSelect = document.getElementById('material_desc');
        const customMaterialDescInput = document.getElementById('custom_material_desc');
        const brandInput = document.getElementById('material_brand');
        const serialInput = document.getElementById('material_serial');
        const qtyInput = document.getElementById('material_qty');
        
        if (purposeSelect) purposeSelect.required = !isMultiple;
        if (customPurposeInput) customPurposeInput.required = (!isMultiple && purposeSelect && purposeSelect.value === 'Others');
        if (materialDescSelect) materialDescSelect.required = !isMultiple;
        if (customMaterialDescInput) customMaterialDescInput.required = (!isMultiple && materialDescSelect && materialDescSelect.value === 'Others');
        if (brandInput) brandInput.required = !isMultiple;
        if (serialInput) serialInput.required = !isMultiple;
        if (qtyInput) qtyInput.required = !isMultiple;
        
        // Dynamic field requirements
        const cards = materialsList.querySelectorAll('.material-item-card');
        cards.forEach(card => {
            const itemPurpose = card.querySelector('.item-purpose');
            const itemCustomPurpose = card.querySelector('.item-custom-purpose');
            const itemDesc = card.querySelector('.item-desc');
            const itemCustomDesc = card.querySelector('.item-custom-desc');
            const itemBrand = card.querySelector('.item-brand');
            const itemSerial = card.querySelector('.item-serial');
            const itemQty = card.querySelector('.item-qty');
            
            if (itemPurpose) itemPurpose.required = isMultiple;
            if (itemCustomPurpose) itemCustomPurpose.required = (isMultiple && itemPurpose && itemPurpose.value === 'Others');
            if (itemDesc) itemDesc.required = isMultiple;
            if (itemCustomDesc) itemCustomDesc.required = (isMultiple && itemDesc && itemDesc.value === 'Others');
            if (itemBrand) itemBrand.required = isMultiple;
            if (itemSerial) itemSerial.required = isMultiple;
            if (itemQty) itemQty.required = isMultiple;
        });
    }

    if (multipleCheckbox) {
        multipleCheckbox.addEventListener('change', () => {
            const isMultiple = multipleCheckbox.checked;
            if (isMultiple) {
                singleMaterialWrapper.classList.add('hidden');
                purposeContainer.classList.add('hidden');
                multipleMaterialsWrapper.classList.remove('hidden');
                
                // If no items have been added, initialize one item
                if (materialsList.querySelectorAll('.material-item-card').length === 0) {
                    addMaterialItem();
                }
            } else {
                singleMaterialWrapper.classList.remove('hidden');
                purposeContainer.classList.remove('hidden');
                multipleMaterialsWrapper.classList.add('hidden');
            }
            toggleRequiredFields();
        });
    }

    if (addMaterialBtn) {
        addMaterialBtn.addEventListener('click', () => {
            addMaterialItem();
        });
    }

    <?php if ($is_multiple_materials && !empty($items)): ?>
        multipleCheckbox.checked = true;
        // Trigger UI setup
        singleMaterialWrapper.classList.add('hidden');
        purposeContainer.classList.add('hidden');
        multipleMaterialsWrapper.classList.remove('hidden');
        <?php foreach ($items as $item): ?>
            addMaterialItem(<?php echo json_encode($item); ?>);
        <?php endforeach; ?>
    <?php endif; ?>

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
        purposeSelect.addEventListener('change', () => {
            toggleCustomPurpose();
            filterDescOptions(purposeSelect.value, document.getElementById('material_desc'));
        });
        toggleCustomPurpose();
    }

    // Initial sync for single fields on load after custom select trigger is initialized
    setTimeout(() => {
        if (purposeSelect) {
            filterDescOptions(purposeSelect.value, document.getElementById('material_desc'));
        }
    }, 0);

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
            sigInput.value = window.getInvertedDataURL(canvas);
        } else if (sigInput.value) {
            const img = new Image();
            img.onload = () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                // Invert dark/black signature to white ink for screen display
                try {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const data = imgData.data;
                    let inverted = false;
                    for (let i = 0; i < data.length; i += 4) {
                        if (data[i + 3] > 0) {
                            const isDark = (data[i] + data[i+1] + data[i+2]) / 3 < 128;
                            if (isDark) {
                                data[i] = 255 - data[i];
                                data[i+1] = 255 - data[i+1];
                                data[i+2] = 255 - data[i+2];
                                inverted = true;
                            }
                        }
                    }
                    if (inverted) {
                        ctx.putImageData(imgData, 0, 0);
                    }
                } catch (e) {
                    console.error("Error inverting signature loaded into register canvas:", e);
                }
            };
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
                
                // Invert dark/black signature to white ink for screen display
                try {
                    const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const data = imgData.data;
                    let inverted = false;
                    for (let i = 0; i < data.length; i += 4) {
                        if (data[i + 3] > 0) {
                            const isDark = (data[i] + data[i+1] + data[i+2]) / 3 < 128;
                            if (isDark) {
                                data[i] = 255 - data[i];
                                data[i+1] = 255 - data[i+1];
                                data[i+2] = 255 - data[i+2];
                                inverted = true;
                            }
                        }
                    }
                    if (inverted) {
                        ctx.putImageData(imgData, 0, 0);
                    }
                } catch (e) {
                    console.error("Error inverting signature loaded from modal into register canvas:", e);
                }
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
            sigInput.value = window.getInvertedDataURL(canvas);
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
        
        const brandVal = document.getElementById('material_brand').value.trim();
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
        
        const isMultiple = multipleCheckbox.checked;
        if (isMultiple) {
            const cards = materialsList.querySelectorAll('.material-item-card');
            if (cards.length === 0) {
                errors.push("At least one material item must be added.");
            } else {
                cards.forEach((card, idx) => {
                    const num = idx + 1;
                    const itemPurposeSelect = card.querySelector('.item-purpose');
                    const itemPurposeVal = itemPurposeSelect ? itemPurposeSelect.value : '';
                    const itemCustomPurposeInput = card.querySelector('.item-custom-purpose');
                    const itemCustomPurposeVal = itemCustomPurposeInput ? itemCustomPurposeInput.value.trim() : '';

                    const itemDescSelect = card.querySelector('.item-desc');
                    const itemDescVal = itemDescSelect ? itemDescSelect.value : '';
                    const itemCustomDescInput = card.querySelector('.item-custom-desc');
                    const itemCustomDescVal = itemCustomDescInput ? itemCustomDescInput.value.trim() : '';

                    const itemBrandVal = card.querySelector('.item-brand').value.trim();
                    const itemSerialVal = card.querySelector('.item-serial').value.trim();
                    const itemQtyVal = parseInt(card.querySelector('.item-qty').value);

                    if (!itemPurposeVal) {
                        errors.push(`Asset #${num}: Purpose of Visit selection is required.`);
                    } else if (itemPurposeVal === 'Others' && !itemCustomPurposeVal) {
                        errors.push(`Asset #${num}: Please specify the purpose of visit.`);
                    }

                    if (!itemDescVal) {
                        errors.push(`Asset #${num}: Description selection is required.`);
                    } else if (itemDescVal === 'Others' && !itemCustomDescVal) {
                        errors.push(`Asset #${num}: Please specify the asset description.`);
                    }

                    if (!itemBrandVal) errors.push(`Asset #${num}: Material Brand is required.`);
                    if (!itemSerialVal) errors.push(`Asset #${num}: Material Serial / S. No. is required.`);
                    if (isNaN(itemQtyVal) || itemQtyVal < 1) {
                        errors.push(`Asset #${num}: Quantity must be 1 or greater.`);
                    }
                });
            }
        } else {
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
            
            if (!brandVal) errors.push("Material Brand is required.");
            if (!serialVal) errors.push("Material Serial / S. No. is required.");
            
            if (isNaN(qtyVal) || qtyVal < 1) {
                errors.push("Quantity must be 1 or greater.");
            }
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
