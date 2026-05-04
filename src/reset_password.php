<?php
session_start();
require_once 'security.php';
require 'conexion.php';

$mensaje_exito = '';
$mensaje_error = '';
$token_valido = false;
$token = isset($_GET['token']) ? $_GET['token'] : (isset($_POST['token']) ? $_POST['token'] : '');

// Verificar que el token es válido y no ha expirado (1 hora)
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT email, created_at FROM PASSWORD_RESETS WHERE token = ?");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if ($reset) {
        $created = new DateTime($reset['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();

        if ($diff < 3600) { // 1 hora = 3600 segundos
            $token_valido = true;
        } else {
            $mensaje_error = "El enlace ha expirado. Solicita uno nuevo.";
            // Limpiar token expirado
            $stmt = $pdo->prepare("DELETE FROM PASSWORD_RESETS WHERE token = ?");
            $stmt->execute([$token]);
        }
    } else {
        $mensaje_error = "El enlace no es válido o ya fue utilizado.";
    }
} else {
    $mensaje_error = "No se proporcionó un token válido.";
}

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_password']) && $token_valido) {
    validate_csrf_token();
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    if (strlen($password_nueva) < 6) {
        $mensaje_error = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje_error = "Las contraseñas no coinciden.";
    } else {
        // Actualizar contraseña
        $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE USUARIOS SET password = ? WHERE email = ?");
        $stmt->execute([$password_hash, $reset['email']]);

        // Eliminar todos los tokens de este email
        $stmt = $pdo->prepare("DELETE FROM PASSWORD_RESETS WHERE email = ?");
        $stmt->execute([$reset['email']]);

        $mensaje_exito = "¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.";
        $token_valido = false; // Ocultar formulario
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Restablecer Contraseña | Vaulta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .form-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .form-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .form-header .icon-circle {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .form-header .icon-circle i {
            color: white;
            font-size: 24px;
        }

        .form-header h2 {
            color: #2d3748;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .form-header p {
            color: #718096;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border: 1px solid #fc8181;
            color: #c53030;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 16px;
        }

        .input-wrapper input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .btn-submit {
            width: 100%;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #002366;
            text-decoration: underline;
        }

        .password-rules {
            background: #f7fafc;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #718096;
        }

        .password-rules li {
            margin: 4px 0;
            margin-left: 15px;
        }
    </style>
<link rel="stylesheet" href="dark-mode.css">
</head>
<body>

    <div class="form-wrapper">
        <div class="form-box">
            <div class="form-header">
                <div class="icon-circle">
                    <i class="fas fa-key"></i>
                </div>
                <h2>Nueva Contraseña</h2>
                <p>Introduce tu nueva contraseña</p>
            </div>

            <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo esc($mensaje_exito); ?>
            </div>
            <a href="login.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none; margin-top: 10px;">
                <i class="fas fa-sign-in-alt"></i> Ir a Iniciar Sesión
            </a>
            <?php elseif (!empty($mensaje_error) && !$token_valido): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($mensaje_error); ?>
            </div>
            <a href="forgot_password.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none; margin-top: 10px;">
                <i class="fas fa-redo"></i> Solicitar Nuevo Enlace
            </a>
            <?php elseif ($token_valido): ?>

            <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($mensaje_error); ?>
            </div>
            <?php endif; ?>

            <div class="password-rules">
                <strong>La contraseña debe:</strong>
                <ul>
                    <li>Tener al menos 6 caracteres</li>
                </ul>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="reset_password" value="1">
                <input type="hidden" name="token" value="<?php echo esc($token); ?>">

                <div class="input-group">
                    <label for="password_nueva">Nueva Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password_nueva" name="password_nueva" placeholder="••••••••" required autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password_confirmar">Confirmar Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password_confirmar" name="password_confirmar" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Guardar Nueva Contraseña
                </button>
            </form>

            <?php endif; ?>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
            </a>
        </div>
    </div>

<script src="dark-mode.js"></script>
</body>
</html>
