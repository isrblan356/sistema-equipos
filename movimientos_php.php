<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensaje = '';

// Procesar nuevo movimiento
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $_POST['accion'] == 'agregar_movimiento') {
    $inventario_id = $_POST['inventario_id'];
    $tipo_movimiento = $_POST['tipo_movimiento'];
    $cantidad = (int)$_POST['cantidad'];
    $precio_unitario = (float)$_POST['precio_unitario'];
    $motivo = limpiarDatos($_POST['motivo']);
    $observaciones = limpiarDatos($_POST['observaciones']);
    $numero_factura = limpiarDatos($_POST['numero_factura']);
    $proveedor = limpiarDatos($_POST['proveedor']);
    $destinatario = limpiarDatos($_POST['destinatario']);
    
    try {
        // Validar stock suficiente para salidas
        if ($tipo_movimiento == 'SALIDA') {
            $stmt = $pdo->prepare("SELECT stock_actual FROM inventario WHERE id = ?");
            $stmt->execute([$inventario_id]);
            $stock_actual = $stmt->fetch()['stock_actual'];
            
            if ($stock_actual < $cantidad) {
                $mensaje = mostrarAlerta('Stock insuficiente. Stock actual: ' . $stock_actual, 'error');
            } else {
                $stmt = $pdo->prepare("INSERT INTO movimientos_inventario (inventario_id, tipo_movimiento, cantidad, precio_unitario, motivo, observaciones, numero_factura, proveedor, destinatario, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$inventario_id, $tipo_movimiento, $cantidad, $precio_unitario, $motivo, $observaciones, $numero_factura, $proveedor, $destinatario, $_SESSION['usuario_id']]);
                $mensaje = mostrarAlerta('Movimiento registrado exitosamente', 'success');
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO movimientos_inventario (inventario_id, tipo_movimiento, cantidad, precio_unitario, motivo, observaciones, numero_factura, proveedor, destinatario, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$inventario_id, $tipo_movimiento, $cantidad, $precio_unitario, $motivo, $observaciones, $numero_factura, $proveedor, $destinatario, $_SESSION['usuario_id']]);
            $mensaje = mostrarAlerta('Movimiento registrado exitosamente', 'success');
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al registrar movimiento: ' . $e->getMessage(), 'error');
    }
}

// Obtener productos para el select
$stmt = $pdo->query("SELECT * FROM inventario WHERE estado = 'Activo' ORDER BY nombre");
$productos = $stmt->fetchAll();

// Filtros
$filtro_producto = isset($_GET['producto']) ? $_GET['producto'] : '';
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// Obtener movimientos con filtros
$sql = "SELECT m.*, i.nombre as producto_nombre, i.marca, i.modelo, u.nombre as usuario_nombre
        FROM movimientos_inventario m
        LEFT JOIN inventario i ON m.inventario_id = i.id
        LEFT JOIN usuarios u ON m.usuario_id = u.id
        WHERE 1=1";

$params = [];

if (!empty($filtro_producto)) {
    $sql .= " AND m.inventario_id = ?";
    $params[] = $filtro_producto;
}

if (!empty($filtro_tipo)) {
    $sql .= " AND m.tipo_movimiento = ?";
    $params[] = $filtro_tipo;
}

if (!empty($fecha_inicio)) {
    $sql .= " AND DATE(m.fecha_movimiento) >= ?";
    $params[] = $fecha_inicio;
}

if (!empty($fecha_fin)) {
    $sql .= " AND DATE(m.fecha_movimiento) <= ?";
    $params[] = $fecha_fin;
}

$sql .= " ORDER BY m.fecha_movimiento DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll();

// Estadísticas rápidas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_inventario WHERE DATE(fecha_movimiento) = CURDATE()");
$movimientos_hoy = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_inventario WHERE tipo_movimiento = 'ENTRADA' AND DATE(fecha_movimiento) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$entradas_semana = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_inventario WHERE tipo_movimiento = 'SALIDA' AND DATE(fecha_movimiento) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$salidas_semana = $stmt->fetch()['total'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos - Sistema de Equipos</title>
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
        .movimiento-entrada { background-color: #d4edda !important; }
        .movimiento-salida { background-color: #f8d7da !important; }
        .movimiento-ajuste { background-color: #fff3cd !important; }
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
                        <a class="nav-link" href="inventario.php">
                            <i class="fas fa-boxes"></i> Inventario
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="movimientos.php">
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
                    <h1 class="h2">Movimientos de Inventario</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalEntrada">
                            <i class="fas fa-plus"></i> Entrada
                        </button>
                        <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#modalSalida">
                            <i class="fas fa-minus"></i> Salida
                        </button>
                        <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalAjuste">
                            <i class="fas fa-edit"></i> Ajuste
                        </button>
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
                                        <h6 class="card-title text-muted">Movimientos Hoy</h6>
                                        <h3 class="mb-0"><?php echo $movimientos_hoy; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar-day fa-2x text-primary"></i>
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
                                        <h6 class="card-title text-muted">Entradas (7 días)</h6>
                                        <h3 class="mb-0"><?php echo $entradas_semana; ?></h3>
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
                                        <h6 class="card-title text-muted">Salidas (7 días)</h6>
                                        <h3 class="mb-0"><?php echo $salidas_semana; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-arrow-down fa-2x text-danger"></i>
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
                                <label class="form-label">Producto</label>
                                <select name="producto" class="form-select">
                                    <option value="">Todos los productos</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id']; ?>" 
                                                <?php echo $filtro_producto == $producto['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($producto['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Tipo</label>
                                <select name="tipo" class="form-select">
                                    <option value="">Todos</option>
                                    <option value="ENTRADA" <?php echo $filtro_tipo == 'ENTRADA' ? 'selected' : ''; ?>>Entrada</option>
                                    <option value="SALIDA" <?php echo $filtro_tipo == 'SALIDA' ? 'selected' : ''; ?>>Salida</option>
                                    <option value="AJUSTE" <?php echo $filtro_tipo == 'AJUSTE' ? 'selected' : ''; ?>>Ajuste</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" class="form-control" 
                                       value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" name="fecha_fin" class="form-control" 
                                       value="<?php echo htmlspecialchars($fecha_fin); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="fas fa-search"></i> Filtrar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <a href="movimientos.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de movimientos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Historial de Movimientos (<?php echo count($movimientos); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unit.</th>
                                        <th>Total</th>
                                        <th>Motivo</th>
                                        <th>Usuario</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($movimientos)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center text-muted">
                                                No se encontraron movimientos
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($movimientos as $movimiento): ?>
                                            <?php 
                                            $clase_fila = '';
                                            switch($movimiento['tipo_movimiento']) {
                                                case 'ENTRADA': $clase_fila = 'movimiento-entrada'; break;
                                                case 'SALIDA': $clase_fila = 'movimiento-salida'; break;
                                                case 'AJUSTE': $clase_fila = 'movimiento-ajuste'; break;
                                            }
                                            ?>
                                            <tr class="<?php echo $clase_fila; ?>">
                                                <td><?php echo date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $movimiento['tipo_movimiento'] == 'ENTRADA' ? 'success' : 
                                                            ($movimiento['tipo_movimiento'] == 'SALIDA' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo $movimiento['tipo_movimiento']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($movimiento['producto_nombre']); ?></strong>
                                                    <?php if (!empty($movimiento['marca']) || !empty($movimiento['modelo'])): ?>
                                                        <br><small class="text-muted">
                                                            <?php echo htmlspecialchars($movimiento['marca'] . ' ' . $movimiento['modelo']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">
                                                        <?php echo $movimiento['cantidad']; ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($movimiento['precio_unitario'], 2); ?></td>
                                                <td>$<?php echo number_format($movimiento['cantidad'] * $movimiento['precio_unitario'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($movimiento['motivo']); ?></td>
                                                <td><?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                                            data-bs-target="#modalDetalle<?php echo $movimiento['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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

    <!-- Modal Entrada -->
    <div class="modal fade" id="modalEntrada" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus"></i> Registrar Entrada de Inventario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_movimiento">
                        <input type="hidden" name="tipo_movimiento" value="ENTRADA">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Producto *</label>
                                <select name="inventario_id" class="form-select" required>
                                    <option value="">Seleccionar producto...</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id']; ?>">
                                            <?php echo htmlspecialchars($producto['nombre'] . ' - ' . $producto['marca'] . ' ' . $producto['modelo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cantidad *</label>
                                <input type="number" name="cantidad" class="form-control" min="1" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Precio Unitario</label>
                                <input type="number" name="precio_unitario" class="form-control" step="0.01" min="0" value="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de Factura</label>
                                <input type="text" name="numero_factura" class="form-control">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor</label>
                                <input type="text" name="proveedor" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Motivo *</label>
                                <input type="text" name="motivo" class="form-control" placeholder="Ej: Compra de equipos" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"></textarea>
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

    <!-- Modal Salida -->
    <div class="modal fade" id="modalSalida" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-minus"></i> Registrar Salida de Inventario
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_movimiento">
                        <input type="hidden" name="tipo_movimiento" value="SALIDA">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Producto *</label>
                                <select name="inventario_id" class="form-select" required id="producto-salida">
                                    <option value="">Seleccionar producto...</option>
                                    <?php foreach ($productos as $producto): ?>
                                        <option value="<?php echo $producto['id']; ?>" data-stock="<?php echo $producto['stock_actual']; ?>">
                                            <?php echo htmlspecialchars($producto['nombre'] . ' - ' . $producto['marca'] . ' ' . $producto['modelo']); ?>
                                            (Stock: <?php echo $producto['stock_actual']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cantidad *</label>
                                <input type="number" name="cantidad" class="form-control" min="1" required id="cantidad-salida">
                                <small class="text-muted">Stock disponible: <span id="stock-disponible">0</span></small>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Destinatario</label>
                                <input type="text" name="destinatario" class="form-control" placeholder="Técnico, área, cliente...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Motivo *</label>
                                <input type="text" name="motivo" class="form-control" placeholder="Ej: Instalación en torre" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"></textarea>
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

    <!-- Modal Ajuste -->
    <div class="modal fade" id="modalAjuste" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="fas fa-edit"></i> Ajuste de Inventario
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="agregar_movimiento">
                        <input type="hidden" name="tipo_movimiento" value="AJUSTE">
                        
                