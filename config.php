<?php
// Adatbázis konfiguráció
// FONTOS: Módosítsd ezeket az értékeket a saját cPanel adatbázis adataidra!

define('DB_HOST', 'localhost');
define('DB_NAME', 'szabolcs_padlas_crm');
define('DB_USER', 'szabolcs_admin');
define('DB_PASS', 'kicsi2001');
define('DB_CHARSET', 'utf8mb4');

// Session konfiguráció
define('SESSION_NAME', 'padlas_crm_session');
define('SESSION_LIFETIME', 86400); // 24 óra

// Alkalmazás konfiguráció
define('APP_NAME', 'Padlás Födém Szigetelés CRM');
define('APP_URL', 'https://crm.szabolcsutep.hu/');

// Időzóna
date_default_timezone_set('Europe/Budapest');

// Hibakezelés (éles környezetben kapcsold ki!)
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

// Adatbázis kapcsolat - PERSISTENT CONNECTION
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true, // Persistent connection engedélyezése
        ]
    );
} catch (PDOException $e) {
    die("Adatbázis kapcsolódási hiba: " . $e->getMessage());
}

// Session indítása
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Helper függvények
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('Nincs jogosultságod ehhez a művelethez.');
    }
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
