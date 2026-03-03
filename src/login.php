<?php
session_start();
require_once 'security.php';
generate_csrf_token(); // Asegura que el token existe en sesión
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Iniciar Sesión | Vaulta</title>
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

        .login-wrapper {
            width: 100%;
            max-width: 420px;
        }

        .login-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: #007bff;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #6b7280;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }

        .alert-error.show {
            display: block;
            animation: shake 0.5s;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
            }
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .input-group label {
            color: #374151;
            font-size: 14px;
            font-weight: 600;
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
            font-size: 15px;
            transition: all 0.3s;
            outline: none;
        }

        .input-wrapper input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: -8px;
        }

        .forgot-pass {
            color: #007bff;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.3s;
        }

        .forgot-pass:hover {
            color: #002366;
            text-decoration: underline;
        }

        .btn-submit {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 123, 255, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .login-footer {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }

        .login-footer p {
            color: #6b7280;
            font-size: 12px;
        }
    </style>
</head>

<body>

    <div class="login-wrapper">

        <div class="login-box">
            <div class="login-header">
                <img src="images/logologin.png" alt="Vaulta Logo" style="height: 60px; margin-bottom: 15px;">
                <p>Gestión Patrimonial Segura</p>
            </div>

            <div class="alert-error" id="errorAlert">
                <i class="fas fa-exclamation-circle"></i> Email o contraseña incorrectos
            </div>

            <div class="alert-error" id="deletedAlert"
                style="background: #c6f6d5; border-color: #9ae6b4; color: #22543d; display: none;">
                <i class="fas fa-check-circle"></i> Tu cuenta ha sido eliminada exitosamente
            </div>

            <form action="auth.php" method="POST" class="login-form">
                <?php echo csrf_field(); ?>

                <div class="input-group">
                    <label for="email">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="tu@email.com" required autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="#" class="forgot-pass">¿Olvidaste tu clave?</a>
                </div>

                <button type="submit" class="btn-submit">
                    Entrar a mi cuenta
                </button>

                <a href="register.php"
                    style="display: block; text-align: center; margin-top: 15px; color: #007bff; text-decoration: none; font-size: 14px;">
                    ¿No tienes cuenta? Regístrate
                </a>
            </form>

            <div class="login-footer">
                <p>Acceso restringido a usuarios autorizados.</p>
            </div>
        </div>

    </div>

    <script>
        // Mostrar mensaje de error si existe el parámetro en la URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error') === '1') {
            document.getElementById('errorAlert').classList.add('show');
        }
        if (urlParams.get('deleted') === '1') {
            document.getElementById('deletedAlert').classList.add('show');
        }
    </script>

</body>

</html>
