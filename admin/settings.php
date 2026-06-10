<?php
$page_title = "System Settings";
require_once __DIR__ . '/../includes/auth.php';
// Secure the page
require_login();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';

$errors = [];
$success_msg = '';

// Fetch dynamic server local IP suggestions
$server_detected_ip = gethostbyname(gethostname());

// Handle Profile Updates
if (isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (empty($full_name) || empty($username) || empty($email) || empty($current_password)) {
        $errors['profile'] = "All profile fields and your current password are required.";
    } else {
        // Verify current password first
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            try {
                // If changing password
                if (!empty($new_password)) {
                    if (strlen($new_password) < 6) {
                        $errors['profile'] = "New password must be at least 6 characters long.";
                    } else {
                        $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ?, password = ? WHERE id = ?");
                        $stmt->execute([$full_name, $username, $email, $hashed_pass, $_SESSION['user_id']]);
                        $success_msg = "Profile and password updated successfully.";
                    }
                } else {
                    // Regular profile update
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, email = ? WHERE id = ?");
                    $stmt->execute([$full_name, $username, $email, $_SESSION['user_id']]);
                    $success_msg = "Profile updated successfully.";
                }

                if (empty($errors)) {
                    // Update session variables
                    $_SESSION['username'] = $username;
                    $_SESSION['full_name'] = $full_name;
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $errors['profile'] = "Username is already taken by another account.";
                } else {
                    $errors['profile'] = "Profile update failed: " . $e->getMessage();
                }
            }
        } else {
            $errors['profile'] = "Incorrect current password. Verification failed.";
        }
    }
}

// Handle System Settings Updates
if (isset($_POST['update_settings'])) {
    $system_name = trim($_POST['system_name'] ?? '');
    $server_ip = trim($_POST['server_ip'] ?? '');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = trim($_POST['smtp_port'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? '');
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');
    $admin_email = trim($_POST['admin_email'] ?? '');

    if (empty($system_name) || empty($server_ip) || empty($admin_email)) {
        $errors['settings'] = "System Name, Server IP, and Admin Notification Email are required.";
    } else {
        update_setting('system_name', $system_name);
        update_setting('server_ip', $server_ip);
        update_setting('smtp_host', $smtp_host);
        update_setting('smtp_port', $smtp_port);
        update_setting('smtp_secure', $smtp_secure);
        update_setting('smtp_user', $smtp_user);
        update_setting('smtp_pass', $smtp_pass);
        update_setting('admin_email', $admin_email);
        
        $success_msg = "System configurations saved successfully.";
        
        // Reload system name
        $system_name_header = $system_name;
    }
}

// Fetch current values
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$sys_name = get_setting('system_name', 'GatePass Pro');
$serv_ip = get_setting('server_ip', 'localhost');
$s_host = get_setting('smtp_host', 'smtp.gmail.com');
$s_port = get_setting('smtp_port', '587');
$s_sec = get_setting('smtp_secure', 'tls');
$s_user = get_setting('smtp_user', '');
$s_pass = get_setting('smtp_pass', '');
$a_email = get_setting('admin_email', 'admin@example.com');
?>

<div class="max-w-4xl mx-auto py-4 space-y-8">
    <div>
        <h1 class="text-3xl font-black text-white tracking-tight">System Settings</h1>
        <p class="text-slate-400 text-sm">Configure system brand identities, SMTP relay configurations, and administrator profiles.</p>
    </div>

    <?php if ($success_msg): ?>
        <!-- Alert Notification -->
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm flex items-center alert-dismissible shadow-lg">
            <i class="fa-solid fa-circle-check text-emerald-400 mr-3 text-lg"></i>
            <span class="font-semibold"><?php echo htmlspecialchars($success_msg); ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-12 gap-8 items-start">
        <!-- Sidebar Navigation (Scroll Spy Anchors) -->
        <div class="md:col-span-4 space-y-3 sticky top-24">
            <a href="#system-config" class="block p-4 rounded-2xl bg-slate-800/40 border border-slate-700/60 hover:bg-slate-800 text-sm font-semibold text-slate-200 hover:text-white transition-all flex items-center gap-3">
                <i class="fa-solid fa-sliders text-indigo-400 text-base"></i>
                <span>General Configurations</span>
            </a>
            <a href="#smtp-settings" class="block p-4 rounded-2xl bg-slate-800/40 border border-slate-700/60 hover:bg-slate-800 text-sm font-semibold text-slate-200 hover:text-white transition-all flex items-center gap-3">
                <i class="fa-solid fa-envelope-open-text text-indigo-400 text-base"></i>
                <span>SMTP Mail Settings</span>
            </a>
            <a href="#admin-profile" class="block p-4 rounded-2xl bg-slate-800/40 border border-slate-700/60 hover:bg-slate-800 text-sm font-semibold text-slate-200 hover:text-white transition-all flex items-center gap-3">
                <i class="fa-solid fa-user-gear text-indigo-400 text-base"></i>
                <span>Admin Profile Security</span>
            </a>
        </div>

        <!-- Setting Sections -->
        <div class="md:col-span-8 space-y-8">
            <!-- Section 1: General configurations -->
            <div id="system-config" class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-indigo-600"></div>
                <h3 class="text-lg font-black text-white mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-indigo-400"></i> General Configurations
                </h3>

                <?php if (isset($errors['settings'])): ?>
                    <div class="mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                        <?php echo $errors['settings']; ?>
                    </div>
                <?php endif; ?>

                <form action="settings.php#system-config" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- System Name -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">System Branding Name</label>
                            <input type="text" name="system_name" required value="<?php echo htmlspecialchars($sys_name); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- Server IP -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Server IP / Domain</label>
                            <input type="text" name="server_ip" required value="<?php echo htmlspecialchars($serv_ip); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all">
                            <span class="block text-[10px] text-slate-500 mt-1">
                                Suggested local network IP: <strong class="text-indigo-400 select-all"><?php echo $server_detected_ip; ?></strong>
                            </span>
                        </div>

                        <!-- Admin Notification Email -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Admin Notification Email</label>
                            <input type="email" name="admin_email" required value="<?php echo htmlspecialchars($a_email); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                                   placeholder="admin@example.com">
                            <span class="block text-[10px] text-slate-500 mt-1">
                                Duplicate copy of newly generated gatepasses will be sent to this email.
                            </span>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800/80 text-right">
                        <button type="submit" name="update_settings"
                                class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-bold text-xs rounded-xl shadow-lg transition-all">
                            Save System Configurations
                        </button>
                    </div>
                </form>
            </div>

            <!-- Section 2: SMTP Mail settings -->
            <div id="smtp-settings" class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 to-emerald-600"></div>
                <h3 class="text-lg font-black text-white mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-envelope-open-text text-emerald-400"></i> SMTP Mail Server Settings
                </h3>

                <!-- Gmail setup instructions helper block -->
                <div class="mb-6 p-4 rounded-2xl bg-indigo-950/20 border border-indigo-900/30 text-slate-300 text-xs space-y-2">
                    <h4 class="font-bold text-indigo-400 flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-info"></i> How to use Gmail SMTP
                    </h4>
                    <p>To automatically dispatch emails to visitor addresses, you must use a Gmail <strong>App Password</strong>:</p>
                    <ol class="list-decimal pl-4 space-y-1">
                        <li>Go to your Google Account settings &gt; Security.</li>
                        <li>Enable <strong>2-Step Verification</strong> (required).</li>
                        <li>Search/Go to <strong>App passwords</strong>.</li>
                        <li>Create a new app called "GatePass" and copy the 16-character generated password (e.g., `abcd efgh ijkl mnop`).</li>
                        <li>Input that password in the <strong>SMTP Password</strong> field below.</li>
                    </ol>
                </div>

                <form action="settings.php#smtp-settings" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- SMTP Host -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">SMTP Host Server</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($s_host); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- SMTP Port -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">SMTP Port</label>
                            <input type="text" name="smtp_port" value="<?php echo htmlspecialchars($s_port); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- SMTP Encryption -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Encryption / Secure Protocol</label>
                            <select name="smtp_secure" class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all cursor-pointer">
                                <option value="tls" <?php echo $s_sec === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                                <option value="ssl" <?php echo $s_sec === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            </select>
                        </div>

                        <!-- Dummy spacers / alignments -->
                        <div></div>

                        <!-- SMTP Username -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">SMTP User / Username</label>
                            <input type="email" name="smtp_user" value="<?php echo htmlspecialchars($s_user); ?>" placeholder="e.g. sender@gmail.com"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- SMTP Password -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">SMTP App Password</label>
                            <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($s_pass); ?>" placeholder="Enter 16-character code"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>
                    </div>

                    <!-- Carry other general settings to avoid overwrites -->
                    <input type="hidden" name="system_name" value="<?php echo htmlspecialchars($sys_name); ?>">
                    <input type="hidden" name="server_ip" value="<?php echo htmlspecialchars($serv_ip); ?>">
                    <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($a_email); ?>">

                    <div class="pt-4 border-t border-slate-800/80 text-right">
                        <button type="submit" name="update_settings"
                                class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white font-bold text-xs rounded-xl shadow-lg transition-all">
                            Save SMTP Credentials
                        </button>
                    </div>
                </form>
            </div>

            <!-- Section 3: Admin profile -->
            <div id="admin-profile" class="glass-card p-6 sm:p-8 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-purple-500 to-purple-600"></div>
                <h3 class="text-lg font-black text-white mb-6 flex items-center gap-2">
                    <i class="fa-solid fa-user-gear text-purple-400"></i> Admin Profile Security
                </h3>

                <?php if (isset($errors['profile'])): ?>
                    <div class="mb-4 p-3 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs">
                        <?php echo $errors['profile']; ?>
                    </div>
                <?php endif; ?>

                <form action="settings.php#admin-profile" method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Full Name -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Profile Full Name</label>
                            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($profile['full_name']); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- Username -->
                        <div class="space-y-1.5">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Login Username</label>
                            <input type="text" name="username" required value="<?php echo htmlspecialchars($profile['username']); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- Contact Email -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Personal Contact Email</label>
                            <input type="email" name="email" required value="<?php echo htmlspecialchars($profile['email']); ?>"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- New Password -->
                        <div class="space-y-1.5 sm:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wide">New Password (Optional)</label>
                            <input type="password" name="new_password" placeholder="Leave blank to keep current password"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>

                        <!-- Current Password (Required to authorise) -->
                        <div class="space-y-1.5 sm:col-span-2 pt-2 border-t border-slate-800/80">
                            <label class="block text-xs font-bold text-rose-400 uppercase tracking-wide">Current Account Password <span class="text-rose-500">*</span></label>
                            <input type="password" name="current_password" required placeholder="Enter current password to save changes"
                                   class="w-full px-4 py-2.5 bg-dark-900 border border-rose-900/30 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white text-sm focus:outline-none focus:ring-1 transition-all">
                        </div>
                    </div>

                    <div class="pt-4 text-right">
                        <button type="submit" name="update_profile"
                                class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 active:scale-95 text-white font-bold text-xs rounded-xl shadow-lg transition-all">
                            Apply Profile Updates
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
