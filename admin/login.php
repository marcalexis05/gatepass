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

<div class="max-w-md mx-auto py-8">
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 text-3xl mb-4 shadow-lg shadow-indigo-500/5">
            <i class="fa-solid fa-user-shield"></i>
        </div>
        <h1 class="text-3xl font-black text-white tracking-tight">Admin Portal</h1>
        <p class="text-slate-400 text-sm mt-1">Authorized security personnel only</p>
    </div>

    <!-- Login Card -->
    <div class="glass-card p-8 rounded-3xl border border-slate-800 shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 via-purple-500 to-emerald-500"></div>

        <?php if ($error): ?>
            <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-center">
                <i class="fa-solid fa-circle-exclamation mr-2 text-sm"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php<?php echo $redirect ? '?redirect=' . urlencode($redirect) : ''; ?>" method="POST" class="space-y-5">
            <!-- Username -->
            <div class="space-y-1.5">
                <label for="username" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Username</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-user text-xs"></i></span>
                    <input type="text" id="username" name="username" required autocomplete="username"
                           class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                           placeholder="Enter your username">
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-xs font-bold text-slate-300 uppercase tracking-wide">Password</label>
                </div>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500"><i class="fa-solid fa-lock text-xs"></i></span>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           class="w-full pl-9 pr-4 py-2.5 bg-dark-900 border border-slate-800 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-1 transition-all"
                           placeholder="Enter your password">
                </div>
            </div>

            <!-- Submit -->
            <div class="pt-2">
                <button type="submit"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 active:scale-[0.99] text-white font-bold text-sm rounded-xl shadow-lg shadow-indigo-600/10 flex items-center justify-center space-x-2 transition-all">
                    <i class="fa-solid fa-right-to-bracket"></i>
                    <span>Authenticate Account</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Go Back Link -->
    <div class="text-center mt-6">
        <a href="../index.php" class="text-xs font-semibold text-slate-500 hover:text-slate-300 transition-colors">
            <i class="fa-solid fa-arrow-left mr-1.5"></i> Return to Main Entrance
        </a>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>
