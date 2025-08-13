<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensaje = '';
$revision_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtener información de la revisión
if ($revision_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, e.nombre as equipo_nombre, e.modelo, e.marca, te.nombre as tipo_nombre
        FROM revisiones r
        LEFT JOIN equipos e ON r.equipo_id = e.id
        LEFT JOIN tipos_equipo te ON e.tipo_equipo_id = te.id
        WHERE r.id = ?
    ");
    $stmt->execute([$revision_id]);
    $revision = $stmt->fetch();
    
    if (!$revision) {
        header('Location: revisiones.php?error=Revisión no encontrada');
        exit;
    }
} else {
    header('Location: revisiones.php');
    exit;
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $estado_revision = $_POST['estado_revision'];
    $temperatura = !empty($_POST['temperatura']) ? $_POST['temperatura'] : null;
    $voltaje = !empty($_POST['voltaje']) ? $_POST['voltaje'] : null;
    $señal_dbm = !empty($_POST['señal_dbm']) ? $_POST['señal_dbm'] : null;
    $velocidad_mbps = !empty($_POST['velocidad_mbps']) ? $_POST['velocidad_mbps'] : null;
    $tiempo_actividad_horas = !empty($_POST['tiempo_actividad_horas']) ? $_POST['tiempo_actividad_horas'] : null;
    $problemas_detectados = limpiarDatos($_POST['problemas_detectados']);
    $acciones_realizadas = limpiarDatos($_POST['acciones_realizadas']);
    $observaciones = limpiarDatos($_POST['observaciones']);
    $requiere_mantenimiento = isset($_POST['requiere_mantenimiento']) ? 1 : 0;
    $fecha_proximo_mantenimiento = !empty($_POST['fecha_proximo_mantenimiento']) ? $_POST['fecha_proximo_mantenimiento'] : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE revisiones SET 
                estado_revision = ?, 
                temperatura = ?, 
                voltaje = ?, 
                señal_dbm = ?, 
                velocidad_mbps = ?, 
                tiempo_actividad_horas = ?, 
                problemas_detectados = ?, 
                acciones_realizadas = ?, 
                observaciones = ?, 
                requiere_mantenimiento = ?, 
                fecha_proximo_mantenimiento = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $estado_revision,
            $temperatura,
            $voltaje,
            $señal_dbm,
            $velocidad_mbps,
            $tiempo_actividad_horas,
            $problemas_detectados,
            $acciones_realizadas,
            $observaciones,
            $requiere_mantenimiento,
            $fecha_proximo_mantenimiento,
            $revision_id
        ]);
        
        // Actualizar estado del equipo si es necesario
        if ($requiere_mantenimiento) {
            $stmt = $pdo->prepare("UPDATE equipos SET estado = 'Mantenimiento' WHERE id = ?");
            $stmt->execute([$revision['equipo_id']]);
        }
        
        $mensaje = mostrarAlerta('Revisión actualizada exitosamente', 'success');
        
        // Recargar datos actualizados
        $stmt = $pdo->prepare("
            SELECT r.*, e.nombre as equipo_nombre, e.modelo, e.marca, te.nombre as tipo_nombre
            FROM revisiones r
            LEFT JOIN equipos e ON r.equipo_id = e.id
            LEFT JOIN tipos_equipo te ON e.tipo_equipo_id = te.id
            WHERE r.id = ?
        ");
        $stmt->execute([$revision_id]);
        $revision = $stmt->fetch();
        
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al actualizar revisión: ' . $e->getMessage(), 'error');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Revisión - Sistema de Equipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
                <div class="text-center mb-4">
                    <h4 class="text-white">
                        <i class="fas fa-wifi"></i> Sistema Equipos
                    </h4>
                    <small class="text-white-50"><?php echo $_SESSION['usuario_nombre']; ?></small>
                </div>
                
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="equipos.php">
                            <i class="fas fa-router"></i> Equipos
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="revisiones.php">
                            <i class="fas fa-clipboard-check"></i> Revisiones
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="reportes.php">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit"></i> Editar Revisión
                    </h1>
                    <a href="revisiones.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver a Revisiones
                    </a>
                </div>

                <?php echo $mensaje; ?>

                <!-- Información del equipo -->
                <div class="card info-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Información del Equipo</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Nombre:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($revision['equipo_nombre']); ?></dd>
                                    
                                    <dt class="col-sm-4">Tipo:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($revision['tipo_nombre']); ?></dd>
                                    
                                    <dt class="col-sm-4">Marca:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($revision['marca']); ?></dd>
                                </dl>
                            </div>
                            <div class="col-md-6">
                                <dl class="row">
                                    <dt class="col-sm-4">Modelo:</dt>
                                    <dd class="col-sm-8"><?php echo htmlspecialchars($revision['modelo']); ?></dd>
                                    
                                    <dt class="col-sm-4">Fecha Revisión:</dt>
                                    <dd class="col-sm-8"><?php echo date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></dd>
                                    
                                    <dt class="col-sm-4">Estado Actual:</dt>
                                    <dd class="col-sm-8">
                                        <span class="badge bg-<?php 
                                            echo $revision['estado_revision'] == 'Excelente' ? 'success' : 
                                                ($revision['estado_revision'] == 'Bueno' ? 'primary' : 
                                                ($revision['estado_revision'] == 'Regular' ? 'warning' : 'danger')); 
                                        ?>">
                                            <?php echo $revision['estado_revision']; ?>
                                        </span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario de edición -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Editar Revisión</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estado General *</label>
                                    <select name="estado_revision" class="form-select" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="Excelente" <?php echo $revision['estado_revision'] == 'Excelente' ? 'selected' : ''; ?>>Excelente</option>
                                        <option value="Bueno" <?php echo $revision['estado_revision'] == 'Bueno' ? 'selected' : ''; ?>>Bueno</option>
                                        <option value="Regular" <?php echo $revision['estado_revision'] == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                                        <option value="Malo" <?php echo $revision['estado_revision'] == 'Malo' ? 'selected' : ''; ?>>Malo</option>
                                        <option value="Crítico" <?php echo $revision['estado_revision'] == 'Crítico' ? 'selected' : ''; ?>>Crítico</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Temperatura (°C)</label>
                                    <input type="number" name="temperatura" class="form-control" step="0.1" 
                                           value="<?php echo $revision['temperatura']; ?>" placeholder="35.5">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Voltaje (V)</label>
                                    <input type="number" name="voltaje" class="form-control" step="0.1" 
                                           value="<?php echo $revision['voltaje']; ?>" placeholder="12.0">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Señal (dBm)</label>
                                    <input type="number" name="señal_dbm" class="form-control" step="0.1" 
                                           value="<?php echo $revision['señal_dbm']; ?>" placeholder="-65.5">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Velocidad (Mbps)</label>
                                    <input type="number" name="velocidad_mbps" class="form-control" step="0.1" 
                                           value="<?php echo $revision['velocidad_mbps']; ?>" placeholder="100.0">
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tiempo de Actividad (horas)</label>
                                    <input type="number" name="tiempo_actividad_horas" class="form-control" 
                                           value="<?php echo $revision['tiempo_actividad_horas']; ?>" placeholder="720">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Problemas Detectados</label>
                                    <textarea name="problemas_detectados" class="form-control" rows="3" 
                                              placeholder="Describe cualquier problema encontrado..."><?php echo htmlspecialchars($revision['problemas_detectados']); ?></textarea>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Acciones Realizadas</label>
                                    <textarea name="acciones_realizadas" class="form-control" rows="3" 
                                              placeholder="Describe las acciones correctivas realizadas..."><?php echo htmlspecialchars($revision['acciones_realizadas']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" class="form-control" rows="2" 
                                          placeholder="Observaciones adicionales..."><?php echo htmlspecialchars($revision['observaciones']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="requiere_mantenimiento" 
                                               id="requiere_mantenimiento" <?php echo $revision['requiere_mantenimiento'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="requiere_mantenimiento">
                                            Requiere Mantenimiento
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha Próximo Mantenimiento</label>
                                    <input type="date" name="fecha_proximo_mantenimiento" class="form-control" 
                                           value="<?php echo $revision['fecha_proximo_mantenimiento']; ?>">
                                </div>
                            </div>
                            
                            <div class="text-end">
                                <a href="revisiones.php" class="btn btn-secondary me-2">Cancelar</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Revisión
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>