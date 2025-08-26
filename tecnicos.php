<?php
// =================================================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN
// =================================================================================
require_once 'config.php'; // Incluye el config, que tiene las funciones globales como limpiarDatos()
verificarLogin();
$pdo = conectarDB();
$mensaje = '';

// =================================================================================
// 2. FUNCIONES AUXILIARES (Específicas de esta página)
// =================================================================================
function mostrarAlerta($texto, $tipo = 'info') {
    $iconos = ['success' => 'fa-check-circle', 'error' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
    return "<div class='alerta alerta-{$tipo}'><i class='fas {$iconos[$tipo]}'></i><p>{$texto}</p></div>";
}

// =================================================================================
// 3. PROCESAMIENTO DE FORMULARIOS
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    try {
        switch ($_POST['accion']) {
            case 'agregar':
                $nombre = limpiarDatos($_POST['nombre']);
                $documento = limpiarDatos($_POST['documento_identidad']);
                $telefono = limpiarDatos($_POST['telefono']);
                
                if (!empty($nombre)) {
                    $stmt = $pdo->prepare("INSERT INTO tecnicos (nombre, documento_identidad, telefono) VALUES (?, ?, ?)");
                    $stmt->execute([$nombre, $documento, $telefono]);
                    $mensaje = mostrarAlerta('Técnico agregado exitosamente.', 'success');
                } else {
                    $mensaje = mostrarAlerta('El nombre del técnico no puede estar vacío.', 'error');
                }
                break;

            case 'editar':
                $id = intval($_POST['id']);
                $nombre = limpiarDatos($_POST['nombre']);
                $documento = limpiarDatos($_POST['documento_identidad']);
                $telefono = limpiarDatos($_POST['telefono']);

                if (!empty($nombre)) {
                    $stmt = $pdo->prepare("UPDATE tecnicos SET nombre = ?, documento_identidad = ?, telefono = ? WHERE id = ?");
                    $stmt->execute([$nombre, $documento, $telefono, $id]);
                    $mensaje = mostrarAlerta('Datos del técnico actualizados.', 'success');
                } else {
                    $mensaje = mostrarAlerta('El nombre del técnico no puede estar vacío.', 'error');
                }
                break;

            case 'cambiar_estado':
                $id = intval($_POST['id']);
                $estado_actual = $_POST['estado_actual'];
                $nuevo_estado = ($estado_actual == 'activo') ? 'inactivo' : 'activo';

                $stmt = $pdo->prepare("UPDATE tecnicos SET estado = ? WHERE id = ?");
                $stmt->execute([$nuevo_estado, $id]);
                $mensaje = mostrarAlerta('Estado del técnico cambiado a ' . ucfirst($nuevo_estado) . '.', 'info');
                break;
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error en la operación: ' . $e->getMessage(), 'error');
    }
}

// =================================================================================
// 4. OBTENCIÓN DE DATOS PARA LA VISTA
// =================================================================================
$stmt = $pdo->query("SELECT * FROM tecnicos ORDER BY nombre ASC");
$tecnicos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Técnicos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .header { background: #fff; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; flex-wrap: wrap; gap: 1rem;}
        .header h1 { color: #2c3e50; font-size: 2rem; display: flex; align-items: center; gap: 15px; margin: 0; }
        .header h1 i { color: #3498db; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .content-grid { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; align-items: flex-start; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .card h3 i { color: #3498db; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; width: 100%; margin-top: 1rem; }
        .btn-sm { padding: 8px 12px; font-size: 0.8rem; border-radius: 20px; }
        .btn-warning { background: linear-gradient(45deg, #f39c12, #e67e22); color: white; }
        .btn-secondary { background: linear-gradient(45deg, #95a5a6, #7f8c8d); color: white; }
        .btn-success { background: linear-gradient(45deg, #2ecc71, #27ae60); color: white; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #ecf0f1; }
        th { background-color: #f8f9fa; font-weight: 600; color: #34495e; }
        .table-actions { display: flex; gap: 5px; }
        .badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; color: white; }
        .badge-activo { background-color: #27ae60; }
        .badge-inactivo { background-color: #7f8c8d; }
        input { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
        .alerta { display: flex; align-items: center; gap: 1rem; padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; color: white; }
        .alerta-success { background-color: #2ecc71; } .alerta-error { background-color: #e74c3c; } .alerta-info { background-color: #3498db; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-users-cog"></i> Gestión de Técnicos</h1>
            <div class="nav-buttons">
                <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard Principal</a>
                <a href="inventario.php" class="btn-nav"><i class="fas fa-boxes"></i> Inventario de Sedes</a>
                <a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?= $mensaje; // Muestra cualquier mensaje de éxito o error ?>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-user-plus"></i> Agregar Nuevo Técnico</h3>
                <form method="POST" action="tecnicos.php">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="form-group">
                        <label for="nombre">Nombre Completo *</label>
                        <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label for="documento">Documento de Identidad (Opcional)</label>
                        <input type="text" id="documento" name="documento_identidad" placeholder="Ej: 12345678">
                    </div>
                    <div class="form-group">
                        <label for="telefono">Teléfono (Opcional)</label>
                        <input type="text" id="telefono" name="telefono" placeholder="Ej: 987654321">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Técnico</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list-ul"></i> Lista de Técnicos</h3>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tecnicos)): ?>
                                <tr><td colspan="5" style="text-align: center; color: #888;">No hay técnicos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($tecnicos as $tecnico): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($tecnico['nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($tecnico['documento_identidad'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($tecnico['telefono'] ?? 'N/A') ?></td>
                                        <td><span class="badge badge-<?= $tecnico['estado'] ?>"><?= ucfirst($tecnico['estado']) ?></span></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $tecnico['id'] ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="tecnicos.php" style="display: inline;" onsubmit="return confirm('¿Estás seguro de cambiar el estado de este técnico?');">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id" value="<?= $tecnico['id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $tecnico['estado'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $tecnico['estado'] == 'activo' ? 'btn-secondary' : 'btn-success' ?>" title="<?= $tecnico['estado'] == 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $tecnico['estado'] == 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
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

    <!-- Modales para Editar -->
    <?php foreach ($tecnicos as $tecnico): ?>
    <div class="modal fade" id="modalEditar<?= $tecnico['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="tecnicos.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $tecnico['id'] ?>">
                        <div class="form-group"><label>Nombre Completo *</label><input type="text" name="nombre" value="<?= htmlspecialchars($tecnico['nombre']) ?>" required></div>
                        <div class="form-group"><label>Documento de Identidad</label><input type="text" name="documento_identidad" value="<?= htmlspecialchars($tecnico['documento_identidad']) ?>"></div>
                        <div class="form-group"><label>Teléfono</label><input type="text" name="telefono" value="<?= htmlspecialchars($tecnico['telefono']) ?>"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="width: auto;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="width: auto;"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>