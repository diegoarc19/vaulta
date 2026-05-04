<?php
session_start();
require_once 'security.php';
require 'conexion.php';

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_reset'])) {
    validate_csrf_token();
    $email = trim($_POST['email']);

    // Buscar usuario por email
    $stmt = $pdo->prepare("SELECT id, nombre FROM USUARIOS WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Eliminar tokens anteriores de este email
        $stmt = $pdo->prepare("DELETE FROM PASSWORD_RESETS WHERE email = ?");
        $stmt->execute([$email]);

        // Generar token seguro
        $token = bin2hex(random_bytes(32));

        // Guardar token en BD
        $stmt = $pdo->prepare("INSERT INTO PASSWORD_RESETS (email, token) VALUES (?, ?)");
        $stmt->execute([$email, $token]);

        // Construir enlace de reset
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $reset_link = "$protocol://$host/reset_password.php?token=$token";

        // Enviar email
        $subject = "Restablecer contraseña - Vaulta";
        $headers  = "From: Vaulta <noreply@vaulta.local>\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = "
        <html>
        <body style='font-family: Segoe UI, Arial, sans-serif; background: #f5f7fa; padding: 30px;'>
            <div style='max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
                <div style='text-align: center; margin-bottom: 25px;'>
                    <h2 style='color: #002366; margin: 0;'>Vaulta</h2>
                    <p style='color: #718096; font-size: 14px;'>Gestión Patrimonial Segura</p>
                </div>
                <p style='color: #2d3748;'>Hola <strong>{$user['nombre']}</strong>,</p>
                <p style='color: #4a5568; line-height: 1.6;'>Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$reset_link' style='background: linear-gradient(135deg, #002366, #007bff); color: white; padding: 14px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;'>Restablecer Contraseña</a>
                </div>
                <p style='color: #718096; font-size: 13px;'>Este enlace expira en <strong>1 hora</strong>.</p>
                <p style='color: #718096; font-size: 13px;'>Si no solicitaste este cambio, ignora este email.</p>
                <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                <p style='color: #a0aec0; font-size: 12px; text-align: center;'>© Vaulta - Gestión Patrimonial</p>
            </div>
        </body>
        </html>";

        mail($email, $subject, $body, $headers);
    }

    // Siempre mostrar éxito (no revelar si el email existe o no)
    $mensaje_exito = "Si el email existe en nuestro sistema, recibirás un enlace para restablecer tu contraseña.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Recuperar Contraseña | Vaulta</title>
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
    </style>
    <link rel="stylesheet" href="dark-mode.css">
</head>
<body>

    <div class="form-wrapper">
        <div class="form-box">
            <div class="form-header">
                <div class="icon-circle">
                    <i class="fas fa-envelope"></i>
                </div>
                <h2>Recuperar Contraseña</h2>
                <p>Introduce tu email y te enviaremos un enlace para restablecer tu contraseña</p>
            </div>

            <?php if (!empty($mensaje_exito)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo esc($mensaje_exito); ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($mensaje_error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($mensaje_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="request_reset" value="1">

                <div class="input-group">
                    <label for="email">Email de tu cuenta</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="tu@email.com" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-paper-plane"></i> Enviar Enlace
                </button>
            </form>

            <a href="login.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
            </a>
        </div>
    </div>

<script src="dark-mode.js"></script>
</body>
</html>
