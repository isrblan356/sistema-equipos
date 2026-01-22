<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';
$equipo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$equipo_id) {
    header('Location: equipos.php');
    exit;
}

// Obtener información del equipo
$stmt = $pdo->prepare("SELECT e.*, te.nombre as tipo_nombre FROM equipos e LEFT JOIN tipos_equipo te ON e.tipo_equipo_id = te.id WHERE e.id = ?");
$stmt->execute([$equipo_id]);
$equipo = $stmt->fetch();
if (!$equipo) {
    $_SESSION['error_flash'] = 'Equipo no encontrado.';
    header('Location: equipos.php');
    exit;
}

// Procesar formulario de nueva revisión
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $estado_revision = $_POST['estado_revision'];
        $problemas_detectados = limpiarDatos($_POST['problemas_detectados']);
        $acciones_realizadas = limpiarDatos($_POST['acciones_realizadas']);
        $observaciones = limpiarDatos($_POST['observaciones']);
        $requiere_mantenimiento = isset($_POST['requiere_mantenimiento']) ? 1 : 0;
        $fecha_proximo_mantenimiento = !empty($_POST['fecha_proximo_mantenimiento']) ? $_POST['fecha_proximo_mantenimiento'] : null;
        
        $pdo->beginTransaction();

        $stmt_insert = $pdo->prepare("INSERT INTO revisiones (equipo_id, usuario_id, estado_revision, problemas_detectados, acciones_realizadas, observaciones, requiere_mantenimiento, fecha_proximo_mantenimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_insert->execute([$equipo_id, $_SESSION['usuario_id'], $estado_revision, $problemas_detectados, $acciones_realizadas, $observaciones, $requiere_mantenimiento, $fecha_proximo_mantenimiento]);
        
        // Solo actualiza el estado si se marca explícitamente que requiere mantenimiento
        if ($requiere_mantenimiento) {
            $stmt_update = $pdo->prepare("UPDATE equipos SET estado = 'Mantenimiento' WHERE id = ?");
            $stmt_update->execute([$equipo_id]);
        }

        $pdo->commit();
        
        $_SESSION['mensaje_flash'] = 'Revisión registrada exitosamente.';
        header("Location: revisiones.php");
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $mensajeHtml = '<div class="error">Error al registrar revisión: ' . $e->getMessage() . '</div>';
    }
}

// Obtener última revisión del equipo para mostrarla
$stmt_last = $pdo->prepare("SELECT * FROM revisiones WHERE equipo_id = ? ORDER BY fecha_revision DESC LIMIT 1");
$stmt_last->execute([$equipo_id]);
$ultima_revision = $stmt_last->fetch();

// Mapa de clases para los badges de estado
$estado_clases = [
    'Activo' => 'success', 'Inactivo' => 'secondary', 'Mantenimiento' => 'warning', 'Dañado' => 'danger',
    'Excelente' => 'success', 'Bueno' => 'primary', 'Regular' => 'warning', 'Malo' => 'danger', 'Crítico' => 'danger'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Equipo: <?= htmlspecialchars($equipo['nombre']) ?></title>
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
        
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.5rem; border-radius: 10px; }
        .info-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .info-label { font-size: 0.85rem; color: #666; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
        .info-value { font-size: 1.1rem; color: var(--text-color); font-weight: 600; }
        
        .last-revision-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; background: #fffbeb; padding: 1.5rem; border-radius: 10px; border: 2px solid #fde68a; }
        .last-revision-item { display: flex; flex-direction: column; gap: 0.25rem; }
        .last-revision-label { font-size: 0.8rem; color: #92400e; font-weight: 500; text-transform: uppercase; }
        .last-revision-value { font-size: 1rem; color: #78350f; font-weight: 600; }
        
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
        .checkbox-helper { font-size: 0.85rem; color: #666; margin-top: 0.25rem; font-style: italic; }
        
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid #eef; margin-top: 2rem; }
        
        .badge { font-size: 0.85rem; padding: 0.4em 0.9em; border-radius: 20px; font-weight: 600; display: inline-block; }
        .bg-excelente, .bg-success { background-color: #e7f5f2; color: #008a6e; }
        .bg-bueno, .bg-primary { background-color: #eef; color: #5154d9; }
        .bg-regular, .bg-warning { background-color: #fff8e1; color: #f59e0b; }
        .bg-malo, .bg-critico, .bg-danger { background-color: #fff1f2; color: #d93749; }
        .bg-secondary { background-color: #f1f3f5; color: #495057; }
        
        .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .helper-text { font-size: 0.85rem; color: #666; margin-top: 0.5rem; font-style: italic; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav active" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
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
            <h2><i class="fas fa-tasks"></i> Registrar Revisión</h2>
            <div class="breadcrumb">
                <a href="dashboard.php">Inicio</a>
                <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                <a href="equipos.php">Equipos</a>
                <i class="fas fa-chevron-right" style="font-size: 0.7rem;"></i>
                <span>Nueva Revisión</span>
            </div>
        </div>
        
        <?= $mensajeHtml; ?>

        <!-- Información del Equipo -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Equipo Seleccionado</h3>
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Nombre del Equipo</span>
                    <span class="info-value"><?= htmlspecialchars($equipo['nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tipo</span>
                    <span class="info-value"><?= htmlspecialchars($equipo['tipo_nombre']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Marca / Modelo</span>
                    <span class="info-value"><?= htmlspecialchars($equipo['marca'] . ' ' . $equipo['modelo']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Número de Serie</span>
                    <span class="info-value"><code style="background:#f1f3f5;padding:4px 8px;border-radius:4px;font-size:0.95rem;"><?= htmlspecialchars($equipo['numero_serie']); ?></code></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Persona Responsable</span>
                    <span class="info-value"><?= htmlspecialchars($equipo['persona_responsable'] ?: 'N/A'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Estado Actual</span>
                    <span class="info-value">
                        <span class="badge bg-<?= $estado_clases[$equipo['estado']] ?? 'secondary'; ?>">
                            <?= htmlspecialchars($equipo['estado']); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Última Revisión -->
        <?php if ($ultima_revision): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Última Revisión Registrada</h3>
            </div>
            <div class="last-revision-grid">
                <div class="last-revision-item">
                    <span class="last-revision-label">Fecha</span>
                    <span class="last-revision-value"><?= date('d/m/Y H:i', strtotime($ultima_revision['fecha_revision'])); ?></span>
                </div>
                <div class="last-revision-item">
                    <span class="last-revision-label">Estado</span>
                    <span class="last-revision-value">
                        <span class="badge bg-<?= $estado_clases[$ultima_revision['estado_revision']] ?? 'secondary'; ?>">
                            <?= htmlspecialchars($ultima_revision['estado_revision']); ?>
                        </span>
                    </span>
                </div>
                <div class="last-revision-item">
                    <span class="last-revision-label">Requiere Mantenimiento</span>
                    <span class="last-revision-value"><?= $ultima_revision['requiere_mantenimiento'] ? 'Sí' : 'No'; ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Formulario de Nueva Revisión -->
        <form method="POST" id="revisionForm">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Datos de la Nueva Revisión</h3>
                </div>

                <div class="form-group">
                    <label>Estado General<span class="required">*</span></label>
                    <select name="estado_revision" required>
                        <option value="">Seleccionar estado...</option>
                        <option value="Excelente">Excelente</option>
                        <option value="Bueno">Bueno</option>
                        <option value="Regular">Regular</option>
                        <option value="Malo">Malo</option>
                        <option value="Crítico">Crítico</option>
                    </select>
                    <div class="helper-text">Evalúa el estado general del equipo durante esta revisión</div>
                </div>

                <div class="form-group">
                    <label>Problemas Detectados</label>
                    <textarea name="problemas_detectados" placeholder="Describe detalladamente los problemas encontrados durante la revisión..."></textarea>
                    <div class="helper-text">Incluye síntomas, errores o anomalías observadas</div>
                </div>

                <div class="form-group">
                    <label>Acciones Realizadas</label>
                    <textarea name="acciones_realizadas" placeholder="Describe las acciones correctivas y mantenimientos realizados..."></textarea>
                    <div class="helper-text">Detalla las soluciones aplicadas, ajustes realizados o reparaciones efectuadas</div>
                </div>

                <div class="form-group">
                    <label>Observaciones</label>
                    <textarea name="observaciones" placeholder="Observaciones adicionales, recomendaciones o notas importantes..."></textarea>
                    <div class="helper-text">Información complementaria que consideres relevante</div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <div class="checkbox-wrapper">
                            <input type="checkbox" name="requiere_mantenimiento" id="requiere_mantenimiento">
                            <label for="requiere_mantenimiento">
                                <i class="fas fa-tools"></i> Requiere Mantenimiento Programado
                            </label>
                        </div>
                        <div class="checkbox-helper">Si se activa, el estado del equipo cambiará a "Mantenimiento"</div>
                    </div>

                    <div class="form-group">
                        <label>Fecha Próximo Mantenimiento</label>
                        <input type="date" name="fecha_proximo_mantenimiento">
                    </div>
                </div>

                <div class="form-actions">
                    <a href="equipos.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Guardar Revisión
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('revisionForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form && submitBtn) {
            form.addEventListener('submit', function() {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            });
        }
    });
    </script>
</body>
</html>