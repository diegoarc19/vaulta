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

// VERIFICAR SI TIENE CUENTAS (Protección Onboarding)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
if ($stmt->fetchColumn() == 0) {
    header("Location: setup.php");
    exit;
}

// Obtener banco del usuario
$stmt = $pdo->prepare("SELECT banco FROM USUARIOS WHERE id = ?");
$stmt->execute([$user_id]);
$user_banco = $stmt->fetchColumn() ?: 'Banco no registrado';

// PROCESAR PAGOS RECURRENTES AUTOMÁTICOS
require_once 'procesar_recurrentes.php';

// FILTRO DE CUENTA SELECCIONADA
$cuenta_seleccionada = isset($_GET['cuenta_id']) ? (int)$_GET['cuenta_id'] : 0;

// 1. OBTENER CUENTAS DEL USUARIO
$stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// 2. CALCULAR SALDO ACTUAL DE CADA CUENTA
foreach ($cuentas as &$cuenta) {
    // Calcular suma de movimientos
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
unset($cuenta); // Romper la referencia

// 3. CALCULAR SALDO TOTAL O DE CUENTA SELECCIONADA
if ($cuenta_seleccionada > 0) {
    // Buscar la cuenta seleccionada
    $cuenta_actual = array_filter($cuentas, function($c) use ($cuenta_seleccionada) {
        return $c['id'] == $cuenta_seleccionada;
    });
    $cuenta_actual = reset($cuenta_actual);
    $saldo_total = $cuenta_actual ? $cuenta_actual['saldo_actual'] : 0;
    $nombre_cuenta = $cuenta_actual ? $cuenta_actual['nombre'] : 'Cuenta';
} else {
    $saldo_total = array_sum(array_column($cuentas, 'saldo_actual'));
    $nombre_cuenta = 'Todas las Cuentas';
}

// 4. OBTENER ÚLTIMOS MOVIMIENTOS (FILTRADOS)
if ($cuenta_seleccionada > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            m.fecha, m.monto, m.descripcion,
            tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
            c.nombre as cuenta_nombre
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        JOIN CUENTAS c ON m.cuenta_id = c.id
        WHERE c.usuario_id = ? AND c.id = ?
        ORDER BY m.fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $cuenta_seleccionada]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            m.fecha, m.monto, m.descripcion,
            tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
            c.nombre as cuenta_nombre
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        JOIN CUENTAS c ON m.cuenta_id = c.id
        WHERE c.usuario_id = ?
        ORDER BY m.fecha DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
}
$movimientos = $stmt->fetchAll();

// 5. CALCULAR GASTOS DEL MES ACTUAL (FILTRADOS)
if ($cuenta_seleccionada > 0) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(m.monto), 0) as total_gastos
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        JOIN CUENTAS c ON m.cuenta_id = c.id
        WHERE c.usuario_id = ? AND c.id = ?
          AND tt.naturaleza = 'GASTO'
          AND MONTH(m.fecha) = MONTH(CURRENT_DATE)
          AND YEAR(m.fecha) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute([$user_id, $cuenta_seleccionada]);
} else {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(m.monto), 0) as total_gastos
        FROM MOVIMIENTOS m
        JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
        JOIN CUENTAS c ON m.cuenta_id = c.id
        WHERE c.usuario_id = ? 
          AND tt.naturaleza = 'GASTO'
          AND MONTH(m.fecha) = MONTH(CURRENT_DATE)
          AND YEAR(m.fecha) = YEAR(CURRENT_DATE)
    ");
    $stmt->execute([$user_id]);
}
$gastos_mes = $stmt->fetch()['total_gastos'];

// 6. OBTENER PAGOS RECURRENTES ACTIVOS (FILTRADOS)
if ($cuenta_seleccionada > 0) {
    $stmt = $pdo->prepare("
        SELECT 
            mr.monto, mr.dia_cargo, mr.periodicidad,
            tt.nombre as tipo_nombre, tt.icono
        FROM MOVIMIENTOS_RECURRENTES mr
        JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
        JOIN CUENTAS c ON mr.cuenta_id = c.id
        WHERE c.usuario_id = ? AND c.id = ? AND mr.activo = 1
        ORDER BY mr.dia_cargo
    ");
    $stmt->execute([$user_id, $cuenta_seleccionada]);
} else {
    $stmt = $pdo->prepare("
        SELECT 
            mr.monto, mr.dia_cargo, mr.periodicidad,
            tt.nombre as tipo_nombre, tt.icono
        FROM MOVIMIENTOS_RECURRENTES mr
        JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
        JOIN CUENTAS c ON mr.cuenta_id = c.id
        WHERE c.usuario_id = ? AND mr.activo = 1
        ORDER BY mr.dia_cargo
    ");
    $stmt->execute([$user_id]);
}
$recurrentes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Resumen | Vaulta</title>
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

        .user-profile-widget {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-dni {
            color: #718096;
            font-size: 14px;
        }

        .avatar-placeholder {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        /* BALANCE OVERVIEW */
        .balance-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .balance-card {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            opacity: 0.9;
        }

        .card-header i {
            font-size: 24px;
            opacity: 0.8;
        }

        .amount {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0;
        }

        .amount.positive {
            color: #48bb78;
        }

        .amount.negative {
            color: #f56565;
        }

        .balance-card .amount {
            color: white;
        }

        .text-muted {
            color: rgba(255,255,255,0.8);
            font-size: 14px;
        }

        .expense-card h3 {
            color: #2d3748;
            margin-bottom: 15px;
        }

        /* SECTIONS */
        .recent-transactions,
        .recurring-payments {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
        }

        /* TRANSACTIONS TABLE */
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: #4a5568;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .transactions-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        /* El .amount en las cards tiene 36px; en la tabla debe ser normal */
        .transactions-table .amount {
            font-size: 15px !important;
            font-weight: 600;
            text-align: right;
            white-space: nowrap;
        }

        .transactions-table tr:hover {
            background: #f7fafc;
        }

        .transaction-type {
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

        /* RECURRING PAYMENTS */
        .recurring-payments h3 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .recurring-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .recurring-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border-left: 4px solid #007bff;
        }

        .rec-info strong {
            display: block;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .rec-period {
            display: inline-block;
            padding: 3px 8px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 5px;
        }

        .rec-amount {
            margin-top: 10px;
            color: #4a5568;
            font-weight: 600;
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

        /* ACCOUNT SELECTOR */
        .account-selector-section {
            margin-bottom: 25px;
        }

        .selector-card {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .selector-card label {
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
            white-space: nowrap;
        }

        .selector-card label i {
            color: #007bff;
        }

        .selector-card select {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .selector-card select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .selector-card select:hover {
            border-color: #007bff;
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
                <li class="active"><a href="dashboard.php">Resumen</a></li>
                <li><a href="movimientos.php">Movimientos</a></li>
                <li><a href="transferencias.php">Transferencias</a></li>
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
                    <h1>Hola, <?php echo htmlspecialchars($nombre_usuario); ?></h1>
                    <p class="current-date"><?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="user-profile-widget">
                    <span class="user-dni"><?php echo htmlspecialchars($user_banco); ?></span>
                    <div class="avatar-placeholder"><?php echo strtoupper(substr($nombre_usuario, 0, 2)); ?></div>
                </div>
            </header>

            <!-- SELECTOR DE CUENTA -->
            <section class="account-selector-section">
                <div class="selector-card">
                    <label for="account-select">
                        <i class="fas fa-filter"></i> Filtrar por cuenta:
                    </label>
                    <select id="account-select" onchange="window.location.href='dashboard.php?cuenta_id=' + this.value">
                        <option value="0" <?php echo $cuenta_seleccionada == 0 ? 'selected' : ''; ?>>Todas las Cuentas</option>
                        <?php foreach ($cuentas as $cuenta): ?>
                        <option value="<?php echo $cuenta['id']; ?>" <?php echo $cuenta_seleccionada == $cuenta['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cuenta['nombre']); ?> (<?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </section>

            <section class="balance-overview">
                
                <article class="card balance-card">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($nombre_cuenta); ?></h3>
                        <i class="fas fa-wallet"></i>
                    </div>
                    
                    <p class="amount">
                        <?php echo number_format($saldo_total, 2, ',', '.'); ?> €
                    </p>
                    
                    <small class="text-muted"><?php echo count($cuentas); ?> cuenta(s)</small>
                </article>
                
                <article class="card expense-card">
                    <h3>Gastos del Mes</h3>
                    <p class="amount negative">- <?php echo number_format($gastos_mes, 2, ',', '.'); ?> €</p>
                    <small style="color: #718096; font-size: 14px;"><?php echo date('F Y'); ?></small>
                </article>

            </section>
            
            <!-- CUENTAS INDIVIDUALES (solo mostrar cuando NO hay cuenta seleccionada) -->
            <?php if ($cuenta_seleccionada == 0): ?>
            <section class="balance-overview">
                <?php foreach ($cuentas as $cuenta): ?>
                <article class="card">
                    <div class="card-header">
                        <h3 style="color: #2d3748;"><?php echo htmlspecialchars($cuenta['nombre']); ?></h3>
                        <i class="fas fa-university" style="color: #007bff;"></i>
                    </div>
                    <p class="amount" style="color: <?php echo $cuenta['saldo_actual'] >= 0 ? '#48bb78' : '#f56565'; ?>;">
                        <?php echo number_format($cuenta['saldo_actual'], 2, ',', '.'); ?> €
                    </p>
                    <small style="color: #718096; font-size: 14px;">Inicial: <?php echo number_format($cuenta['saldo_inicial'], 2, ',', '.'); ?> €</small>
                </article>
                <?php endforeach; ?>
            </section>
            <?php endif; ?>

            <section class="recent-transactions">
                <div class="section-header">
                    <h2>Últimos Movimientos</h2>
                </div>

                <?php if (count($movimientos) > 0): ?>
                <table class="transactions-table">
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
                        <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?php echo date('d/m/Y', strtotime($mov['fecha'])); ?></td>
                            
                            <td>
                                <div class="transaction-type">
                                    <span class="icon-box">
                                        <i class="fas <?php echo htmlspecialchars($mov['icono']); ?>"></i>
                                    </span>
                                    <span><?php echo htmlspecialchars($mov['tipo_nombre']); ?></span>
                                </div>
                            </td>

                            <td><?php echo htmlspecialchars($mov['descripcion']); ?></td>
                            <td style="color: #718096; font-size: 13px;"><?php echo htmlspecialchars($mov['cuenta_nombre']); ?></td>
                            
                            <td class="amount" style="color: <?php echo $mov['naturaleza'] == 'INGRESO' ? '#48bb78' : '#f56565'; ?>; font-weight: 600;">
                                <?php echo $mov['naturaleza'] == 'INGRESO' ? '+' : '-'; ?> <?php echo number_format($mov['monto'], 2, ',', '.'); ?> €
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No hay movimientos registrados</p>
                </div>
                <?php endif; ?>
            </section>
            
            <section class="recurring-payments">
                <h3>Próximos Cargos (Recurrentes)</h3>
                <?php if (count($recurrentes) > 0): ?>
                <div class="recurring-grid">
                    <?php foreach ($recurrentes as $rec): ?>
                    <div class="recurring-item">
                        <div class="rec-info">
                             <strong><i class="fas <?php echo htmlspecialchars($rec['icono']); ?>"></i> <?php echo htmlspecialchars($rec['tipo_nombre']); ?></strong>
                             <span class="rec-period"><?php echo htmlspecialchars($rec['periodicidad']); ?></span>
                        </div>
                        <div class="rec-amount">
                             <?php echo number_format($rec['monto'], 2, ',', '.'); ?> € (Día <?php echo $rec['dia_cargo']; ?>)
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No hay pagos recurrentes configurados</p>
                </div>
                <?php endif; ?>
            </section>

        </main>
    </div>

<script src="dark-mode.js"></script>
</body>
</html>
