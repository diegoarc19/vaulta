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
    $stmt = $pdo->prepare("SELECT id, nombre, email, DNI, password, two_factor_enabled FROM USUARIOS WHERE email = ?");
    $stmt->execute([$email_input]);
    $user = $stmt->fetch();

    // 3. Verificamos si existe y si la contraseña coincide
    if ($user) {
        if (password_verify($password_input, $user['password'])) {

            // ¿Tiene 2FA activado?
            if ($user['two_factor_enabled']) {
                // Generar código de 6 dígitos
                $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

                // Eliminar códigos anteriores
                $stmt = $pdo->prepare("DELETE FROM TWO_FACTOR_CODES WHERE user_id = ?");
                $stmt->execute([$user['id']]);

                // Guardar nuevo código
                $stmt = $pdo->prepare("INSERT INTO TWO_FACTOR_CODES (user_id, code) VALUES (?, ?)");
                $stmt->execute([$user['id'], $code]);

                // Enviar email con el código
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
                        <p style='color: #2d3748;'>Hola <strong>{$user['nombre']}</strong>,</p>
                        <p style='color: #4a5568; line-height: 1.6;'>Tu código de verificación para iniciar sesión es:</p>
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

                mail($user['email'], $subject, $body, $headers);

                // Guardar user_id temporalmente en sesión (SIN loggedin=true)
                session_regenerate_id(true);
                unset($_SESSION['csrf_token']);
                $_SESSION['2fa_user_id'] = $user['id'];

                header("Location: verify_2fa.php");
                exit;
            }

            // ¡LOGIN CORRECTO (sin 2FA)!
            // Regenerar ID de sesión para prevenir session fixation
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']); // Se generará uno nuevo en la siguiente página

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_nombre'] = $user['nombre'];
            $_SESSION['user_email'] = $user['email'];
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