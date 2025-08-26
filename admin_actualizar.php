<?php
// actualizador.php - Sistema de Actualización Automática
require_once 'config.php';
verificarLogin();
verificarAdmin(); // Solo administradores pueden actualizar

$mensaje = '';
$error = '';
$progreso = '';

// Configuración de actualización
$ACTUALIZACION_CONFIG = [
    'max_file_size' => 50 * 1024 * 1024, // 50MB máximo para el archivo del sistema
    'max_sql_file_size' => 10 * 1024 * 1024, // 10MB máximo para SQL
    'backup_dir' => 'backups/sistema/',
    'temp_dir' => 'temp/actualizacion/',
    'allowed_extensions' => ['zip', 'tar', 'tar.gz'],
    'allowed_sql_extensions' => ['sql'], // Extensiones para SQL
    'excluded_files' => ['config.php', '.htaccess', 'actualizador.php'],
    'validation_required' => true
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'subir_actualizacion') {
            
            // Validar archivo de actualización subido
            if (!isset($_FILES['archivo_actualizacion']) || $_FILES['archivo_actualizacion']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al subir el archivo de actualización.");
            }
            
            $archivo = $_FILES['archivo_actualizacion'];
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            
            // Validar extensión del archivo de actualización
            if (!in_array($extension, $ACTUALIZACION_CONFIG['allowed_extensions'])) {
                throw new Exception("Solo se permiten archivos: " . implode(', ', $ACTUALIZACION_CONFIG['allowed_extensions']));
            }
            
            // Validar tamaño del archivo de actualización
            if ($archivo['size'] > $ACTUALIZACION_CONFIG['max_file_size']) {
                throw new Exception("El archivo es demasiado grande. Máximo: " . ($ACTUALIZACION_CONFIG['max_file_size']/1024/1024) . "MB");
            }
            
            $progreso .= "📁 Validación de archivo de sistema completada...\n";
            
            // Validar y preparar archivo SQL si existe
            $sqlPath = null;
            if (isset($_FILES['archivo_sql']) && $_FILES['archivo_sql']['error'] === UPLOAD_ERR_OK) {
                $archivoSql = $_FILES['archivo_sql'];
                $extensionSql = strtolower(pathinfo($archivoSql['name'], PATHINFO_EXTENSION));

                if (!in_array($extensionSql, $ACTUALIZACION_CONFIG['allowed_sql_extensions'])) {
                    throw new Exception("El archivo de base de datos debe ser de tipo: " . implode(', ', $ACTUALIZACION_CONFIG['allowed_sql_extensions']));
                }

                if ($archivoSql['size'] > $ACTUALIZACION_CONFIG['max_sql_file_size']) {
                    throw new Exception("El archivo SQL es demasiado grande. Máximo: " . ($ACTUALIZACION_CONFIG['max_sql_file_size']/1024/1024) . "MB");
                }
                $progreso .= "🗃️ Archivo SQL detectado y validado...\n";
            }
            
            // Crear directorios necesarios
            foreach ([$ACTUALIZACION_CONFIG['backup_dir'], $ACTUALIZACION_CONFIG['temp_dir']] as $dir) {
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
            }

            // Mover archivo SQL a temporal si existe
            if (isset($archivoSql)) {
                $sqlPath = $ACTUALIZACION_CONFIG['temp_dir'] . 'update.sql';
                if (!move_uploaded_file($archivoSql['tmp_name'], $sqlPath)) {
                    throw new Exception("Error al mover el archivo SQL temporal.");
                }
            }
            
            // Generar backup antes de actualizar
            $fechaBackup = date('Y-m-d_H-i-s');
            $backupPath = $ACTUALIZACION_CONFIG['backup_dir'] . "backup_pre_actualizacion_{$fechaBackup}";
            mkdir($backupPath, 0755, true);
            
            $progreso .= "💾 Generando backup de seguridad...\n";
            
            // Copiar archivos actuales (excluyendo algunos)
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator('.', RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                $relativePath = $iterator->getSubPathName();
                
                if (strpos($relativePath, 'backups/') === 0 || strpos($relativePath, 'temp/') === 0 || in_array(basename($relativePath), $ACTUALIZACION_CONFIG['excluded_files'])) {
                    continue;
                }
                
                $destino = $backupPath . '/' . $relativePath;
                
                if ($item->isDir()) {
                    mkdir($destino, 0755, true);
                } else {
                    $destinoDir = dirname($destino);
                    if (!is_dir($destinoDir)) { mkdir($destinoDir, 0755, true); }
                    copy($item->getPathname(), $destino);
                }
            }
            
            $progreso .= "✅ Backup completado en: {$backupPath}\n";
            
            // Extraer archivo de actualización
            $tempPath = $ACTUALIZACION_CONFIG['temp_dir'] . 'nueva_version/';
            if (is_dir($tempPath)) {
                eliminarDirectorio($tempPath);
            }
            mkdir($tempPath, 0755, true);
            
            $progreso .= "📦 Extrayendo archivos de actualización...\n";
            
            $archivoActualizacion = $ACTUALIZACION_CONFIG['temp_dir'] . 'actualizacion.' . $extension;
            move_uploaded_file($archivo['tmp_name'], $archivoActualizacion);
            
            if ($extension === 'zip') {
                $zip = new ZipArchive;
                if ($zip->open($archivoActualizacion) === TRUE) {
                    $zip->extractTo($tempPath);
                    $zip->close();
                } else { throw new Exception("Error al extraer el archivo ZIP"); }
            } else {
                $cmd = "tar -xf {$archivoActualizacion} -C {$tempPath}";
                exec($cmd, $output, $returnCode);
                if ($returnCode !== 0) { throw new Exception("Error al extraer el archivo TAR"); }
            }
            
            $progreso .= "✅ Archivos extraídos correctamente\n";
            
            if ($ACTUALIZACION_CONFIG['validation_required']) {
                $progreso .= "🔍 Validando archivos PHP...\n";
                $archivosValidados = validarArchivosPhp($tempPath);
                if (!$archivosValidados['valido']) {
                    throw new Exception("Errores de validación encontrados:\n" . implode("\n", $archivosValidados['errores']));
                }
                $progreso .= "✅ Validación PHP completada - {$archivosValidados['archivos_validados']} archivos OK\n";
                
                $progreso .= "🔍 Validando estructura de proyecto...\n";
                validarEstructuraProyecto($tempPath);
                $progreso .= "✅ Estructura de proyecto validada\n";
            }
            
            $progreso .= "🚀 Aplicando actualización de archivos...\n";
            
            $archivosActualizados = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $item) {
                $relativePath = $iterator->getSubPathName();
                $destino = './' . $relativePath;
                
                if (in_array(basename($relativePath), $ACTUALIZACION_CONFIG['excluded_files'])) {
                    continue;
                }
                
                if ($item->isDir()) {
                    if (!is_dir($destino)) { mkdir($destino, 0755, true); }
                } else {
                    $destinoDir = dirname($destino);
                    if (!is_dir($destinoDir)) { mkdir($destinoDir, 0755, true); }
                    copy($item->getPathname(), $destino);
                    $archivosActualizados++;
                }
            }
            $progreso .= "✅ {$archivosActualizados} archivos fueron actualizados\n";

            // Ejecutar script SQL si existe
            if ($sqlPath && file_exists($sqlPath)) {
                $progreso .= "🔄 Ejecutando script de base de datos...\n";
                ejecutarSqlDesdeArchivo($sqlPath);
                $progreso .= "✅ Script de base de datos ejecutado correctamente.\n";
            }
            
            // Limpiar archivos temporales
            eliminarDirectorio($ACTUALIZACION_CONFIG['temp_dir']);
            
            $progreso .= "✅ Actualización completada exitosamente!\n";
            $progreso .= "📊 Total de archivos actualizados: {$archivosActualizados}\n";
            $progreso .= "💾 Backup guardado en: {$backupPath}\n";
            
            $mensaje = "Sistema actualizado correctamente. Se procesaron {$archivosActualizados} archivos.";
            
        } elseif ($_POST['accion'] === 'restaurar_backup') {
            $backupPath = $_POST['backup_path'];
            
            if (!is_dir($backupPath)) {
                throw new Exception("El backup especificado no existe.");
            }
            
            $progreso .= "🔄 Restaurando desde backup: {$backupPath}\n";
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($backupPath, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            $archivosRestaurados = 0;
            foreach ($iterator as $item) {
                $relativePath = $iterator->getSubPathName();
                $destino = './' . $relativePath;
                
                if ($item->isDir()) {
                    if (!is_dir($destino)) { mkdir($destino, 0755, true); }
                } else {
                    $destinoDir = dirname($destino);
                    if (!is_dir($destinoDir)) { mkdir($destinoDir, 0755, true); }
                    copy($item->getPathname(), $destino);
                    $archivosRestaurados++;
                }
            }
            
            $progreso .= "✅ Restauración completada: {$archivosRestaurados} archivos restaurados\n";
            $mensaje = "Sistema restaurado exitosamente desde el backup.";
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        
        if (isset($backupPath) && is_dir($backupPath)) {
            $progreso .= "❌ Error detectado. El sistema no fue modificado.\n";
            $progreso .= "🔄 Puede restaurar manualmente desde el backup: {$backupPath}\n";
        }
    }
}

function obtenerBackups() {
    global $ACTUALIZACION_CONFIG;
    $backups = [];
    
    if (is_dir($ACTUALIZACION_CONFIG['backup_dir'])) {
        $dirs = glob($ACTUALIZACION_CONFIG['backup_dir'] . 'backup_pre_actualizacion_*', GLOB_ONLYDIR);
        if ($dirs) {
            foreach ($dirs as $dir) {
                $backups[] = [
                    'path' => $dir,
                    'name' => basename($dir),
                    'date' => filemtime($dir),
                    'size' => obtenerTamanoDirectorio($dir)
                ];
            }
            
            usort($backups, function($a, $b) {
                return $b['date'] - $a['date'];
            });
        }
    }
    
    return $backups;
}

function validarArchivosPhp($directorio) {
    $resultado = ['valido' => true, 'errores' => [], 'archivos_validados' => 0];
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directorio, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $archivo) {
        if ($archivo->isFile() && $archivo->getExtension() === 'php') {
            $contenido = file_get_contents($archivo->getPathname());
            
            if (empty(trim($contenido))) continue;

            $tempFile = tempnam(sys_get_temp_dir(), 'php_validation');
            file_put_contents($tempFile, $contenido);
            
            $output = [];
            $returnCode = 0;
            exec("php -l {$tempFile} 2>&1", $output, $returnCode);
            
            unlink($tempFile);
            
            if ($returnCode !== 0) {
                $resultado['valido'] = false;
                $resultado['errores'][] = "Error en {$archivo->getFilename()}: " . implode(' ', $output);
            } else {
                $resultado['archivos_validados']++;
            }
        }
    }
    
    return $resultado;
}

function validarEstructuraProyecto($directorio) {
    $archivosRequeridos = ['index.php'];
    
    foreach ($archivosRequeridos as $archivo) {
        if (!file_exists($directorio . '/' . $archivo)) {
            throw new Exception("Archivo requerido no encontrado en la actualización: {$archivo}");
        }
    }
}

function ejecutarSqlDesdeArchivo($filePath) {
    if (!file_exists($filePath)) {
        throw new Exception("No se encontró el archivo SQL en la ruta: $filePath");
    }

    if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
        throw new Exception("Las constantes de conexión a la base de datos no están definidas en config.php.");
    }

    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($mysqli->connect_error) {
        throw new Exception("Error de conexión a la base de datos: " . $mysqli->connect_error);
    }

    $mysqli->set_charset('utf8mb4');

    $sql = file_get_contents($filePath);
    if ($sql === false) {
        throw new Exception("No se pudo leer el archivo SQL.");
    }

    if ($mysqli->multi_query($sql)) {
        do {
            if ($result = $mysqli->store_result()) {
                $result->free();
            }
        } while ($mysqli->next_result());
    }

    if ($mysqli->error) {
        $errorMsg = $mysqli->error;
        $mysqli->close();
        throw new Exception("Error al ejecutar el script SQL: " . $errorMsg);
    }

    $mysqli->close();
}

function eliminarDirectorio($dir) {
    if (!is_dir($dir)) return;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getPathname());
        } else {
            unlink($file->getPathname());
        }
    }
    
    rmdir($dir);
}

function obtenerTamanoDirectorio($dir) {
    $size = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
           $size += $file->getSize();
        }
    }
    return $size;
}

function formatearTamano($bytes) {
    if ($bytes == 0) return "0 B";
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

$backupsDisponibles = obtenerBackups();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualizador del Sistema</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .update-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 2rem; border-radius: 10px; margin-bottom: 2rem; }
        .progress-container { background: #fff; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .progress-log { background: #1e1e1e; color: #00ff00; padding: 1rem; border-radius: 5px; font-family: monospace; height: 300px; overflow-y: auto; white-space: pre-wrap; }
        .card { box-shadow: 0 2px 10px rgba(0,0,0,0.1); border: none; border-radius: 10px; }
        .alert-warning { border-left: 4px solid #ffc107; }
        .alert-danger { border-left: 4px solid #dc3545; }
        .alert-success { border-left: 4px solid #28a745; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="update-header text-center">
            <h1><i class="fas fa-sync-alt"></i> Sistema de Actualización</h1>
            <p class="mb-0">Actualiza tu sistema de forma segura con validación y backups automáticos</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <strong>Éxito:</strong> <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($progreso): ?>
            <div class="progress-container">
                <h5><i class="fas fa-terminal"></i> Log de Actualización</h5>
                <div class="progress-log"><?= htmlspecialchars($progreso) ?></div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-upload"></i> Subir Nueva Actualización</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> Se creará un backup antes de aplicar cualquier cambio. Los archivos <code>config.php</code> y <code>.htaccess</code> no serán modificados.
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="accion" value="subir_actualizacion">
                            
                            <div class="mb-3">
                                <label for="archivo_actualizacion" class="form-label">
                                    <i class="fas fa-file-archive"></i> Archivo de Actualización (ZIP, TAR)
                                </label>
                                <input type="file" class="form-control" id="archivo_actualizacion" name="archivo_actualizacion" accept=".zip,.tar,.tar.gz" required>
                                <div class="form-text">
                                    Formatos permitidos: ZIP, TAR, TAR.GZ | Tamaño máximo: <?= $ACTUALIZACION_CONFIG['max_file_size']/1024/1024 ?>MB
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="archivo_sql" class="form-label">
                                    <i class="fas fa-database"></i> Script de Base de Datos (SQL)
                                </label>
                                <input type="file" class="form-control" id="archivo_sql" name="archivo_sql" accept=".sql">
                                <div class="form-text">
                                    Opcional. Sube aquí el archivo <code>.sql</code> si la actualización requiere cambios en la base de datos.
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirmar_actualizacion" required>
                                    <label class="form-check-label" for="confirmar_actualizacion">
                                        He verificado que este archivo contiene una versión válida del sistema.
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-rocket"></i> Iniciar Actualización
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-history"></i> Backups Disponibles</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($backupsDisponibles)): ?>
                            <p class="text-muted">No hay backups disponibles</p>
                        <?php else: ?>
                            <?php foreach ($backupsDisponibles as $backup): ?>
                                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                    <div>
                                        <strong><?= date('d/m/Y H:i', $backup['date']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($backup['name']) ?> (<?= formatearTamano($backup['size']) ?>)</small>
                                    </div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="accion" value="restaurar_backup">
                                        <input type="hidden" name="backup_path" value="<?= htmlspecialchars($backup['path']) ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Estás seguro de restaurar este backup? Se revertirán todos los cambios actuales.');">
                                            <i class="fas fa-undo"></i> Restaurar
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-info-circle"></i> Información del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><strong>PHP:</strong> <?= PHP_VERSION ?></li>
                            <li><strong>Servidor:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido' ?></li>
                            <li><strong>Memoria PHP:</strong> <?= ini_get('memory_limit') ?></li>
                            <li><strong>Max Upload:</strong> <?= ini_get('upload_max_filesize') ?></li>
                            <li><strong>Última mod.:</strong> <small><?= date('d/m/Y H:i:s', filemtime(__FILE__)) ?></small></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const logContainer = document.querySelector('.progress-log');
            if (logContainer) {
                logContainer.scrollTop = logContainer.scrollHeight;
            }
        });
    </script>
</body>
</html>