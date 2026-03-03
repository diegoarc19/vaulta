<?php
// conexion.php

// ── Cabeceras de seguridad HTTP ─────────────────────────────────────────────
// Evita que la página sea embebida en iframes (clickjacking)
header('X-Frame-Options: DENY');
// El navegador no debe adivinar el tipo MIME (MIME-sniffing)
header('X-Content-Type-Options: nosniff');
// Activa el filtro XSS del navegador (legacy)
header('X-XSS-Protection: 1; mode=block');
// No enviar la URL de referencia a sitios externos
header('Referrer-Policy: strict-origin-when-cross-origin');
// Content Security Policy: sólo recursos del mismo origen + CDN de FontAwesome y Google Fonts
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:;");
// ────────────────────────────────────────────────────────────────────────────

// EN DOCKER, EL HOST ES EL NOMBRE DEL SERVICIO EN DOCKER-COMPOSE (normalmente 'db' o 'mysql')
$host = 'db'; 
$db   = 'finanzas_app';
$user = 'root';
$pass = 'root'; // O la contraseña que hayas puesto en el docker-compose
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
// ... resto igual
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
?>