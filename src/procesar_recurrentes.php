<?php
/**
 * Script de procesamiento automático de pagos recurrentes
 * Este script debe ejecutarse cada vez que un usuario inicia sesión
 * o periódicamente mediante un cron job
 */

// Iniciar sesión solo si no está ya activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'conexion.php';

// Verificar que el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    exit;
}

$user_id = $_SESSION['user_id'];

/**
 * Calcula la próxima fecha de ejecución según la periodicidad
 */
function calcularProximaEjecucion($periodicidad, $dia_cargo, $fecha_especifica, $fecha_actual) {
    $fecha_base = new DateTime($fecha_actual);
    
    switch ($periodicidad) {
        case 'SEMANAL':
            // dia_cargo es 1-7 (Lunes-Domingo)
            $dia_semana_actual = (int)$fecha_base->format('N');
            $diferencia = $dia_cargo - $dia_semana_actual;
            if ($diferencia <= 0) {
                $diferencia += 7;
            }
            $fecha_base->modify("+{$diferencia} days");
            return $fecha_base->format('Y-m-d');
            
        case 'MENSUAL':
            // dia_cargo es 1-31 (día del mes)
            $dia_actual = (int)$fecha_base->format('d');
            
            if ($dia_cargo > $dia_actual) {
                // Este mes
                $fecha_base->setDate(
                    (int)$fecha_base->format('Y'),
                    (int)$fecha_base->format('m'),
                    min($dia_cargo, (int)$fecha_base->format('t'))
                );
            } else {
                // Próximo mes
                $fecha_base->modify('first day of next month');
                $fecha_base->setDate(
                    (int)$fecha_base->format('Y'),
                    (int)$fecha_base->format('m'),
                    min($dia_cargo, (int)$fecha_base->format('t'))
                );
            }
            return $fecha_base->format('Y-m-d');
            
        case 'SEMESTRAL':
        case 'ANUAL':
            // Usar fecha_especifica
            if ($fecha_especifica) {
                $fecha_objetivo = new DateTime($fecha_especifica);
                $año_actual = (int)$fecha_base->format('Y');
                
                // Establecer el año actual a la fecha objetivo
                $fecha_objetivo->setDate($año_actual, (int)$fecha_objetivo->format('m'), (int)$fecha_objetivo->format('d'));
                
                // Si ya pasó este año, usar el próximo
                if ($fecha_objetivo <= $fecha_base) {
                    if ($periodicidad == 'ANUAL') {
                        $fecha_objetivo->setDate($año_actual + 1, (int)$fecha_objetivo->format('m'), (int)$fecha_objetivo->format('d'));
                    } else {
                        // SEMESTRAL: añadir 6 meses
                        $fecha_objetivo->modify('+6 months');
                    }
                }
                
                return $fecha_objetivo->format('Y-m-d');
            }
            return null;
            
        default:
            return null;
    }
}

/**
 * Procesa pagos recurrentes pendientes
 */
function procesarPagosRecurrentes($pdo, $user_id) {
    $fecha_actual = date('Y-m-d');
    $pagos_ejecutados = 0;
    
    // Obtener pagos recurrentes activos del usuario que deben ejecutarse
    $query = "
        SELECT 
            mr.id, mr.cuenta_id, mr.tipo_id, mr.monto, mr.dia_cargo, 
            mr.periodicidad, mr.fecha_especifica, mr.ultima_ejecucion, mr.proxima_ejecucion,
            tt.nombre as tipo_nombre, tt.naturaleza,
            c.nombre as cuenta_nombre
        FROM MOVIMIENTOS_RECURRENTES mr
        JOIN TIPOS_TRANSACCION tt ON mr.tipo_id = tt.id
        JOIN CUENTAS c ON mr.cuenta_id = c.id
        WHERE c.usuario_id = ? 
        AND mr.activo = 1
        AND (mr.proxima_ejecucion IS NULL OR mr.proxima_ejecucion <= ?)
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id, $fecha_actual]);
    $pagos = $stmt->fetchAll();
    
    foreach ($pagos as $pago) {
        try {
            // Calcular próxima ejecución si no existe
            if ($pago['proxima_ejecucion'] === null) {
                $proxima = calcularProximaEjecucion(
                    $pago['periodicidad'],
                    $pago['dia_cargo'],
                    $pago['fecha_especifica'],
                    $fecha_actual
                );
                
                // Si la próxima ejecución es futura, actualizar y continuar
                if ($proxima > $fecha_actual) {
                    $stmt_update = $pdo->prepare("
                        UPDATE MOVIMIENTOS_RECURRENTES 
                        SET proxima_ejecucion = ? 
                        WHERE id = ?
                    ");
                    $stmt_update->execute([$proxima, $pago['id']]);
                    continue;
                }
            }
            
            // Ejecutar el pago: crear movimiento real
            $descripcion = "Pago automático: " . $pago['tipo_nombre'];
            
            $stmt_movimiento = $pdo->prepare("
                INSERT INTO MOVIMIENTOS (cuenta_id, tipo_id, monto, fecha, descripcion)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt_movimiento->execute([
                $pago['cuenta_id'],
                $pago['tipo_id'],
                $pago['monto'],
                $fecha_actual,
                $descripcion
            ]);
            
            // Calcular la siguiente fecha de ejecución
            $siguiente = calcularProximaEjecucion(
                $pago['periodicidad'],
                $pago['dia_cargo'],
                $pago['fecha_especifica'],
                $fecha_actual
            );
            
            // Actualizar el registro recurrente
            $stmt_update = $pdo->prepare("
                UPDATE MOVIMIENTOS_RECURRENTES 
                SET ultima_ejecucion = ?, proxima_ejecucion = ?
                WHERE id = ?
            ");
            $stmt_update->execute([$fecha_actual, $siguiente, $pago['id']]);
            
            $pagos_ejecutados++;
            
            // Log opcional
            error_log("Pago recurrente ejecutado: ID {$pago['id']}, {$pago['tipo_nombre']}, {$pago['monto']}€");
            
        } catch (Exception $e) {
            // Log de error pero continuar con otros pagos
            error_log("Error procesando pago recurrente ID {$pago['id']}: " . $e->getMessage());
        }
    }
    
    return $pagos_ejecutados;
}

// Ejecutar procesamiento
try {
    $pagos_ejecutados = procesarPagosRecurrentes($pdo, $user_id);
    
    // Opcionalmente almacenar en sesión para mostrar mensaje
    if ($pagos_ejecutados > 0) {
        $_SESSION['pagos_procesados'] = $pagos_ejecutados;
    }
    
} catch (Exception $e) {
    error_log("Error en procesamiento de recurrentes: " . $e->getMessage());
}
?>
