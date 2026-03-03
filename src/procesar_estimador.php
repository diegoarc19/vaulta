<?php
session_start();

// Si el usuario no ha iniciado sesión, lo echamos fuera
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.html");
    exit;
}

// Conectar a la base de datos
require 'conexion.php';

// Verificar que se recibieron datos por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: estimador_gastos.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$cuenta_id = $_POST['cuenta_id'] ?? null;
$gastos = $_POST['gastos'] ?? [];
$ingresos = $_POST['ingresos'] ?? [];

// Validar que se seleccionó una cuenta
if (!$cuenta_id) {
    $_SESSION['error'] = "Debes seleccionar una cuenta.";
    header("Location: estimador_gastos.php");
    exit;
}

// Verificar que la cuenta pertenece al usuario
$stmt = $pdo->prepare("SELECT id FROM CUENTAS WHERE id = ? AND usuario_id = ?");
$stmt->execute([$cuenta_id, $user_id]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = "La cuenta seleccionada no es válida.";
    header("Location: estimador_gastos.php");
    exit;
}

// Contadores
$movimientos_creados = 0;
$total_gastos = 0;
$total_ingresos = 0;

try {
    $pdo->beginTransaction();

    // Procesar GASTOS
    foreach ($gastos as $categoria) {
        $monto = floatval($categoria['monto'] ?? 0);
        
        // Solo procesar si hay un monto válido
        if ($monto <= 0) {
            continue;
        }

        $nombre = $categoria['nombre'] ?? 'Gasto';
        $icono = $categoria['icono'] ?? 'fa-money-bill';

        // Buscar si ya existe un tipo de transacción con ese nombre
        $stmt = $pdo->prepare("SELECT id FROM TIPOS_TRANSACCION WHERE nombre = ? AND naturaleza = 'GASTO'");
        $stmt->execute([$nombre]);
        $tipo_existente = $stmt->fetch();

        if ($tipo_existente) {
            $tipo_id = $tipo_existente['id'];
        } else {
            // Crear nuevo tipo de transacción
            $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES (?, 'GASTO', ?)");
            $stmt->execute([$nombre, $icono]);
            $tipo_id = $pdo->lastInsertId();
        }

        // Verificar si ya existe un movimiento recurrente para esta combinación
        $stmt = $pdo->prepare("
            SELECT id FROM MOVIMIENTOS_RECURRENTES 
            WHERE cuenta_id = ? AND tipo_id = ? AND activo = 1
        ");
        $stmt->execute([$cuenta_id, $tipo_id]);
        $recurrente_existente = $stmt->fetch();

        if ($recurrente_existente) {
            // Actualizar el movimiento recurrente existente
            $stmt = $pdo->prepare("
                UPDATE MOVIMIENTOS_RECURRENTES 
                SET monto = ?, dia_cargo = 1, periodicidad = 'MENSUAL'
                WHERE id = ?
            ");
            $stmt->execute([$monto, $recurrente_existente['id']]);
        } else {
            // Crear nuevo movimiento recurrente
            $stmt = $pdo->prepare("
                INSERT INTO MOVIMIENTOS_RECURRENTES (cuenta_id, tipo_id, monto, dia_cargo, periodicidad, activo)
                VALUES (?, ?, ?, 1, 'MENSUAL', 1)
            ");
            $stmt->execute([$cuenta_id, $tipo_id, $monto]);
        }

        $movimientos_creados++;
        $total_gastos += $monto;
    }

    // Procesar INGRESOS
    foreach ($ingresos as $categoria) {
        $monto = floatval($categoria['monto'] ?? 0);
        
        // Solo procesar si hay un monto válido
        if ($monto <= 0) {
            continue;
        }

        $nombre = $categoria['nombre'] ?? 'Ingreso';
        $icono = $categoria['icono'] ?? 'fa-money-bill-wave';

        // Buscar si ya existe un tipo de transacción con ese nombre
        $stmt = $pdo->prepare("SELECT id FROM TIPOS_TRANSACCION WHERE nombre = ? AND naturaleza = 'INGRESO'");
        $stmt->execute([$nombre]);
        $tipo_existente = $stmt->fetch();

        if ($tipo_existente) {
            $tipo_id = $tipo_existente['id'];
        } else {
            // Crear nuevo tipo de transacción
            $stmt = $pdo->prepare("INSERT INTO TIPOS_TRANSACCION (nombre, naturaleza, icono) VALUES (?, 'INGRESO', ?)");
            $stmt->execute([$nombre, $icono]);
            $tipo_id = $pdo->lastInsertId();
        }

        // Verificar si ya existe un movimiento recurrente para esta combinación
        $stmt = $pdo->prepare("
            SELECT id FROM MOVIMIENTOS_RECURRENTES 
            WHERE cuenta_id = ? AND tipo_id = ? AND activo = 1
        ");
        $stmt->execute([$cuenta_id, $tipo_id]);
        $recurrente_existente = $stmt->fetch();

        if ($recurrente_existente) {
            // Actualizar el movimiento recurrente existente
            $stmt = $pdo->prepare("
                UPDATE MOVIMIENTOS_RECURRENTES 
                SET monto = ?, dia_cargo = 1, periodicidad = 'MENSUAL'
                WHERE id = ?
            ");
            $stmt->execute([$monto, $recurrente_existente['id']]);
        } else {
            // Crear nuevo movimiento recurrente
            $stmt = $pdo->prepare("
                INSERT INTO MOVIMIENTOS_RECURRENTES (cuenta_id, tipo_id, monto, dia_cargo, periodicidad, activo)
                VALUES (?, ?, ?, 1, 'MENSUAL', 1)
            ");
            $stmt->execute([$cuenta_id, $tipo_id, $monto]);
        }

        $movimientos_creados++;
        $total_ingresos += $monto;
    }

    $pdo->commit();

    // Calcular balance
    $balance = $total_ingresos - $total_gastos;
    $balance_texto = $balance >= 0 ? "positivo de +" : "negativo de ";

    // Mensaje de éxito
    $mensaje = "¡Estimación completada! Se han creado/actualizado $movimientos_creados movimientos recurrentes mensuales.<br>";
    $mensaje .= "📊 <strong>Resumen:</strong><br>";
    $mensaje .= "• Ingresos: " . number_format($total_ingresos, 2, ',', '.') . " €<br>";
    $mensaje .= "• Gastos: " . number_format($total_gastos, 2, ',', '.') . " €<br>";
    $mensaje .= "• Balance mensual: " . $balance_texto . number_format(abs($balance), 2, ',', '.') . " €";
    
    $_SESSION['success'] = $mensaje;
    
    // Marcar que el usuario ya usó el estimador (cookie por 365 días)
    setcookie('estimador_usado', 'true', time() + (365 * 24 * 60 * 60), '/');
    
    header("Location: recurrentes.php");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Error al procesar la estimación: " . $e->getMessage();
    header("Location: estimador_gastos.php");
    exit;
}
?>
