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

// 1. OBTENER CUENTAS DEL USUARIO Y CALCULAR SALDO ACTUAL
$stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$user_id]);
$cuentas = $stmt->fetchAll();

// Calcular saldo actual de cada cuenta
$saldo_total_actual = 0;
foreach ($cuentas as &$cuenta) {
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
    $saldo_total_actual += $cuenta['saldo_actual'];
}
unset($cuenta);

// 2. OBTENER MOVIMIENTOS RECURRENTES ACTIVOS (SEPARAR INGRESOS Y GASTOS)
$stmt = $pdo->prepare("
    SELECT 
        mr.monto, mr.periodicidad, mr.dia_cargo,
        tt.nombre as tipo_nombre, tt.icono, tt.naturaleza,
        c.nombre as cuenta_nombre
    FROM MOVIMIENTOS_RECURRENTES mr
    JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
    JOIN CUENTAS c ON mr.cuenta_id = c.id
    WHERE c.usuario_id = ? AND mr.activo = 1
    ORDER BY tt.naturaleza DESC, mr.monto DESC
");
$stmt->execute([$user_id]);
$recurrentes = $stmt->fetchAll();

// 3. CALCULAR IMPACTO MENSUAL DE RECURRENTES (SEPARANDO INGRESOS Y GASTOS)
$ingreso_mensual_total = 0;
$gasto_mensual_total = 0;

foreach ($recurrentes as $rec) {
    $monto_mensual = 0;
    
    switch ($rec['periodicidad']) {
        case 'MENSUAL':
            $monto_mensual = $rec['monto'];
            break;
        case 'SEMANAL':
            $monto_mensual = $rec['monto'] * 4; // Aproximadamente 4 semanas por mes
            break;
        case 'ANUAL':
            $monto_mensual = $rec['monto'] / 12;
            break;
    }
    
    if ($rec['naturaleza'] == 'INGRESO') {
        $ingreso_mensual_total += $monto_mensual;
    } else {
        $gasto_mensual_total += $monto_mensual;
    }
}

$balance_mensual_neto = $ingreso_mensual_total - $gasto_mensual_total;

// 4. PROYECTAR SALDO PARA LOS PRÓXIMOS 6 MESES
$proyecciones = [];
$saldo_proyectado = $saldo_total_actual;

for ($i = 1; $i <= 6; $i++) {
    $fecha = new DateTime();
    $fecha->modify("+$i month");
    
    $saldo_proyectado += $balance_mensual_neto;
    
    $proyecciones[] = [
        'mes' => $fecha->format('F Y'),
        'mes_corto' => $fecha->format('M Y'),
        'saldo' => $saldo_proyectado,
        'ingreso' => $ingreso_mensual_total,
        'gasto' => $gasto_mensual_total,
        'balance' => $balance_mensual_neto,
        'alerta' => $saldo_proyectado < 0
    ];
}

// Traducir meses al español
$meses_es = [
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
    'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
    'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
    'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre',
    'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
    'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago', 'Sep' => 'Sep',
    'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
];

foreach ($proyecciones as &$proy) {
    $proy['mes'] = str_replace(array_keys($meses_es), array_values($meses_es), $proy['mes']);
    $proy['mes_corto'] = str_replace(array_keys($meses_es), array_values($meses_es), $proy['mes_corto']);
}
unset($proy);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Previsión Mensual | Vaulta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
            color: white;
        }

        .stat-card.success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .stat-label {
            font-size: 14px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-card:not(.primary):not(.danger) .stat-label {
            color: #718096;
        }

        .stat-card:not(.primary):not(.danger) .stat-value {
            color: #2d3748;
        }

        /* SECTIONS */
        .section {
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

        /* PROYECCIONES */
        .proyecciones-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .proyeccion-card {
            padding: 20px;
            background: #f7fafc;
            border-radius: 10px;
            border-left: 4px solid #007bff;
            transition: all 0.3s;
        }

        .proyeccion-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .proyeccion-card.alerta {
            border-left-color: #f56565;
            background: #fff5f5;
        }

        .proyeccion-mes {
            font-size: 16px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .proyeccion-saldo {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .proyeccion-saldo.positivo {
            color: #48bb78;
        }

        .proyeccion-saldo.negativo {
            color: #f56565;
        }

        .proyeccion-gasto {
            font-size: 13px;
            color: #718096;
        }

        .proyeccion-gasto strong {
            color: #f56565;
        }

        /* RECURRENTES LIST */
        .recurrentes-list {
            display: grid;
            gap: 12px;
        }

        .recurrente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border-left: 3px solid #007bff;
        }

        .recurrente-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .recurrente-icon {
            width: 40px;
            height: 40px;
            background: #edf2f7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007bff;
            font-size: 18px;
        }

        .recurrente-details h4 {
            font-size: 15px;
            color: #2d3748;
            margin-bottom: 3px;
        }

        .recurrente-badge {
            display: inline-block;
            padding: 3px 8px;
            background: #007bff;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .recurrente-monto {
            font-size: 18px;
            font-weight: 700;
        }

        .recurrente-monto.ingreso {
            color: #48bb78;
        }

        .recurrente-monto.gasto {
            color: #f56565;
        }

        .recurrente-item.ingreso {
            border-left-color: #48bb78;
        }

        .alert-warning {
            background: #fffaf0;
            border: 1px solid #fbd38d;
            color: #744210;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-warning i {
            font-size: 20px;
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

        /* GRÁFICO */
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .chart-container {
                height: 300px;
            }
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
                <li class="active"><a href="prevision.php">Previsión</a></li>
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
                    <h1><i class="fas fa-chart-line"></i> Previsión Mensual</h1>
                    <p class="current-date">Proyección basada en ingresos y gastos recurrentes</p>
                </div>
            </header>

            <!-- ALERTA SI HAY SALDO NEGATIVO EN ALGÚN MES -->
            <?php 
            $hay_alerta = false;
            foreach ($proyecciones as $proy) {
                if ($proy['alerta']) {
                    $hay_alerta = true;
                    break;
                }
            }
            if ($hay_alerta): 
            ?>
            <div class="alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>¡Atención!</strong> Tu saldo proyectado será negativo en uno o más meses. Considera ajustar tus gastos recurrentes.
                </div>
            </div>
            <?php endif; ?>

            <!-- ESTADÍSTICAS ACTUALES -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-label"><i class="fas fa-wallet"></i> Saldo Actual Total</div>
                    <div class="stat-value"><?php echo number_format($saldo_total_actual, 2, ',', '.'); ?> €</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label"><i class="fas fa-arrow-up"></i> Ingresos Recurrentes/Mes</div>
                    <div class="stat-value">+<?php echo number_format($ingreso_mensual_total, 2, ',', '.'); ?> €</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label"><i class="fas fa-arrow-down"></i> Gastos Recurrentes/Mes</div>
                    <div class="stat-value">-<?php echo number_format($gasto_mensual_total, 2, ',', '.'); ?> €</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label"><i class="fas fa-balance-scale"></i> Balance Mensual Neto</div>
                    <div class="stat-value" style="color: <?php echo $balance_mensual_neto >= 0 ? '#48bb78' : '#f56565'; ?>;">
                        <?php echo $balance_mensual_neto >= 0 ? '+' : ''; ?><?php echo number_format($balance_mensual_neto, 2, ',', '.'); ?> €
                    </div>
                </div>
            </div>

            <!-- PROYECCIONES MENSUALES -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-crystal-ball"></i> Proyección Próximos 6 Meses</h2>
                
                <!-- GRÁFICO DE PROYECCIÓN -->
                <div class="chart-container">
                    <canvas id="proyeccionChart"></canvas>
                </div>

                <div class="proyecciones-grid">
                    <?php foreach ($proyecciones as $proy): ?>
                    <div class="proyeccion-card <?php echo $proy['alerta'] ? 'alerta' : ''; ?>">
                        <div class="proyeccion-mes">
                            <i class="fas fa-calendar"></i> <?php echo $proy['mes']; ?>
                        </div>
                        <div class="proyeccion-saldo <?php echo $proy['saldo'] >= 0 ? 'positivo' : 'negativo'; ?>">
                            <?php echo number_format($proy['saldo'], 2, ',', '.'); ?> €
                        </div>
                        <div class="proyeccion-gasto">
                            <span style="color: #48bb78;">+<?php echo number_format($proy['ingreso'], 2, ',', '.'); ?> €</span> • 
                            <span style="color: #f56565;">-<?php echo number_format($proy['gasto'], 2, ',', '.'); ?> €</span> = 
                            <strong style="color: <?php echo $proy['balance'] >= 0 ? '#48bb78' : '#f56565'; ?>;">
                                <?php echo $proy['balance'] >= 0 ? '+' : ''; ?><?php echo number_format($proy['balance'], 2, ',', '.'); ?> €
                            </strong>
                        </div>
                        <?php if ($proy['alerta']): ?>
                        <div style="margin-top: 10px; color: #c53030; font-size: 13px; font-weight: 600;">
                            <i class="fas fa-exclamation-circle"></i> Saldo negativo
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- DESGLOSE DE MOVIMIENTOS RECURRENTES -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-list-ul"></i> Desglose de Movimientos Recurrentes</h2>
                
                <?php if (count($recurrentes) > 0): ?>
                <div class="recurrentes-list">
                    <?php foreach ($recurrentes as $rec): ?>
                    <div class="recurrente-item <?php echo strtolower($rec['naturaleza']); ?>">
                        <div class="recurrente-info">
                            <div class="recurrente-icon">
                                <i class="fas <?php echo htmlspecialchars($rec['icono']); ?>"></i>
                            </div>
                            <div class="recurrente-details">
                                <h4><?php echo htmlspecialchars($rec['tipo_nombre']); ?></h4>
                                <span class="recurrente-badge" style="background: <?php echo $rec['naturaleza'] == 'INGRESO' ? '#48bb78' : '#007bff'; ?>;">
                                    <?php echo htmlspecialchars($rec['periodicidad']); ?>
                                </span>
                                <small style="color: #718096; margin-left: 8px;">
                                    <?php echo htmlspecialchars($rec['cuenta_nombre']); ?> • Día <?php echo $rec['dia_cargo']; ?>
                                </small>
                            </div>
                        </div>
                        <div class="recurrente-monto <?php echo strtolower($rec['naturaleza']); ?>">
                            <?php echo $rec['naturaleza'] == 'INGRESO' ? '+' : '-'; ?><?php echo number_format($rec['monto'], 2, ',', '.'); ?> €
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <p>No hay movimientos recurrentes configurados</p>
                </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        // Datos para el gráfico
        const labels = [
            'Actual',
            <?php foreach ($proyecciones as $proy): ?>
            '<?php echo $proy['mes_corto']; ?>',
            <?php endforeach; ?>
        ];

        const saldos = [
            <?php echo $saldo_total_actual; ?>,
            <?php foreach ($proyecciones as $proy): ?>
            <?php echo $proy['saldo']; ?>,
            <?php endforeach; ?>
        ];

        // Crear gradiente para la línea
        const ctx = document.getElementById('proyeccionChart').getContext('2d');
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(0, 123, 255, 0.3)');
        gradient.addColorStop(1, 'rgba(0, 123, 255, 0.05)');

        // Configurar el gráfico
        const config = {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Saldo Proyectado (€)',
                    data: saldos,
                    borderColor: '#007bff',
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 6,
                    pointHoverRadius: 8,
                    pointBackgroundColor: saldos.map(s => s >= 0 ? '#48bb78' : '#f56565'),
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointHoverBackgroundColor: '#007bff',
                    pointHoverBorderColor: '#fff',
                    pointHoverBorderWidth: 3,
                    segment: {
                        borderColor: ctx => {
                            const curr = ctx.p1.parsed.y;
                            return curr < 0 ? '#f56565' : '#007bff';
                        }
                    }
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: {
                                size: 14,
                                family: "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"
                            },
                            padding: 15,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        },
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                const value = context.parsed.y;
                                label += new Intl.NumberFormat('es-ES', {
                                    style: 'currency',
                                    currency: 'EUR'
                                }).format(value);
                                
                                if (value < 0) {
                                    label += ' ⚠️ Saldo negativo';
                                }
                                return label;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12
                            },
                            callback: function(value) {
                                return new Intl.NumberFormat('es-ES', {
                                    style: 'currency',
                                    currency: 'EUR',
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                }).format(value);
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        };

        // Crear el gráfico
        const proyeccionChart = new Chart(ctx, config);
    </script>

</body>
</html>
