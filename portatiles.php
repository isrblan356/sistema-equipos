<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'principal'; // 'principal', 'revisiones', 'repuestos'
$portatil_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        $accion = $_POST['accion'];
        
        // --- LÓGICA PARA PORTÁTILES Y REVISIONES ---
        if ($accion == 'agregar_portatil' || $accion == 'editar_portatil') {
            $nombre_equipo = limpiarDatos($_POST['nombre_equipo']); $usuario_asignado = limpiarDatos($_POST['usuario_asignado']); $marca = limpiarDatos($_POST['marca']); $modelo = limpiarDatos($_POST['modelo']); $numero_serie = limpiarDatos($_POST['numero_serie']); $cpu = limpiarDatos($_POST['cpu']); $ram_gb = intval($_POST['ram_gb']); $almacenamiento_gb = intval($_POST['almacenamiento_gb']); $estado = limpiarDatos($_POST['estado']); $fecha_adquisicion = !empty($_POST['fecha_adquisicion']) ? $_POST['fecha_adquisicion'] : null; $notas = limpiarDatos($_POST['notas']);
            if ($accion == 'agregar_portatil') {
                $stmt = $pdo->prepare("INSERT INTO portatiles (nombre_equipo, usuario_asignado, marca, modelo, numero_serie, cpu, ram_gb, almacenamiento_gb, estado, fecha_adquisicion, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre_equipo, $usuario_asignado, $marca, $modelo, $numero_serie, $cpu, $ram_gb, $almacenamiento_gb, $estado, $fecha_adquisicion, $notas]);
                $_SESSION['mensaje_flash'] = 'Portátil agregado exitosamente.';
            } else {
                $id = intval($_POST['id']);
                $stmt = $pdo->prepare("UPDATE portatiles SET nombre_equipo=?, usuario_asignado=?, marca=?, modelo=?, numero_serie=?, cpu=?, ram_gb=?, almacenamiento_gb=?, estado=?, fecha_adquisicion=?, notas=? WHERE id=?");
                $stmt->execute([$nombre_equipo, $usuario_asignado, $marca, $modelo, $numero_serie, $cpu, $ram_gb, $almacenamiento_gb, $estado, $fecha_adquisicion, $notas, $id]);
                $_SESSION['mensaje_flash'] = 'Portátil actualizado exitosamente.';
            }
        } elseif ($accion == 'eliminar_portatil') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM portatiles WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['mensaje_flash'] = 'Portátil y su historial eliminados.';
        } elseif ($accion == 'agregar_revision') {
            $portatil_id_rev = intval($_POST['portatil_id']); $estado_revision = limpiarDatos($_POST['estado_revision']); $observaciones = limpiarDatos($_POST['observaciones']); $nuevo_estado_portatil = limpiarDatos($_POST['nuevo_estado_portatil']); $tecnico_id = $_SESSION['usuario_id'];
            $pdo->beginTransaction();
            $stmt_rev = $pdo->prepare("INSERT INTO revisiones_portatiles (portatil_id, tecnico_id, estado_revision, observaciones) VALUES (?, ?, ?, ?)");
            $stmt_rev->execute([$portatil_id_rev, $tecnico_id, $estado_revision, $observaciones]);
            $stmt_portatil = $pdo->prepare("UPDATE portatiles SET estado = ?, ultima_revision = NOW() WHERE id = ?");
            $stmt_portatil->execute([$nuevo_estado_portatil, $portatil_id_rev]);
            $pdo->commit();
            $_SESSION['mensaje_flash'] = 'Revisión agregada y estado del portátil actualizado.';
        } 
        
        // --- LÓGICA PARA REPUESTOS ---
        elseif ($accion == 'agregar_repuesto') {
            $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("INSERT INTO repuestos (nombre, cantidad) VALUES (?, ?)");
            $stmt->execute([$nombre, $cantidad]);
            $_SESSION['mensaje_flash'] = 'Repuesto agregado al inventario.';
        } elseif ($accion == 'editar_repuesto') {
            $id = intval($_POST['id']); $nombre = limpiarDatos($_POST['nombre']); $cantidad = intval($_POST['cantidad']);
            $stmt = $pdo->prepare("UPDATE repuestos SET nombre = ?, cantidad = ? WHERE id = ?");
            $stmt->execute([$nombre, $cantidad, $id]);
            $_SESSION['mensaje_flash'] = 'Repuesto actualizado correctamente.';
        } elseif ($accion == 'eliminar_repuesto') {
            $id = intval($_POST['id']);
            $stmt = $pdo->prepare("DELETE FROM repuestos WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['mensaje_flash'] = 'Repuesto eliminado del inventario.';
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
    }
    
    $redirect_url = 'portatiles.php';
    if ($accion == 'agregar_revision') { $redirect_url = "portatiles.php?vista=revisiones&id=" . intval($_POST['portatil_id']); }
    elseif (strpos($accion, 'repuesto') !== false) { $redirect_url = "portatiles.php?vista=repuestos"; }
    header("Location: " . $redirect_url);
    exit();
}

$portatiles = []; $repuestos = []; $revisiones = []; $portatil_actual = null;
if ($vista === 'principal') {
    $portatiles = $pdo->query("SELECT * FROM portatiles ORDER BY nombre_equipo ASC")->fetchAll();
} elseif ($vista === 'revisiones' && $portatil_id > 0) {
    $stmt_portatil = $pdo->prepare("SELECT * FROM portatiles WHERE id = ?"); $stmt_portatil->execute([$portatil_id]);
    $portatil_actual = $stmt_portatil->fetch();
    if (!$portatil_actual) { header("Location: portatiles.php"); exit(); }
    $stmt_revisiones = $pdo->prepare("SELECT r.*, u.nombre as tecnico_nombre FROM revisiones_portatiles r LEFT JOIN usuarios u ON r.tecnico_id = u.id WHERE r.portatil_id = ? ORDER BY r.fecha_revision DESC");
    $stmt_revisiones->execute([$portatil_id]);
    $revisiones = $stmt_revisiones->fetchAll();
} elseif ($vista === 'repuestos') {
    $repuestos = $pdo->query("SELECT * FROM repuestos ORDER BY nombre ASC")->fetchAll();
} elseif ($vista !== 'principal') {
    header("Location: portatiles.php"); exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Hardware</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #0ea5e9; --secondary-color: #3b82f6; --text-color: #334155; --bg-color: #f1f5f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--primary-color); }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-info a { text-decoration: none; color: inherit; font-weight: 500; }
        .logout-btn { background: #fee2e2; color: #ef4444; font-size: 0.9rem; font-weight: 500; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #ef4444; color: white; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #e2e8f0; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 1.5rem; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-sm { padding: 8px 16px; font-size: 0.875rem; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #64748b; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #dcfce7; color: #166534; border-color: #86efac; }
        .error { background-color: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; color: #475569; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        tr:hover { background: #f8fafc; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-operativo { background-color: #dcfce7; color: #166534; } .bg-en-mantenimiento { background-color: #fef9c3; color: #854d0e; }
        .bg-dañado, .bg-de-baja { background-color: #fee2e2; color: #991b1b; }
        .bg-bueno { background-color: #dcfce7; color: #166534; } .bg-regular { background-color: #fef9c3; color: #854d0e; } .bg-malo { background-color: #fee2e2; color: #991b1b; }
        .table-actions { display: flex; gap: 0.5rem; }
        .table-actions .btn { padding: 8px 12px; border-radius: 20px; }
        .modal-content { border: none; border-radius: var(--border-radius); }
        .modal-header { border-bottom: none; }
        .modal-footer { border-top: 1px solid #e2e8f0; padding-top: 1rem; }
        .modal-body .row > div { margin-bottom: 1rem; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 2rem; }
        .nav-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; font-weight: 600; padding: 0.75rem 1.25rem; text-decoration: none; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-laptop"></i> Gestión de Hardware</h1>
            <div class="user-info">
                <a href="dashboard.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
                <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?= $mensajeHtml; ?>
        <ul class="nav nav-tabs">
            <li class="nav-item"><a class="nav-link <?= ($vista === 'principal' || $vista === 'revisiones') ? 'active' : '' ?>" href="portatiles.php?vista=principal"><i class="fas fa-laptop"></i> Inventario de Portátiles</a></li>
            <li class="nav-item"><a class="nav-link <?= $vista === 'repuestos' ? 'active' : '' ?>" href="portatiles.php?vista=repuestos"><i class="fas fa-tools"></i> Inventario de Repuestos</a></li>
        </ul>

        <?php if ($vista === 'principal'): ?>
            <div class="page-header"><h2>Inventario General de Portátiles</h2><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarPortatil"><i class="fas fa-plus"></i> Agregar Portátil</button></div>
            <div class="card">
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Nombre</th><th>Usuario Asignado</th><th>Marca/Modelo</th><th>Serie</th><th>Estado</th><th>Última Revisión</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php if (empty($portatiles)): ?>
                                <tr><td colspan="7" class="text-center p-5">No hay portátiles registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($portatiles as $portatil): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($portatil['nombre_equipo']); ?></strong></td>
                                        <td><?= htmlspecialchars($portatil['usuario_asignado']); ?></td>
                                        <td><?= htmlspecialchars($portatil['marca'] . ' ' . $portatil['modelo']); ?></td>
                                        <td><code><?= htmlspecialchars($portatil['numero_serie']); ?></code></td>
                                        <td><span class="badge bg-<?= strtolower(str_replace(' ', '-', $portatil['estado'])); ?>"><?= $portatil['estado']; ?></span></td>
                                        <td><?= $portatil['ultima_revision'] ? date('d/m/Y', strtotime($portatil['ultima_revision'])) : 'Nunca'; ?></td>
                                        <td class="table-actions">
                                            <a href="?vista=revisiones&id=<?= $portatil['id']; ?>" class="btn btn-sm btn-outline-info" title="Ver Revisiones"><i class="fas fa-history"></i></a>
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarPortatil<?= $portatil['id'] ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este portátil y todo su historial?');"><input type="hidden" name="id" value="<?= $portatil['id'] ?>"><input type="hidden" name="accion" value="eliminar_portatil"><button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($vista === 'revisiones' && isset($portatil_actual)): ?>
            <div class="page-header">
                <a href="portatiles.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Inventario</a>
                <h2 style="flex-grow: 1; text-align: center;">Historial de: <?= htmlspecialchars($portatil_actual['nombre_equipo']); ?></h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarRevision"><i class="fas fa-plus"></i> Nueva Revisión</button>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-laptop-medical"></i> Resumen del Equipo</h3></div>
                <div class="summary-grid">
                    <div><strong>Usuario Asignado:</strong><br><?= htmlspecialchars($portatil_actual['usuario_asignado']); ?></div>
                    <div><strong>Marca/Modelo:</strong><br><?= htmlspecialchars($portatil_actual['marca'] . ' / ' . $portatil_actual['modelo']); ?></div>
                    <div><strong>Estado Actual:</strong><br><span class="badge bg-<?= strtolower(str_replace(' ', '-', $portatil_actual['estado'])); ?>"><?= $portatil_actual['estado']; ?></span></div>
                    <div><strong>Última Revisión:</strong><br><?= $portatil_actual['ultima_revision'] ? date('d/m/Y H:i', strtotime($portatil_actual['ultima_revision'])) : 'Nunca'; ?></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3><i class="fas fa-history"></i> Historial de Revisiones</h3></div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Fecha</th><th>Técnico</th><th>Estado Reportado</th><th>Observaciones</th></tr></thead>
                        <tbody>
                            <?php if (empty($revisiones)): ?>
                                <tr><td colspan="4" class="text-center p-5">Este portátil no tiene revisiones registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($revisiones as $revision): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($revision['fecha_revision'])); ?></td>
                                        <td><?= htmlspecialchars($revision['tecnico_nombre'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-<?= strtolower($revision['estado_revision']); ?>"><?= $revision['estado_revision']; ?></span></td>
                                        <td><?= nl2br(htmlspecialchars($revision['observaciones'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ($vista === 'repuestos'): ?>
            <div class="page-header"><h2>Inventario de Repuestos</h2><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarRepuesto"><i class="fas fa-plus"></i> Agregar Repuesto</button></div>
            <div class="card">
                <div style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>Nombre del Repuesto</th><th>Cantidad en Stock</th><th>Acciones</th></tr></thead>
                        <tbody>
                            <?php if (empty($repuestos)): ?>
                                <tr><td colspan="3" class="text-center p-5">No hay repuestos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($repuestos as $repuesto): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($repuesto['nombre']); ?></strong></td>
                                        <td><h3><?= htmlspecialchars($repuesto['cantidad']); ?></h3></td>
                                        <td class="table-actions">
                                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarRepuesto<?= $repuesto['id'] ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este repuesto?');"><input type="hidden" name="id" value="<?= $repuesto['id'] ?>"><input type="hidden" name="accion" value="eliminar_repuesto"><button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modales de Portátiles -->
    <?php if ($vista === 'principal' || $vista === 'revisiones'): ?>
        <div class="modal fade" id="modalAgregarPortatil" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Agregar Nuevo Portátil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="agregar_portatil">
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Nombre del Equipo *</label><input type="text" name="nombre_equipo" class="form-control" required placeholder="Ej: MKT-01-HP"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Usuario Asignado *</label><input type="text" name="usuario_asignado" class="form-control" required></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Marca</label><input type="text" name="marca" class="form-control"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Modelo</label><input type="text" name="modelo" class="form-control"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Número de Serie *</label><input type="text" name="numero_serie" class="form-control" required></div></div>
                                <div class="col-md-6"><div class="form-group"><label>CPU</label><input type="text" name="cpu" class="form-control" placeholder="Ej: Core i7-1165G7"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>RAM (GB)</label><input type="number" name="ram_gb" class="form-control" placeholder="Ej: 16"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Almacenamiento (GB)</label><input type="number" name="almacenamiento_gb" class="form-control" placeholder="Ej: 512"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Estado Inicial *</label><select name="estado" class="form-select" required><option value="Operativo">Operativo</option><option value="En Mantenimiento">En Mantenimiento</option><option value="Dañado">Dañado</option><option value="De Baja">De Baja</option></select></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Fecha de Adquisición</label><input type="date" name="fecha_adquisicion" class="form-control"></div></div>
                                <div class="col-12"><div class="form-group"><label>Notas Adicionales</label><textarea name="notas" rows="3" class="form-control"></textarea></div></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Portátil</button></div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($vista === 'principal' && !empty($portatiles)): foreach ($portatiles as $portatil): ?>
            <div class="modal fade" id="modalEditarPortatil<?= $portatil['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Editar Portátil</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="accion" value="editar_portatil"><input type="hidden" name="id" value="<?= $portatil['id'] ?>">
                                <div class="row">
                                    <div class="col-md-6"><div class="form-group"><label>Nombre del Equipo *</label><input type="text" name="nombre_equipo" class="form-control" required value="<?= htmlspecialchars($portatil['nombre_equipo']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Usuario Asignado *</label><input type="text" name="usuario_asignado" class="form-control" required value="<?= htmlspecialchars($portatil['usuario_asignado']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Marca</label><input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($portatil['marca']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Modelo</label><input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($portatil['modelo']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Número de Serie *</label><input type="text" name="numero_serie" class="form-control" required value="<?= htmlspecialchars($portatil['numero_serie']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>CPU</label><input type="text" name="cpu" class="form-control" value="<?= htmlspecialchars($portatil['cpu']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>RAM (GB)</label><input type="number" name="ram_gb" class="form-control" value="<?= htmlspecialchars($portatil['ram_gb']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Almacenamiento (GB)</label><input type="number" name="almacenamiento_gb" class="form-control" value="<?= htmlspecialchars($portatil['almacenamiento_gb']) ?>"></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Estado *</label><select name="estado" class="form-select" required>
                                        <option value="Operativo" <?= $portatil['estado'] == 'Operativo' ? 'selected' : '' ?>>Operativo</option>
                                        <option value="En Mantenimiento" <?= $portatil['estado'] == 'En Mantenimiento' ? 'selected' : '' ?>>En Mantenimiento</option>
                                        <option value="Dañado" <?= $portatil['estado'] == 'Dañado' ? 'selected' : '' ?>>Dañado</option>
                                        <option value="De Baja" <?= $portatil['estado'] == 'De Baja' ? 'selected' : '' ?>>De Baja</option>
                                    </select></div></div>
                                    <div class="col-md-6"><div class="form-group"><label>Fecha de Adquisición</label><input type="date" name="fecha_adquisicion" class="form-control" value="<?= htmlspecialchars($portatil['fecha_adquisicion']) ?>"></div></div>
                                    <div class="col-12"><div class="form-group"><label>Notas Adicionales</label><textarea name="notas" rows="3" class="form-control"><?= htmlspecialchars($portatil['notas']) ?></textarea></div></div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; endif; ?>
        <?php if ($vista === 'revisiones' && isset($portatil_actual)): ?>
            <div class="modal fade" id="modalAgregarRevision" tabindex="-1">
                 <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Agregar Nueva Revisión</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="accion" value="agregar_revision"><input type="hidden" name="portatil_id" value="<?= $portatil_actual['id'] ?>">
                                <div class="form-group"><label>Estado Reportado</label><select name="estado_revision" class="form-select" required><option value="Bueno">Bueno</option><option value="Regular">Regular</option><option value="Malo">Malo</option></select></div>
                                <div class="form-group"><label>Nuevo Estado General del Portátil</label><select name="nuevo_estado_portatil" class="form-select" required>
                                    <option value="Operativo" <?= $portatil_actual['estado'] == 'Operativo' ? 'selected' : '' ?>>Operativo</option>
                                    <option value="En Mantenimiento" <?= $portatil_actual['estado'] == 'En Mantenimiento' ? 'selected' : '' ?>>En Mantenimiento</option>
                                    <option value="Dañado" <?= $portatil_actual['estado'] == 'Dañado' ? 'selected' : '' ?>>Dañado</option>
                                    <option value="De Baja" <?= $portatil_actual['estado'] == 'De Baja' ? 'selected' : '' ?>>De Baja</option>
                                </select></div>
                                <div class="form-group"><label>Observaciones y Acciones Realizadas</label><textarea name="observaciones" rows="4" class="form-control" required></textarea></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Revisión</button></div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Modales para Repuestos -->
    <?php if ($vista === 'repuestos'): ?>
        <div class="modal fade" id="modalAgregarRepuesto" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Agregar Nuevo Repuesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="agregar_repuesto">
                            <div class="form-group"><label>Nombre del Repuesto *</label><input type="text" name="nombre" class="form-control" required></div>
                            <div class="form-group"><label>Cantidad Inicial *</label><input type="number" name="cantidad" class="form-control" required min="0" value="0"></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php if (!empty($repuestos)): foreach ($repuestos as $repuesto): ?>
        <div class="modal fade" id="modalEditarRepuesto<?= $repuesto['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Editar Repuesto</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="editar_repuesto">
                            <input type="hidden" name="id" value="<?= $repuesto['id'] ?>">
                            <div class="form-group"><label>Nombre del Repuesto *</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($repuesto['nombre']) ?>" required></div>
                            <div class="form-group"><label>Cantidad en Stock *</label><input type="number" name="cantidad" class="form-control" value="<?= htmlspecialchars($repuesto['cantidad']) ?>" required min="0"></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>