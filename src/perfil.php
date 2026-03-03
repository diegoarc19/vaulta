<?php
session_start();
require_once 'security.php';

// Si el usuario no ha iniciado sesión, lo echamos fuera
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

// Conectar a la base de datos
require 'conexion.php';

// Datos del usuario
$nombre_usuario = $_SESSION['user_nombre'];
$user_id = $_SESSION['user_id'];
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Detectar error CSRF (redirección desde validate_csrf_token)
$mensaje_error = csrf_error_message();

// MANEJO DE CREACIÓN DE CUENTA (debe ir primero para el redirect)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_account'])) {
    validate_csrf_token();
    $nombre_cuenta = trim($_POST['nombre_cuenta']);
    $saldo_inicial = (float)$_POST['saldo_inicial'];
    
    $stmt = $pdo->prepare("INSERT INTO CUENTAS (usuario_id, nombre, saldo_inicial) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $nombre_cuenta, $saldo_inicial]);
    
    // Recargar página
    header("Location: perfil.php");
    exit;
}

// Conectar a la base de datos
require 'conexion.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    validate_csrf_token();
    $nuevo_nombre = trim($_POST['nombre']);
    $nuevo_email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("UPDATE USUARIOS SET nombre = ?, email = ? WHERE id = ?");
    $stmt->execute([$nuevo_nombre, $nuevo_email, $user_id]);
    
    $_SESSION['user_nombre'] = $nuevo_nombre;
    $_SESSION['user_email'] = $nuevo_email;
    
    $nombre_usuario = $nuevo_nombre;
    $user_email = $nuevo_email;
    $mensaje_exito = "Perfil actualizado correctamente";
}

// MANEJO DE CAMBIO DE CONTRASEÑA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    validate_csrf_token();
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    
    // Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM USUARIOS WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    // Verificar contraseña actual usando password_verify
    if (password_verify($password_actual, $user['password'])) {
        if ($password_nueva === $password_confirmar) {
            // Hashear la nueva contraseña antes de guardarla
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE USUARIOS SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $user_id]);
            $mensaje_exito = "Contraseña actualizada correctamente";
        } else {
            $mensaje_error = "Las contraseñas nuevas no coinciden";
        }
    } else {
        $mensaje_error = "Contraseña actual incorrecta";
    }
}


// MANEJO DE ELIMINACIÓN DE CUENTA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    validate_csrf_token();
    $cuenta_id = (int)$_POST['cuenta_id'];
    
    // Verificar que la cuenta pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM CUENTAS WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$cuenta_id, $user_id]);
    
    if ($stmt->fetch()) {
        // Eliminar la cuenta (esto eliminará en cascada los movimientos si está configurado)
        $stmt = $pdo->prepare("DELETE FROM CUENTAS WHERE id = ?");
        $stmt->execute([$cuenta_id]);
        
        header("Location: perfil.php");
        exit;
    }
}

// MANEJO DE ELIMINACIÓN DE USUARIO COMPLETO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user'])) {
    validate_csrf_token();
    $password_confirmacion = $_POST['password_confirmacion'];
    
    // Verificar contraseña para seguridad
    $stmt = $pdo->prepare("SELECT password FROM USUARIOS WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (password_verify($password_confirmacion, $user['password'])) {
        // Eliminar usuario (esto eliminará en cascada todas sus cuentas y movimientos)
        $stmt = $pdo->prepare("DELETE FROM USUARIOS WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Destruir sesión
        session_destroy();
        
        // Redirigir a página de login con mensaje
        header("Location: login.php?deleted=1");
        exit;
    } else {
        $mensaje_error = "Contraseña incorrecta. No se puede eliminar la cuenta.";
    }
}


// OBTENER ESTADÍSTICAS
$stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE usuario_id = ? ORDER BY id");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular saldo actual de cada cuenta
$cuentas_procesadas = [];
foreach ($cuentas as $cuenta) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(
            CASE 
                WHEN tt.naturaleza = 'INGRESO' THEN m.monto
                WHEN tt.naturaleza = 'GASTO' THEN -m.monto
                ELSE 0
            END
        ), 0) as total_movimientos
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        WHERE m.cuenta_id = ?
    ");
    $stmt->execute([$cuenta['id']]);
    $movimientos_total = $stmt->fetchColumn();
    
    $cuenta['saldo_actual'] = $cuenta['saldo_inicial'] + $movimientos_total;
    $cuentas_procesadas[] = $cuenta;
}

$cuentas = $cuentas_procesadas;

$saldo_total = array_sum(array_column($cuentas, 'saldo_actual'));

// Contar movimientos
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM MOVIMIENTOS m
    JOIN CUENTAS c ON m.cuenta_id = c.id
    WHERE c.usuario_id = ?
");
$stmt->execute([$user_id]);
$total_movimientos = $stmt->fetchColumn();

// Contar pagos recurrentes
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM MOVIMIENTOS_RECURRENTES mr
    JOIN CUENTAS c ON mr.cuenta_id = c.id
    WHERE c.usuario_id = ? AND mr.activo = 1
");
$stmt->execute([$user_id]);
$total_recurrentes = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Mi Perfil | Vaulta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        .dashboard-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* SIDEBAR */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #002366 0%, #007bff 100%);
            color: white;
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .brand {
            padding: 0 30px 30px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .brand img {
            max-width: 100%;
            height: auto;
            max-height: 60px;
        }

        .nav-links {
            list-style: none;
            padding: 20px 0;
            flex: 1;
        }

        .nav-links li {
            margin: 5px 0;
        }

        .nav-links a {
            display: block;
            padding: 12px 30px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-links a:hover,
        .nav-links li.active a {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left: 4px solid white;
            padding-left: 26px;
        }

        .logout-section {
            padding: 0 30px;
            border-top: 1px solid rgba(255,255,255,0.2);
            padding-top: 20px;
        }

        .btn-logout {
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }

        /* MAIN CONTENT */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        /* TOP BAR */
        .top-bar {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .welcome-text h1 {
            font-size: 28px;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .current-date {
            color: #718096;
            font-size: 14px;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #007bff;
        }

        .stat-label {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-value.positive {
            color: #48bb78;
        }

        /* PROFILE SECTION */
        .profile-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .btn-primary {
            padding: 12px 24px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        }

        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: #fed7d7;
            border: 1px solid #fc8181;
            color: #c53030;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        /* ACCOUNTS LIST */
        .accounts-list {
            display: grid;
            gap: 15px;
        }

        .account-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            gap: 15px;
        }

        .account-info {
            flex: 1;
        }

        .account-name {
            font-weight: 600;
            color: #2d3748;
        }

        .account-balance {
            font-size: 18px;
            font-weight: 700;
        }

        .account-balance.positive {
            color: #48bb78;
        }

        .account-balance.negative {
            color: #f56565;
        }

        .two-column {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }

        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }

        /* MODAL */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
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

        .modal-header {
            margin-bottom: 25px;
        }

        .modal-header h3 {
            font-size: 22px;
            color: #2d3748;
        }

        .btn-cancel {
            padding: 12px 24px;
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-cancel:hover {
            background: #cbd5e0;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-add {
            padding: 10px 20px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        }

        .btn-delete-account {
            padding: 8px 12px;
            background: #fed7d7;
            color: #c53030;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-delete-account:hover {
            background: #fc8181;
            color: white;
        }

        /* DANGER ZONE */
        .danger-zone {
            background: #fff5f5;
            border: 2px solid #feb2b2;
            border-radius: 12px;
            padding: 25px;
            margin-top: 25px;
        }

        .danger-zone .section-title {
            color: #c53030;
            border-bottom-color: #feb2b2;
        }

        .danger-warning {
            background: #fed7d7;
            border-left: 4px solid #f56565;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .danger-warning p {
            color: #742a2a;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }

        .btn-danger {
            padding: 12px 24px;
            background: #c53030;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-danger:hover {
            background: #9b2c2c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 48, 48, 0.4);
        }
    </style>
</head>
<body>

    <div class="dashboard-wrapper">

        <nav class="sidebar">
            <div class="brand">
                <img src="images/logotrans.png" alt="Vaulta">
            </div>
            
            <ul class="nav-links">
                <li><a href="dashboard.php">Resumen</a></li>
                <li><a href="movimientos.php">Movimientos</a></li>
                <li><a href="transferencias.php">Transferencias</a></li>
                <li><a href="recurrentes.php">Recurrentes</a></li>
                <li><a href="prevision.php">Previsión</a></li>
                <li><a href="objetivos.php">Objetivos</a></li>
                <li class="active"><a href="perfil.php">Mi Perfil</a></li>
            </ul>

            <div class="logout-section">
                <form action="logout.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="btn-logout">Salir</button>
                </form>
            </div>
        </nav>

        <main class="main-content">

            <header class="top-bar">
                <div class="welcome-text">
                    <h1>Mi Perfil</h1>
                    <p class="current-date">Gestiona tu información personal</p>
                </div>
            </header>

            <?php if (isset($mensaje_exito)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo esc($mensaje_exito); ?>
            </div>
            <?php endif; ?>

            <?php if (isset($mensaje_error)): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo esc($mensaje_error); ?>
            </div>
            <?php endif; ?>

            <!-- ESTADÍSTICAS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Saldo Total</div>
                    <div class="stat-value positive"><?php echo number_format($saldo_total, 2, ',', '.'); ?> €</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Cuentas</div>
                    <div class="stat-value"><?php echo count($cuentas); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Movimientos</div>
                    <div class="stat-value"><?php echo $total_movimientos; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pagos Recurrentes</div>
                    <div class="stat-value"><?php echo $total_recurrentes; ?></div>
                </div>
            </div>

            <div class="two-column">
                <!-- INFORMACIÓN PERSONAL -->
                <div class="profile-section">
                    <h2 class="section-title"><i class="fas fa-user"></i> Información Personal</h2>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label>Nombre</label>
                            <input type="text" name="nombre" value="<?php echo htmlspecialchars($nombre_usuario); ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </form>
                </div>

                <!-- CAMBIAR CONTRASEÑA -->
                <div class="profile-section">
                    <h2 class="section-title"><i class="fas fa-lock"></i> Cambiar Contraseña</h2>
                    
                    <form method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label>Contraseña Actual</label>
                            <input type="password" name="password_actual" required>
                        </div>

                        <div class="form-group">
                            <label>Nueva Contraseña</label>
                            <input type="password" name="password_nueva" required>
                        </div>

                        <div class="form-group">
                            <label>Confirmar Nueva Contraseña</label>
                            <input type="password" name="password_confirmar" required>
                        </div>

                        <button type="submit" class="btn-primary">
                            <i class="fas fa-key"></i> Cambiar Contraseña
                        </button>
                    </form>
                </div>
            </div>

            <!-- MIS CUENTAS -->
            <div class="profile-section">
                <div class="section-header">
                    <h2 class="section-title" style="margin: 0; padding: 0; border: none;"><i class="fas fa-wallet"></i> Mis Cuentas</h2>
                    <button class="btn-add" onclick="openModal()">
                        <i class="fas fa-plus"></i> Añadir Cuenta
                    </button>
                </div>
                
                <div class="accounts-list">
                    <?php foreach ($cuentas as $cuenta): ?>
                    <div class="account-item">
                        <div class="account-info">
                            <div class="account-name"><?php echo htmlspecialchars($cuenta['nombre']); ?></div>
                            <small style="color: #718096;">Saldo inicial: <?php echo number_format($cuenta['saldo_inicial'], 2, ',', '.'); ?> €</small>
                        </div>
                        <div class="account-balance <?php echo $cuenta['saldo_actual'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €
                        </div>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Estás seguro de eliminar esta cuenta? Se eliminarán también todos sus movimientos.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_account" value="1">
                            <input type="hidden" name="cuenta_id" value="<?php echo $cuenta['id']; ?>">
                            <button type="submit" class="btn-delete-account">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ZONA DE PELIGRO: ELIMINAR CUENTA -->
            <div class="danger-zone">
                <h2 class="section-title"><i class="fas fa-exclamation-triangle"></i> Zona de Peligro</h2>
                
                <div class="danger-warning">
                    <p><strong>⚠️ Advertencia:</strong> Eliminar tu cuenta es una acción permanente e irreversible. 
                    Se eliminarán todos tus datos incluyendo cuentas, movimientos, transferencias y pagos recurrentes.</p>
                </div>

                <button type="button" class="btn-danger" onclick="openDeleteUserModal()">
                    <i class="fas fa-user-times"></i> Eliminar Mi Cuenta
                </button>
            </div>

        </main>
    </div>

    <!-- MODAL AÑADIR CUENTA -->
    <div class="modal" id="addAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Añadir Nueva Cuenta</h3>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_account" value="1">

                <div class="form-group">
                    <label>Nombre de la Cuenta</label>
                    <input type="text" name="nombre_cuenta" placeholder="Ej: Cuenta Banco, Efectivo, Ahorros..." required>
                </div>

                <div class="form-group">
                    <label>Saldo Inicial (€)</label>
                    <input type="number" name="saldo_inicial" step="0.01" placeholder="0.00" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Crear Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL ELIMINAR USUARIO -->
    <div class="modal" id="deleteUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: #c53030;"><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación de Cuenta</h3>
            </div>

            <div class="danger-warning" style="margin-bottom: 20px;">
                <p><strong>Esta acción no se puede deshacer.</strong></p>
                <p>Al eliminar tu cuenta se eliminarán permanentemente:</p>
                <ul style="margin: 10px 0 0 20px; color: #742a2a;">
                    <li>Tu perfil y datos personales</li>
                    <li>Todas tus cuentas bancarias</li>
                    <li>Todos tus movimientos y transacciones</li>
                    <li>Todos tus pagos recurrentes</li>
                </ul>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="delete_user" value="1">

                <div class="form-group">
                    <label>Confirma tu contraseña para continuar</label>
                    <input type="password" name="password_confirmacion" placeholder="Introduce tu contraseña" required autofocus>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeDeleteUserModal()">Cancelar</button>
                    <button type="submit" class="btn-danger">
                        <i class="fas fa-trash-alt"></i> Sí, Eliminar Mi Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addAccountModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('addAccountModal').classList.remove('show');
        }

        function openDeleteUserModal() {
            document.getElementById('deleteUserModal').classList.add('show');
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserModal').classList.remove('show');
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('addAccountModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('deleteUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteUserModal();
            }
        });
    </script>

</body>
</html>
