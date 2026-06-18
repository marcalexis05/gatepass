<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

$error = '';
$redirect = trim($_GET['redirect'] ?? '');

if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];

                // Redirect to dynamic destination or dashboard
                if (!empty($redirect)) {
                    header("Location: " . $redirect);
                } else {
                    header("Location: dashboard.php");
                }
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

$page_title = "Admin Login";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-md mx-auto py-12 px-4 sm:px-0">
    <div class="text-center mb-8 animate-fade-in">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-teal/15 border border-brand-teal/30 text-brand-teal text-3xl mb-4 shadow-[0_0_20px_rgba(37,226,204,0.15)]">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <h1 class="text-3xl font-black text-white tracking-tight uppercase font-display">Admin Portal</h1>
        <p class="text-slate-400 text-sm mt-1">Authorized security personnel only</p>
    </div>

    <!-- Login Card -->
    <div class="glass-card p-8 sm:p-10 rounded-[2rem] border border-white/10 shadow-2xl relative overflow-hidden bg-dark-900/40 backdrop-blur-xl">
        <div class="absolute top-0 left-0 right-0 h-[3px] bg-gradient-to-r from-brand-blue via-brand-teal to-brand-blue"></div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2.5 text-sm text-rose-400"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>" method="POST" class="space-y-6">
            <!-- Username -->
            <div class="space-y-2">
                <label for="username" class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest font-display">Username</label>
                <div class="relative group">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 group-focus-within:text-brand-teal transition-colors"><i class="fa-solid fa-user text-xs"></i></span>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           class="w-full pl-10 pr-4 py-3 bg-dark-900/60 border border-white/10 focus:border-brand-teal/50 focus:ring-1 focus:ring-brand-teal/30 rounded-xl text-white placeholder-slate-600 text-sm focus:outline-none transition-all duration-300"
                           placeholder="Enter your username">
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-[11px] font-bold text-slate-300 uppercase tracking-widest font-display">Password</label>
                </div>
                <div class="relative group">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 group-focus-within:text-brand-teal transition-colors"><i class="fa-solid fa-lock text-xs"></i></span>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full pl-10 pr-10 py-3 bg-dark-900/60 border border-white/10 focus:border-brand-teal/50 focus:ring-1 focus:ring-brand-teal/30 rounded-xl text-white placeholder-slate-600 text-sm focus:outline-none transition-all duration-300"
                           placeholder="Enter your password">
                    <button type="button" id="toggle-password" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-brand-teal focus:text-brand-teal transition-colors focus:outline-none" aria-label="Toggle password visibility">
                        <i class="fa-solid fa-eye-slash text-xs" id="eye-icon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full py-3.5 bg-brand-teal hover:bg-[#1fd4be] active:scale-[0.99] text-dark-900 font-bold text-sm rounded-xl shadow-lg shadow-brand-teal/15 flex items-center justify-center space-x-2 transition-all duration-300">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Login</span>
                </button>
            </div>
        </form>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.getElementById('toggle-password');
    const eyeIcon = document.getElementById('eye-icon');

    if (toggleButton && passwordInput && eyeIcon) {
        toggleButton.addEventListener('click', function() {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.className = isPassword ? 'fa-solid fa-eye text-xs' : 'fa-solid fa-eye-slash text-xs';
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
