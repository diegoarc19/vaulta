<?php
session_start();
require_once 'security.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al login
header("Location: login.php");
exit;
?>
