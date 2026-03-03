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

// Detectar error CSRF (redirección desde validate_csrf_token)
$mensaje_error = csrf_error_message();

// MANEJO DE ELIMINACIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    validate_csrf_token();
    $delete_id = (int)$_POST['delete_id'];
    
    // Verificar que el movimiento pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT m.id FROM MOVIMIENTOS m
        JOIN CUENTAS c ON m.cuenta_id = c.id
        WHERE m.id = ? AND c.usuario_id = ?
    ");
    $stmt->execute([$delete_id, $user_id]);
    
    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM MOVIMIENTOS WHERE id = ?");
        $stmt->execute([$delete_id]);
        $mensaje_exito = "Movimiento eliminado correctamente";
    }
}

// MANEJO DE ADICIÓN
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_movement'])) {
    validate_csrf_token();
    $cuenta_id = (int)$_POST['cuenta_id'];
    $naturaleza = $_POST['naturaleza']; // INGRESO o GASTO
    $nombre_tipo = trim($_POST['nombre_tipo']);
    $icono = $_POST['icono'];
    $monto = (float)$_POST['monto'];
    $fecha = $_POST['fecha'];
    $descripcion = trim($_POST['descripcion']);
    
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
            // Crear nuevo tipo
            $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES (?, ?, ?)");
            $stmt->execute([$nombre_tipo, $naturaleza, $icono]);
            $tipo_id = $pdo->lastInsertId();
        }
        
        // Insertar movimiento
        $stmt = $pdo->prepare("
            INSERT INTO MOVIMIENTOS (cuenta_id, tipo_id, monto, fecha, descripcion)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$cuenta_id, $tipo_id, $monto, $fecha, $descripcion]);
        $mensaje_exito = "Movimiento añadido correctamente";
    }
}

// FILTROS
$filtro_cuenta = isset($_GET['cuenta']) ? (int)$_GET['cuenta'] : 0;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

// OBTENER CUENTAS DEL USUARIO
$stmt = $pdo->prepare("SELECT id, nombre FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// OBTENER TIPOS DE TRANSACCIÓN
$stmt = $pdo->query("SELECT id, nombre, naturaleza, icono FROM TIPOS_TRANSACCION ORDER BY nombre");
$tipos = $stmt->fetchAll();

// OBTENER MOVIMIENTOS CON FILTROS
$query = "
    SELECT 
        m.id, m.fecha, m.monto, m.descripcion,
        tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
        c.nombre as cuenta_nombre
    FROM MOVIMIENTOS m
    JOIN TIPOS_TRANSACCION tt ON m.tipo_id = tt.id
    JOIN CUENTAS c ON m.cuenta_id = c.id
    WHERE c.usuario_id = ?
";

$params = [$user_id];

if ($filtro_cuenta > 0) {
    $query .= " AND c.id = ?";
    $params[] = $filtro_cuenta;
}

if ($filtro_tipo != '') {
    $query .= " AND tt.naturaleza = ?";
    $params[] = $filtro_tipo;
}

$query .= " ORDER BY m.fecha DESC, m.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Movimientos | Vaulta</title>
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

        /* MOVEMENTS TABLE */
        .movements-section {
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

        .movements-table {
            width: 100%;
            border-collapse: collapse;
        }

        .movements-table th {
            text-align: left;
            padding: 12px;
            background: #f7fafc;
            color: #4a5568;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .movements-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .movements-table tr:hover {
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

        .amount {
            font-weight: 600;
        }

        .amount.positive {
            color: #48bb78;
        }

        .amount.negative {
            color: #f56565;
        }

        .btn-delete {
            padding: 6px 12px;
            background: #fed7d7;
            color: #c53030;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-delete:hover {
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
            padding: 20px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
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
                <li class="active"><a href="movimientos.php">Movimientos</a></li>
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
                    <h1>Movimientos</h1>
                    <p class="current-date">Gestiona tus ingresos y gastos</p>
                </div>
            </header>

            <?php if (isset($mensaje_exito)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo esc($mensaje_exito); ?>
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
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">Todos</option>
                        <option value="INGRESO" <?php echo $filtro_tipo == 'INGRESO' ? 'selected' : ''; ?>>Ingresos</option>
                        <option value="GASTO" <?php echo $filtro_tipo == 'GASTO' ? 'selected' : ''; ?>>Gastos</option>
                    </select>
                </div>

                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Filtrar
                </button>
            </form>

            <!-- TABLA DE MOVIMIENTOS -->
            <section class="movements-section">
                <div class="section-header">
                    <h2>Todos los Movimientos (<?php echo count($movimientos); ?>)</h2>
                </div>

                <?php if (count($movimientos) > 0): ?>
                <table class="movements-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Cuenta</th>
                            <th>Monto</th>
                            <th>Acciones</th>
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
                            <td style="color: #718096;"><?php echo htmlspecialchars($mov['cuenta_nombre']); ?></td>
                            
                            <td class="amount <?php echo $mov['naturaleza'] == 'INGRESO' ? 'positive' : 'negative'; ?>">
                                <?php echo $mov['naturaleza'] == 'INGRESO' ? '+' : '-'; ?> <?php echo number_format($mov['monto'], 2, ',', '.'); ?> €
                            </td>

                            <td>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('¿Eliminar este movimiento?');">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="delete_id" value="<?php echo $mov['id']; ?>">
                                    <button type="submit" class="btn-delete">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </form>
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

        </main>
    </div>

    <!-- BOTÓN FLOTANTE AÑADIR -->
    <button class="btn-add-float" onclick="openModal()">
        <i class="fas fa-plus"></i>
    </button>

    <!-- MODAL AÑADIR MOVIMIENTO -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Añadir Movimiento</h3>
            </div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="add_movement" value="1">

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
                    <label>Nombre del Movimiento</label>
                    <input type="text" name="nombre_tipo" placeholder="Ej: Nómina, Supermercado, Netflix..." required>
                </div>

                <div class="form-group">
                    <label>Icono</label>
                    <select name="icono" required>
                        <option value="">Selecciona un icono</option>
                        <optgroup label="Dinero">
                            <option value="fa-money-bill-wave">💵 Dinero</option>
                            <option value="fa-coins">🪙 Monedas</option>
                            <option value="fa-wallet">👛 Cartera</option>
                            <option value="fa-credit-card">💳 Tarjeta</option>
                        </optgroup>
                        <optgroup label="Trabajo">
                            <option value="fa-briefcase">💼 Trabajo</option>
                            <option value="fa-building">🏢 Empresa</option>
                            <option value="fa-chart-line">📈 Inversión</option>
                        </optgroup>
                        <optgroup label="Comida">
                            <option value="fa-shopping-cart">🛒 Supermercado</option>
                            <option value="fa-utensils">🍴 Restaurante</option>
                            <option value="fa-coffee">☕ Café</option>
                            <option value="fa-pizza-slice">🍕 Comida</option>
                        </optgroup>
                        <optgroup label="Transporte">
                            <option value="fa-car">🚗 Coche</option>
                            <option value="fa-bus">🚌 Transporte</option>
                            <option value="fa-gas-pump">⛽ Gasolina</option>
                        </optgroup>
                        <optgroup label="Casa">
                            <option value="fa-home">🏠 Casa</option>
                            <option value="fa-bolt">⚡ Electricidad</option>
                            <option value="fa-tint">💧 Agua</option>
                            <option value="fa-wifi">📶 Internet</option>
                        </optgroup>
                        <optgroup label="Entretenimiento">
                            <option value="fa-film">🎬 Cine</option>
                            <option value="fa-gamepad">🎮 Juegos</option>
                            <option value="fa-music">🎵 Música</option>
                            <option value="fa-tv">📺 Streaming</option>
                        </optgroup>
                        <optgroup label="Salud">
                            <option value="fa-heartbeat">💓 Salud</option>
                            <option value="fa-pills">💊 Farmacia</option>
                            <option value="fa-dumbbell">🏋️ Gimnasio</option>
                        </optgroup>
                        <optgroup label="Otros">
                            <option value="fa-shopping-bag">🛍️ Compras</option>
                            <option value="fa-gift">🎁 Regalo</option>
                            <option value="fa-plane">✈️ Viaje</option>
                            <option value="fa-book">📚 Educación</option>
                            <option value="fa-paw">🐾 Mascotas</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group">
                    <label>Monto (€)</label>
                    <input type="number" name="monto" step="0.01" min="0.01" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>Fecha</label>
                    <input type="date" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Descripción</label>
                    <textarea name="descripcion" rows="3" placeholder="Describe el movimiento..." required></textarea>
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
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>

</body>
</html>
