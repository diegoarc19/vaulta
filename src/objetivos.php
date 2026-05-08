<?php
session_start();

// Verificar si el usuario estÃ¡ logueado
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.html');
    exit();
}

require_once 'conexion.php';

// Obtener informaciÃ³n del usuario
$usuario_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT nombre FROM USUARIOS WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

// Obtener cuentas del usuario y calcular saldo total
$stmt = $pdo->prepare("SELECT id, nombre, saldo_inicial FROM CUENTAS WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$cuentas = $stmt->fetchAll();

// Calcular saldo actual de cada cuenta
$saldo_total = 0;
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
    $saldo_total += $cuenta['saldo_actual'];
}
unset($cuenta);

// Calcular balance mensual neto desde recurrentes activos
$stmt = $pdo->prepare("
    SELECT mr.monto, mr.periodicidad, tt.naturaleza
    FROM MOVIMIENTOS_RECURRENTES mr
    JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
    JOIN CUENTAS c ON mr.cuenta_id = c.id
    WHERE c.usuario_id = ? AND mr.activo = 1
");
$stmt->execute([$usuario_id]);
$recurrentes_todos = $stmt->fetchAll();

$balance_mensual_neto = 0;
foreach ($recurrentes_todos as $r) {
    $mensual = match($r['periodicidad']) {
        'MENSUAL' => (float)$r['monto'],
        'SEMANAL' => (float)$r['monto'] * 4,
        'ANUAL'   => (float)$r['monto'] / 12,
        default   => 0
    };
    $balance_mensual_neto += ($r['naturaleza'] === 'INGRESO') ? $mensual : -$mensual;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="images/favicon.svg">
    <title>Objetivos de Ahorro | Vaulta</title>
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
            position: fixed;
            height: 100vh;
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
            margin-left: 260px;
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

        /* CARDS */
        .content-grid {
            display: grid;
            gap: 30px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .card h2 {
            color: #2d3748;
            margin-bottom: 20px;
            font-size: 20px;
        }

        /* GOALS SPECIFIC STYLES */
        .btn-primary {
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 123, 255, 0.4);
        }

        .goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .goal-card {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 25px;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .goal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .goal-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .goal-title {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .goal-description {
            font-size: 14px;
            color: #718096;
            margin-bottom: 15px;
        }

        .goal-amounts {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .goal-amounts .current {
            color: #48bb78;
            font-weight: 600;
        }

        .goal-amounts .target {
            color: #718096;
        }

        .progress-bar-container {
            background: rgba(255,255,255,0.5);
            height: 12px;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #48bb78 0%, #38a169 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
        }

        .progress-bar.completed {
            background: linear-gradient(90deg, #f6ad55 0%, #ed8936 100%);
        }

        .progress-percentage {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            text-align: center;
        }

        .goal-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .goal-date {
            font-size: 13px;
            color: #718096;
        }

        .goal-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            background: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            color: #718096;
        }

        .btn-icon:hover {
            background: #002366;
            color: white;
        }

        .btn-icon.delete:hover {
            background: #f56565;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 40px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h2 {
            color: #2d3748;
            font-size: 24px;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #718096;
            transition: all 0.3s;
        }

        .close-modal:hover {
            color: #f56565;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #007bff;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .icon-option {
            width: 50px;
            height: 50px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 24px;
            transition: all 0.3s;
        }

        .icon-option:hover {
            border-color: #007bff;
            transform: scale(1.1);
        }

        .icon-option.selected {
            border-color: #007bff;
            background: #007bff;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 80px;
            color: #e2e8f0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #2d3748;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert.error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        .alert.active {
            display: block;
        }
    </style>
<link rel="stylesheet" href="responsive.css">
<link rel="stylesheet" href="dark-mode.css">
</head>
<body>
    <nav class="sidebar">
        <div class="brand">
            <img src="images/logotrans.png" alt="Vaulta">
        </div>
        
        <ul class="nav-links">
            <li><a href="dashboard.php">Resumen</a></li>
            <li><a href="movimientos.php">Movimientos</a></li>
            <li><a href="transferencias.php">Transferencias</a></li>
            <li><a href="recurrentes.php">Recurrentes</a></li>
            <li><a href="prevision.php">PrevisiÃ³n</a></li>
            <li class="active"><a href="objetivos.php">Objetivos</a></li>
            <li><a href="perfil.php">Mi Perfil</a></li>
        </ul>

        <div class="logout-section">
            <form action="logout.php" method="POST">
                <button type="submit" class="btn-logout">Salir</button>
            </form>
        </div>
    </nav>

    <div class="dashboard-wrapper">
        <main class="main-content">

            <header class="top-bar">
                <div class="welcome-text">
                    <h1><i class="fas fa-bullseye"></i> Objetivos de Ahorro</h1>
                    <p class="current-date"><?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="user-profile-widget">
                    <span class="user-dni">Saldo Total: <?php echo number_format($saldo_total, 2, ',', '.'); ?> &euro;</span>
                    <div class="avatar-placeholder"><?php echo strtoupper(substr($usuario['nombre'], 0, 2)); ?></div>
                </div>
            </header>

            <!-- Contexto financiero real -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
                <div style="background:linear-gradient(135deg,#002366,#007bff);color:white;padding:20px;border-radius:12px;">
                    <div style="font-size:13px;opacity:.85;margin-bottom:6px;"><i class="fas fa-wallet"></i> Saldo total disponible</div>
                    <div style="font-size:28px;font-weight:700;"><?php echo number_format($saldo_total, 2, ',', '.'); ?> &euro;</div>
                </div>
                <div style="background:linear-gradient(135deg,<?php echo $balance_mensual_neto>=0?'#38a169,#48bb78':'#c53030,#f56565'; ?>);color:white;padding:20px;border-radius:12px;">
                    <div style="font-size:13px;opacity:.85;margin-bottom:6px;"><i class="fas fa-chart-line"></i> Balance neto mensual (recurrentes)</div>
                    <div style="font-size:28px;font-weight:700;"><?php echo ($balance_mensual_neto >= 0 ? '+' : '') . number_format($balance_mensual_neto, 2, ',', '.'); ?> &euro;</div>
                </div>
                <div style="background:white;border:2px solid #e2e8f0;padding:20px;border-radius:12px;">
                    <div style="font-size:13px;color:#718096;margin-bottom:6px;"><i class="fas fa-info-circle"></i> Cada objetivo muestra</div>
                    <div style="font-size:13px;color:#2d3748;line-height:1.5;">Cu&aacute;nto cubre tu saldo actual y en cu&aacute;ntos meses lo alcanzar&iacute;as ahorrando mensualmente.</div>
                </div>
            </div>

        <div class="content-grid">
            <div class="card">
                <div id="goalsHeader" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h2>Mis Objetivos</h2>
                    <button class="btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Nuevo Objetivo</button>
                </div>
                <div id="alert" class="alert"></div>
                <div id="goalsContainer" class="goals-grid"></div>
                <div id="emptyState" class="empty-state" style="display:none;">
                    <i class="fas fa-bullseye"></i>
                    <h3>No tienes objetivos de ahorro</h3>
                    <p>Crea tu primer objetivo y comienza a planificar tu futuro financiero</p>
                    <button class="btn-primary" onclick="openModal()"><i class="fas fa-plus"></i> Crear Primer Objetivo</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="goalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nuevo Objetivo de Ahorro</h2>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form id="goalForm" onsubmit="saveGoal(event)">
                <input type="hidden" id="goalId">
                <div class="form-group"><label>Nombre *</label><input type="text" id="goalName" required placeholder="Ej: Vacaciones"></div>
                <div class="form-group"><label>Descripci&oacute;n</label><textarea id="goalDescription" placeholder="Describe tu objetivo..."></textarea></div>
                <div class="form-group"><label>Icono</label>
                    <div class="icon-selector">
                        <div class="icon-option" data-icon="&#127958;" onclick="selectIcon(this)">&#127958;</div>
                        <div class="icon-option" data-icon="&#127968;" onclick="selectIcon(this)">&#127968;</div>
                        <div class="icon-option" data-icon="&#128663;" onclick="selectIcon(this)">&#128663;</div>
                        <div class="icon-option" data-icon="&#128141;" onclick="selectIcon(this)">&#128141;</div>
                        <div class="icon-option" data-icon="&#127891;" onclick="selectIcon(this)">&#127891;</div>
                        <div class="icon-option" data-icon="&#128188;" onclick="selectIcon(this)">&#128188;</div>
                        <div class="icon-option" data-icon="&#127918;" onclick="selectIcon(this)">&#127918;</div>
                        <div class="icon-option" data-icon="&#128241;" onclick="selectIcon(this)">&#128241;</div>
                        <div class="icon-option" data-icon="&#128187;" onclick="selectIcon(this)">&#128187;</div>
                        <div class="icon-option" data-icon="&#127928;" onclick="selectIcon(this)">&#127928;</div>
                        <div class="icon-option" data-icon="&#9889;" onclick="selectIcon(this)">&#9889;</div>
                        <div class="icon-option selected" data-icon="&#128176;" onclick="selectIcon(this)">&#128176;</div>
                    </div>
                    <input type="hidden" id="goalIcon" value="&#128176;">
                </div>
                <div class="form-group"><label>Meta de Ahorro (&euro;) *</label><input type="number" id="goalTarget" step="0.01" min="0.01" required placeholder="5000.00"></div>
                <div class="form-group"><label>Ahorro Acumulado Propio (&euro;)</label><input type="number" id="goalCurrent" step="0.01" min="0" value="0"></div>
                <div class="form-group"><label>Fecha Objetivo</label><input type="date" id="goalDate"></div>
                <div style="display:flex;gap:10px;margin-top:30px;">
                    <button type="submit" class="btn-primary" style="flex:1;"><i class="fas fa-save"></i> Guardar</button>
                    <button type="button" class="btn-primary" onclick="closeModal()" style="background:#95a5a6;flex:1;"><i class="fas fa-times"></i> Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const userId      = <?php echo $usuario_id; ?>;
        const storageKey  = `vaulta_goals_${userId}`;
        const SALDO_REAL  = <?php echo (float)$saldo_total; ?>;
        const BAL_MENSUAL = <?php echo (float)$balance_mensual_neto; ?>;
        let goals = [], editingGoalId = null;

        document.addEventListener('DOMContentLoaded', () => { loadGoals(); renderGoals(); });
        function loadGoals() { const s = localStorage.getItem(storageKey); goals = s ? JSON.parse(s) : []; }
        function saveGoals() { localStorage.setItem(storageKey, JSON.stringify(goals)); }
        function fmt(n) { return n.toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}); }

        function renderGoals() {
            const container = document.getElementById('goalsContainer');
            const empty     = document.getElementById('emptyState');
            const hdr       = document.getElementById('goalsHeader');
            if (!goals.length) { container.innerHTML=''; empty.style.display='block'; hdr.style.display='none'; return; }
            empty.style.display='none'; hdr.style.display='flex';
            container.innerHTML = goals.map(g => {
                const pct       = (g.current / g.target) * 100;
                const done      = pct >= 100;
                const daysLeft  = g.date ? calcDays(g.date) : null;
                const falta     = Math.max(0, g.target - SALDO_REAL);
                const cubre     = SALDO_REAL >= g.target;
                const saldoPct  = Math.min((SALDO_REAL / g.target) * 100, 100);
                const color     = done ? '#f39c12' : cubre ? '#38a169' : '#667eea';
                let insight = '';
                if (done)       insight = `<div style="margin-top:10px;padding:10px;background:#f0fff4;border-radius:8px;border-left:3px solid #48bb78;font-size:13px;color:#276749;">&#127881; Meta manual alcanzada</div>`;
                else if (cubre) insight = `<div style="margin-top:10px;padding:10px;background:#f0fff4;border-radius:8px;border-left:3px solid #48bb78;font-size:13px;color:#276749;"><i class="fas fa-check-circle"></i> Tu saldo actual (${fmt(SALDO_REAL)} &euro;) ya cubre esta meta.</div>`;
                else if (BAL_MENSUAL > 0) { const m = Math.ceil(falta/BAL_MENSUAL); insight = `<div style="margin-top:10px;padding:10px;background:#ebf8ff;border-radius:8px;border-left:3px solid #3b82f6;font-size:13px;color:#1e3a5f;"><i class="fas fa-clock"></i> Faltan <strong>${fmt(falta)} &euro;</strong>. Con tu ahorro neto (~${fmt(BAL_MENSUAL)} &euro;/mes) lo alcanzar&iacute;as en <strong>~${m} ${m===1?'mes':'meses'}</strong>.</div>`; }
                else            insight = `<div style="margin-top:10px;padding:10px;background:#fff5f5;border-radius:8px;border-left:3px solid #f56565;font-size:13px;color:#742a2a;"><i class="fas fa-exclamation-triangle"></i> Tu balance mensual es negativo. Revisa tus recurrentes.</div>`;
                const dateHtml = daysLeft !== null ? (daysLeft>0?`<i class="fas fa-calendar"></i> ${daysLeft} d&iacute;as restantes`:daysLeft===0?`<i class="fas fa-calendar"></i> &iexcl;Hoy es el d&iacute;a!`:`<i class="fas fa-calendar"></i> Fecha pasada`) : '<i class="fas fa-infinity"></i> Sin fecha l&iacute;mite';
                return `<div class="goal-card">
                    <div class="goal-header"><div>
                        <div class="goal-icon" style="background:${color};color:white;">${g.icon}</div>
                        <div class="goal-title">${g.name}</div>
                        ${g.description?`<div class="goal-description">${g.description}</div>`:''}
                    </div></div>
                    <div class="goal-amounts"><span class="current">Propio: ${fmt(g.current)} &euro;</span><span class="target">Meta: ${fmt(g.target)} &euro;</span></div>
                    <div class="progress-bar-container"><div class="progress-bar ${done?'completed':''}" style="width:${Math.min(pct,100)}%"></div></div>
                    <div style="font-size:12px;color:#718096;margin-bottom:10px;">${pct.toFixed(1)}% ahorro propio ${done?'&#127881;':''}</div>
                    <div style="font-size:12px;color:#718096;margin-bottom:4px;">Cobertura con saldo real (${fmt(SALDO_REAL)} &euro;):</div>
                    <div class="progress-bar-container"><div style="height:100%;width:${saldoPct}%;background:${cubre?'linear-gradient(90deg,#38a169,#48bb78)':'linear-gradient(90deg,#007bff,#3b82f6)'};border-radius:10px;transition:width .5s;"></div></div>
                    <div style="font-size:12px;color:#718096;margin-bottom:4px;">${cubre?'&check; Saldo suficiente':'&#8987; '+saldoPct.toFixed(1)+'% cubierto con saldo actual'}</div>
                    ${insight}
                    <div class="goal-meta" style="margin-top:14px;">
                        <div class="goal-date">${dateHtml}</div>
                        <div class="goal-actions">
                            <button class="btn-icon" onclick="addProgress('${g.id}')" title="A&ntilde;adir"><i class="fas fa-plus"></i></button>
                            <button class="btn-icon" onclick="editGoal('${g.id}')" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon delete" onclick="deleteGoal('${g.id}')" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }

        function calcDays(ds) { const t=new Date(); t.setHours(0,0,0,0); const d=new Date(ds); d.setHours(0,0,0,0); return Math.ceil((d-t)/86400000); }
        function openModal() { editingGoalId=null; document.getElementById('modalTitle').textContent='Nuevo Objetivo'; document.getElementById('goalForm').reset(); document.getElementById('goalId').value=''; document.getElementById('goalIcon').value='&#128176;'; document.querySelectorAll('.icon-option').forEach(o=>o.classList.remove('selected')); const def=document.querySelector('.icon-option[data-icon="&#128176;"]'); if(def) def.classList.add('selected'); document.getElementById('goalModal').classList.add('active'); }
        function closeModal() { document.getElementById('goalModal').classList.remove('active'); editingGoalId=null; }
        function selectIcon(el) { document.querySelectorAll('.icon-option').forEach(o=>o.classList.remove('selected')); el.classList.add('selected'); document.getElementById('goalIcon').value=el.dataset.icon; }
        function saveGoal(e) { e.preventDefault(); const d={id:editingGoalId||Date.now().toString(),name:document.getElementById('goalName').value,description:document.getElementById('goalDescription').value,icon:document.getElementById('goalIcon').value,target:parseFloat(document.getElementById('goalTarget').value),current:parseFloat(document.getElementById('goalCurrent').value)||0,date:document.getElementById('goalDate').value,createdAt:editingGoalId?goals.find(g=>g.id===editingGoalId).createdAt:new Date().toISOString()}; if(editingGoalId){goals[goals.findIndex(g=>g.id===editingGoalId)]=d;showAlert('Objetivo actualizado','success');}else{goals.push(d);showAlert('Objetivo creado','success');} saveGoals();renderGoals();closeModal(); }
        function editGoal(id) { const g=goals.find(x=>x.id===id); if(!g) return; editingGoalId=id; document.getElementById('modalTitle').textContent='Editar Objetivo'; document.getElementById('goalId').value=g.id; document.getElementById('goalName').value=g.name; document.getElementById('goalDescription').value=g.description||''; document.getElementById('goalTarget').value=g.target; document.getElementById('goalCurrent').value=g.current; document.getElementById('goalDate').value=g.date||''; document.getElementById('goalIcon').value=g.icon; document.querySelectorAll('.icon-option').forEach(o=>o.classList.remove('selected')); const el=document.querySelector(`.icon-option[data-icon="${g.icon}"]`); if(el) el.classList.add('selected'); document.getElementById('goalModal').classList.add('active'); }
        function deleteGoal(id) { if(!confirm('¿Eliminar este objetivo?')) return; goals=goals.filter(g=>g.id!==id); saveGoals();renderGoals();showAlert('Objetivo eliminado','success'); }
        function addProgress(id) { const g=goals.find(x=>x.id===id); if(!g) return; const v=parseFloat(prompt(`¿Cuánto añadir al objetivo "${g.name}"?`,'0')); if(isNaN(v)||v<=0){showAlert('Introduce una cantidad válida mayor que 0','error');return;} g.current+=v; saveGoals();renderGoals(); showAlert(g.current>=g.target?`🎉 ¡Objetivo "${g.name}" completado!`:`Se añadieron ${v.toFixed(2)} € al objetivo`,'success'); }
        function showAlert(msg,type) { const el=document.getElementById('alert'); el.textContent=msg; el.className=`alert ${type} active`; setTimeout(()=>el.classList.remove('active'),5000); }
        document.getElementById('goalModal').addEventListener('click',e=>{ if(e.target===document.getElementById('goalModal')) closeModal(); });
    </script>
<script src="dark-mode.js"></script>
</body>
</html>
