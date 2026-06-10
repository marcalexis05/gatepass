<?php
$page_title = "Register Visitor Pass";
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/mailer.php';

$errors = [];
$success = false;

// Default values
$visitor_name = '';
$visitor_email = '';
$visitor_phone = '';
$company_org = '';
$purpose = '';
$host_name = '';
$department = '';
$visit_date = date('Y-m-d'); // Default to today

// Form submission handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $visitor_name = trim($_POST['visitor_name'] ?? '');
    $visitor_email = trim($_POST['visitor_email'] ?? '');
    $visitor_phone = trim($_POST['visitor_phone'] ?? '');
    $company_org = trim($_POST['company_org'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $host_name = trim($_POST['host_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $visit_date = trim($_POST['visit_date'] ?? '');

    // Form Validations
    if (empty($visitor_name)) $errors['visitor_name'] = "Full Name is required.";
    
    if (empty($visitor_email)) {
        $errors['visitor_email'] = "Email Address is required.";
    } elseif (!filter_var($visitor_email, FILTER_VALIDATE_EMAIL)) {
        $errors['visitor_email'] = "Please enter a valid email address.";
    }
    
    if (empty($visitor_phone)) {
        $errors['visitor_phone'] = "Phone/Mobile number is required.";
    } elseif (!preg_match('/^[0-9+() -]{7,20}$/', $visitor_phone)) {
        $errors['visitor_phone'] = "Please enter a valid phone number.";
    }
    
    if (empty($purpose)) $errors['purpose'] = "Purpose of visit is required.";
    if (empty($host_name)) $errors['host_name'] = "Host person's name is required.";
    if (empty($department)) $errors['department'] = "Department is required.";
    
    if (empty($visit_date)) {
        $errors['visit_date'] = "Scheduled date is required.";
    } elseif (strtotime($visit_date) < strtotime(date('Y-m-d'))) {
        $errors['visit_date'] = "Visit date cannot be in the past.";
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

            // Insert into Database
            $insert_sql = "INSERT INTO gatepasses (gatepass_no, visitor_name, visitor_email, visitor_phone, company_org, purpose, host_name, department, visit_date, status)
                           VALUES (:gatepass_no, :visitor_name, :visitor_email, :visitor_phone, :company_org, :purpose, :host_name, :department, :visit_date, 'Pending')";
            
            $stmt = $pdo->prepare($insert_sql);
            $stmt->execute([
                'gatepass_no' => $gatepass_no,
                'visitor_name' => $visitor_name,
                'visitor_email' => $visitor_email,
                'visitor_phone' => $visitor_phone,
                'company_org' => $company_org ?: null,
                'purpose' => $purpose,
                'host_name' => $host_name,
                'department' => $department,
                'visit_date' => $visit_date
            ]);

            // Commit transaction
            $pdo->commit();

            // Fetch the inserted record for mailing
            $stmt = $pdo->prepare("SELECT * FROM gatepasses WHERE gatepass_no = ?");
            $stmt->execute([$gatepass_no]);
            $gatepass_data = $stmt->fetch();

            // Send Emails (Admin and Visitor)
            $admin_email = get_setting('admin_email', 'admin@example.com');
            
            // Attempt to send to visitor
            send_gatepass_email($gatepass_data, $visitor_email, $visitor_name, 'visitor');
            // Attempt to send to admin
            send_gatepass_email($gatepass_data, $admin_email, 'Administrator', 'admin');

            // Redirect to success screen
            header("Location: success.php?code=" . urlencode($gatepass_no) . "&new=1");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors['db'] = "An error occurred while saving your registration. Please try again. Info: " . $e->getMessage();
        }
    }
}
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

        <form action="register.php" method="POST" class="space-y-6">
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

                    <!-- Company / Organization -->
                    <div class="space-y-1.5">
                        <label for="company_org" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Company / Organization</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-building text-xs"></i></span>
                            <input type="text" id="company_org" name="company_org" value="<?php echo htmlspecialchars($company_org); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. Acme Corp (Optional)">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="space-y-1.5">
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

                    <!-- Phone Number -->
                    <div class="space-y-1.5">
                        <label for="visitor_phone" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Mobile Number <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-phone text-xs"></i></span>
                            <input type="text" id="visitor_phone" name="visitor_phone" required value="<?php echo htmlspecialchars($visitor_phone); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['visitor_phone']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. 09123456789">
                        </div>
                        <?php if (isset($errors['visitor_phone'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['visitor_phone']; ?></p>
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
                    <!-- Host Employee Name -->
                    <div class="space-y-1.5">
                        <label for="host_name" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Host Person / Contact <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-user-tie text-xs"></i></span>
                            <input type="text" id="host_name" name="host_name" required value="<?php echo htmlspecialchars($host_name); ?>"
                                   class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['host_name']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="e.g. Ms. Jane Smith">
                        </div>
                        <?php if (isset($errors['host_name'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['host_name']; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Department -->
                    <div class="space-y-1.5">
                        <label for="department" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Department <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-users-gear text-xs"></i></span>
                            <select id="department" name="department" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['department']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo empty($department) ? 'selected' : ''; ?>>Select Department</option>
                                <option value="Administration" <?php echo $department === 'Administration' ? 'selected' : ''; ?>>Administration</option>
                                <option value="Information Technology" <?php echo $department === 'Information Technology' ? 'selected' : ''; ?>>Information Technology</option>
                                <option value="Human Resources" <?php echo $department === 'Human Resources' ? 'selected' : ''; ?>>Human Resources</option>
                                <option value="Finance & Accounting" <?php echo $department === 'Finance & Accounting' ? 'selected' : ''; ?>>Finance & Accounting</option>
                                <option value="Operations" <?php echo $department === 'Operations' ? 'selected' : ''; ?>>Operations</option>
                                <option value="Security Office" <?php echo $department === 'Security Office' ? 'selected' : ''; ?>>Security Office</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
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
                    <div class="space-y-1.5">
                        <label for="purpose" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Purpose of Visit <span class="text-rose-500">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-circle-question text-xs"></i></span>
                            <select id="purpose" name="purpose" required
                                    class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border <?php echo isset($errors['purpose']) ? 'border-rose-500/80 focus:border-rose-500 focus:ring-rose-500' : 'border-slate-800 focus:border-indigo-500 focus:ring-indigo-500'; ?> rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all appearance-none cursor-pointer">
                                <option value="" disabled <?php echo empty($purpose) ? 'selected' : ''; ?>>Select Purpose</option>
                                <option value="Business Meeting" <?php echo $purpose === 'Business Meeting' ? 'selected' : ''; ?>>Business Meeting</option>
                                <option value="Maintenance / IT Support" <?php echo $purpose === 'Maintenance / IT Support' ? 'selected' : ''; ?>>Maintenance / IT Support</option>
                                <option value="Job Interview" <?php echo $purpose === 'Job Interview' ? 'selected' : ''; ?>>Job Interview</option>
                                <option value="Delivery / Courier" <?php echo $purpose === 'Delivery / Courier' ? 'selected' : ''; ?>>Delivery / Courier</option>
                                <option value="Personal Visit" <?php echo $purpose === 'Personal Visit' ? 'selected' : ''; ?>>Personal Visit</option>
                                <option value="Official Inquiry" <?php echo $purpose === 'Official Inquiry' ? 'selected' : ''; ?>>Official Inquiry</option>
                            </select>
                            <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-slate-500">
                                <i class="fa-solid fa-chevron-down text-xs"></i>
                            </span>
                        </div>
                        <?php if (isset($errors['purpose'])): ?>
                            <p class="text-rose-400 text-[11px]"><?php echo $errors['purpose']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-slate-800/80">
                <button type="submit"
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>
