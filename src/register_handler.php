<?php
session_start();
require 'conexion.php';
require_once 'security.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    // Recoger datos del formulario
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $dni = trim($_POST['dni']);
    $banco = trim($_POST['banco']);

    // Validar que no estén vacíos
    if (empty($nombre) || empty($email) || empty($password) || empty($dni) || empty($banco)) {
        header("Location: register.php?error=empty_fields");
        exit;
    }

    // Validar formato de email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: register.php?error=invalid_email");
        exit;
    }

    // Validar formato de DNI/NIE español
    function validarDNI($dni) {
        $dni = strtoupper(trim($dni));
        if (!preg_match('/^[0-9]{8}[A-Z]$/', $dni) && !preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $dni)) {
            return false;
        }
        $letras = 'TRWAGMYFPDXBNJZSQVHLCKE';
        if (preg_match('/^[XYZ]/', $dni)) {
            $nie_conversion = ['X' => '0', 'Y' => '1', 'Z' => '2'];
            $numero = $nie_conversion[$dni[0]] . substr($dni, 1, 7);
        } else {
            $numero = substr($dni, 0, 8);
        }
        $letra_calculada = $letras[intval($numero) % 23];
        $letra_proporcionada = substr($dni, -1);
        return $letra_calculada === $letra_proporcionada;
    }

    if (!validarDNI($dni)) {
        header("Location: register.php?error=invalid_dni");
        exit;
    }

    // Verificar si el email ya existe
    $stmt = $pdo->prepare("SELECT id FROM USUARIOS WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header("Location: register.php?error=email_exists");
        exit;
    }

    // Hashear la contraseña usando bcrypt
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $sql = "INSERT INTO USUARIOS (nombre, email, password, DNI, banco) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nombre, $email, $password_hash, $dni, $banco]);

        header("Location: login.php?registered=1");
        exit;

    } catch (PDOException $e) {
        error_log("Error registro: " . $e->getMessage());
        header("Location: register.php?error=db_error");
        exit;
    }
} else {
    header("Location: register.php");
    exit;
}
?>
