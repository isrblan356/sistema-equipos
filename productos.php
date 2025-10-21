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
    return "<div class='alerta alerta-{$tipo}'><i class='fas {$iconos[$tipo]}'></i><p>{$texto}</p></div>";
}

// -- FUNCIÓN ELIMINADA: verificarColumnaReferencia() ya no es necesaria --

function verificarColumnaStockMinimo($pdo) {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM productos LIKE 'stock_minimo'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) { return false; }
}

function obtenerMarcas($pdo) {
    try {
        $stmt = $pdo->query("SELECT marca_id AS id, nombre FROM marca ORDER BY nombre ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

function obtenerTiposTecnologia($pdo) {
    try {
        $stmt = $pdo->query("SELECT tipo_tecnologia_id AS id, nombre FROM tipo_tecnologia ORDER BY nombre ASC");
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

$tieneStockMinimo = verificarColumnaStockMinimo($pdo);
$marcas = obtenerMarcas($pdo);
$tipos_tecnologia = obtenerTiposTecnologia($pdo);


// =================================================================================
// 3. PROCESAMIENTO DE FORMULARIOS
// =================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    try {
        switch ($_POST['accion']) {
            case 'agregar':
                $marca_id = intval($_POST['marca_id']);
                $tipo_tecnologia_id = intval($_POST['tipo_tecnologia_id']);
                $nombre = limpiarDatos($_POST['nombre']);
                $partnumber = limpiarDatos($_POST['part_number'] ?? '');
                $stock_minimo = intval($_POST['stock_minimo'] ?? 0);
                
                if (!empty($nombre) && $marca_id > 0 && $tipo_tecnologia_id > 0) {
                    // -- LÓGICA ACTUALIZADA: Sin codigo ni referencia --
                    $campos = ['nombre', 'part_number', 'marca_id', 'tipo_tecnologia_id'];
                    $valores = [$nombre, $partnumber, $marca_id, $tipo_tecnologia_id];
                    $placeholders = ['?', '?', '?', '?'];
                    
                    if ($tieneStockMinimo) {
                        $campos[] = 'stock_minimo';
                        $valores[] = $stock_minimo;
                        $placeholders[] = '?';
                    }
                    
                    $sql = "INSERT INTO productos (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($valores);
                    $mensaje = mostrarAlerta('Producto agregado exitosamente.', 'success');
                } else {
                    $mensaje = mostrarAlerta('Nombre, Marca y Tipo de Tecnología son campos obligatorios.', 'error');
                }
                break;

            case 'editar':
                $id = intval($_POST['id']);
                $marca_id = intval($_POST['marca_id']);
                $tipo_tecnologia_id = intval($_POST['tipo_tecnologia_id']);
                $nombre = limpiarDatos($_POST['nombre']);
                $partnumber = limpiarDatos($_POST['part_number'] ?? '');
                $stock_minimo = intval($_POST['stock_minimo'] ?? 0);

                if (!empty($nombre) && $marca_id > 0 && $tipo_tecnologia_id > 0) {
                    // -- LÓGICA ACTUALIZADA: Sin codigo ni referencia --
                    $campos = ['nombre = ?', 'part_number = ?', 'marca_id = ?', 'tipo_tecnologia_id = ?'];
                    $valores = [$nombre, $partnumber, $marca_id, $tipo_tecnologia_id];
                    
                    if ($tieneStockMinimo) {
                        $campos[] = 'stock_minimo = ?';
                        $valores[] = $stock_minimo;
                    }
                    
                    $valores[] = $id; // ID para el WHERE
                    $sql = "UPDATE productos SET " . implode(', ', $campos) . " WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($valores);
                    $mensaje = mostrarAlerta('Datos del producto actualizados.', 'success');
                } else {
                    $mensaje = mostrarAlerta('Nombre, Marca y Tipo de Tecnología son campos obligatorios.', 'error');
                }
                break;

            case 'cambiar_estado':
                $id = intval($_POST['id']);
                $estado_actual = $_POST['estado_actual'];
                $nuevo_estado = ($estado_actual == 'activo') ? 'inactivo' : 'activo';
                $stmt = $pdo->prepare("UPDATE productos SET estado = ? WHERE id = ?");
                $stmt->execute([$nuevo_estado, $id]);
                $mensaje = mostrarAlerta('Estado del producto cambiado a ' . ucfirst($nuevo_estado) . '.', 'info');
                break;

            case 'eliminar':
                $id = intval($_POST['id']);
                if ($id > 0) {
                    $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
                    $stmt->execute([$id]);
                    $mensaje = mostrarAlerta('Producto eliminado permanentemente.', 'success');
                } else {
                    $mensaje = mostrarAlerta('ID de producto no válido para eliminar.', 'error');
                }
                break;
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error en la operación: ' . $e->getMessage(), 'error');
    }
}

// =================================================================================
// 4. OBTENCIÓN DE DATOS PARA LA VISTA
// =================================================================================
$sql = "SELECT 
            p.*, 
            m.nombre as marca_nombre, 
            t.nombre as tecnologia_nombre
        FROM productos p
        LEFT JOIN marca m ON p.marca_id = m.marca_id
        LEFT JOIN tipo_tecnologia t ON p.tipo_tecnologia_id = t.tipo_tecnologia_id
        ORDER BY p.nombre ASC";
$stmt = $pdo->query($sql);
$productos = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ... (Tu CSS mejorado sin cambios) ... */
        body { font-family: 'Segoe UI', sans-serif; background-color: #f4f7f6; }
        .header { background: #fff; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; flex-wrap: wrap; gap: 1rem;}
        .header h1 { color: #2c3e50; font-size: 2rem; display: flex; align-items: center; gap: 15px; margin: 0; }
        .header h1 i { color: #3498db; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .content-grid { display: grid; grid-template-columns: 380px 1fr; gap: 2rem; align-items: flex-start; }
        @media (max-width: 992px) { .content-grid { grid-template-columns: 1fr; } }
        .card { background: white; border-radius: 15px; padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .card h3 i { color: #3498db; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .form-control { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: linear-gradient(45deg, #3498db, #2980b9); color: white; width: 100%; margin-top: 1rem; }
        .btn-sm { padding: 8px 12px; font-size: 0.8rem; border-radius: 20px; }
        .btn-warning { background: linear-gradient(45deg, #f39c12, #e67e22); color: white; }
        .btn-danger { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; }
        .btn-secondary { background: linear-gradient(45deg, #95a5a6, #7f8c8d); color: white; }
        .btn-success { background: linear-gradient(45deg, #2ecc71, #27ae60); color: white; }
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 1.05rem; }
        th, td { padding: 16px; text-align: left; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        th { background-color: #f8f9fa; font-weight: 600; color: #34495e; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        tr:nth-child(even) { background-color: #f9fafb; }
        tr:hover { background-color: #f0f4f8; }
        td:nth-child(1) strong { color: #2c3e50; }
        th:nth-child(5), td:nth-child(5), th:nth-child(6), td:nth-child(6), th:nth-child(7), td:nth-child(7) { text-align: center; }
        .table-actions { display: flex; gap: 8px; justify-content: center; }
        .badge { padding: 6px 14px; font-size: 0.85rem; font-weight: 600; border-radius: 20px; color: white; }
        .badge-activo { background-color: #27ae60; }
        .badge-inactivo { background-color: #7f8c8d; }
        .stock-minimo { display: inline-block; background-color: #e67e22; color: white; padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-boxes"></i> Gestión de Productos</h1>
            <div class="nav-buttons">
                <a href="inventario_compras.php" class="btn-nav"><i class="fas fa-home"></i> volver a Dashboard</a>
                <a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <main class="container">
        <?= $mensaje; ?>

        <div class="content-grid">
            <div class="card">
                <h3><i class="fas fa-plus-circle"></i> Agregar Nuevo Producto</h3>
                <form method="POST" action="productos.php">
                    <input type="hidden" name="accion" value="agregar">
                    <div class="form-group">
                        <label for="marca_id">Marca *</label>
                        <select id="marca_id" name="marca_id" class="form-control" required>
                            <option value="">-- Seleccionar Marca --</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?= $marca['id'] ?>"><?= htmlspecialchars($marca['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="part_number">Número de parte (Opcional)</label>
                        <input type="text" id="part_number" name="part_number" class="form-control" placeholder="Ej: 12345678">
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre Completo *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej: Litebeam AC" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo_tecnologia_id">Tipo Tecnología *</label>
                        <select id="tipo_tecnologia_id" name="tipo_tecnologia_id" class="form-control" required>
                            <option value="">-- Seleccionar Tecnología --</option>
                            <?php foreach ($tipos_tecnologia as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($tieneStockMinimo): ?>
                    <div class="form-group">
                        <label for="stock_minimo">Stock Mínimo</label>
                        <input type="number" id="stock_minimo" name="stock_minimo" class="form-control" value="0" min="0" placeholder="Ej: 5">
                    </div>
                    <?php endif; ?>
                    
                    <!-- Campos de código y referencia ELIMINADOS -->

                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Producto</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list-ul"></i> Lista de productos</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Marca</th>
                                <th>Tecnología</th>
                                <th>Part Number</th>
                                <?php if ($tieneStockMinimo): ?>
                                <th>Stock Mínimo</th>
                                <?php endif; ?>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr><td colspan="7" style="text-align: center; color: #888;">No hay productos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($producto['nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($producto['marca_nombre'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($producto['tecnologia_nombre'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($producto['part_number'] ?? 'N/A') ?></td>
                                        <?php if ($tieneStockMinimo): ?>
                                        <td><span class="stock-minimo"><?= intval($producto['stock_minimo'] ?? 0) ?></span></td>
                                        <?php endif; ?>
                                        <td><span class="badge badge-<?= $producto['estado'] ?>"><?= ucfirst($producto['estado']) ?></span></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $producto['id'] ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="productos.php" style="display: inline-block; margin: 0;" onsubmit="return confirm('¿Seguro de cambiar el estado?');">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $producto['estado'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $producto['estado'] == 'activo' ? 'btn-secondary' : 'btn-success' ?>" title="<?= $producto['estado'] == 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $producto['estado'] == 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="productos.php" style="display: inline-block; margin: 0;" onsubmit="return confirm('¡PELIGRO! ¿Seguro de ELIMINAR este producto? Esta acción no se puede deshacer.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar definitivamente">
                                                    <i class="fas fa-trash-alt"></i>
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

    <!-- Modales -->
    <?php foreach ($productos as $producto): ?>
    <div class="modal fade" id="modalEditar<?= $producto['id'] ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="productos.php">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar">
                        <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                        <div class="form-group">
                            <label>Marca *</label>
                            <select name="marca_id" class="form-control" required>
                                <?php foreach ($marcas as $marca): ?>
                                    <option value="<?= $marca['id'] ?>" <?= ($marca['id'] == $producto['marca_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($marca['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Part Number</label>
                            <input type="text" name="part_number" class="form-control" value="<?= htmlspecialchars($producto['part_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($producto['nombre']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tipo Tecnología *</label>
                            <select name="tipo_tecnologia_id" class="form-control" required>
                                <?php foreach ($tipos_tecnologia as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>" <?= ($tipo['id'] == $producto['tipo_tecnologia_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($tieneStockMinimo): ?>
                        <div class="form-group">
                            <label>Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="form-control" value="<?= intval($producto['stock_minimo'] ?? 0) ?>" min="0">
                        </div>
                        <?php endif; ?>

                        <!-- Campos de código y referencia ELIMINADOS del modal -->

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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const alerta = document.querySelector('.alerta');
            if (alerta) {
                setTimeout(() => {
                    alerta.style.opacity = '0';
                    setTimeout(() => { alerta.style.display = 'none'; }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>