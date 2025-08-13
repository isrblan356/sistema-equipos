<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();

// Estadísticas generales de inventario
$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos");
$total_productos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(stock_actual) as total FROM productos");
$total_stock = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE stock_actual <= stock_minimo");
$productos_stock_bajo = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM productos WHERE stock_actual = 0");
$productos_sin_stock = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(stock_actual * precio_unitario) as total FROM productos");
$valor_inventario = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM movimientos_inventario WHERE DATE(fecha_movimiento) = CURDATE()");
$movimientos_hoy = $stmt->fetch()['total'];

// Obtener productos recientes
$stmt = $pdo->query("
    SELECT p.*, c.nombre as categoria_nombre,
    CASE 
        WHEN p.stock_actual = 0 THEN 'sin_stock'
        WHEN p.stock_actual <= p.stock_minimo THEN 'stock_bajo'
        ELSE 'stock_normal'
    END as estado_stock
    FROM productos p 
    LEFT JOIN categorias c ON p.categoria_id = c.id 
    ORDER BY p.fecha_registro DESC 
    LIMIT 5
");
$productos_recientes = $stmt->fetchAll();

// Obtener movimientos recientes
$stmt = $pdo->query("
    SELECT m.*, p.nombre as producto_nombre, p.codigo, u.nombre as usuario_nombre
    FROM movimientos_inventario m
    LEFT JOIN productos p ON m.producto_id = p.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    ORDER BY m.fecha_movimiento DESC
    LIMIT 5
");
$movimientos_recientes = $stmt->fetchAll();

// Productos con stock crítico
$stmt = $pdo->query("
    SELECT p.*, c.nombre as categoria_nombre
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.stock_actual <= p.stock_minimo
    ORDER BY (p.stock_actual / NULLIF(p.stock_minimo, 0)) ASC
    LIMIT 10
");
$productos_criticos = $stmt->fetchAll();

// Top productos por valor
$stmt = $pdo->query("
    SELECT p.*, c.nombre as categoria_nombre, 
           (p.stock_actual * p.precio_unitario) as valor_total
    FROM productos p
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.stock_actual > 0
    ORDER BY valor_total DESC
    LIMIT 5
");
$productos_valor = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Inventario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stat-card {
            border-left: 4px solid;
        }
        .stat-card.primary { border-left-color: #007bff; }
        .stat-card.success { border-left-color: #28a745; }
        .stat-card.warning { border-left-color: #ffc107; }
        .stat-card.danger { border-left-color: #dc3545; }
        .stat-card.info { border-left-color: #17a2b8; }
        .stat-card.purple { border-left-color: #6f42c1; }
        
        .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        .nav-link:hover, .nav-link.active {
            color: white;
            background-color: rgba(255,255,255,0.1);
            border-radius: 5px;
        }
        
        .alert-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .stock-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .stock-normal { background-color: #28a745; }
        .stock-bajo { background-color: #ffc107; }
        .stock-sin { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
                <div class="text-center mb-4">
                    <h4 class="text-white">
                        <i class="fas fa-boxes"></i> Sistema Inventario
                    </h4>
                    <small class="text-white-50">Bienvenido, <?php echo $_SESSION['usuario_nombre']; ?></small>
                </div>
                
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2 position-relative">
                        <a class="nav-link" href="inventario.php">
                            <i class="fas fa-boxes"></i> Inventario
                            <?php if ($productos_stock_bajo > 0): ?>
                                <span class="alert-badge"><?php echo $productos_stock_bajo; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="reportes.php">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="categorias.php">
                            <i class="fas fa-tags"></i> Categorías
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
                    <h1 class="h2">Dashboard de Inventario</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                            </button>
                        </div>
                        <div class="btn-group">
                            <a href="inventario.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-boxes"></i> Ir a Inventario
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alertas de Stock -->
                <?php if ($productos_stock_bajo > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>¡Atención!</strong> Tienes <?php echo $productos_stock_bajo; ?> producto(s) con stock bajo o agotado.
                    <a href="inventario.php?stock=bajo" class="alert-link">Ver productos</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tarjetas de estadísticas -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card primary">
                            <div class="card-body text-center">
                                <i class="fas fa-boxes fa-2x text-primary mb-2"></i>
                                <h3 class="mb-0"><?php echo number_format($total_productos); ?></h3>
                                <small class="text-muted">Total Productos</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card success">
                            <div class="card-body text-center">
                                <i class="fas fa-cubes fa-2x text-success mb-2"></i>
                                <h3 class="mb-0"><?php echo number_format($total_stock); ?></h3>
                                <small class="text-muted">Unidades Total</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card warning">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                                <h3 class="mb-0"><?php echo $productos_stock_bajo; ?></h3>
                                <small class="text-muted">Stock Bajo</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card danger">
                            <div class="card-body text-center">
                                <i class="fas fa-ban fa-2x text-danger mb-2"></i>
                                <h3 class="mb-0"><?php echo $productos_sin_stock; ?></h3>
                                <small class="text-muted">Sin Stock</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card info">
                            <div class="card-body text-center">
                                <i class="fas fa-exchange-alt fa-2x text-info mb-2"></i>
                                <h3 class="mb-0"><?php echo $movimientos_hoy; ?></h3>
                                <small class="text-muted">Movimientos Hoy</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card purple">
                            <div class="card-body text-center">
                                <i class="fas fa-dollar-sign fa-2x text-purple mb-2"></i>
                                <h3 class="mb-0">$<?php echo number_format($valor_inventario, 0); ?></h3>
                                <small class="text-muted">Valor Total</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Productos Recientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-box"></i> Productos Recientes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($productos_recientes)): ?>
                                    <p class="text-muted">No hay productos registrados.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($productos_recientes as $producto): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                                    <small class="text-muted">
                                                        <?php echo htmlspecialchars($producto['categoria_nombre']); ?> - 
                                                        <?php echo htmlspecialchars($producto['codigo']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="stock-indicator stock-<?php echo str_replace('_', '-', $producto['estado_stock']); ?>"></span>
                                                    <span class="badge bg-<?php 
                                                        echo $producto['estado_stock'] == 'sin_stock' ? 'danger' : 
                                                            ($producto['estado_stock'] == 'stock_bajo' ? 'warning' : 'success'); 
                                                    ?>">
                                                        <?php echo $producto['stock_actual']; ?> <?php echo htmlspecialchars($producto['unidad_medida']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="inventario.php" class="btn btn-outline-primary btn-sm">
                                        Ver todos los productos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Movimientos Recientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> Movimientos Recientes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($movimientos_recientes)): ?>
                                    <p class="text-muted">No hay movimientos registrados.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($movimientos_recientes as $movimiento): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-<?php echo $movimiento['tipo_movimiento'] == 'ENTRADA' ? 'success' : 'warning'; ?> me-2">
                                                                <?php echo $movimiento['tipo_movimiento']; ?>
                                                            </span>
                                                            <?php echo htmlspecialchars($movimiento['producto_nombre']); ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <strong>Cantidad:</strong> <?php echo $movimiento['cantidad']; ?> |
                                                            <strong>Motivo:</strong> <?php echo htmlspecialchars($movimiento['motivo']); ?>
                                                        </p>
                                                        <small>Por: <?php echo htmlspecialchars($movimiento['usuario_nombre']); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y H:i', strtotime($movimiento['fecha_movimiento'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="inventario.php" class="btn btn-outline-info btn-sm">
                                        Ver historial completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Productos con Stock Crítico -->
                    <?php if (!empty($productos_criticos)): ?>
                    <div class="col-lg-8 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Productos con Stock Crítico
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Categoría</th>
                                                <th>Stock Actual</th>
                                                <th>Stock Mín.</th>
                                                <th>Estado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($productos_criticos as $producto): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($producto['codigo']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($producto['categoria_nombre']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $producto['stock_actual'] == 0 ? 'danger' : 'warning'; ?>">
                                                            <?php echo $producto['stock_actual']; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $producto['stock_minimo']; ?></td>
                                                    <td>
                                                        <?php if ($producto['stock_actual'] == 0): ?>
                                                            <span class="badge bg-danger">Sin Stock</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">Stock Bajo</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-success" onclick="registrarEntrada(<?php echo $producto['id']; ?>)">
                                                            <i class="fas fa-plus"></i> Entrada
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Top Productos por Valor -->
                    <div class="col-lg-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-medal"></i> Top Productos por Valor
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($productos_valor)): ?>
                                    <p class="text-muted">No hay productos con stock.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($productos_valor as $index => $producto): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-primary rounded-pill me-2"><?php echo $index + 1; ?></span>
                                                    <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong><br>
                                                    <small class="text-muted">
                                                        Stock: <?php echo $producto['stock_actual']; ?> × 
                                                        $<?php echo number_format($producto['precio_unitario'], 0); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <h6 class="mb-0 text-success">
                                                        $<?php echo number_format($producto['valor_total'], 0); ?>
                                                    </h6>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function registrarEntrada(productoId) {
            // Redirigir al módulo de inventario con modal de entrada abierto
            window.location.href = `inventario.php?entrada=${productoId}`;
        }

        // Auto-dismiss alerts after 8 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 8000);

        // Actualizar estadísticas cada 5 minutos
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>