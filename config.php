<?php
// =====================================================
// Bus Ticket Booking - configuration
// Every page loads this file first.
// =====================================================
session_start();
date_default_timezone_set('Asia/Manila');

// ---- Database settings (XAMPP defaults) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'bus_ticket_booking');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- Base URL of the app (e.g. "/deb"), detected automatically ----
// Works no matter which folder (root, admin/, customer/) the current page is in.
(function () {
    $appRoot = str_replace('\\', '/', realpath(__DIR__));
    $script  = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME']));
    if ($script !== '' && stripos($script, $appRoot . '/') === 0) {
        $rel  = substr($script, strlen($appRoot));                  // e.g. "/admin/routes.php"
        $base = substr($_SERVER['SCRIPT_NAME'], 0, -strlen($rel));  // e.g. "/deb"
    } else {
        $base = dirname($_SERVER['SCRIPT_NAME']);
    }
    define('BASE_URL', rtrim(str_replace('\\', '/', $base), '/'));
})();

// ---- Shared helpers ----
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// ---- Database connection ----
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $ex) {
    die('<h3>Database connection failed.</h3><p>Check the settings in config.php and make sure you imported bus_ticket_booking.sql.</p><pre>'
        . htmlspecialchars($ex->getMessage()) . '</pre>');
}

// Trips whose departure time has passed are marked as departed
$pdo->exec("UPDATE schedules SET status = 'departed'
            WHERE departure_time < NOW() AND status IN ('open','full')");
