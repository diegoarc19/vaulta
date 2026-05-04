<?php
session_start();
require 'conexion.php';
require_once 'security.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['user_nombre'];

// MANEJAR CREACIÓN DE CUENTA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_cuenta'])) {
    validate_csrf_token();
    $nombre_cuenta = trim($_POST['nombre_cuenta']);
    $saldo_inicial = (float) $_POST['saldo_inicial'];

    if (!empty($nombre_cuenta) && $saldo_inicial >= 0) {
        $stmt = $pdo->prepare("INSERT INTO CUENTAS (usuario_id, nombre, saldo_inicial) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $nombre_cuenta, $saldo_inicial]);
        // Recargar para mostrar la cuenta en la lista
        header("Location: setup.php");
        exit;
    }
}

// MANEJAR FINALIZACIÓN (Ir al Dashboard)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar'])) {
    validate_csrf_token();
    // Verificar que tenga al menos una cuenta
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM CUENTAS WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() > 0) {
        header("Location: dashboard.php");
        exit;
    }
}

// OBTENER CUENTAS ACTUALES INTRODUCIDAS
$stmt = $pdo->prepare("SELECT * FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();
$tiene_cuentas = count($cuentas) > 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Puesta a Punto | Vaulta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .setup-header {
            background: #f7fafc;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .setup-header h2 { color: #002366; font-size: 24px; margin-bottom: 5px; }
        .setup-header p { color: #718096; font-size: 14px; }
        .setup-body { padding: 30px; }
        
        .form-section { margin-bottom: 30px; }
        .form-section h3 { color: #2d3748; font-size: 18px; margin-bottom: 15px; border-left: 4px solid #007bff; padding-left: 10px; }
        
        .input-group { margin-bottom: 15px; }
        .input-group label { display: block; color: #4a5568; margin-bottom: 5px; font-weight: 600; font-size: 14px; }
        .input-group input { 
            width: 100%; padding: 12px; border: 2px solid #e2e8f0; border-radius: 8px; outline: none; transition: border-color 0.3s;
        }
        .input-group input:focus { border-color: #007bff; }
        
        .btn-add {
            background: #007bff; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; transition: background 0.3s;
        }
        .btn-add:hover { background: #0056b3; }

        .accounts-list {
            margin-bottom: 30px;
        }
        .account-item {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px; background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; margin-bottom: 10px;
        }
        .account-info strong { display: block; color: #2d3748; }
        .account-info span { color: #718096; font-size: 14px; }
        .account-balance { color: #28a745; font-weight: 700; }

        .btn-finish {
            background: #28a745; color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: 700; width: 100%; font-size: 16px; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.3s;
        }
        .btn-finish:hover:not(:disabled) { background: #218838; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3); }
        .btn-finish:disabled { background: #cbd5e0; cursor: not-allowed; opacity: 0.7; }

        .info-alert {
            background: #e6fffa; border: 1px solid #b2f5ea; color: #285e61; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; display: flex; align-items: start; gap: 10px;
        }
    </style>
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="dark-mode.css">
</head>
<body>

<div class="setup-container">
    <div class="setup-header">
        <h2><i class="fas fa-rocket" style="color: #007bff; margin-right: 10px;"></i>Puesta a Punto</h2>
        <p>Configura tu espacio financiero en Vaulta</p>
    </div>

    <div class="setup-body">
        
        <div class="info-alert">
            <i class="fas fa-info-circle" style="margin-top: 3px;"></i>
            <div>
                <strong>¡Bienvenido, <?php echo htmlspecialchars($nombre_usuario); ?>!</strong><br>
                Para comenzar, necesitas registrar al menos una cuenta (banco, efectivo, ahorros) y su saldo actual.
            </div>
        </div>

        <!-- FORMULARIO AÑADIR CUENTA -->
        <div class="form-section">
            <h3>1. Añadir Cuenta</h3>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <div class="input-group">
                    <label>Nombre de la Cuenta / Banco</label>
                    <input type="text" name="nombre_cuenta" placeholder="Ej. Banco Santander, Cartera..." required>
                </div>
                <div class="input-group">
                    <label>Saldo Inicial (€)</label>
                    <input type="number" step="0.01" name="saldo_inicial" placeholder="0.00" min="0.01" required>
                    <small style="color: #718096;">* Introduce un saldo mayor a 0 para empezar.</small>
                </div>
                <button type="submit" name="crear_cuenta" class="btn-add">
                    <i class="fas fa-plus"></i> Añadir Cuenta
                </button>
            </form>
        </div>

        <!-- LISTA DE CUENTAS CREADAS -->
        <?php if ($tiene_cuentas): ?>
        <div class="accounts-list">
            <h3>2. Cuentas creadas</h3>
            <?php foreach ($cuentas as $cuenta): ?>
                <div class="account-item">
                    <div class="account-info">
                        <strong><?php echo htmlspecialchars($cuenta['nombre']); ?></strong>
                        <span>Saldo Inicial</span>
                    </div>
                    <div class="account-balance">
                        <?php echo number_format($cuenta['saldo_inicial'], 2, ',', '.'); ?> €
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- BOTÓN FINALIZAR -->
        <form method="POST" action="">
            <?php echo csrf_field(); ?>
            <button type="submit" name="finalizar" class="btn-finish" <?php echo !$tiene_cuentas ? 'disabled' : ''; ?>>
                Finalizar y Entrar <i class="fas fa-arrow-right" style="margin-left: 10px;"></i>
            </button>
        </form>

    </div>
</div>

<script src="dark-mode.js"></script>
</body>
</html>
