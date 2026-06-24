<?php
// Session configuration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

// Require user to be logged in, otherwise redirect to login page
function require_login() {
    if (!is_logged_in()) {
        // Redirect to login page
        // Find root URL dynamically
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'];
        
        // Find the base path of the project
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        // If in admin directory, we want to go up one level or point directly to admin/login.php
        if (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false) {
            header("Location: login.php");
        } else {
            header("Location: admin/login.php");
        }
        exit;
    }
}

// Log out the user
function logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
?>
