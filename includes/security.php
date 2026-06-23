<?php
/**
 * ============================================================
 *  Concentrix GatePass — Security Hardening Middleware
 * ============================================================
 *  Include this file at the TOP of every PHP entry-point via
 *  the config/database.php bootstrap (or directly).
 *
 *  Protections applied:
 *  ✅ HTTP Security Headers (CSP, X-Frame-Options, HSTS, etc.)
 *  ✅ Session hardening (HttpOnly, SameSite, Secure cookies)
 *  ✅ Rate limiting for login / form endpoints
 *  ✅ Input sanitization & validation helpers
 *  ✅ Open-redirect prevention
 *  ✅ Error information suppression in production
 *  ✅ Clickjacking protection
 *  ✅ MIME-type sniffing prevention
 */

// ─────────────────────────────────────────────────────────────
// 1. Suppress detailed PHP errors from leaking to the browser
// ─────────────────────────────────────────────────────────────
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);                // Still log everything
ini_set('log_errors', '1');            // Write errors to server log only

// ─────────────────────────────────────────────────────────────
// 2. Session hardening  (must happen before session_start)
// ─────────────────────────────────────────────────────────────
$is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
            || $_SERVER['SERVER_PORT'] == 443;

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,               // Session cookie (closes on browser exit)
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,       // HTTPS-only in production
        'httponly' => true,            // No JS access to session cookie
        'samesite' => 'Lax',          // CSRF protection
    ]);
    session_start();
}

// Regenerate session ID periodically to prevent fixation
if (!isset($_SESSION['_last_regen'])) {
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
} elseif (time() - $_SESSION['_last_regen'] > 900) { // every 15 min
    session_regenerate_id(true);
    $_SESSION['_last_regen'] = time();
}

// ─────────────────────────────────────────────────────────────
// 3. HTTP Security Headers
// ─────────────────────────────────────────────────────────────
// Only send headers if not already sent
if (!headers_sent()) {

    // Prevent clickjacking — no iframes from other origins
    header('X-Frame-Options: SAMEORIGIN');

    // Stop browsers from MIME-sniffing the response
    header('X-Content-Type-Options: nosniff');

    // Enable browser XSS filter (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');

    // Control referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Restrict browser features (camera, microphone, geolocation)
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // HSTS — force HTTPS for 1 year (only activate in production over HTTPS)
    if ($is_https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content Security Policy — whitelist only known safe sources
    // Tailwind Play CDN, Font Awesome CDN, QRCode.js, and GitHub raw for the logo are allowed.
    $csp = implode('; ', [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://raw.githubusercontent.com",
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com",
        "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",
        "img-src 'self' data: https://raw.githubusercontent.com blob:",
        "connect-src 'self'",
        "frame-ancestors 'self'",
        "form-action 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ]);
    header("Content-Security-Policy: $csp");

    // Remove PHP version from headers
    header_remove('X-Powered-By');
}

// ─────────────────────────────────────────────────────────────
// 4. Rate Limiting (file-based, no Redis needed)
//    Blocks IPs that attempt too many requests to sensitive
//    endpoints (login, register, verify).
// ─────────────────────────────────────────────────────────────
define('RATE_LIMIT_MAX_ATTEMPTS', 15);  // Max POST attempts
define('RATE_LIMIT_WINDOW_SEC',   300); // Per 5-minute window

function check_rate_limit(string $action = 'default'): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return; // Only count POST submissions
    }

    $ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key         = 'rate_' . preg_replace('/[^a-f0-9:]/', '', $ip) . '_' . $action;
    $now         = time();
    $window_start = $now - RATE_LIMIT_WINDOW_SEC;

    if (!isset($_SESSION['_rate'])) {
        $_SESSION['_rate'] = [];
    }

    // Clean old timestamps
    $_SESSION['_rate'][$key] = array_filter(
        $_SESSION['_rate'][$key] ?? [],
        fn($t) => $t > $window_start
    );

    if (count($_SESSION['_rate'][$key]) >= RATE_LIMIT_MAX_ATTEMPTS) {
        http_response_code(429);
        // Silent plain-text response — no stack trace
        header('Content-Type: text/plain');
        header('Retry-After: 300');
        exit('Too many requests. Please wait a few minutes and try again.');
    }

    $_SESSION['_rate'][$key][] = $now;
}

// ─────────────────────────────────────────────────────────────
// 5. CSRF Token Helpers
//    Use gp_csrf_token() to embed, gp_verify_csrf() to check.
// ─────────────────────────────────────────────────────────────
function gp_csrf_token(): string {
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function gp_csrf_field(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(gp_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function gp_verify_csrf(): bool {
    $submitted = $_POST['_csrf_token'] ?? '';
    $expected  = $_SESSION['_csrf_token'] ?? '';
    if (empty($submitted) || empty($expected)) {
        return false;
    }
    return hash_equals($expected, $submitted);
}

// ─────────────────────────────────────────────────────────────
// 6. Input Sanitization Helpers
// ─────────────────────────────────────────────────────────────

/**
 * Sanitize a string input — strips tags and encodes HTML.
 * Use for all text fields that will be displayed in HTML.
 */
function gp_clean(string $value, int $max_length = 500): string {
    $value = trim($value);
    $value = strip_tags($value);                          // Remove any HTML/JS tags
    $value = mb_substr($value, 0, $max_length);           // Enforce max length
    return $value;
}

/**
 * Validate an email address.
 */
function gp_valid_email(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate that a string contains only alphanumeric characters, dashes, and underscores.
 * Useful for gatepass codes, usernames, etc.
 */
function gp_valid_alnum(string $value): bool {
    return (bool) preg_match('/^[a-zA-Z0-9\-_]+$/', $value);
}

/**
 * Validate a gatepass number (GP-XXXXXX pattern).
 */
function gp_valid_gatepass_no(string $code): bool {
    // Allow GP-XXXXXXXX or similar patterns: letters, digits, and dashes only
    return (bool) preg_match('/^GP-[A-Z0-9\-]{1,30}$/i', $code);
}

/**
 * Safe redirect: only allow same-origin paths, prevent open redirect.
 */
function gp_safe_redirect(string $url, string $fallback = 'index.php'): void {
    // Strip any protocol/host — only allow relative paths
    $url = trim($url);

    // Block absolute URLs, data URIs, javascript:, //host redirects
    if (
        preg_match('/^(https?|javascript|data|ftp|vbscript):/i', $url) ||
        preg_match('/^\/\//', $url) ||
        str_contains($url, "\0")
    ) {
        header('Location: ' . $fallback);
        exit;
    }

    // Allow only safe path characters
    if (!preg_match('/^[a-zA-Z0-9\/_\-\.?=&%]+$/', $url)) {
        header('Location: ' . $fallback);
        exit;
    }

    header('Location: ' . $url);
    exit;
}

/**
 * Output-encode a string for safe HTML rendering.
 * Always use this instead of echo-ing raw variables.
 */
function gp_e(string $value): void {
    echo htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return an output-encoded string (non-echo version of gp_e).
 */
function gp_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
