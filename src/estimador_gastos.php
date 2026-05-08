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

// Obtener cuentas del usuario
$stmt = $pdo->prepare("SELECT id, nombre FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// Obtener tipos de transacción de tipo GASTO
$stmt = $pdo->prepare("SELECT id, nombre, icono FROM TIPOS_TRANSACCION WHERE naturaleza = 'GASTO' ORDER BY nombre");
$stmt->execute();
$tipos_gasto = $stmt->fetchAll();

// Categorías predefinidas para GASTOS
$categorias_gastos = [
    ['nombre' => 'Alimentación', 'icono' => 'fa-utensils', 'placeholder' => 'Ej: 300'],
    ['nombre' => 'Transporte', 'icono' => 'fa-car', 'placeholder' => 'Ej: 100'],
    ['nombre' => 'Vivienda (Alquiler/Hipoteca)', 'icono' => 'fa-home', 'placeholder' => 'Ej: 600'],
    ['nombre' => 'Servicios (Luz, Agua, Gas)', 'icono' => 'fa-bolt', 'placeholder' => 'Ej: 150'],
    ['nombre' => 'Telecomunicaciones', 'icono' => 'fa-wifi', 'placeholder' => 'Ej: 50'],
    ['nombre' => 'Ocio y Entretenimiento', 'icono' => 'fa-gamepad', 'placeholder' => 'Ej: 80'],
    ['nombre' => 'Ropa y Calzado', 'icono' => 'fa-shirt', 'placeholder' => 'Ej: 60'],
    ['nombre' => 'Salud y Farmacia', 'icono' => 'fa-heart-pulse', 'placeholder' => 'Ej: 40'],
    ['nombre' => 'Educación', 'icono' => 'fa-graduation-cap', 'placeholder' => 'Ej: 100'],
    ['nombre' => 'Otros Gastos', 'icono' => 'fa-ellipsis', 'placeholder' => 'Ej: 50']
];

// Categorías predefinidas para INGRESOS
$categorias_ingresos = [
    ['nombre' => 'Nómina', 'icono' => 'fa-money-bill-wave', 'placeholder' => 'Ej: 1500'],
    ['nombre' => 'Paga Extra', 'icono' => 'fa-gift', 'placeholder' => 'Ej: 1500'],
    ['nombre' => 'Freelance/Autónomo', 'icono' => 'fa-laptop', 'placeholder' => 'Ej: 800'],
    ['nombre' => 'Inversiones/Dividendos', 'icono' => 'fa-chart-line', 'placeholder' => 'Ej: 100'],
    ['nombre' => 'Alquiler de Propiedad', 'icono' => 'fa-building', 'placeholder' => 'Ej: 500'],
    ['nombre' => 'Ayudas/Subsidios', 'icono' => 'fa-hand-holding-dollar', 'placeholder' => 'Ej: 300'],
    ['nombre' => 'Pensión', 'icono' => 'fa-user-clock', 'placeholder' => 'Ej: 900'],
    ['nombre' => 'Otros Ingresos', 'icono' => 'fa-plus-circle', 'placeholder' => 'Ej: 200']
];

// Capturar mensajes de sesión
$mensaje_error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Estimador Financiero | Vaulta</title>
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

        /* ── SIDEBAR ─────────────────────────────────── */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #002366 0%, #007bff 100%);
            color: white;
            padding: 30px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            flex-shrink: 0;
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

        .nav-links li { margin: 5px 0; }

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

        .btn-logout:hover { background: rgba(255,255,255,0.3); }

        /* ── MAIN CONTENT ────────────────────────────── */
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

        .subtitle {
            color: #718096;
            font-size: 14px;
        }

        /* FORM SECTION */
        .form-section {
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

        .info-box {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .info-box h3 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .info-box p {
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.6;
        }

        /* CATEGORIAS GRID */
        .categorias-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .categoria-item {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }

        .categoria-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .categoria-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .categoria-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            flex-shrink: 0;
        }

        .categoria-item.ingreso .categoria-icon {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .categoria-item.ingreso {
            border-left-color: #48bb78;
        }

        .categoria-nombre {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
            line-height: 1.3;
        }

        .input-group {
            position: relative;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
            background: white;
            color: #2d3748;
        }

        .input-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        .input-group .currency {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
            font-weight: 600;
            pointer-events: none;
        }

        /* CUENTA SELECTOR */
        .cuenta-selector {
            margin-bottom: 30px;
        }

        .cuenta-selector label {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .cuenta-selector select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            color: #2d3748;
            cursor: pointer;
            transition: all 0.3s;
        }

        .cuenta-selector select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }

        /* RESUMEN */
        .resumen-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .resumen-box {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .resumen-box.gastos {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
        }

        .resumen-box.ingresos {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        }

        .resumen-box.balance {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
        }

        .resumen-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .resumen-total {
            font-size: 36px;
            font-weight: 700;
        }

        /* BUTTONS */
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-warning {
            background: #fffaf0;
            border: 1px solid #fbd38d;
            color: #744210;
        }

        .alert-info {
            background: #ebf8ff;
            border: 1px solid #90cdf4;
            color: #2c5282;
        }

        .alert-error {
            background: #fff5f5;
            border: 1px solid #fc8181;
            color: #c53030;
        }

        /* ── RESPONSIVE ──────────────────────────────── */
        @media (max-width: 768px) {
            .sidebar {
                display: none;
            }
            .main-content {
                padding: 15px;
            }
            .resumen-total {
                font-size: 26px;
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
                    <h1><i class="fas fa-calculator"></i> Estimador Financiero Mensual</h1>
                    <p class="subtitle">Planifica tus ingresos y gastos, genera movimientos recurrentes automáticamente</p>
                </div>
            </header>

            <?php if ($mensaje_error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><?php echo htmlspecialchars($mensaje_error); ?></div>
            </div>
            <?php endif; ?>

            <div class="info-box">
                <h3><i class="fas fa-lightbulb"></i> ¿Cómo funciona?</h3>
                <p>Introduce tus ingresos y gastos estimados mensuales en cada categoría. El sistema calculará los totales y el balance, y creará automáticamente movimientos recurrentes mensuales para cada categoría con valor. Esto te ayudará a tener una previsión más precisa de tus finanzas.</p>
            </div>

            <?php if (count($cuentas) == 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>No tienes cuentas creadas.</strong> Primero debes crear una cuenta desde tu perfil para poder usar el estimador.
                </div>
            </div>
            <?php else: ?>

            <form id="estimadorForm" action="procesar_estimador.php" method="POST">

                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-wallet"></i> Selecciona la Cuenta</h2>

                    <div class="cuenta-selector">
                        <label for="cuenta_id">¿En qué cuenta se registrarán estos gastos?</label>
                        <select name="cuenta_id" id="cuenta_id" required>
                            <option value="">-- Selecciona una cuenta --</option>
                            <?php foreach ($cuentas as $cuenta): ?>
                            <option value="<?php echo $cuenta['id']; ?>"><?php echo htmlspecialchars($cuenta['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-list-check"></i> Estima tus Gastos Mensuales</h2>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>Solo completa las categorías que apliquen a tu situación. Deja en blanco o en 0 las que no uses.</div>
                    </div>

                    <div class="categorias-grid">
                        <?php foreach ($categorias_gastos as $index => $cat): ?>
                        <div class="categoria-item">
                            <div class="categoria-header">
                                <div class="categoria-icon">
                                    <i class="fas <?php echo $cat['icono']; ?>"></i>
                                </div>
                                <div class="categoria-nombre"><?php echo $cat['nombre']; ?></div>
                            </div>
                            <div class="input-group">
                                <input
                                    type="number"
                                    name="gastos[<?php echo $index; ?>][monto]"
                                    class="gasto-monto"
                                    step="0.01"
                                    min="0"
                                    placeholder="<?php echo $cat['placeholder']; ?>"
                                    data-categoria="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                >
                                <span class="currency">€</span>
                                <input type="hidden" name="gastos[<?php echo $index; ?>][nombre]" value="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                <input type="hidden" name="gastos[<?php echo $index; ?>][icono]" value="<?php echo $cat['icono']; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- SECCIÓN DE INGRESOS -->
                <div class="form-section">
                    <h2 class="section-title"><i class="fas fa-arrow-trend-up"></i> Estima tus Ingresos Mensuales</h2>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>Introduce tus ingresos recurrentes mensuales. Esto te ayudará a tener una visión completa de tu balance financiero.</div>
                    </div>

                    <div class="categorias-grid">
                        <?php foreach ($categorias_ingresos as $index => $cat): ?>
                        <div class="categoria-item ingreso">
                            <div class="categoria-header">
                                <div class="categoria-icon">
                                    <i class="fas <?php echo $cat['icono']; ?>"></i>
                                </div>
                                <div class="categoria-nombre"><?php echo $cat['nombre']; ?></div>
                            </div>
                            <div class="input-group">
                                <input
                                    type="number"
                                    name="ingresos[<?php echo $index; ?>][monto]"
                                    class="ingreso-monto"
                                    step="0.01"
                                    min="0"
                                    placeholder="<?php echo $cat['placeholder']; ?>"
                                    data-categoria="<?php echo htmlspecialchars($cat['nombre']); ?>"
                                >
                                <span class="currency">€</span>
                                <input type="hidden" name="ingresos[<?php echo $index; ?>][nombre]" value="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                <input type="hidden" name="ingresos[<?php echo $index; ?>][icono]" value="<?php echo $cat['icono']; ?>">
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- RESUMEN EN TIEMPO REAL -->
                <div class="resumen-grid">
                    <div class="resumen-box ingresos">
                        <div class="resumen-label"><i class="fas fa-arrow-up"></i> Total Ingresos</div>
                        <div class="resumen-total" id="totalIngresos">0,00 €</div>
                    </div>
                    <div class="resumen-box gastos">
                        <div class="resumen-label"><i class="fas fa-arrow-down"></i> Total Gastos</div>
                        <div class="resumen-total" id="totalGastos">0,00 €</div>
                    </div>
                    <div class="resumen-box balance">
                        <div class="resumen-label"><i class="fas fa-balance-scale"></i> Balance Mensual</div>
                        <div class="resumen-total" id="balanceMensual">0,00 €</div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-rotate-left"></i> Limpiar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Generar Movimientos Recurrentes
                    </button>
                </div>

            </form>

            <?php endif; ?>

        </main>
    </div>

    <script>
        // Calcular totales en tiempo real
        const inputsGastos = document.querySelectorAll('.gasto-monto');
        const inputsIngresos = document.querySelectorAll('.ingreso-monto');
        const totalIngresosElement = document.getElementById('totalIngresos');
        const totalGastosElement = document.getElementById('totalGastos');
        const balanceMensualElement = document.getElementById('balanceMensual');

        function calcularTotales() {
            let totalGastos = 0;
            let totalIngresos = 0;

            inputsGastos.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                totalGastos += valor;
            });

            inputsIngresos.forEach(input => {
                const valor = parseFloat(input.value) || 0;
                totalIngresos += valor;
            });

            const balance = totalIngresos - totalGastos;

            totalIngresosElement.textContent = totalIngresos.toLocaleString('es-ES', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' €';

            totalGastosElement.textContent = totalGastos.toLocaleString('es-ES', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' €';

            balanceMensualElement.textContent = balance.toLocaleString('es-ES', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' €';

            // Colorear el balance según sea positivo o negativo
            if (balance < 0) {
                balanceMensualElement.closest('.resumen-box').style.background = 'linear-gradient(135deg, #f56565 0%, #c53030 100%)';
            } else {
                balanceMensualElement.closest('.resumen-box').style.background = 'linear-gradient(135deg, #002366 0%, #007bff 100%)';
            }
        }

        inputsGastos.forEach(input => {
            input.addEventListener('input', calcularTotales);
        });

        inputsIngresos.forEach(input => {
            input.addEventListener('input', calcularTotales);
        });

        function resetForm() {
            document.getElementById('estimadorForm').reset();
            calcularTotales();
        }

        // Validación antes de enviar
        document.getElementById('estimadorForm').addEventListener('submit', function(e) {
            let hayAlgunValor = false;

            inputsGastos.forEach(input => {
                if (parseFloat(input.value) > 0) hayAlgunValor = true;
            });

            inputsIngresos.forEach(input => {
                if (parseFloat(input.value) > 0) hayAlgunValor = true;
            });

            if (!hayAlgunValor) {
                e.preventDefault();
                alert('Por favor, introduce al menos un ingreso o gasto en alguna categoría.');
                return false;
            }

            const cuenta = document.getElementById('cuenta_id').value;
            if (!cuenta) {
                e.preventDefault();
                alert('Por favor, selecciona una cuenta.');
                return false;
            }

            return confirm('¿Estás seguro de que deseas crear estos movimientos recurrentes? Se generarán automáticamente en tu cuenta.');
        });
    </script>

<script src="dark-mode.js"></script>
</body>
</html>
