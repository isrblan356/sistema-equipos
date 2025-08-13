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
        
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO revisiones (equipo_id, usuario_id, estado_revision, temperatura, voltaje, señal_dbm, velocidad_mbps, tiempo_actividad_horas, problemas_detectados, acciones_realizadas, observaciones, requiere_mantenimiento, fecha_proximo_mantenimiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$equipo_id, $_SESSION['usuario_id'], $estado_revision, $temperatura, $voltaje, $señal_dbm, $velocidad_mbps, $tiempo_actividad_horas, $problemas_detectados, $acciones_realizadas, $observaciones, $requiere_mantenimiento, $fecha_proximo_mantenimiento]);
        
        // Actualizar estado del equipo
        $nuevo_estado = $requiere_mantenimiento ? 'Mantenimiento' : 'Activo';
        $stmt_update = $pdo->prepare("UPDATE equipos SET estado = ? WHERE id = ?");
        $stmt_update->execute([$nuevo_estado, $equipo_id]);

        $pdo->commit();
        
        $_SESSION['mensaje_flash'] = 'Revisión registrada exitosamente.';
        header("Location: revisiones.php?equipo=" . $equipo_id); // Redirigir al historial
        exit();
        
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $mensajeHtml = '<div class="error">Error al registrar revisión: ' . $e->getMessage() . '</div>';
    }
}

// Obtener última revisión del equipo para mostrarla
$stmt = $pdo->prepare("SELECT * FROM revisiones WHERE equipo_id = ? ORDER BY fecha_revision DESC LIMIT 1");
$stmt->execute([$equipo_id]);
$ultima_revision = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revisar Equipo: <?= htmlspecialchars($equipo['nombre']) ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--primary-color); }
        .user-info a { text-decoration: none; color: inherit; font-weight: 500; }
        .logout-btn { background: #ffeef0; color: #d93749; font-size: 0.9rem; font-weight: 500; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #d93749; color: white; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; color: var(--text-color); }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #eef; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .card-header h3 { font-size: 1.5rem; color: var(--text-color); display: flex; align-items: center; gap: 10px; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #e7f5f2; color: #008a6e; border-color: #a3e9d8; }
        .error { background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
        .summary-grid div { background-color: #f8f9fa; padding: 1rem; border-radius: 8px; }
        .summary-grid strong { display: block; color: #555; font-size: 0.9rem; margin-bottom: 0.25rem; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-success { background-color: #e7f5f2; color: #008a6e; } .bg-warning { background-color: #fff8e1; color: #f59e0b; }
        .bg-danger { background-color: #fff1f2; color: #d93749; }
        .form-check-input { width: 1.5em; height: 1.5em; }
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
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Registrar Nueva Revisión</h2>
            <a href="equipos.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver a Equipos</a>
        </div>
        <?= $mensajeHtml; ?>
        <div class="card mb-4">
            <div class="card-header"><h3><i class="fas fa-info-circle"></i> Información del Equipo</h3></div>
            <div class="summary-grid">
                <div><strong>Nombre:</strong> <?= htmlspecialchars($equipo['nombre']); ?></div>
                <div><strong>Tipo:</strong> <?= htmlspecialchars($equipo['tipo_nombre']); ?></div>
                <div><strong>Marca/Modelo:</strong> <?= htmlspecialchars($equipo['marca'] . ' / ' . $equipo['modelo']); ?></div>
                <div><strong>Serie:</strong> <code><?= htmlspecialchars($equipo['numero_serie']); ?></code></div>
                <div><strong>Ubicación:</strong> <?= htmlspecialchars($equipo['ubicacion']); ?></div>
                <div><strong>Estado Actual:</strong> <span class="badge bg-<?= strtolower($equipo['estado']); ?>"><?= $equipo['estado']; ?></span></div>
            </div>
        </div>

        <?php if ($ultima_revision): ?>
        <div class="card mb-4">
            <div class="card-header"><h3><i class="fas fa-history"></i> Datos de la Última Revisión</h3></div>
            <div class="summary-grid">
                <div><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($ultima_revision['fecha_revision'])); ?></div>
                <div><strong>Estado:</strong> <span class="badge bg-<?= strtolower($ultima_revision['estado_revision']); ?>"><?= $ultima_revision['estado_revision']; ?></span></div>
                <div><strong>Temperatura:</strong> <?= $ultima_revision['temperatura'] ? $ultima_revision['temperatura'] . '°C' : 'N/A'; ?></div>
                <div><strong>Voltaje:</strong> <?= $ultima_revision['voltaje'] ? $ultima_revision['voltaje'] . 'V' : 'N/A'; ?></div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-plus-circle"></i> Formulario de Nueva Revisión</h3></div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group"><label>Estado General en esta Revisión *</label><select name="estado_revision" class="form-select" required><option value="">Seleccionar...</option><option value="Excelente">Excelente</option><option value="Bueno">Bueno</option><option value="Regular">Regular</option><option value="Malo">Malo</option><option value="Crítico">Crítico</option></select></div>
                    <div class="form-group"><label>Temperatura (°C)</label><input type="number" name="temperatura" class="form-control" step="0.1" placeholder="Ej: 35.5"></div>
                    <div class="form-group"><label>Voltaje (V)</label><input type="number" name="voltaje" class="form-control" step="0.1" placeholder="Ej: 12.0"></div>
                    <div class="form-group"><label>Señal (dBm)</label><input type="number" name="señal_dbm" class="form-control" placeholder="Ej: -65"></div>
                    <div class="form-group"><label>Velocidad (Mbps)</label><input type="number" name="velocidad_mbps" class="form-control" step="0.1" placeholder="Ej: 100.0"></div>
                    <div class="form-group"><label>Tiempo de Actividad (horas)</label><input type="number" name="tiempo_actividad_horas" class="form-control" placeholder="Ej: 720"></div>
                </div>
                <div class="form-grid">
                    <div class="form-group"><label>Problemas Detectados</label><textarea name="problemas_detectados" class="form-control" rows="3" placeholder="Describe cualquier problema..."></textarea></div>
                    <div class="form-group"><label>Acciones Realizadas</label><textarea name="acciones_realizadas" class="form-control" rows="3" placeholder="Describe las acciones correctivas..."></textarea></div>
                </div>
                <div class="form-group"><label>Observaciones Adicionales</label><textarea name="observaciones" class="form-control" rows="2"></textarea></div>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <div class="form-check form-switch fs-5"><input class="form-check-input" type="checkbox" name="requiere_mantenimiento" id="requiere_mantenimiento"><label class="form-check-label" for="requiere_mantenimiento">¿Requiere Mantenimiento?</label></div>
                    </div>
                    <div class="col-md-6 form-group"><label>Fecha Próximo Mantenimiento</label><input type="date" name="fecha_proximo_mantenimiento" class="form-control"></div>
                </div>
                <div class="text-end mt-3"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Revisión</button></div>
            </form>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>