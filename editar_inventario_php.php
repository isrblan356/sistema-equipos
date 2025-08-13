<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensaje = '';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de producto inválido');
}
$id = (int) $_GET['id'];

// Obtener tipos de equipo
$stmtTipos = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre");
$tipos_equipo = $stmtTipos->fetchAll();

// Obtener producto actual
$stmt = $pdo->prepare("SELECT * FROM inventario WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch();

if (!$producto) {
    die('Producto no encontrado');
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $marca = limpiarDatos($_POST['marca']);
    $modelo = limpiarDatos($_POST['modelo']);
    $tipo_equipo_id = !empty($_POST['tipo_equipo_id']) ? $_POST['tipo_equipo_id'] : null;
    $stock_minimo = (int)$_POST['stock_minimo'];
    $precio_unitario = (float)$_POST['precio_unitario'];
    $ubicacion_almacen = limpiarDatos($_POST['ubicacion_almacen']);
    $estado = $_POST['estado'];

    try {
        $stmt = $pdo->prepare("UPDATE inventario 
                               SET nombre = ?, descripcion = ?, marca = ?, modelo = ?, 
                                   tipo_equipo_id = ?, stock_minimo = ?, precio_unitario = ?, 
                                   ubicacion_almacen = ?, estado = ? 
                               WHERE id = ?");
        $stmt->execute([$nombre, $descripcion, $marca, $modelo, $tipo_equipo_id, $stock_minimo, $precio_unitario, $ubicacion_almacen, $estado, $id]);
        header('Location: inventario.php?actualizado=1');
        exit();
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al actualizar producto: ' . $e->getMessage(), 'danger');
    }
}

// Obtener estadísticas del producto
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_movimientos,
        SUM(CASE WHEN tipo_movimiento = 'ENTRADA' THEN cantidad ELSE 0 END) as total_entradas,
        SUM(CASE WHEN tipo_movimiento = 'SALIDA' THEN cantidad ELSE 0 END) as total_salidas
    FROM movimientos_inventario 
    WHERE inventario_id = ?
");
$stmt->execute([$id]);
$estadisticas = $stmt->fetch();

// Obtener últimos movimientos
$stmt = $pdo->prepare("
    SELECT m.*, u.nombre as usuario_nombre
    FROM movimientos_inventario m
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.inventario_id = ?
    ORDER BY m.fecha_movimiento DESC
    LIMIT 5
");
$stmt->execute([$id]);
$ultimos_movimientos = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto - Sistema de Equipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.danger { border-left-color: #dc3545; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
            <div class="text-center mb-4">
                <h4 class="text-white">
                    <i class="fas fa-wifi"></i> Sistema Equipos
                </h4>
                <small class="text-white-50"><?php echo $_SESSION['usuario_nombre']; ?></small>
            </div>

            <ul class="nav nav-pills flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="inventario.php"><i class="fas fa-boxes"></i> Inventario</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="movimientos.php"><i class="fas fa-exchange-alt"></i> Movimientos</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                </li>
            </ul>
        </nav>

        <!-- Main -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
                <h2><i class="fas fa-edit"></i> Editar Producto</h2>
                <div class="btn-toolbar">
                    <a href="inventario.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Volver al Inventario
                    </a>
                    <a href="movimientos.php?producto=<?php echo $id; ?>" class="btn btn-outline-info">
                        <i class="fas fa-history"></i> Ver Movimientos
                    </a>
                </div>
            </div>

            <?php echo $mensaje; ?>

            <!-- Estadísticas rápidas del producto -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card stat-card primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Stock Actual</h6>
                                    <h3 class="mb-0"><?php echo $producto['stock_actual']; ?></h3>
                                </div>
                                <div class="mt-4">
                                    <a href="inventario.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left"></i> Cancelar
                                    </a>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Guardar Cambios
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Información adicional -->
                <div class="col-md-4">
                    <!-- Información del stock -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-info-circle"></i> Información del Stock</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6"><strong>Stock Actual:</strong></div>
                                <div class="col-6">
                                    <span class="badge bg-<?php 
                                        echo $producto['stock_actual'] == 0 ? 'danger' : 
                                            ($producto['stock_actual'] <= $producto['stock_minimo'] ? 'warning' : 'success'); 
                                    ?>">
                                        <?php echo $producto['stock_actual']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>Stock Mínimo:</strong></div>
                                <div class="col-6"><?php echo $producto['stock_minimo']; ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>Valor Total:</strong></div>
                                <div class="col-6">$<?php echo number_format($producto['stock_actual'] * $producto['precio_unitario'], 2); ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6"><strong>Total Movimientos:</strong></div>
                                <div class="col-6"><?php echo $estadisticas['total_movimientos'] ?? 0; ?></div>
                            </div>
                            <div class="row">
                                <div class="col-6"><strong>Registrado:</strong></div>
                                <div class="col-6"><?php echo date('d/m/Y', strtotime($producto['fecha_registro'])); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones rápidas -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-bolt"></i> Acciones Rápidas</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalEntradaRapida">
                                    <i class="fas fa-plus"></i> Entrada Rápida
                                </button>
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modalSalidaRapida">
                                    <i class="fas fa-minus"></i> Salida Rápida
                                </button>
                                <a href="movimientos.php?producto=<?php echo $id; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-history"></i> Ver Historial
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimos movimientos -->
            <?php if (!empty($ultimos_movimientos)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-history"></i> Últimos Movimientos</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tipo</th>
                                            <th>Cantidad</th>
                                            <th>Motivo</th>
                                            <th>Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($ultimos_movimientos as $mov): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($mov['fecha_movimiento'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $mov['tipo_movimiento'] == 'ENTRADA' ? 'success' : 
                                                            ($mov['tipo_movimiento'] == 'SALIDA' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $mov['tipo_movimiento']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $mov['cantidad']; ?></td>
                                                <td><?php echo htmlspecialchars($mov['motivo']); ?></td>
                                                <td><?php echo htmlspecialchars($mov['usuario_nombre']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- Modal Entrada Rápida -->
<div class="modal fade" id="modalEntradaRapida" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Entrada Rápida
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="movimientos.php">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_movimiento">
                    <input type="hidden" name="tipo_movimiento" value="ENTRADA">
                    <input type="hidden" name="inventario_id" value="<?php echo $id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Producto</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($producto['nombre']); ?>" readonly>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="cantidad" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Precio Unitario</label>
                            <input type="number" name="precio_unitario" class="form-control" 
                                   step="0.01" min="0" value="<?php echo $producto['precio_unitario']; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo *</label>
                        <input type="text" name="motivo" class="form-control" placeholder="Motivo de la entrada" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Proveedor</label>
                        <input type="text" name="proveedor" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Registrar Entrada
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Salida Rápida -->
<div class="modal fade" id="modalSalidaRapida" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-minus"></i> Salida Rápida
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="movimientos.php">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_movimiento">
                    <input type="hidden" name="tipo_movimiento" value="SALIDA">
                    <input type="hidden" name="inventario_id" value="<?php echo $id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Producto</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($producto['nombre']); ?>" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" name="cantidad" class="form-control" min="1" max="<?php echo $producto['stock_actual']; ?>" required>
                        <small class="text-muted">Stock disponible: <?php echo $producto['stock_actual']; ?></small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Motivo *</label>
                        <input type="text" name="motivo" class="form-control" placeholder="Motivo de la salida" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Destinatario</label>
                        <input type="text" name="destinatario" class="form-control" placeholder="Técnico, área, cliente...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-save"></i> Registrar Salida
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>="align-self-center">
                                    <i class="fas fa-boxes fa-2x text-primary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card stat-card success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Total Entradas</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_entradas'] ?? 0; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-arrow-up fa-2x text-success"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-3">
                    <div class="card stat-card danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title text-muted">Total Salidas</h6>
                                    <h3 class="mb-0"><?php echo $estadisticas['total_salidas'] ?? 0; ?></h3>
                                </div>
                                <div class="align-self-center">
                                    <i class="fas fa-arrow-down fa-2x text-danger"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Formulario de edición -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-edit"></i> Datos del Producto</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del Producto *</label>
                                        <input type="text" name="nombre" class="form-control" 
                                               value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de Equipo</label>
                                        <select name="tipo_equipo_id" class="form-select">
                                            <option value="">Sin tipo específico</option>
                                            <?php foreach ($tipos_equipo as $tipo): ?>
                                                <option value="<?php echo $tipo['id']; ?>" 
                                                        <?php echo $producto['tipo_equipo_id'] == $tipo['id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea name="descripcion" class="form-control" rows="2"><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Marca</label>
                                        <input type="text" name="marca" class="form-control" 
                                               value="<?php echo htmlspecialchars($producto['marca']); ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Modelo</label>
                                        <input type="text" name="modelo" class="form-control" 
                                               value="<?php echo htmlspecialchars($producto['modelo']); ?>">
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Stock Mínimo *</label>
                                        <input type="number" name="stock_minimo" class="form-control" 
                                               value="<?php echo $producto['stock_minimo']; ?>" min="0" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Precio Unitario</label>
                                        <input type="number" name="precio_unitario" class="form-control" 
                                               value="<?php echo $producto['precio_unitario']; ?>" step="0.01" min="0">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Estado *</label>
                                        <select name="estado" class="form-select" required>
                                            <option value="Activo" <?php echo $producto['estado'] == 'Activo' ? 'selected' : ''; ?>>Activo</option>
                                            <option value="Inactivo" <?php echo $producto['estado'] == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                            <option value="Descontinuado" <?php echo $producto['estado'] == 'Descontinuado' ? 'selected' : ''; ?>>Descontinuado</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Ubicación en Almacén</label>
                                    <input type="text" name="ubicacion_almacen" class="form-control" 
                                           value="<?php echo htmlspecialchars($producto['ubicacion_almacen']); ?>">
                                </div>

                                <div class