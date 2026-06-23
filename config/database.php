<?php
// ── Security hardening — must be first ──────────────────────
require_once __DIR__ . '/../includes/security.php';

// Helper to parse .env file
function load_env() {
    $env_file = __DIR__ . '/../.env';
    if (file_exists($env_file)) {
        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) continue;
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}
load_env();

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'gatepass_db');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Function to fetch a setting value
function get_setting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        if ($result && !empty($result['setting_value'])) {
            return $result['setting_value'];
        }
    } catch (PDOException $e) {
        // Fall through
    }
    
    // Fall back to environment variable if database value is empty
    $env_key = strtoupper($key);
    $env_val = getenv($env_key);
    if ($env_val !== false && $env_val !== '') {
        return $env_val;
    }
    
    return $default;
}

// Function to update a setting value
function update_setting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                                ON DUPLICATE KEY UPDATE setting_value = ?");
        return $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        return false;
    }
}

// Auto-migrate: Ensure security_name column exists in gatepasses table
try {
    $pdo->query("SELECT security_name FROM gatepasses LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE gatepasses ADD COLUMN security_name VARCHAR(100) DEFAULT NULL");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}

// Auto-migrate: Ensure checked_out_by column exists
try {
    $pdo->query("SELECT checked_out_by FROM gatepasses LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE gatepasses ADD COLUMN checked_out_by VARCHAR(50) DEFAULT NULL");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}

// Auto-migrate: Ensure manager_name column exists
try {
    $pdo->query("SELECT manager_name FROM gatepasses LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE gatepasses ADD COLUMN manager_name VARCHAR(100) DEFAULT NULL");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}

// Auto-migrate: Ensure security_signature column exists
try {
    $pdo->query("SELECT security_signature FROM gatepasses LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE gatepasses ADD COLUMN security_signature LONGTEXT DEFAULT NULL");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}

// Auto-migrate: Ensure material_brand column exists
try {
    $pdo->query("SELECT material_brand FROM gatepasses LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("ALTER TABLE gatepasses ADD COLUMN material_brand VARCHAR(100) DEFAULT NULL AFTER material_desc");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}

// Auto-migrate: Ensure gatepass_materials table exists
try {
    $pdo->query("SELECT 1 FROM gatepass_materials LIMIT 1");
} catch (Exception $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `gatepass_materials` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `gatepass_no` VARCHAR(20) NOT NULL,
            `purpose` VARCHAR(255) NOT NULL,
            `material_desc` VARCHAR(255) DEFAULT NULL,
            `material_brand` VARCHAR(100) DEFAULT NULL,
            `material_serial` VARCHAR(100) DEFAULT NULL,
            `material_qty` INT DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`gatepass_no`) REFERENCES `gatepasses` (`gatepass_no`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Copy existing records to gatepass_materials to maintain backward compatibility
        $pdo->exec("INSERT INTO gatepass_materials (gatepass_no, purpose, material_desc, material_brand, material_serial, material_qty)
            SELECT gatepass_no, purpose, material_desc, material_brand, material_serial, COALESCE(material_qty, 1)
            FROM gatepasses
            WHERE gatepass_no NOT IN (SELECT DISTINCT gatepass_no FROM gatepass_materials)
              AND (material_desc IS NOT NULL OR purpose IS NOT NULL)");
    } catch (Exception $ex) {
        // Silent fallthrough
    }
}
?>

