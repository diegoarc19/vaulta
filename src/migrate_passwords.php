<?php
/**
 * Script de Migración de Contraseñas
 * 
 * Este script hashea las contraseñas en texto plano existentes en la base de datos.
 * IMPORTANTE: Ejecutar SOLO UNA VEZ después de actualizar el código de autenticación.
 * 
 * Seguridad: Se requiere una clave de acceso para ejecutar este script.
 * Para usar: Añadir ?key=MIGRATE_PASSWORDS_2026 a la URL
 */

// Clave de seguridad para ejecutar el script
define('MIGRATION_KEY', 'MIGRATE_PASSWORDS_2026');

// Verificar la clave de acceso
if (!isset($_GET['key']) || $_GET['key'] !== MIGRATION_KEY) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Acceso Denegado</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
            }
            h1 {
                color: #e53e3e;
                margin-top: 0;
            }
            p {
                color: #4a5568;
                line-height: 1.6;
            }
            code {
                background: #f7fafc;
                padding: 2px 6px;
                border-radius: 4px;
                font-family: monospace;
            }
        </style>
    <link rel="stylesheet" href="dark-mode.css">
</head>
    <body>
        <div class="container">
            <h1>⛔ Acceso Denegado</h1>
            <p>Este script requiere una clave de acceso válida.</p>
            <p><strong>Uso:</strong> <code>migrate_passwords.php?key=CLAVE</code></p>
        </div>
    <script src="dark-mode.js"></script>
</body>
    </html>
    ');
}

require 'conexion.php';

// Iniciar log
$log = [];
$log[] = "=== MIGRACIÓN DE CONTRASEÑAS ===";
$log[] = "Fecha: " . date('Y-m-d H:i:s');
$log[] = "";

try {
    // Obtener todos los usuarios
    $stmt = $pdo->query("SELECT id, email, password FROM USUARIOS");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_usuarios = count($usuarios);
    $migrados = 0;
    $ya_hasheados = 0;
    $errores = 0;
    
    $log[] = "Total de usuarios encontrados: $total_usuarios";
    $log[] = "";
    
    foreach ($usuarios as $usuario) {
        $id = $usuario['id'];
        $email = $usuario['email'];
        $password = $usuario['password'];
        
        // Verificar si la contraseña ya está hasheada
        // Las contraseñas bcrypt comienzan con $2y$ o $2a$ o $2b$ y tienen 60 caracteres
        if (preg_match('/^\$2[ayb]\$.{56}$/', $password)) {
            $log[] = "✓ Usuario ID $id ($email): Ya hasheado, omitiendo...";
            $ya_hasheados++;
            continue;
        }
        
        // Hashear la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($password_hash) {
            // Actualizar en la base de datos
            $update_stmt = $pdo->prepare("UPDATE USUARIOS SET password = ? WHERE id = ?");
            $update_stmt->execute([$password_hash, $id]);
            
            $log[] = "✓ Usuario ID $id ($email): Contraseña migrada exitosamente";
            $migrados++;
        } else {
            $log[] = "✗ Usuario ID $id ($email): Error al hashear contraseña";
            $errores++;
        }
    }
    
    $log[] = "";
    $log[] = "=== RESUMEN ===";
    $log[] = "Total de usuarios: $total_usuarios";
    $log[] = "Migrados exitosamente: $migrados";
    $log[] = "Ya hasheados (omitidos): $ya_hasheados";
    $log[] = "Errores: $errores";
    $log[] = "";
    $log[] = "Migración completada.";
    
    // Guardar log en archivo
    $log_filename = 'migration_log_' . date('Y-m-d_His') . '.txt';
    file_put_contents($log_filename, implode("\n", $log));
    
} catch (PDOException $e) {
    $log[] = "";
    $log[] = "ERROR CRÍTICO: " . $e->getMessage();
    $errores++;
}

// Mostrar resultado
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración de Contraseñas - Vaulta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .header h1 {
            color: #007bff;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #718096;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #007bff;
        }
        
        .stat-card.success {
            border-left-color: #48bb78;
        }
        
        .stat-card.warning {
            border-left-color: #ed8936;
        }
        
        .stat-card.error {
            border-left-color: #f56565;
        }
        
        .stat-label {
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .log-section {
            background: #1a202c;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
        }
        
        .log-section pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #c6f6d5;
            border: 1px solid #9ae6b4;
            color: #22543d;
        }
        
        .alert-warning {
            background: #feebc8;
            border: 1px solid #fbd38d;
            color: #7c2d12;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #002366 0%, #007bff 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
    </style>
<link rel="stylesheet" href="dark-mode.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Migración de Contraseñas</h1>
            <p>Sistema de Seguridad - Vaulta</p>
        </div>
        
        <?php if ($errores > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> 
            <strong>Advertencia:</strong> Se encontraron <?php echo $errores; ?> error(es) durante la migración. 
            Revisa el log para más detalles.
        </div>
        <?php else: ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <strong>¡Migración completada exitosamente!</strong> Todas las contraseñas han sido hasheadas.
        </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Usuarios</div>
                <div class="stat-value"><?php echo $total_usuarios; ?></div>
            </div>
            <div class="stat-card success">
                <div class="stat-label">Migrados</div>
                <div class="stat-value"><?php echo $migrados; ?></div>
            </div>
            <div class="stat-card warning">
                <div class="stat-label">Ya Hasheados</div>
                <div class="stat-value"><?php echo $ya_hasheados; ?></div>
            </div>
            <div class="stat-card error">
                <div class="stat-label">Errores</div>
                <div class="stat-value"><?php echo $errores; ?></div>
            </div>
        </div>
        
        <h3 style="margin-bottom: 15px; color: #2d3748;">
            <i class="fas fa-file-alt"></i> Log de Migración
        </h3>
        <div class="log-section">
            <pre><?php echo htmlspecialchars(implode("\n", $log)); ?></pre>
        </div>
        
        <div class="footer">
            <p style="color: #718096; margin-bottom: 15px;">
                Log guardado en: <code><?php echo $log_filename; ?></code>
            </p>
            <a href="login.html" class="btn">
                <i class="fas fa-sign-in-alt"></i> Ir al Login
            </a>
        </div>
    </div>
<script src="dark-mode.js"></script>
</body>
</html>
