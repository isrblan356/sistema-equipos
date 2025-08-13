<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensaje = '';

// Procesar formulario de nuevo producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar') {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $marca = limpiarDatos($_POST['marca']);
    $modelo = limpiarDatos($_POST['modelo']);
    $tipo_equipo_id = !empty($_POST['tipo_equipo_id']) ? $_POST['tipo_equipo_id'] : null;
    $stock_minimo = (int)$_POST['stock_minimo'];
    $precio_unitario = (float)$_POST['precio_unitario'];
    $ubicacion_almacen = limpiarDatos($_POST['ubicacion_almacen']);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO inventario (nombre, descripcion, marca, modelo, tipo_equipo_id, stock_minimo, precio_unitario, ubicacion_almacen, usuario_registro_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $descripcion, $marca, $modelo, $tipo_equipo_id, $stock_minimo, $precio_unitario, $ubicacion_almacen, $_SESSION['usuario_id']]);
        
        $mensaje = mostrarAlerta('Producto agregado al inventario exitosamente', 'success');
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al agregar producto: ' . $e->getMessage(), 'error');
    }
}

// Procesar eliminación
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    try {
        // Verificar si tiene movimientos
        $stmt = $pdo->prepare("SELECT COUNT(*) as movimientos FROM movimientos_inventario WHERE inventario_id = ?");
        $stmt->execute([$_GET['eliminar']]);
        $movimientos = $stmt->fetch()['movimientos'];
        
        if ($movimientos > 0) {
            $mensaje = mostrarAlerta('No se puede eliminar el producto porque tiene movimientos registrados', 'error');
        } else {
            $stmt = $pdo->prepare("DELETE FROM inventario WHERE id = ?");
            $stmt->execute([$_GET['eliminar']]);
            $mensaje = mostrarAlerta('Producto eliminado exitosamente', 'success');
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al eliminar producto: ' . $e->getMessage(), 'error');
    }
}

// Obtener tipos de equipo
$stmt = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre");
$tipos_equipo = $stmt->fetchAll();

// Obtener inventario con filtros
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$buscar = isset($_GET['buscar']) ? limpiarDatos($_GET['buscar']) : '';
$mostrar_bajo_stock = isset($_GET['bajo_stock']) ? true : false;

$sql = "SELECT i.*, te.nombre as tipo_nombre, u.nombre as usuario_nombre 
        FROM inventario i 
        LEFT JOIN tipos_equipo te ON i.tipo_equipo_id = te.id 
        LEFT JOIN usuarios u ON i.usuario_registro_id = u.id 
        WHERE 1=1";

$params = [];

if (!empty($filtro_tipo)) {
    $sql .= " AND i.tipo_equipo_id = ?";
    $params[] = $filtro_tipo;
}

if (!empty($filtro_estado)) {
    $sql .= " AND i.estado = ?";
    $params[] = $filtro_estado;
}

if (!empty($buscar)) {
    $sql .= " AND (i.nombre LIKE ? OR i.descripcion LIKE ? OR i.marca LIKE ? OR i.modelo LIKE ?)";
    $buscar_param = "%$buscar%";
    $params = array_merge($params, [$buscar_param, $buscar_param, $buscar_param, $buscar_param]);
}

if ($mostrar_bajo_stock) {
    $sql .= " AND i.stock_actual <= i.stock_minimo";
}

$sql .= " ORDER BY i.nombre";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Estadísticas rápidas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM inventario WHERE estado = 'Activo'");
$total_productos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM inventario WHERE stock_actual <= stock_minimo AND estado = 'Activo'");
$productos_bajo_stock = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(stock_actual * precio_unitario) as valor FROM inventario WHERE estado = 'Activo'");
$valor_total = $stmt->fetch()['valor'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario - Sistema de Equipos</title>
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
        .stock-bajo {
            background-color: #fff3cd !important;
        }
        .stock-critico {
            background-color: #f8d7da !important;
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.success { border-left-color: #28a745; }
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="equipos.php">
                            <i class="fas fa-router"></i> Equipos
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="inventario.php">
                            <i class="fas fa-boxes"></i> Inventario
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="movimientos.php">
                            <i class="fas fa-exchange-alt"></i> Movimientos
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="revisiones.php">
                            <i class="fas fa-clipboard-check"></i> Revisiones
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="reportes.php">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item mt-4">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Gestión de Inventario</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#modalAgregarProducto">
                            <i class="fas fa-plus"></i> Agregar Producto
                        </button>
                        <a href="movimientos.php" class="btn btn-outline-secondary">
                            <i class="fas fa-exchange-alt"></i> Ver Movimientos
                        </a>
                    </div>
                </div>

                <?php echo $mensaje; ?>

                <!-- Estadísticas rápidas -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Total Productos</h6>
                                        <h3 class="mb-0"><?php echo $total_productos; ?></h3>
                                    </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Precio Unitario</label>
                                <input type="number" name="precio_unitario" class="form-control" step="0.01" min="0" value="0.00">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Ubicación en Almacén</label>
                                <input type="text" name="ubicacion_almacen" class="form-control" placeholder="Ej: Estante A1">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x text-primary"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <div class="card stat-card warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="card-title text-muted">Bajo Stock</h6>
                                        <h3 class="mb-0"><?php echo $productos_bajo_stock; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
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
                                        <h6 class="card-title text-muted">Valor Total</h6>
                                        <h3 class="mb-0">$<?php echo number_format($valor_total, 2); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filtros</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Tipo de Equipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($tipos_equipo as $tipo): ?>
                                        <option value="<?php echo $tipo['id']; ?>" 
                                                <?php echo $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Estado</label>
                                <select name="estado" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="Activo" <?php echo $filtro_estado == 'Activo' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="Inactivo" <?php echo $filtro_estado == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                    <option value="Descontinuado" <?php echo $filtro_estado == 'Descontinuado' ? 'selected' : ''; ?>>Descontinuado</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Buscar</label>
                                <input type="text" name="buscar" class="form-control" 
                                       placeholder="Nombre, marca, modelo..." 
                                       value="<?php echo htmlspecialchars($buscar); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Filtros</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="bajo_stock" 
                                           <?php echo $mostrar_bajo_stock ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Solo bajo stock</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de inventario -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Productos en Inventario (<?php echo count($productos); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Producto</th>
                                        <th>Marca/Modelo</th>
                                        <th>Tipo</th>
                                        <th>Stock Actual</th>
                                        <th>Stock Mínimo</th>
                                        <th>Precio Unit.</th>
                                        <th>Valor Total</th>
                                        <th>Ubicación</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($productos)): ?>
                                        <tr>
                                            <td colspan="10" class="text-center text-muted">
                                                No se encontraron productos
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($productos as $producto): ?>
                                            <?php 
                                            $clase_fila = '';
                                            if ($producto['stock_actual'] <= $producto['stock_minimo'] && $producto['stock_actual'] > 0) {
                                                $clase_fila = 'stock-bajo';
                                            } elseif ($producto['stock_actual'] == 0) {
                                                $clase_fila = 'stock-critico';
                                            }
                                            ?>
                                            <tr class="<?php echo $clase_fila; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                                    <?php if (!empty($producto['descripcion'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($producto['descripcion']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($producto['marca']); ?><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($producto['modelo']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($producto['tipo_nombre'] ?? 'Sin tipo'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $producto['stock_actual'] == 0 ? 'danger' : 
                                                            ($producto['stock_actual'] <= $producto['stock_minimo'] ? 'warning' : 'success'); 
                                                    ?>">
                                                        <?php echo $producto['stock_actual']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $producto['stock_minimo']; ?></td>
                                                <td>$<?php echo number_format($producto['precio_unitario'], 2); ?></td>
                                                <td>$<?php echo number_format($producto['stock_actual'] * $producto['precio_unitario'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($producto['ubicacion_almacen']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $producto['estado'] == 'Activo' ? 'success' : 
                                                            ($producto['estado'] == 'Inactivo' ? 'secondary' : 'warning'); 
                                                    ?>">
                                                        <?php echo $producto['estado']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="movimientos.php?producto=<?php echo $producto['id']; ?>" 
                                                           class="btn btn-info" title="Ver movimientos">
                                                            <i class="fas fa-history"></i>
                                                        </a>
                                                        <a href="editar_inventario.php?id=<?php echo $producto['id']; ?>" 
                                                           class="btn btn-warning" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="?eliminar=<?php echo $producto['id']; ?>" 
                                                           class="btn btn-danger" title="Eliminar"
                                                           onclick="return confirm('¿Estás seguro de eliminar este producto?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
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
            </main>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> Agregar Producto al Inventario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre del Producto *</label>
                                <input type="text" name="nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Equipo</label>
                                <select name="tipo_equipo_id" class="form-select">
                                    <option value="">Sin tipo específico</option>
                                    <?php foreach ($tipos_equipo as $tipo): ?>
                                        <option value="<?php echo $tipo['id']; ?>">
                                            <?php echo htmlspecialchars($tipo['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Marca</label>
                                <input type="text" name="marca" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Modelo</label>
                                <input type="text" name="modelo" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Stock Mínimo *</label>
                                <input type="number" name="stock_minimo" class="form-control" value="0" min="0" required>