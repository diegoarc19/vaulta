<?php
session_start();
require_once 'security.php';
require 'conexion.php';

// Verificar que hay un usuario pendiente de 2FA
if (!isset($_SESSION['2fa_user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['2fa_user_id'];
$mensaje_error = '';
$mensaje_exito = '';

// Obtener datos del usuario para mostrar email parcial
$stmt = $pdo->prepare("SELECT email FROM USUARIOS WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$email_masked = preg_replace('/(.{2})(.*)(@.*)/', '$1***$3', $user['email']);

// Procesar verificación del código
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_code'])) {
    validate_csrf_token();
    $code_input = trim($_POST['code']);

    // Buscar código válido (máx 10 minutos)
    $stmt = $pdo->prepare("SELECT id, code, created_at FROM TWO_FACTOR_CODES WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $code_record = $stmt->fetch();

    if ($code_record) {
        $created = new DateTime($code_record['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();

        if ($diff > 600) {
            $mensaje_error = "El código ha expirado. Solicita uno nuevo.";
        } elseif ($code_input === $code_record['code']) {
            // ¡Código correcto! Completar login
            // Limpiar códigos usados
            $stmt = $pdo->prepare("DELETE FROM TWO_FACTOR_CODES WHERE user_id = ?");
            $stmt->execute([$user_id]);

            // Restaurar datos de sesión completos
            $stmt = $pdo->prepare("SELECT id, nombre, email FROM USUARIOS WHERE id = ?");
            $stmt->execute([$user_id]);
            $user_data = $stmt->fetch();

            session_regenerate_id(true);
            $_SESSION['user_id'] = $user_data['id'];
            $_SESSION['user_nombre'] = $user_data['nombre'];
            $_SESSION['user_email'] = $user_data['email'];
            $_SESSION['loggedin'] = true;
            unset($_SESSION['2fa_user_id']);

            // Verificar si tiene cuentas
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM CUENTAS WHERE usuario_id = ?");
            $stmt->execute([$user_data['id']]);
            $num_cuentas = $stmt->fetchColumn();

            if ($num_cuentas == 0) {
                header("Location: setup.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $mensaje_error = "Código incorrecto. Inténtalo de nuevo.";
        }
    } else {
        $mensaje_error = "No se encontró un código válido. Solicita uno nuevo.";
    }
}

// Reenviar código
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_code'])) {
    validate_csrf_token();

    // Eliminar códigos anteriores
    $stmt = $pdo->prepare("DELETE FROM TWO_FACTOR_CODES WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Generar nuevo código
    $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("INSERT INTO TWO_FACTOR_CODES (user_id, code) VALUES (?, ?)");
    $stmt->execute([$user_id, $code]);

    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT nombre, email FROM USUARIOS WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch();

    // Enviar email
    $subject = "Código de verificación - Vaulta";
    $headers  = "From: Vaulta <noreply@vaulta.local>\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $body = "
    <html>
    <body style='font-family: Segoe UI, Arial, sans-serif; background: #f5f7fa; padding: 30px;'>
        <div style='max-width: 500px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 25px;'>
                <h2 style='color: #002366; margin: 0;'>Vaulta</h2>
                <p style='color: #718096; font-size: 14px;'>Verificación en dos pasos</p>
            </div>
            <p style='color: #2d3748;'>Hola <strong>{$user_data['nombre']}</strong>,</p>
            <p style='color: #4a5568; line-height: 1.6;'>Tu código de verificación es:</p>
            <div style='text-align: center; margin: 25px 0;'>
                <div style='background: #f7fafc; border: 2px dashed #007bff; border-radius: 12px; padding: 20px; display: inline-block;'>
                    <span style='font-size: 36px; font-weight: 700; letter-spacing: 8px; color: #002366;'>$code</span>
                </div>
            </div>
            <p style='color: #718096; font-size: 13px;'>Este código expira en <strong>10 minutos</strong>.</p>
            <p style='color: #718096; font-size: 13px;'>Si no has intentado iniciar sesión, alguien puede estar intentando acceder a tu cuenta.</p>
            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <p style='color: #a0aec0; font-size: 12px; text-align: center;'>© Vaulta - Gestión Patrimonial</p>
        </div>
    </body>
    </html>";

    mail($user_data['email'], $subject, $body, $headers);
    $mensaje_exito = "Se ha reenviado un nuevo código a tu email.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Verificación 2FA | Vaulta</title>
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
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .form-header .icon-circle i {
            color: white;
            font-size: 28px;
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

        .email-badge {
            background: #edf2f7;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: 600;
            color: #4a5568;
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

        .code-input-wrapper {
            margin-bottom: 24px;
        }

        .code-input-wrapper label {
            display: block;
            color: #374151;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
        }

        .code-input {
            width: 100%;
            padding: 18px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            letter-spacing: 12px;
            color: #002366;
            transition: all 0.3s;
            outline: none;
        }

        .code-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 4px rgba(0, 123, 255, 0.1);
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

        .actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .back-link, .resend-btn {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            background: none;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .back-link:hover, .resend-btn:hover {
            color: #002366;
            text-decoration: underline;
        }

        .timer-info {
            text-align: center;
            margin-top: 16px;
            color: #a0aec0;
            font-size: 13px;
        }
    </style>
    <link rel="stylesheet" href="dark-mode.css">
</head>
<body>

    <div class="form-wrapper">
        <div class="form-box">
            <div class="form-header">
                <div class="icon-circle">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h2>Verificación en 2 Pasos</h2>
                <p>Hemos enviado un código de 6 dígitos a<br><span class="email-badge"><?php echo esc($email_masked); ?></span></p>
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
                <input type="hidden" name="verify_code" value="1">

                <div class="code-input-wrapper">
                    <label>Introduce el código</label>
                    <input type="text" name="code" class="code-input" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" placeholder="000000" required autofocus>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-check"></i> Verificar Código
                </button>
            </form>

            <div class="actions-row">
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <form method="POST" style="display: inline;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="resend_code" value="1">
                    <button type="submit" class="resend-btn">
                        <i class="fas fa-redo"></i> Reenviar código
                    </button>
                </form>
            </div>

            <div class="timer-info">
                <i class="fas fa-clock"></i> El código expira en 10 minutos
            </div>
        </div>
    </div>

<script src="dark-mode.js"></script>
</body>
</html>
