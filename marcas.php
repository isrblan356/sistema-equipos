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
            case 'agregar_marca':
                $nombre = limpiarDatos($_POST['nombre']);
                if (!empty($nombre)) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marca WHERE nombre = ?");
                    $stmt->execute([$nombre]);
                    if ($stmt->fetchColumn() > 0) {
                        $mensaje = mostrarAlerta('La marca "' . htmlspecialchars($nombre) . '" ya existe.', 'error');
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO marca (nombre) VALUES (?)");
                        $stmt->execute([$nombre]);
                        $mensaje = mostrarAlerta('Marca agregada exitosamente.', 'success');
                    }
                } else {
                    $mensaje = mostrarAlerta('El nombre de la marca no puede estar vacío.', 'error');
                }
                break;

            case 'editar_marca':
                $id = intval($_POST['id']);
                $nombre = limpiarDatos($_POST['nombre']);

                if ($id > 0 && !empty($nombre)) {
                    // Verificar que el nuevo nombre no exista ya en otra marca
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marca WHERE nombre = ? AND marca_id != ?");
                    $stmt->execute([$nombre, $id]);
                    if ($stmt->fetchColumn() > 0) {
                        $mensaje = mostrarAlerta('Ya existe otra marca con el nombre "' . htmlspecialchars($nombre) . '".', 'error');
                    } else {
                        $stmt = $pdo->prepare("UPDATE marca SET nombre = ? WHERE marca_id = ?");
                        $stmt->execute([$nombre, $id]);
                        $mensaje = mostrarAlerta('Marca actualizada correctamente.', 'success');
                    }
                } else {
                    $mensaje = mostrarAlerta('El nombre no puede estar vacío.', 'error');
                }
                break;

            case 'cambiar_estado_marca':
                $id = intval($_POST['id']);
                $estado_actual = $_POST['estado_actual'];
                $nuevo_estado = ($estado_actual == 'activo') ? 'inactivo' : 'activo';
                
                $stmt = $pdo->prepare("UPDATE marca SET estado = ? WHERE marca_id = ?");
                $stmt->execute([$nuevo_estado, $id]);
                $mensaje = mostrarAlerta('Estado de la marca cambiado a ' . ucfirst($nuevo_estado) . '.', 'info');
                break;
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error en la operación: ' . $e->getMessage(), 'error');
    }
}

// =================================================================================
// 4. OBTENCIÓN DE DATOS PARA LA VISTA
// =================================================================================
$stmt = $pdo->query("SELECT marca_id, nombre, estado FROM marca ORDER BY nombre ASC");
$marcas = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Marcas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Reutilizamos los estilos de tu página de productos para un diseño consistente */
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .header { background: #fff; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1200px; margin: 0 auto; flex-wrap: wrap; gap: 1rem;}
        .header h1 { color: #2c3e50; font-size: 2rem; display: flex; align-items: center; gap: 15px; margin: 0; }
        .header h1 i { color: #e74c3c; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .content-grid { display: grid; grid-template-columns: 380px 1fr; gap: 2rem; align-items: flex-start; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 15px; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .card h3 i { color: #e74c3c; }
        .form-group { margin-bottom: 1.2rem; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; width: 100%; margin-top: 1rem; }
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
            <h1><i class="fas fa-tags"></i> Gestión de Marcas</h1>
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
                <h3><i class="fas fa-plus-circle"></i> Agregar Nueva Marca</h3>
                <form method="POST" action="marcas.php">
                    <input type="hidden" name="accion" value="agregar_marca">
                    <div class="form-group">
                        <label for="nombre">Nombre de la Marca *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej: Ubiquiti, Mikrotik" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Marca</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list-ul"></i> Lista de Marcas</h3>
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
                            <?php if (empty($marcas)): ?>
                                <tr><td colspan="4" style="text-align: center; color: #888;">No hay marcas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($marcas as $marca): ?>
                                    <tr>
                                        <td><?= $marca['marca_id'] ?></td>
                                        <td><strong><?= htmlspecialchars($marca['nombre']) ?></strong></td>
                                        <td style="text-align: center;">
                                            <span class="badge badge-<?= $marca['estado'] ?>"><?= ucfirst($marca['estado']) ?></span>
                                        </td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditarMarca<?= $marca['marca_id'] ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="marcas.php" style="display: inline-block;" onsubmit="return confirm('¿Está seguro de cambiar el estado de esta marca?');">
                                                <input type="hidden" name="accion" value="cambiar_estado_marca">
                                                <input type="hidden" name="id" value="<?= $marca['marca_id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $marca['estado'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $marca['estado'] == 'activo' ? 'btn-secondary' : 'btn-success' ?>" title="<?= $marca['estado'] == 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $marca['estado'] == 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
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
    <?php foreach ($marcas as $marca): ?>
    <div class="modal fade" id="modalEditarMarca<?= $marca['marca_id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Marca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="marcas.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar_marca">
                        <input type="hidden" name="id" value="<?= $marca['marca_id'] ?>">
                        <div class="form-group">
                            <label for="nombre_edit_<?= $marca['marca_id'] ?>">Nombre de la Marca *</label>
                            <input type="text" id="nombre_edit_<?= $marca['marca_id'] ?>" name="nombre" class="form-control" value="<?= htmlspecialchars($marca['nombre']) ?>" required>
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