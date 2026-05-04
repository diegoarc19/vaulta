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

// MANEJO DE ELIMINACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    validate_csrf_token();
    $delete_id = (int)$_POST['delete_id'];
    
    // Verificar que el pago recurrente pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT mr.id FROM MOVIMIENTOS_RECURRENTES mr
        JOIN CUENTAS c ON mr.cuenta_id = c.id
        WHERE mr.id = ? AND c.usuario_id = ?
    ");
    $stmt->execute([$delete_id, $user_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM MOVIMIENTOS_RECURRENTES WHERE id = ?");
        $stmt->execute([$delete_id]);
        $mensaje_exito = "Pago recurrente eliminado correctamente";
    }
}

// MANEJO DE TOGGLE ACTIVO/INACTIVO
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle_id'])) {
    validate_csrf_token();
    $toggle_id = (int)$_POST['toggle_id'];
    $nuevo_estado = (int)$_POST['nuevo_estado'];
    
    // Verificar que pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT mr.id FROM MOVIMIENTOS_RECURRENTES mr
        JOIN CUENTAS c ON mr.cuenta_id = c.id
        WHERE mr.id = ? AND c.usuario_id = ?
    ");
    $stmt->execute([$toggle_id, $user_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("UPDATE MOVIMIENTOS_RECURRENTES SET activo = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $toggle_id]);
        $mensaje_exito = $nuevo_estado ? "Pago activado" : "Pago desactivado";
    }
}

// MANEJO DE ADICIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_recurring'])) {
    validate_csrf_token();
    $cuenta_id = (int)$_POST['cuenta_id'];
    $naturaleza = $_POST['naturaleza'];
    $nombre_tipo = trim($_POST['nombre_tipo']);
    $icono = $_POST['icono'];
    $monto = (float)$_POST['monto'];
    $periodicidad = $_POST['periodicidad'];
    
    // Determinar el día de cargo y fecha específica según la periodicidad
    if ($periodicidad == 'SEMANAL') {
        $dia_cargo = (int)$_POST['dia_semana']; //  1-7 (Lunes-Domingo)
        $fecha_especifica = null;
    } elseif ($periodicidad == 'SEMESTRAL' || $periodicidad == 'ANUAL') {
        // Para anual/semestral, usar fecha específica del calendario
        $fecha_especifica = isset($_POST['fecha_especifica']) ? $_POST['fecha_especifica'] : null;
        $dia_cargo = $fecha_especifica ? (int)date('d', strtotime($fecha_especifica)) : 1;
    } else {
        $dia_cargo = (int)$_POST['dia_cargo']; // 1-31 (día del mes)
        $fecha_especifica = null;
    }
    
    // Verificar que la cuenta pertenece al usuario
    $stmt = $pdo->prepare("SELECT id FROM CUENTAS WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$cuenta_id, $user_id]);
    
    if ($stmt->fetch()) {
        // Buscar o crear el tipo de transacción
        $stmt = $pdo->prepare("SELECT id FROM TIPOS_TRANSACCION WHERE nombre = ? AND naturaleza = ? AND icono = ?");
        $stmt->execute([$nombre_tipo, $naturaleza, $icono]);
        $tipo = $stmt->fetch();
        
        if ($tipo) {
            $tipo_id = $tipo['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES (?, ?, ?)");
            $stmt->execute([$nombre_tipo, $naturaleza, $icono]);
            $tipo_id = $pdo->lastInsertId();
        }
        
        // Insertar pago recurrente con nuevos campos
        $stmt = $pdo->prepare("
            INSERT INTO MOVIMIENTOS_RECURRENTES (cuenta_id, tipo_id, monto, dia_cargo, periodicidad, activo, fecha_especifica)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ");
        $stmt->execute([$cuenta_id, $tipo_id, $monto, $dia_cargo, $periodicidad, $fecha_especifica]);
        $mensaje_exito = "Pago recurrente añadido correctamente";
    }
}

// FILTROS
$filtro_cuenta = isset($_GET['cuenta']) ? (int)$_GET['cuenta'] : 0;
$filtro_periodicidad = isset($_GET['periodicidad']) ? $_GET['periodicidad'] : '';

// OBTENER CUENTAS DEL USUARIO
$stmt = $pdo->prepare("SELECT id, nombre FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// OBTENER PAGOS RECURRENTES CON FILTROS
$query = "
    SELECT 
        mr.id, mr.monto, mr.dia_cargo, mr.periodicidad, mr.activo,
        mr.fecha_especifica, mr.ultima_ejecucion, mr.proxima_ejecucion,
        tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
        c.nombre as cuenta_nombre
    FROM MOVIMIENTOS_RECURRENTES mr
    JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
    JOIN CUENTAS c ON mr.cuenta_id = c.id
    WHERE c.usuario_id = ?
";

$params = [$user_id];

if ($filtro_cuenta > 0) {
    $query .= " AND c.id = ?";
    $params[] = $filtro_cuenta;
}

if ($filtro_periodicidad != '') {
    $query .= " AND mr.periodicidad = ?";
    $params[] = $filtro_periodicidad;
}

$query .= " ORDER BY mr.activo DESC, mr.dia_cargo ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$recurrentes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Pagos Recurrentes | Vaulta</title>
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

        /* FILTERS */
        .filters-section {
            background: white;
            padding: 20px 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 13px;
            color: #4a5568;
            font-weight: 600;
        }

        .filter-group select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            color: #2d3748;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #007bff;
        }

        .btn-filter {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: auto;
        }

        .btn-filter:hover {
            background: #5568d3;
        }

        /* RECURRING PAYMENTS GRID */
        .recurring-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 20px;
            color: #2d3748;
        }

        .recurring-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .recurring-card {
            padding: 20px;
            background: #f7fafc;
            border-radius: 12px;
            border-left: 4px solid #007bff;
            position: relative;
            transition: all 0.3s;
        }

        .recurring-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .recurring-card.inactive {
            opacity: 0.6;
            border-left-color: #cbd5e0;
        }

        .card-header-rec {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .rec-type {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .rec-icon {
            width: 40px;
            height: 40px;
            background: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
            font-size: 18px;
        }

        .rec-name {
            font-weight: 700;
            color: #2d3748;
            font-size: 16px;
        }

        .rec-amount {
            font-size: 24px;
            font-weight: 700;
            margin: 10px 0;
        }

        .rec-amount.positive {
            color: #48bb78;
        }

        .rec-amount.negative {
            color: #f56565;
        }

        .rec-details {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
            color: #718096;
        }

        .rec-detail {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rec-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #007bff;
            color: white;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-toggle {
            flex: 1;
            padding: 8px;
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-toggle:hover {
            background: #cbd5e0;
        }

        .btn-toggle.active {
            background: #48bb78;
            color: white;
        }

        .btn-delete-rec {
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

        .btn-delete-rec:hover {
            background: #fc8181;
            color: white;
        }

        /* FLOATING ADD BUTTON */
        .btn-add-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: all 0.3s;
            z-index: 1000;
        }

        .btn-add-float:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
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
            max-height: 90vh;
            overflow-y: auto;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 25px;
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

        .btn-submit {
            padding: 12px 24px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* BANNER ESTIMADOR */
        .estimador-banner {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .estimador-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: pulse 3s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.3; }
        }

        .estimador-content {
            flex: 1;
            z-index: 1;
        }

        .estimador-content h3 {
            font-size: 20px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .estimador-content p {
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.5;
        }

        .estimador-actions {
            display: flex;
            gap: 10px;
            z-index: 1;
        }

        .btn-estimador {
            padding: 12px 24px;
            background: white;
            color: #007bff;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-estimador:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-dismiss {
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-dismiss:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* ENLACE PEQUEÑO ESTIMADOR */
        .estimador-link-small {
            text-align: center;
            padding: 20px;
            margin-top: 30px;
        }

        .estimador-link-small a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #f7fafc;
            border: 2px dashed #cbd5e0;
            border-radius: 8px;
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .estimador-link-small a:hover {
            background: #edf2f7;
            border-color: #007bff;
            transform: translateY(-2px);
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
                <li><a href="transferencias.php">Transferencias</a></li>
                <li class="active"><a href="recurrentes.php">Recurrentes</a></li>
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
                    <h1>Pagos Recurrentes</h1>
                    <p class="current-date">Gestiona tus suscripciones y pagos automáticos</p>
                </div>
            </header>

            <?php if (isset($mensaje_exito)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo esc($mensaje_exito); ?>
            </div>
            <?php endif; ?>

            <?php 
            // Mostrar banner del estimador solo si NO ha usado el estimador antes
            $mostrar_banner = !isset($_COOKIE['estimador_usado']);
            if ($mostrar_banner): 
            ?>
            <div class="estimador-banner" id="estimadorBanner">
                <div class="estimador-content">
                    <h3>
                        <i class="fas fa-calculator"></i>
                        ¡Calcula tus gastos automáticamente!
                    </h3>
                    <p>Usa nuestro estimador para planificar tus gastos mensuales por categorías y generar movimientos recurrentes automáticamente. Ahorra tiempo y mejora tu previsión financiera.</p>
                </div>
                <div class="estimador-actions">
                    <a href="estimador_gastos.php" class="btn-estimador">
                        <i class="fas fa-arrow-right"></i>
                        Ir al Estimador
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- FILTROS -->
            <form method="GET" class="filters-section">
                <div class="filter-group">
                    <label>Cuenta</label>
                    <select name="cuenta">
                        <option value="0">Todas las cuentas</option>
                        <?php foreach ($cuentas as $cuenta): ?>
                        <option value="<?php echo $cuenta['id']; ?>" <?php echo $filtro_cuenta == $cuenta['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cuenta['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Periodicidad</label>
                    <select name="periodicidad">
                        <option value="">Todas</option>
                        <option value="MENSUAL" <?php echo $filtro_periodicidad == 'MENSUAL' ? 'selected' : ''; ?>>Mensual</option>
                         <option value="SEMANAL" <?php echo $filtro_periodicidad == 'SEMANAL' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="MENSUAL" <?php echo $filtro_periodicidad == 'MENSUAL' ? 'selected' : ''; ?>>Mensual</option>
                        <option value="SEMESTRAL" <?php echo $filtro_periodicidad == 'SEMESTRAL' ? 'selected' : ''; ?>>Semestral</option>
                        <option value="ANUAL" <?php echo $filtro_periodicidad == 'ANUAL' ? 'selected' : ''; ?>>Anual</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </form>

            <!-- GRID DE PAGOS RECURRENTES -->
            <section class="recurring-section">
                <div class="section-header">
                    <h2>Todos los Pagos (<?php echo count($recurrentes); ?>)</h2>
                </div>

                <?php if (count($recurrentes) > 0): ?>
                <div class="recurring-grid">
                    <?php foreach ($recurrentes as $rec): ?>
                    <div class="recurring-card <?php echo $rec['activo'] ? '' : 'inactive'; ?>">
                        <div class="card-header-rec">
                            <div class="rec-type">
                                <div class="rec-icon">
                                    <i class="fas <?php echo htmlspecialchars($rec['icono']); ?>"></i>
                                </div>
                                <div>
                                    <div class="rec-name"><?php echo htmlspecialchars($rec['tipo_nombre']); ?></div>
                                    <small style="color: #718096;"><?php echo htmlspecialchars($rec['cuenta_nombre']); ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="rec-amount <?php echo $rec['naturaleza'] == 'INGRESO' ? 'positive' : 'negative'; ?>">
                            <?php echo $rec['naturaleza'] == 'INGRESO' ? '+' : '-'; ?> <?php echo number_format($rec['monto'], 2, ',', '.'); ?> €
                        </div>

                        <div class="rec-details">
                            <div class="rec-detail">
                                <i class="fas fa-calendar-day"></i>
                                <?php 
                                if ($rec['fecha_especifica']) {
                                    echo date('d/m/Y', strtotime($rec['fecha_especifica']));
                                } else {
                                    echo 'Día ' . $rec['dia_cargo'];
                                }
                                ?>
                            </div>
                            <div class="rec-detail">
                                <span class="rec-badge"><?php echo $rec['periodicidad']; ?></span>
                            </div>
                            <?php if ($rec['proxima_ejecucion']): ?>
                            <div class="rec-detail">
                                <i class="fas fa-clock"></i>
                                Próx: <?php echo date('d/m', strtotime($rec['proxima_ejecucion'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-actions">
                            <form method="POST" style="flex: 1;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="toggle_id" value="<?php echo $rec['id']; ?>">
                                <input type="hidden" name="nuevo_estado" value="<?php echo $rec['activo'] ? 0 : 1; ?>">
                                <button type="submit" class="btn-toggle <?php echo $rec['activo'] ? 'active' : ''; ?>">
                                    <i class="fas fa-<?php echo $rec['activo'] ? 'check' : 'times'; ?>"></i>
                                    <?php echo $rec['activo'] ? 'Activo' : 'Inactivo'; ?>
                                </button>
                            </form>

                            <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este pago recurrente?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="delete_id" value="<?php echo $rec['id']; ?>">
                                <button type="submit" class="btn-delete-rec">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
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

            <!-- ENLACE PEQUEÑO AL ESTIMADOR (visible después de usar el estimador) -->
            <?php if (isset($_COOKIE['estimador_usado'])): ?>
            <div class="estimador-link-small">
                <a href="estimador_gastos.php">
                    <i class="fas fa-calculator"></i>
                    Recalcular gastos
                </a>
            </div>
            <?php endif; ?>

        </main>
    </div>

    <!-- BOTÓN FLOTANTE AÑADIR -->
    <button class="btn-add-float" onclick="openModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- MODAL AÑADIR PAGO RECURRENTE -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-calendar-plus"></i> Añadir Pago Recurrente</h3>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_recurring" value="1">

                <div class="form-group">
                    <label>Cuenta</label>
                    <select name="cuenta_id" required>
                        <option value="">Selecciona una cuenta</option>
                        <?php foreach ($cuentas as $cuenta): ?>
                        <option value="<?php echo $cuenta['id']; ?>">
                            <?php echo htmlspecialchars($cuenta['nombre']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Tipo</label>
                    <select name="naturaleza" required>
                        <option value="">Selecciona el tipo</option>
                        <option value="INGRESO">💰 Ingreso</option>
                        <option value="GASTO">💸 Gasto</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Nombre del Pago</label>
                    <input type="text" name="nombre_tipo" placeholder="Ej: Netflix, Spotify, Alquiler..." required>
                </div>

                <div class="form-group">
                    <label>Icono</label>
                    <select name="icono" required>
                        <option value="">Selecciona un icono</option>
                        <optgroup label="Streaming">
                            <option value="fa-tv">📺 TV/Streaming</option>
                            <option value="fa-film">🎬 Cine</option>
                            <option value="fa-music">🎵 Música</option>
                        </optgroup>
                        <optgroup label="Casa">
                            <option value="fa-home">🏠 Alquiler</option>
                            <option value="fa-bolt">⚡ Electricidad</option>
                            <option value="fa-tint">💧 Agua</option>
                            <option value="fa-wifi">📶 Internet</option>
                            <option value="fa-phone">📞 Teléfono</option>
                        </optgroup>
                        <optgroup label="Transporte">
                            <option value="fa-car">🚗 Coche</option>
                            <option value="fa-bus">🚌 Transporte</option>
                        </optgroup>
                        <optgroup label="Salud">
                            <option value="fa-heartbeat">💓 Seguro Salud</option>
                            <option value="fa-dumbbell">🏋️ Gimnasio</option>
                        </optgroup>
                        <optgroup label="Otros">
                            <option value="fa-credit-card">💳 Tarjeta</option>
                            <option value="fa-briefcase">💼 Trabajo</option>
                            <option value="fa-graduation-cap">🎓 Educación</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label>Monto (€)</label>
                    <input type="number" name="monto" step="0.01" min="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>Periodicidad</label>
                    <select name="periodicidad" id="periodicidad" required onchange="updateDayField()">
                        <option value="">Selecciona periodicidad</option>
                        <option value="SEMANAL">Semanal</option>
                        <option value="MENSUAL">Mensual</option>
                        <option value="SEMESTRAL">Semestral (cada 6 meses)</option>
                        <option value="ANUAL">Anual (cada año)</option>
                    </select>
                </div>

                <!-- Campo para SEMANAL: Día de la semana -->
                <div class="form-group" id="field-dia-semana" style="display: none;">
                    <label>Día de la Semana</label>
                    <select name="dia_semana" id="dia_semana">
                        <option value="1">Lunes</option>
                        <option value="2">Martes</option>
                        <option value="3">Miércoles</option>
                        <option value="4">Jueves</option>
                        <option value="5">Viernes</option>
                        <option value="6">Sábado</option>
                        <option value="7">Domingo</option>
                    </select>
                </div>

                <!-- Campo para MENSUAL: Día del mes -->
                <div class="form-group" id="field-dia-mes" style="display: none;">
                    <label>Día del Mes (1-31)</label>
                    <input type="number" name="dia_cargo" id="dia_cargo" min="1" max="31" placeholder="15">
                </div>

                <!-- Campo para SEMESTRAL/ANUAL: Fecha específica con calendario -->
                <div class="form-group" id="field-fecha-especifica" style="display: none;">
                    <label>Fecha del Año <small>(día y mes del pago)</small></label>
                    <input type="date" name="fecha_especifica" id="fecha_especifica" placeholder="Selecciona una fecha">
                    <small style="color: #718096; font-size: 12px; display: block; margin-top: 8px;">
                        📅 El año se ignorará, solo importa el día y mes. Ej: Si introduces 15 de marzo, el pago se repetirá cada 15 de marzo.
                    </small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal()">Cancelar</button>
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('addModal').classList.remove('show');
            // Reset form
            document.getElementById('periodicidad').value = '';
            updateDayField();
        }

        function updateDayField() {
            const periodicidad = document.getElementById('periodicidad').value;
            const fieldDiaSemana = document.getElementById('field-dia-semana');
            const fieldDiaMes = document.getElementById('field-dia-mes');
            const fieldFechaEspecifica = document.getElementById('field-fecha-especifica');
            const diaSemana = document.getElementById('dia_semana');
            const diaCargo = document.getElementById('dia_cargo');
            const fechaEspecifica = document.getElementById('fecha_especifica');

            // Ocultar todos los campos primero
            fieldDiaSemana.style.display = 'none';
            fieldDiaMes.style.display = 'none';
            fieldFechaEspecifica.style.display = 'none';
            
            // Remover required de todos
            diaSemana.removeAttribute('required');
            diaCargo.removeAttribute('required');
            fechaEspecifica.removeAttribute('required');

            // Mostrar el campo apropiado según periodicidad
            if (periodicidad === 'SEMANAL') {
                fieldDiaSemana.style.display = 'block';
                diaSemana.setAttribute('required', 'required');
            } else if (periodicidad === 'MENSUAL') {
                fieldDiaMes.style.display = 'block';
                diaCargo.setAttribute('required', 'required');
            } else if (periodicidad === 'SEMESTRAL' || periodicidad === 'ANUAL') {
                fieldFechaEspecifica.style.display = 'block';
                fechaEspecifica.setAttribute('required', 'required');
            }
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>

<script src="dark-mode.js"></script>
</body>
</html>
