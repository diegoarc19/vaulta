<?php
session_start();
require 'conexion.php';
require_once 'security.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf_token();

    // 1. Recibimos los datos del formulario HTML
    $email_input = trim($_POST['email']);
    $password_input = $_POST['password'];

    // 2. Buscamos el usuario por email en la base de datos
    $stmt = $pdo->prepare("SELECT id, nombre, email, DNI, password FROM USUARIOS WHERE email = ?");
    $stmt->execute([$email_input]);
    $user = $stmt->fetch();

    // 3. Verificamos si existe y si la contraseña coincide
    if ($user) {
        if (password_verify($password_input, $user['password'])) {

            // ¡LOGIN CORRECTO!
            // Regenerar ID de sesión para prevenir session fixation
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']); // Se generará uno nuevo en la siguiente página

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['loggedin'] = true;

            // Verificar si el usuario tiene cuentas creadas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM CUENTAS WHERE usuario_id = ?");
            $stmt->execute([$user['id']]);
            $num_cuentas = $stmt->fetchColumn();

            if ($num_cuentas == 0) {
                header("Location: setup.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            header("Location: login.php?error=1");
            exit;
        }
    } else {
        header("Location: login.php?error=1");
        exit;
    }
}
?>