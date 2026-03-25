<?php
// ============================================================
//  config.php — Nagrik Seva Portal
//  Place this file in: C:\xampp2\htdocs\InnovatX\
// ============================================================

// ── Database Configuration ──────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Default XAMPP username
define('DB_PASS', '');             // Default XAMPP password (empty)
define('DB_NAME', 'nagrik_seva');  // Database name from your SQL file

// ── Connect ─────────────────────────────────────────────────
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ── Check connection ─────────────────────────────────────────
if (!$conn) {
    die("
    <div style='font-family:Segoe UI,sans-serif;padding:30px;max-width:600px;margin:40px auto;
                background:#ffebee;color:#c62828;border-radius:14px;border:1px solid #ffcdd2;'>
        <h3 style='margin:0 0 12px'>❌ Database Connection Failed</h3>
        <p><strong>Error:</strong> " . htmlspecialchars(mysqli_connect_error()) . "</p>
        <hr style='border:none;border-top:1px solid #ffcdd2;margin:16px 0'>
        <p><strong>Fix checklist:</strong></p>
        <ol style='line-height:2;padding-left:18px;'>
            <li>Open XAMPP Control Panel — make sure <strong>Apache</strong> and <strong>MySQL</strong> are both <span style='color:green'>green</span></li>
            <li>Open <a href='http://localhost/phpmyadmin' target='_blank' style='color:#c62828'>phpMyAdmin</a></li>
            <li>Click <strong>Import</strong> and run your <code>nagrik_seva.sql</code> file</li>
            <li>Refresh this page</li>
        </ol>
    </div>");
}

// ── Set charset ──────────────────────────────────────────────
mysqli_set_charset($conn, 'utf8mb4');
?>