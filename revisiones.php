<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';

// Procesar eliminación de revisión (con redirección)
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM revisiones WHERE id = ?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_flash'] = 'Revisión eliminada exitosamente.';
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al eliminar revisión: ' . $e->getMessage();
    }
    header("Location: revisiones.php");
    exit();
}

// Mostrar mensajes flash después de la redirección
if (isset($_SESSION['mensaje_flash'])) {
    $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>';
    unset($_SESSION['mensaje_flash']);
}
if (isset($_SESSION['error_flash'])) {
    $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>';
    unset($_SESSION['error_flash']);
}

// Filtros
$filtro_equipo = isset($_GET['equipo']) ? $_GET['equipo'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

$equipos = $pdo->query("SELECT id, nombre FROM equipos ORDER BY nombre")->fetchAll();

$sql = "SELECT r.*, e.nombre as equipo_nombre, e.modelo, e.marca, e.persona_responsable, u.nombre as usuario_nombre FROM revisiones r LEFT JOIN equipos e ON r.equipo_id = e.id LEFT JOIN usuarios u ON r.usuario_id = u.id WHERE 1=1";
$params = [];

if (!empty($filtro_equipo)) { $sql .= " AND r.equipo_id = ?"; $params[] = $filtro_equipo; }
if (!empty($filtro_estado)) { $sql .= " AND r.estado_revision = ?"; $params[] = $filtro_estado; }
if (!empty($filtro_fecha_desde)) { $sql .= " AND DATE(r.fecha_revision) >= ?"; $params[] = $filtro_fecha_desde; }
if (!empty($filtro_fecha_hasta)) { $sql .= " AND DATE(r.fecha_revision) <= ?"; $params[] = $filtro_fecha_hasta; }

$sql .= " ORDER BY r.fecha_revision DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$revisiones = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Revisiones</title>
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
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-header h2 { font-size: 2.5rem; color: var(--text-color); margin-bottom: 2rem; }
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
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #555; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #eef; }
        td { padding: 15px; border-bottom: 1px solid #eef; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-excelente { background-color: #e7f5f2; color: #008a6e; } .bg-bueno { background-color: #eef; color: #5154d9; }
        .bg-regular { background-color: #fff8e1; color: #f59e0b; } .bg-malo, .bg-critico { background-color: #fff1f2; color: #d93749; }
        .table-actions .btn { padding: 8px 12px; border-radius: 20px; }
        .modal-content { border: none; border-radius: var(--border-radius); }
        .modal-header { background: linear-gradient(45deg, #f8f9fa, #e9ecef); border-bottom: 1px solid #ddd; }
        .modal-title { color: var(--text-color); font-weight: 600; }
        .modal-footer { border-top: 1px solid #eef; padding-top: 1rem; }
        /* ===== ESTILOS PARA EL MODAL DE DETALLES ===== */
        .detail-group { margin-bottom: 1.75rem; }
        .detail-group h6 { font-size: 1.1rem; font-weight: 600; color: var(--primary-color); margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; padding-bottom: 0.75rem; border-bottom: 1px solid #eef; }
        .detail-item { display: flex; justify-content: space-between; align-items: center; padding: 0.6rem 0.25rem; font-size: 0.95rem; border-bottom: 1px solid #f5f5f5; }
        .detail-group .detail-item:last-child { border-bottom: none; }
        .detail-item-label { color: #555; font-weight: 500; }
        .detail-item-value { color: var(--text-color); font-weight: 600; text-align: right; }
        .detail-text-block { background-color: #f8f9fa; border-left: 4px solid var(--primary-color); padding: 1rem; border-radius: 0 8px 8px 0; color: #333; white-space: pre-wrap; line-height: 1.6; }
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
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header"><h2>Historial de Revisiones</h2></div>
        <?= $mensajeHtml; ?>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3></div>
            <form method="GET">
                <div class="form-grid">
                    <div class="form-group"><label>Equipo</label><select name="equipo"><option value="">Todos</option><?php foreach ($equipos as $equipo): ?><option value="<?= $equipo['id']; ?>" <?= $filtro_equipo == $equipo['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($equipo['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Estado</label><select name="estado"><option value="">Todos</option><option value="Excelente" <?= $filtro_estado == 'Excelente' ? 'selected' : ''; ?>>Excelente</option><option value="Bueno" <?= $filtro_estado == 'Bueno' ? 'selected' : ''; ?>>Bueno</option><option value="Regular" <?= $filtro_estado == 'Regular' ? 'selected' : ''; ?>>Regular</option><option value="Malo" <?= $filtro_estado == 'Malo' ? 'selected' : ''; ?>>Malo</option><option value="Crítico" <?= $filtro_estado == 'Crítico' ? 'selected' : ''; ?>>Crítico</option></select></div>
                    <div class="form-group"><label>Desde</label><input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde); ?>"></div>
                    <div class="form-group"><label>Hasta</label><input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta); ?>"></div>
                    <div class="form-group d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Buscar</button></div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-list"></i> Lista de Revisiones (<?= count($revisiones); ?>)</h3></div>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Fecha</th><th>Equipo</th><th>Responsable</th><th>Estado</th><th>Técnico</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($revisiones)): ?>
                            <tr><td colspan="6" class="text-center p-5"><p class="text-muted mb-0">No se encontraron revisiones.</p></td></tr>
                        <?php else: ?>
                            <?php foreach ($revisiones as $revision): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></td>
                                    <td><strong><?= htmlspecialchars($revision['equipo_nombre']); ?></strong><br><small class="text-muted"><?= htmlspecialchars($revision['marca'] . ' ' . $revision['modelo']); ?></small></td>
                                    <td><?= htmlspecialchars($revision['persona_responsable'] ?: 'N/A'); ?></td>
                                    <td><span class="badge bg-<?= strtolower($revision['estado_revision']); ?>"><?= $revision['estado_revision']; ?></span></td>
                                    <td><?= htmlspecialchars($revision['usuario_nombre']); ?></td>
                                    <td class="table-actions">
                                        <button type="button" class="btn btn-sm btn-outline-info" title="Ver detalle" data-bs-toggle="modal" data-bs-target="#modalDetalle<?= $revision['id']; ?>"><i class="fas fa-eye"></i></button>
                                        <a href="editar_revision.php?id=<?= $revision['id']; ?>" class="btn btn-sm btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                        <a href="?eliminar=<?= $revision['id']; ?>" class="btn btn-sm btn-outline-danger" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar esta revisión?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== MODALES DE DETALLE ===== -->
    <?php foreach ($revisiones as $revision): ?>
    <div class="modal fade" id="modalDetalle<?= $revision['id']; ?>" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h3"><i class="fas fa-clipboard-check text-primary"></i> Detalle de Revisión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="detail-group">
                        <h6><i class="fas fa-info-circle"></i> Información General</h6>
                        <div class="detail-item"><span class="detail-item-label">Equipo</span><span class="detail-item-value"><?= htmlspecialchars($revision['equipo_nombre']); ?></span></div>
                        <div class="detail-item"><span class="detail-item-label">Marca / Modelo</span><span class="detail-item-value"><?= htmlspecialchars($revision['marca'] . ' ' . $revision['modelo']); ?></span></div>
                        <div class="detail-item"><span class="detail-item-label">Persona Responsable</span><span class="detail-item-value"><?= htmlspecialchars($revision['persona_responsable'] ?: 'N/A'); ?></span></div>
                        <div class="detail-item"><span class="detail-item-label">Fecha</span><span class="detail-item-value"><?= date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></span></div>
                        <div class="detail-item"><span class="detail-item-label">Estado</span><span class="detail-item-value"><span class="badge bg-<?= strtolower($revision['estado_revision']); ?>"><?= $revision['estado_revision']; ?></span></span></div>
                        <div class="detail-item"><span class="detail-item-label">Técnico</span><span class="detail-item-value"><?= htmlspecialchars($revision['usuario_nombre']); ?></span></div>
                        <div class="detail-item"><span class="detail-item-label">Requiere Mantenimiento</span><span class="detail-item-value"><?= $revision['requiere_mantenimiento'] ? '<span class="badge" style="background:#fff8e1;color:#f59e0b;">Sí</span>' : '<span class="badge" style="background:#f1f3f5;color:#495057;">No</span>'; ?></span></div>
                        <?php if ($revision['fecha_proximo_mantenimiento']): ?>
                        <div class="detail-item"><span class="detail-item-label">Próximo Mantenimiento</span><span class="detail-item-value"><?= date('d/m/Y', strtotime($revision['fecha_proximo_mantenimiento'])); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($revision['problemas_detectados'])): ?>
                    <div class="detail-group"><h6><i class="fas fa-exclamation-triangle text-warning"></i> Problemas Detectados</h6><div class="detail-text-block"><?= nl2br(htmlspecialchars($revision['problemas_detectados'])); ?></div></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($revision['acciones_realizadas'])): ?>
                    <div class="detail-group"><h6><i class="fas fa-tools text-success"></i> Acciones Realizadas</h6><div class="detail-text-block"><?= nl2br(htmlspecialchars($revision['acciones_realizadas'])); ?></div></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($revision['observaciones'])): ?>
                    <div class="detail-group"><h6><i class="fas fa-comment"></i> Observaciones</h6><div class="detail-text-block"><?= nl2br(htmlspecialchars($revision['observaciones'])); ?></div></div>
                    <?php endif; ?>
                </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>