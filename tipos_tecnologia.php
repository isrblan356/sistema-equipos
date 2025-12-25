<?php
// =================================================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN
// =================================================================================
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();
$mensaje = '';

// =================================================================================
// 2. FUNCIONES AUXILIARES
// =================================================================================
function mostrarAlerta($texto, $tipo = 'info') {
    $iconos = ['success' => 'fa-check-circle', 'error' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
    $icono = $iconos[$tipo] ?? 'fa-info-circle';
    return "<div class='alerta alerta-{$tipo}'><i class='fas {$icono}'></i><p>{$texto}</p></div>";
}

// =================================================================================
// 3. PROCESAMIENTO DE FORMULARIOS
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    try {
        switch ($_POST['accion']) {
            case 'agregar_tipo_tecnologia':
                $nombre = limpiarDatos($_POST['nombre']);
                if (!empty($nombre)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tipo_tecnologia WHERE nombre = ?");
                    $stmt->execute([$nombre]);
                    if ($stmt->fetchColumn() > 0) {
                        $mensaje = mostrarAlerta('El tipo de tecnología "' . htmlspecialchars($nombre) . '" ya existe.', 'error');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO tipo_tecnologia (nombre) VALUES (?)");
                        $stmt->execute([$nombre]);
                        $mensaje = mostrarAlerta('Tipo de tecnología agregado exitosamente.', 'success');
                    }
                } else {
                    $mensaje = mostrarAlerta('El nombre no puede estar vacío.', 'error');
                }
                break;

            case 'editar_tipo_tecnologia':
                $id = intval($_POST['id']);
                $nombre = limpiarDatos($_POST['nombre']);

                if ($id > 0 && !empty($nombre)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tipo_tecnologia WHERE nombre = ? AND tipo_tecnologia_id != ?");
                    $stmt->execute([$nombre, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $mensaje = mostrarAlerta('Ya existe otro tipo de tecnología con ese nombre.', 'error');
                    } else {
                        $stmt = $pdo->prepare("UPDATE tipo_tecnologia SET nombre = ? WHERE tipo_tecnologia_id = ?");
                        $stmt->execute([$nombre, $id]);
                        $mensaje = mostrarAlerta('Tipo de tecnología actualizado correctamente.', 'success');
                    }
                } else {
                    $mensaje = mostrarAlerta('El nombre no puede estar vacío.', 'error');
                }
                break;

            case 'cambiar_estado_tipo_tecnologia':
                $id = intval($_POST['id']);
                $estado_actual = $_POST['estado_actual'];
                $nuevo_estado = ($estado_actual == 'activo') ? 'inactivo' : 'activo';
                
                $stmt = $pdo->prepare("UPDATE tipo_tecnologia SET estado = ? WHERE tipo_tecnologia_id = ?");
                $stmt->execute([$nuevo_estado, $id]);
                $mensaje = mostrarAlerta('Estado cambiado a ' . ucfirst($nuevo_estado) . '.', 'info');
                break;
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error en la operación: ' . $e->getMessage(), 'error');
    }
}

// =================================================================================
// 4. OBTENCIÓN DE DATOS PARA LA VISTA
// =================================================================================
$stmt = $pdo->query("SELECT tipo_tecnologia_id, nombre, estado FROM tipo_tecnologia ORDER BY nombre ASC");
$tipos_tecnologia = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Tipos de Tecnología</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Estilos consistentes con tus otros módulos, con un toque de color diferente */
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .header { background: #fff; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; flex-wrap: wrap; gap: 1rem;}
        .header h1 { color: #2c3e50; font-size: 2rem; display: flex; align-items: center; gap: 15px; margin: 0; }
        .header h1 i { color: #8e44ad; } /* Nuevo color para el ícono */
        .nav-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .content-grid { display: grid; grid-template-columns: 380px 1fr; gap: 2rem; align-items: flex-start; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 15px; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .card h3 i { color: #8e44ad; }
        .form-group { margin-bottom: 1.2rem; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(45deg, #9b59b6, #8e44ad); color: white; width: 100%; margin-top: 1rem; }
        .btn-sm { padding: 8px 12px; font-size: 0.8rem; border-radius: 20px; }
        .btn-warning { background: linear-gradient(45deg, #f39c12, #e67e22); color: white; }
        .btn-secondary { background: linear-gradient(45deg, #95a5a6, #7f8c8d); color: white; }
        .btn-success { background: linear-gradient(45deg, #2ecc71, #27ae60); color: white; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        th { background-color: #f8f9fa; font-weight: 600; color: #34495e; text-transform: uppercase; font-size: 0.9rem; }
        .table-actions { display: flex; gap: 8px; justify-content: center; }
        .badge { padding: 6px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 20px; color: white; }
        .badge-activo { background-color: #27ae60; }
        .badge-inactivo { background-color: #7f8c8d; }
        .alerta { padding: 1rem; margin-bottom: 1rem; border-radius: 8px; display: flex; align-items: center; gap: 10px; opacity: 1; transition: opacity 0.5s ease; }
        .alerta-success { background-color: #d4edda; color: #155724; }
        .alerta-error { background-color: #f8d7da; color: #721c24; }
        .alerta-info { background-color: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-sitemap"></i> Gestión de Tipos de Tecnología</h1>
            <div class="nav-buttons">
                <a href="productos.php" class="btn-nav"><i class="fas fa-home"></i> Volver a Productos</a>
                <a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?= $mensaje; ?>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Agregar Tipo de Tecnología</h3>
                <form method="POST" action="tipos_tecnologia.php">
                    <input type="hidden" name="accion" value="agregar_tipo_tecnologia">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Tecnología *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej: Fibra Óptica, Wireless, etc." required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Tecnología</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list-ul"></i> Lista de Tipos de Tecnología</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th style="text-align: center;">Estado</th>
                                <th style="text-align: center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tipos_tecnologia)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #888;">No hay tipos de tecnología registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tipos_tecnologia as $tipo): ?>
                                    <tr>
                                        <td><?= $tipo['tipo_tecnologia_id'] ?></td>
                                        <td><strong><?= htmlspecialchars($tipo['nombre']) ?></strong></td>
                                        <td style="text-align: center;">
                                            <span class="badge badge-<?= $tipo['estado'] ?>"><?= ucfirst($tipo['estado']) ?></span>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarTipo<?= $tipo['tipo_tecnologia_id'] ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="tipos_tecnologia.php" style="display: inline-block;" onsubmit="return confirm('¿Está seguro de cambiar el estado?');">
                                                <input type="hidden" name="accion" value="cambiar_estado_tipo_tecnologia">
                                                <input type="hidden" name="id" value="<?= $tipo['tipo_tecnologia_id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $tipo['estado'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $tipo['estado'] == 'activo' ? 'btn-secondary' : 'btn-success' ?>" title="<?= $tipo['estado'] == 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $tipo['estado'] == 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modales para Edición -->
    <?php foreach ($tipos_tecnologia as $tipo): ?>
    <div class="modal fade" id="modalEditarTipo<?= $tipo['tipo_tecnologia_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Tipo de Tecnología</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="tipos_tecnologia.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar_tipo_tecnologia">
                        <input type="hidden" name="id" value="<?= $tipo['tipo_tecnologia_id'] ?>">
                        <div class="form-group">
                            <label for="nombre_edit_<?= $tipo['tipo_tecnologia_id'] ?>">Nombre de la Tecnología *</label>
                            <input type="text" id="nombre_edit_<?= $tipo['tipo_tecnologia_id'] ?>" name="nombre" class="form-control" value="<?= htmlspecialchars($tipo['nombre']) ?>" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="width: auto; background: #6c757d;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="width: auto;"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerta = document.querySelector('.alerta');
            if (alerta) {
                setTimeout(() => {
                    alerta.style.opacity = '0';
                    setTimeout(() => { alerta.style.display = 'none'; }, 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>