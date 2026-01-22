<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
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
        $_SESSION['error_flash'] = 'Revisión no encontrada';
        header('Location: revisiones.php');
        exit;
    }
} else {
    header('Location: revisiones.php');
    exit;
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $estado_revision = $_POST['estado_revision'];
    $problemas_detectados = limpiarDatos($_POST['problemas_detectados']);
    $acciones_realizadas = limpiarDatos($_POST['acciones_realizadas']);
    $observaciones = limpiarDatos($_POST['observaciones']);
    $requiere_mantenimiento = isset($_POST['requiere_mantenimiento']) ? 1 : 0;
    $fecha_proximo_mantenimiento = !empty($_POST['fecha_proximo_mantenimiento']) ? $_POST['fecha_proximo_mantenimiento'] : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE revisiones SET 
                estado_revision = ?, 
                problemas_detectados = ?, 
                acciones_realizadas = ?, 
                observaciones = ?, 
                requiere_mantenimiento = ?, 
                fecha_proximo_mantenimiento = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $estado_revision,
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
        
        $_SESSION['mensaje_flash'] = 'Revisión actualizada exitosamente';
        header('Location: revisiones.php');
        exit();
        
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al actualizar revisión: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Revisión - Sistema de Equipos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--primary-color); }
        
        .nav-buttons { display: flex; align-items: center; gap: 10px; }
        .btn-nav { font-size: 0.9rem; font-weight: 500; color: #555; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .btn-nav:hover { background-color: #eef; color: var(--primary-color); }
        .btn-nav.active { background: var(--primary-color); color: white; }
        .user-info { display: flex; align-items: center; gap: 10px; padding-left: 15px; border-left: 1px solid #ddd; }
        .logout-btn { background: #ffeef0; color: #d93749; }
        .logout-btn:hover { background: #d93749; color: white; }
        
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; color: var(--text-color); margin-bottom: 0.5rem; }
        .breadcrumb { display: flex; align-items: center; gap: 0.5rem; color: #666; font-size: 0.9rem; }
        .breadcrumb a { color: var(--primary-color); text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #eef; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .card-header h3 { font-size: 1.5rem; color: var(--text-color); display: flex; align-items: center; gap: 10px; }
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem; }
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .info-label { font-size: 0.85rem; color: #666; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 1.1rem; color: var(--text-color); font-weight: 600; }
        
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; font-size: 0.95rem; }
        .form-group label .required { color: #d93749; margin-left: 3px; }
        
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.3s ease; background: white; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
        
        .checkbox-wrapper { display: flex; align-items: center; gap: 10px; padding: 12px; background: #f8f9fa; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
        .checkbox-wrapper:hover { background: #e9ecef; }
        .checkbox-wrapper input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; }
        .checkbox-wrapper label { cursor: pointer; margin: 0; font-weight: 500; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid #eef; margin-top: 2rem; }
        
        .badge { font-size: 0.85rem; padding: 0.4em 0.9em; border-radius: 20px; font-weight: 600; display: inline-block; }
        .bg-excelente { background-color: #e7f5f2; color: #008a6e; }
        .bg-bueno { background-color: #eef; color: #5154d9; }
        .bg-regular { background-color: #fff8e1; color: #f59e0b; }
        .bg-malo, .bg-critico { background-color: #fff1f2; color: #d93749; }
        
        .helper-text { font-size: 0.85rem; color: #666; margin-top: 0.5rem; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav active" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                <a class="btn-nav" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <div class="user-info">
                    <a href="perfil.php" style="text-decoration:none; color:inherit;">
                        <i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                    </a>
                </div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-edit"></i> Editar Revisión</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a>
                <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                <a href="revisiones.php">Revisiones</a>
                <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                <span>Editar</span>
            </div>
        </div>

        <!-- Información del Equipo -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Información del Equipo</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nombre del Equipo</span>
                    <span class="info-value"><?= htmlspecialchars($revision['equipo_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo</span>
                    <span class="info-value"><?= htmlspecialchars($revision['tipo_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Marca / Modelo</span>
                    <span class="info-value"><?= htmlspecialchars($revision['marca'] . ' ' . $revision['modelo']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Fecha de Revisión</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado Actual</span>
                    <span class="info-value">
                        <span class="badge bg-<?= strtolower($revision['estado_revision']); ?>">
                            <?= $revision['estado_revision']; ?>
                        </span>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Técnico Responsable</span>
                    <span class="info-value"><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span>
                </div>
            </div>
        </div>

        <!-- Formulario de Edición -->
        <form method="POST">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Datos de la Revisión</h3>
                </div>

                <div class="form-group">
                    <label>Estado General<span class="required">*</span></label>
                    <select name="estado_revision" required>
                        <option value="">Seleccionar estado...</option>
                        <option value="Excelente" <?= $revision['estado_revision'] == 'Excelente' ? 'selected' : ''; ?>>Excelente</option>
                        <option value="Bueno" <?= $revision['estado_revision'] == 'Bueno' ? 'selected' : ''; ?>>Bueno</option>
                        <option value="Regular" <?= $revision['estado_revision'] == 'Regular' ? 'selected' : ''; ?>>Regular</option>
                        <option value="Malo" <?= $revision['estado_revision'] == 'Malo' ? 'selected' : ''; ?>>Malo</option>
                        <option value="Crítico" <?= $revision['estado_revision'] == 'Crítico' ? 'selected' : ''; ?>>Crítico</option>
                    </select>
                    <div class="helper-text">Evalúa el estado general del equipo después de la revisión</div>
                </div>

                <div class="form-group">
                    <label>Problemas Detectados</label>
                    <textarea name="problemas_detectados" placeholder="Describe detalladamente los problemas encontrados durante la revisión..."><?= htmlspecialchars($revision['problemas_detectados']); ?></textarea>
                    <div class="helper-text">Incluye síntomas, errores o anomalías observadas</div>
                </div>

                <div class="form-group">
                    <label>Acciones Realizadas</label>
                    <textarea name="acciones_realizadas" placeholder="Describe las acciones correctivas y mantenimientos realizados..."><?= htmlspecialchars($revision['acciones_realizadas']); ?></textarea>
                    <div class="helper-text">Detalla las soluciones aplicadas, ajustes realizados o reparaciones efectuadas</div>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" placeholder="Observaciones adicionales, recomendaciones o notas importantes..."><?= htmlspecialchars($revision['observaciones']); ?></textarea>
                    <div class="helper-text">Información complementaria que consideres relevante</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="requiere_mantenimiento" id="requiere_mantenimiento" 
                                   <?= $revision['requiere_mantenimiento'] ? 'checked' : ''; ?>>
                            <label for="requiere_mantenimiento">
                                <i class="fas fa-tools"></i> Requiere Mantenimiento Programado
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Fecha Próximo Mantenimiento</label>
                        <input type="date" name="fecha_proximo_mantenimiento" 
                               value="<?= $revision['fecha_proximo_mantenimiento']; ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="revisiones.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Revisión
                    </button>
                </div>
            </div>
        </form>
    </div>
</body>
</html>