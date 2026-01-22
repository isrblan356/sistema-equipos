<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';

// Procesar eliminación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        // Verificar si hay equipos asociados
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM equipos WHERE tipo_equipo_id = ?");
        $stmt->execute([$_GET['eliminar']]);
        $resultado = $stmt->fetch();
        
        if ($resultado['total'] > 0) {
            $_SESSION['error_flash'] = 'No se puede eliminar este tipo de equipo porque tiene ' . $resultado['total'] . ' equipo(s) asociado(s).';
        } else {
            $stmt = $pdo->prepare("DELETE FROM tipos_equipo WHERE id = ?");
            $stmt->execute([$_GET['eliminar']]);
            $_SESSION['mensaje_flash'] = 'Tipo de equipo eliminado exitosamente.';
        }
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al eliminar tipo de equipo: ' . $e->getMessage();
    }
    header("Location: tipos_equipo.php");
    exit();
}

// Procesar formulario de nuevo tipo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tipos_equipo (nombre, descripcion) VALUES (?, ?)");
        $stmt->execute([$nombre, $descripcion]);
        $_SESSION['mensaje_flash'] = 'Tipo de equipo agregado exitosamente.';
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al agregar tipo de equipo: ' . $e->getMessage();
    }
    header("Location: tipos_equipo.php");
    exit();
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'editar') {
    $id = $_POST['id'];
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    
    try {
        $stmt = $pdo->prepare("UPDATE tipos_equipo SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $id]);
        $_SESSION['mensaje_flash'] = 'Tipo de equipo actualizado exitosamente.';
    } catch (PDOException $e) {
        $_SESSION['error_flash'] = 'Error al actualizar tipo de equipo: ' . $e->getMessage();
    }
    header("Location: tipos_equipo.php");
    exit();
}

// Mostrar mensajes flash
if (isset($_SESSION['mensaje_flash'])) {
    $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>';
    unset($_SESSION['mensaje_flash']);
}
if (isset($_SESSION['error_flash'])) {
    $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>';
    unset($_SESSION['error_flash']);
}

// Obtener todos los tipos de equipo con conteo de equipos
$sql = "SELECT te.*, COUNT(e.id) as total_equipos 
        FROM tipos_equipo te 
        LEFT JOIN equipos e ON te.id = e.tipo_equipo_id 
        GROUP BY te.id 
        ORDER BY te.nombre";
$tipos_equipo = $pdo->query($sql)->fetchAll();

// Obtener datos para edición si se solicita
$tipo_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM tipos_equipo WHERE id = ?");
    $stmt->execute([$_GET['editar']]);
    $tipo_editar = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tipos de Equipos</title>
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
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        textarea { resize: vertical; min-height: 100px; }
        .tipo-card { background: var(--card-bg); border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 1rem; transition: all 0.3s ease; border: 1px solid #eef; }
        .tipo-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.1); transform: translateY(-2px); }
        .tipo-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; }
        .tipo-nombre { font-size: 1.25rem; font-weight: 700; color: var(--text-color); margin: 0; }
        .tipo-descripcion { color: #6c757d; margin: 0.5rem 0; line-height: 1.6; }
        .tipo-stats { display: flex; align-items: center; gap: 1rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eef; }
        .stat-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f8f9fa; border-radius: 20px; font-size: 0.875rem; color: #555; }
        .stat-badge i { color: var(--primary-color); }
        .grid-tipos { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .modal-content { border: none; border-radius: var(--border-radius); }
        .modal-header { border-bottom: 1px solid #eef; }
        .modal-footer { border-top: 1px solid #eef; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav active" href="tipos_equipo.php"><i class="fas fa-tags"></i> Tipos</a>
                <a class="btn-nav" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                <a class="btn-nav" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Tipos de Equipos</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarTipo"><i class="fas fa-plus"></i> Agregar Nuevo Tipo</button>
        </div>
        
        <?= $mensajeHtml; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tags"></i> Lista de Tipos (<?= count($tipos_equipo); ?> registrados)</h3>
            </div>
            
            <?php if (empty($tipos_equipo)): ?>
                <div class="text-center p-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No hay tipos de equipo registrados. ¡Crea el primero!</p>
                </div>
            <?php else: ?>
                <div class="grid-tipos">
                    <?php foreach ($tipos_equipo as $tipo): ?>
                        <div class="tipo-card">
                            <div class="tipo-header">
                                <h4 class="tipo-nombre"><i class="fas fa-tag" style="color: var(--primary-color);"></i> <?= htmlspecialchars($tipo['nombre']); ?></h4>
                                <div class="btn-group">
                                    <a href="?editar=<?= $tipo['id']; ?>" class="btn btn-xs btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>
                                    <a href="?eliminar=<?= $tipo['id']; ?>" class="btn btn-xs btn-outline-danger" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar este tipo de equipo?')"><i class="fas fa-trash"></i></a>
                                </div>
                            </div>
                            
                            <p class="tipo-descripcion">
                                <?= !empty($tipo['descripcion']) ? htmlspecialchars($tipo['descripcion']) : '<em class="text-muted">Sin descripción</em>'; ?>
                            </p>
                            
                            <div class="tipo-stats">
                                <span class="stat-badge">
                                    <i class="fas fa-desktop"></i>
                                    <strong><?= $tipo['total_equipos']; ?></strong> equipo<?= $tipo['total_equipos'] != 1 ? 's' : ''; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Agregar Tipo -->
    <div class="modal fade" id="modalAgregarTipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h3"><i class="fas fa-plus-circle text-primary"></i> Agregar Nuevo Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        <div class="form-group mb-3">
                            <label>Nombre del Tipo *</label>
                            <input type="text" name="nombre" class="form-control" placeholder="Ej: Router, Switch, Servidor..." required>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4" placeholder="Descripción detallada del tipo de equipo..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Tipo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Tipo -->
    <?php if ($tipo_editar): ?>
    <div class="modal fade show" id="modalEditarTipo" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h3"><i class="fas fa-edit text-warning"></i> Editar Tipo</h5>
                    <a href="tipos_equipo.php" class="btn-close"></a>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $tipo_editar['id']; ?>">
                        <div class="form-group mb-3">
                            <label>Nombre del Tipo *</label>
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($tipo_editar['nombre']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4"><?= htmlspecialchars($tipo_editar['descripcion'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="tipos_equipo.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Tipo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>