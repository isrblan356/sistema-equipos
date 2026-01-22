<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';

// Procesar eliminación y redirigir
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM equipos WHERE id = ?");
        $stmt->execute([$_GET['eliminar']]);
        $_SESSION['mensaje_flash'] = 'Equipo eliminado exitosamente.';
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al eliminar equipo: ' . $e->getMessage();
    }
    header("Location: equipos.php");
    exit();
}

// Procesar formulario de nuevo equipo y redirigir
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']); // AGREGADO
    $modelo = limpiarDatos($_POST['modelo']);
    $marca = limpiarDatos($_POST['marca']);
    $numero_serie = limpiarDatos($_POST['numero_serie']);
    $tipo_equipo_id = $_POST['tipo_equipo_id'];
    $ubicacion = limpiarDatos($_POST['ubicacion']);
    $persona_responsable = limpiarDatos($_POST['persona_responsable']);
    $ip_address = limpiarDatos($_POST['ip_address']);
    $estado = $_POST['estado'];
    $fecha_instalacion = !empty($_POST['fecha_instalacion']) ? $_POST['fecha_instalacion'] : null;
    
    try {
        // MODIFICADO: Agregado campo descripcion
        $stmt = $pdo->prepare("INSERT INTO equipos (nombre, descripcion, modelo, marca, numero_serie, tipo_equipo_id, ubicacion, persona_responsable, ip_address, estado, fecha_instalacion, usuario_registro_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $modelo, $marca, $numero_serie, $tipo_equipo_id, $ubicacion, $persona_responsable, $ip_address, $estado, $fecha_instalacion, $_SESSION['usuario_id']]);
        $_SESSION['mensaje_flash'] = 'Equipo agregado exitosamente.';
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al agregar equipo: ' . $e->getMessage();
    }
    header("Location: equipos.php");
    exit();
}

// Mostrar mensajes flash
if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

// Obtener datos
$tipos_equipo = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre")->fetchAll();
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';

$sql = "SELECT e.*, te.nombre as tipo_nombre, u.nombre as usuario_nombre FROM equipos e LEFT JOIN tipos_equipo te ON e.tipo_equipo_id = te.id LEFT JOIN usuarios u ON e.usuario_registro_id = u.id WHERE 1=1";
$params = [];
if (!empty($filtro_tipo)) { $sql .= " AND e.tipo_equipo_id = ?"; $params[] = $filtro_tipo; }
if (!empty($filtro_estado)) { $sql .= " AND e.estado = ?"; $params[] = $filtro_estado; }
if (!empty($buscar)) { $sql .= " AND (e.nombre LIKE ? OR e.modelo LIKE ? OR e.numero_serie LIKE ? OR e.ubicacion LIKE ? OR e.persona_responsable LIKE ?)"; $buscar_param = "%$buscar%"; $params = array_merge($params, [$buscar_param, $buscar_param, $buscar_param, $buscar_param, $buscar_param]); }
$sql .= " ORDER BY e.fecha_registro DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipos</title>
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
        .nav-buttons { display: flex; align-items: center; gap: 10px; }
        .btn-nav { font-size: 0.9rem; font-weight: 500; color: #555; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .btn-nav:hover { background-color: #eef; color: var(--primary-color); }
        .btn-nav.active { background: var(--primary-color); color: white; }
        .user-info { display: flex; align-items: center; gap: 10px; padding-left: 15px; border-left: 1px solid #ddd; }
        .logout-btn { background: #ffeef0; color: #d93749; }
        .logout-btn:hover { background: #d93749; color: white; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; color: var(--text-color); }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #eef; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .card-header h3 { font-size: 1.5rem; color: var(--text-color); display: flex; align-items: center; gap: 10px; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-sm { padding: 8px 16px; font-size: 0.875rem; }
        .btn-xs { padding: 6px 12px; font-size: 0.75rem; border-radius: 20px;}
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline-primary { color: var(--primary-color); border: 1px solid var(--primary-color); background: transparent; }
        .btn-outline-primary:hover { background: var(--primary-color); color: white; }
        .btn-outline-warning { color: #f59e0b; border: 1px solid #fde68a; background: #fffbeb; }
        .btn-outline-warning:hover { background: #f59e0b; color: white; }
        .btn-outline-danger { color: #ef4444; border: 1px solid #fecaca; background: #fef2f2; }
        .btn-outline-danger:hover { background: #ef4444; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .btn-group { display: flex; gap: 8px; align-items: center; }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #e7f5f2; color: #008a6e; border-color: #a3e9d8; }
        .error { background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        textarea { resize: vertical; min-height: 80px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #555; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #eef; }
        td { padding: 15px; border-bottom: 1px solid #eef; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; }
        .bg-success { background-color: #e7f5f2; color: #212529; } 
        .bg-warning { background-color: #fff8e1; color: #212529; }
        .bg-secondary { background-color: #f1f3f5; color: #212529; } 
        .bg-danger { background-color: #fff1f2; color: #212529; }
        .modal-header { border-bottom: none; }
        .modal-footer { border-top: 1px solid #eef; padding-top: 1rem; }
        .modal-content { border: none; border-radius: var(--border-radius); }
        .modal-body .row > div { margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav active" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav" href="tipos_equipo.php"><i class="fas fa-tags"></i> Tipos Equipos</a>
                <a class="btn-nav" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                <a class="btn-nav" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Gestión de Equipos</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarEquipo"><i class="fas fa-plus"></i> Agregar Nuevo Equipo</button>
        </div>
        <?= $mensajeHtml; ?>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3></div>
            <form method="GET">
                <div class="form-grid">
                    <div class="form-group"><label>Tipo de Equipo</label><select name="tipo" class="form-select"><option value="">Todos</option><?php foreach ($tipos_equipo as $tipo): ?><option value="<?= $tipo['id']; ?>" <?= $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($tipo['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Estado</label><select name="estado" class="form-select"><option value="">Todos</option><option value="Activo" <?= $filtro_estado == 'Activo' ? 'selected' : ''; ?>>Activo</option><option value="Inactivo" <?= $filtro_estado == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option><option value="Mantenimiento" <?= $filtro_estado == 'Mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option><option value="Dañado" <?= $filtro_estado == 'Dañado' ? 'selected' : ''; ?>>Dañado</option></select></div>
                    <div class="form-group"><label>Búsqueda General</label><input type="text" name="buscar" placeholder="Nombre, serie, responsable..." value="<?= htmlspecialchars($buscar); ?>"></div>
                    <div class="form-group d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Buscar</button></div>
                </div>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-list"></i> Lista de Equipos (<?= count($equipos); ?> Encontrados)</h3></div>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>Nombre</th><th>Tipo</th><th>Serie</th><th>Responsable</th><th>Ubicación</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php if (empty($equipos)): ?>
                            <tr><td colspan="7" class="text-center p-5"><p class="text-muted mb-0">No se encontraron equipos. Intente ajustar los filtros.</p></td></tr>
                        <?php else: ?>
                            <?php
                            $estado_clases = [
                                'Activo' => 'success',
                                'Inactivo' => 'secondary',
                                'Mantenimiento' => 'warning',
                                'Dañado' => 'danger'
                            ];
                            ?>
                            <?php foreach ($equipos as $equipo): ?>
                                <?php
                                $clase_estado = $estado_clases[$equipo['estado']] ?? 'secondary';
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($equipo['nombre']); ?></strong><br><small class="text-muted"><?= htmlspecialchars($equipo['marca']); ?> <?= htmlspecialchars($equipo['modelo']); ?></small></td>
                                    <td><?= htmlspecialchars($equipo['tipo_nombre']); ?></td>
                                    <td><code><?= htmlspecialchars($equipo['numero_serie']); ?></code></td>
                                    <td><?= !empty($equipo['persona_responsable']) ? htmlspecialchars($equipo['persona_responsable']) : '<span class="text-muted">N/A</span>'; ?></td>
                                    <td><?= htmlspecialchars($equipo['ubicacion']); ?></td>
                                    <td><span class="badge bg-<?= $clase_estado; ?>"><?= htmlspecialchars($equipo['estado']); ?></span></td>
                                    <td class="table-actions">
                                        <div class="btn-group">
                                            <a href="revisar_equipo.php?id=<?= $equipo['id']; ?>" class="btn btn-xs btn-outline-primary" title="Revisar"><i class="fas fa-clipboard-check"></i></a>
                                            <a href="editar_equipo.php?id=<?= $equipo['id']; ?>" class="btn btn-xs btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                            <a href="?eliminar=<?= $equipo['id']; ?>" class="btn btn-xs btn-outline-danger" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este equipo?')"><i class="fas fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Agregar Equipo -->
    <div class="modal fade" id="modalAgregarEquipo" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title h3"><i class="fas fa-plus-circle text-primary"></i> Agregar Nuevo Equipo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        <div class="row">
                            <div class="col-md-6"><div class="form-group"><label>Nombre del Equipo *</label><input type="text" name="nombre" class="form-control" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Tipo de Equipo *</label><select name="tipo_equipo_id" class="form-select" required><option value="">Seleccionar...</option><?php foreach ($tipos_equipo as $tipo): ?><option value="<?= $tipo['id']; ?>"><?= htmlspecialchars($tipo['nombre']); ?></option><?php endforeach; ?></select></div></div>
                            <div class="col-12"><div class="form-group"><label>Descripción</label><textarea name="descripcion" class="form-control" rows="3" placeholder="Descripción detallada del equipo..."></textarea></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Marca *</label><input type="text" name="marca" class="form-control" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Modelo *</label><input type="text" name="modelo" class="form-control" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Número de Serie *</label><input type="text" name="numero_serie" class="form-control" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Dirección IP</label><input type="text" name="ip_address" class="form-control" placeholder="Ej: 192.168.1.100"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Estado *</label><select name="estado" class="form-select" required><option value="Activo">Activo</option><option value="Inactivo">Inactivo</option><option value="Mantenimiento">Mantenimiento</option><option value="Dañado">Dañado</option></select></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Fecha de Instalación</label><input type="date" name="fecha_instalacion" class="form-control"></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Persona Responsable *</label><input type="text" name="persona_responsable" class="form-control" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Ubicación</label><input type="text" name="ubicacion" class="form-control"></div></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Equipo</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>