<?php
session_start();
require_once 'security.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require 'conexion.php';

$user_id = $_SESSION['user_id'];
$nombre_usuario = $_SESSION['user_nombre'];

// VERIFICAR SI TIENE CUENTAS
$stmt = $pdo->prepare("SELECT COUNT(*) FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() == 0) {
    header("Location: setup.php");
    exit;
}

// Variables para mensajes
$mensaje = '';
$tipo_mensaje = ''; // success, error, warning

// PROCESAR TRANSFERENCIA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['realizar_transferencia'])) {
    validate_csrf_token();
    $cuenta_origen_id = (int) $_POST['cuenta_origen'];
    $cuenta_destino_id = (int) $_POST['cuenta_destino'];
    $monto = (float) $_POST['monto'];
    $descripcion = trim($_POST['descripcion']);
    
    // Validaciones
    if ($cuenta_origen_id == $cuenta_destino_id) {
        $mensaje = 'No puedes transferir a la misma cuenta';
        $tipo_mensaje = 'error';
    } elseif ($monto <= 0) {
        $mensaje = 'El monto debe ser mayor a 0';
        $tipo_mensaje = 'error';
    } else {
        // Verificar que ambas cuentas pertenecen al usuario
        $stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE id IN (?, ?) AND usuario_id = ?");
        $stmt->execute([$cuenta_origen_id, $cuenta_destino_id, $user_id]);
        $cuentas_verificadas = $stmt->fetchAll();
        
        if (count($cuentas_verificadas) != 2) {
            $mensaje = 'Error: Cuentas no válidas';
            $tipo_mensaje = 'error';
        } else {
            // Calcular saldo actual de la cuenta origen
            $stmt = $pdo->prepare("
                SELECT SUM(CASE WHEN tt.naturaleza = 'INGRESO' THEN m.monto ELSE -m.monto END) as total
                FROM MOVIMIENTOS m
                JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
                WHERE m.cuenta_id = ?
            ");
            $stmt->execute([$cuenta_origen_id]);
            $movimientos_total = $stmt->fetch()['total'] ?? 0;
            
            // Obtener saldo inicial
            $stmt = $pdo->prepare("SELECT saldo_inicial FROM CUENTAS WHERE id = ?");
            $stmt->execute([$cuenta_origen_id]);
            $saldo_inicial = $stmt->fetchColumn();
            
            $saldo_actual_origen = $saldo_inicial + $movimientos_total;
            
            // Verificar fondos suficientes
            if ($saldo_actual_origen < $monto) {
                $mensaje = 'Fondos insuficientes en la cuenta de origen';
                $tipo_mensaje = 'error';
            } else {
                // Buscar o crear tipo de transacción "Transferencia"
                $stmt = $pdo->prepare("SELECT id FROM TIPOS_TRANSACCION WHERE nombre = 'Transferencia'");
                $stmt->execute();
                $tipo_transferencia = $stmt->fetch();
                
                if (!$tipo_transferencia) {
                    // Crear tipo de transacción si no existe
                    $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES ('Transferencia', 'GASTO', 'fa-exchange-alt')");
                    $stmt->execute();
                    $tipo_transferencia_id = $pdo->lastInsertId();
                } else {
                    $tipo_transferencia_id = $tipo_transferencia['id'];
                }
                
                try {
                    $pdo->beginTransaction();
                    
                    // Registrar GASTO en cuenta origen
                    $stmt = $pdo->prepare("
                        INSERT INTO MOVIMIENTOS (cuenta_id, tipo_id, monto, fecha, descripcion)
                        VALUES (?, ?, ?, CURDATE(), ?)
                    ");
                    $desc_origen = "Transferencia a " . $descripcion;
                    $stmt->execute([$cuenta_origen_id, $tipo_transferencia_id, $monto, $desc_origen]);
                    
                    // Registrar INGRESO en cuenta destino
                    // Necesitamos un tipo de transacción de ingreso para transferencias
                    $stmt = $pdo->prepare("SELECT id FROM TIPOS_TRANSACCION WHERE nombre = 'Transferencia Recibida'");
                    $stmt->execute();
                    $tipo_transferencia_ingreso = $stmt->fetch();
                    
                    if (!$tipo_transferencia_ingreso) {
                        $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES ('Transferencia Recibida', 'INGRESO', 'fa-exchange-alt')");
                        $stmt->execute();
                        $tipo_transferencia_ingreso_id = $pdo->lastInsertId();
                    } else {
                        $tipo_transferencia_ingreso_id = $tipo_transferencia_ingreso['id'];
                    }
                    
                    $desc_destino = "Transferencia desde " . $descripcion;
                    $stmt = $pdo->prepare("
                        INSERT INTO MOVIMIENTOS (cuenta_id, tipo_id, monto, fecha, descripcion)
                        VALUES (?, ?, ?, CURDATE(), ?)
                    ");
                    $stmt->execute([$cuenta_destino_id, $tipo_transferencia_ingreso_id, $monto, $desc_destino]);
                    
                    $pdo->commit();
                    
                    $mensaje = '¡Transferencia realizada con éxito!';
                    $tipo_mensaje = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensaje = 'Error al realizar la transferencia: ' . $e->getMessage();
                    $tipo_mensaje = 'error';
                }
            }
        }
    }
}

// OBTENER CUENTAS DEL USUARIO CON SALDO ACTUAL
$stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// Calcular saldo actual de cada cuenta
foreach ($cuentas as &$cuenta) {
    $stmt = $pdo->prepare("
        SELECT SUM(CASE WHEN tt.naturaleza = 'INGRESO' THEN m.monto ELSE -m.monto END) as total
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        WHERE m.cuenta_id = ?
    ");
    $stmt->execute([$cuenta['id']]);
    $movimientos_total = $stmt->fetch()['total'] ?? 0;
    $cuenta['saldo_actual'] = $cuenta['saldo_inicial'] + $movimientos_total;
}
unset($cuenta);

// OBTENER ÚLTIMAS TRANSFERENCIAS
$stmt = $pdo->prepare("
    SELECT 
        m.fecha, m.monto, m.descripcion,
        tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
        c.nombre as cuenta_nombre
    FROM MOVIMIENTOS m
    JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
    JOIN CUENTAS c ON m.cuenta_id = c.id
    WHERE c.usuario_id = ? 
      AND (tt.nombre = 'Transferencia' OR tt.nombre = 'Transferencia Recibida')
    ORDER BY m.fecha DESC, m.id DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$transferencias_recientes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Transferencias | Vaulta</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        /* ALERT MESSAGES */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert i {
            font-size: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        /* TRANSFER FORM */
        .transfer-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header i {
            font-size: 24px;
            color: #007bff;
        }

        .section-header h2 {
            font-size: 22px;
            color: #2d3748;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            color: #4a5568;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group select,
        .form-group input {
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            background: white;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .account-option {
            padding: 10px;
        }

        .btn-transfer {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-transfer:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.3);
        }

        .btn-transfer:active {
            transform: translateY(0);
        }

        /* ACCOUNT CARDS */
        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .account-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }

        .account-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .account-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .account-card-header h3 {
            font-size: 16px;
            color: #2d3748;
        }

        .account-card-header i {
            color: #007bff;
            font-size: 20px;
        }

        .account-balance {
            font-size: 28px;
            font-weight: 700;
            color: #48bb78;
            margin-bottom: 5px;
        }

        .account-balance.negative {
            color: #f56565;
        }

        .account-initial {
            font-size: 13px;
            color: #718096;
        }

        /* RECENT TRANSFERS */
        .recent-transfers {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .transfers-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transfers-table th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: #4a5568;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transfers-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .transfers-table tr:hover {
            background: #f7fafc;
        }

        .transfer-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .icon-box {
            width: 35px;
            height: 35px;
            background: #edf2f7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
        }

        .amount {
            font-weight: 600;
        }

        .amount.positive {
            color: #48bb78;
        }

        .amount.negative {
            color: #f56565;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* TRANSFER ARROW */
        .transfer-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
            font-size: 24px;
            margin: 10px 0;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .accounts-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="dark-mode.css">
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
                <li class="active"><a href="transferencias.php">Transferencias</a></li>
                <li><a href="recurrentes.php">Recurrentes</a></li>
                <li><a href="prevision.php">Previsión</a></li>
                <li><a href="objetivos.php">Objetivos</a></li>
                <li><a href="perfil.php">Mi Perfil</a></li>
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
                    <h1>Transferencias entre Cuentas</h1>
                    <p class="current-date"><?php echo date('d/m/Y'); ?></p>
                </div>
            </header>

            <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?>">
                <i class="fas fa-<?php echo $tipo_mensaje == 'success' ? 'check-circle' : ($tipo_mensaje == 'error' ? 'exclamation-circle' : 'exclamation-triangle'); ?>"></i>
                <span><?php echo htmlspecialchars($mensaje); ?></span>
            </div>
            <?php endif; ?>

            <!-- MIS CUENTAS -->
            <section class="accounts-grid">
                <?php foreach ($cuentas as $cuenta): ?>
                <div class="account-card">
                    <div class="account-card-header">
                        <h3><?php echo htmlspecialchars($cuenta['nombre']); ?></h3>
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="account-balance <?php echo $cuenta['saldo_actual'] < 0 ? 'negative' : ''; ?>">
                        <?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €
                    </div>
                    <div class="account-initial">
                        Saldo inicial: <?php echo number_format($cuenta['saldo_inicial'], 2, ',', '.'); ?> €
                    </div>
                </div>
                <?php endforeach; ?>
            </section>

            <!-- FORMULARIO DE TRANSFERENCIA -->
            <section class="transfer-section">
                <div class="section-header">
                    <i class="fas fa-exchange-alt"></i>
                    <h2>Nueva Transferencia</h2>
                </div>

                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="cuenta_origen">
                                <i class="fas fa-arrow-up" style="color: #f56565;"></i> Cuenta Origen
                            </label>
                            <select name="cuenta_origen" id="cuenta_origen" required>
                                <option value="">Selecciona una cuenta</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                <option value="<?php echo $cuenta['id']; ?>">
                                    <?php echo htmlspecialchars($cuenta['nombre']); ?> 
                                    (<?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="cuenta_destino">
                                <i class="fas fa-arrow-down" style="color: #48bb78;"></i> Cuenta Destino
                            </label>
                            <select name="cuenta_destino" id="cuenta_destino" required>
                                <option value="">Selecciona una cuenta</option>
                                <?php foreach ($cuentas as $cuenta): ?>
                                <option value="<?php echo $cuenta['id']; ?>">
                                    <?php echo htmlspecialchars($cuenta['nombre']); ?> 
                                    (<?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="monto">
                                <i class="fas fa-euro-sign"></i> Monto a Transferir
                            </label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="monto" placeholder="0.00" required>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">
                                <i class="fas fa-comment"></i> Descripción
                            </label>
                            <input type="text" name="descripcion" id="descripcion" placeholder="Ej. Ahorro mensual" required>
                        </div>

                        <div class="form-group full-width">
                            <button type="submit" name="realizar_transferencia" class="btn-transfer">
                                <i class="fas fa-paper-plane"></i>
                                Realizar Transferencia
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- TRANSFERENCIAS RECIENTES -->
            <section class="recent-transfers">
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    <h2>Transferencias Recientes</h2>
                </div>

                <?php if (count($transferencias_recientes) > 0): ?>
                <table class="transfers-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transferencias_recientes as $trans): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($trans['fecha'])); ?></td>
                            
                            <td>
                                <div class="transfer-type">
                                    <span class="icon-box">
                                        <i class="fas <?php echo htmlspecialchars($trans['icono']); ?>"></i>
                                    </span>
                                    <span><?php echo htmlspecialchars($trans['tipo_nombre']); ?></span>
                                </div>
                            </td>

                            <td><?php echo htmlspecialchars($trans['descripcion']); ?></td>
                            <td style="color: #718096; font-size: 13px;"><?php echo htmlspecialchars($trans['cuenta_nombre']); ?></td>
                            
                            <td class="amount <?php echo $trans['naturaleza'] == 'INGRESO' ? 'positive' : 'negative'; ?>">
                                <?php echo $trans['naturaleza'] == 'INGRESO' ? '+' : '-'; ?> 
                                <?php echo number_format($trans['monto'], 2, ',', '.'); ?> €
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exchange-alt"></i>
                    <p>No hay transferencias registradas</p>
                </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

<script src="dark-mode.js"></script>
</body>
</html>
