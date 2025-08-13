<?php
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// --- CREACIÓN DE TABLAS INICIALES ---
$pdo->exec("CREATE TABLE IF NOT EXISTS productos ( id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(255) NOT NULL, codigo VARCHAR(100) NOT NULL UNIQUE, descripcion TEXT, stock_actual INT DEFAULT 0, stock_minimo INT DEFAULT 0, fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP )");
$pdo->exec("CREATE TABLE IF NOT EXISTS movimientos ( id INT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, tipo ENUM('entrada', 'salida') NOT NULL, cantidad INT NOT NULL, fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE )");

// --- FUNCIONES ---
function limpiar($dato) { return htmlspecialchars(trim($dato)); }
function obtenerEstadisticasSede($pdo, $tabla_productos, $tabla_movimientos) { $hoy = date('Y-m-d'); $stats = []; $stats['total_productos'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos`")->fetchColumn(); $stats['stock_bajo'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos` WHERE stock_actual <= stock_minimo AND stock_actual > 0")->fetchColumn(); $stats['sin_stock'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_productos` WHERE stock_actual <= 0")->fetchColumn(); $stats['movimientos_hoy'] = $pdo->query("SELECT COUNT(*) FROM `$tabla_movimientos` WHERE DATE(fecha) = '$hoy'")->fetchColumn(); return $stats; }
function hex2rgb($hex) { $hex = str_replace("#", "", $hex); if(strlen($hex) == 3) { $r = hexdec(substr($hex,0,1).substr($hex,0,1)); $g = hexdec(substr($hex,1,1).substr($hex,1,1)); $b = hexdec(substr($hex,2,1).substr($hex,2,1)); } else { $r = hexdec(substr($hex,0,2)); $g = hexdec(substr($hex,2,2)); $b = hexdec(substr($hex,4,2)); } return "$r, $g, $b"; }

// --- CONFIGURACIÓN DINÁMICA DE SEDES ---
$sedes_query = $pdo->query("SELECT * FROM sedes WHERE activa = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$sedes_config = [];
foreach ($sedes_query as $sede) { $sedes_config[$sede['id']] = $sede; }

// --- VISTA ACTUAL ---
$vista_actual = isset($_GET['sede_id']) ? $_GET['sede_id'] : 'vista_inventario_dashboard';

// --- PROCESAR FORMULARIOS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion']) && $vista_actual != 'vista_inventario_dashboard') {
    $config = $sedes_config[$vista_actual];
    $tabla_productos = $config['tabla_productos'];
    $tabla_movimientos = $config['tabla_movimientos'];
    if ($_POST['accion'] == 'agregar') { $stmt = $pdo->prepare("INSERT INTO `$tabla_productos` (nombre, codigo, descripcion, stock_actual, stock_minimo) VALUES (?, ?, ?, ?, ?)"); $stmt->execute([limpiar($_POST['nombre']), limpiar($_POST['codigo']), limpiar($_POST['descripcion']), intval($_POST['stock_actual']), intval($_POST['stock_minimo'])]); }
    elseif ($_POST['accion'] == 'editar') { $stmt = $pdo->prepare("UPDATE `$tabla_productos` SET nombre=?, codigo=?, descripcion=?, stock_minimo=? WHERE id=?"); $stmt->execute([limpiar($_POST['nombre']), limpiar($_POST['codigo']), limpiar($_POST['descripcion']), intval($_POST['stock_minimo']), intval($_POST['id'])]); }
    elseif ($_POST['accion'] == 'eliminar') { $stmt = $pdo->prepare("DELETE FROM `$tabla_productos` WHERE id=?"); $stmt->execute([intval($_POST['id'])]); }
    elseif ($_POST['accion'] == 'movimiento') { $producto_id = intval($_POST['producto_id']); $tipo = $_POST['tipo']; $cantidad = intval($_POST['cantidad']); $stmt = $pdo->prepare("INSERT INTO `$tabla_movimientos` (producto_id, tipo, cantidad) VALUES (?, ?, ?)"); $stmt->execute([$producto_id, $tipo, $cantidad]); $update = $tipo === 'entrada' ? "UPDATE `$tabla_productos` SET stock_actual = stock_actual + ? WHERE id = ?" : "UPDATE `$tabla_productos` SET stock_actual = stock_actual - ? WHERE id = ?"; $stmt = $pdo->prepare($update); $stmt->execute([$cantidad, $producto_id]); }
    header("Location: inventario.php?sede_id=" . $vista_actual); exit();
}

// --- OBTENER DATOS PARA LA VISTA ---
if ($vista_actual == 'vista_inventario_dashboard') {
    $stats_por_sede = []; foreach($sedes_config as $id => $sede) { $stats_por_sede[$id] = obtenerEstadisticasSede($pdo, $sede['tabla_productos'], $sede['tabla_movimientos']); }
    $totales = [ 'total_productos' => array_sum(array_column($stats_por_sede, 'total_productos')), 'stock_bajo'      => array_sum(array_column($stats_por_sede, 'stock_bajo')), 'sin_stock'       => array_sum(array_column($stats_por_sede, 'sin_stock')), 'movimientos_hoy' => array_sum(array_column($stats_por_sede, 'movimientos_hoy')) ];
    $ultimos_movimientos = []; foreach($sedes_config as $id => $sede) { $movs_query = $pdo->query("SELECT m.*, p.nombre as producto_nombre, '{$sede['nombre']}' as sede_nombre FROM `{$sede['tabla_movimientos']}` m JOIN `{$sede['tabla_productos']}` p ON m.producto_id = p.id ORDER BY m.fecha DESC LIMIT 5"); if($movs_query) { $ultimos_movimientos = array_merge($ultimos_movimientos, $movs_query->fetchAll(PDO::FETCH_ASSOC)); } }
    usort($ultimos_movimientos, fn($a, $b) => strtotime($b['fecha']) - strtotime($a['fecha'])); $ultimos_movimientos = array_slice($ultimos_movimientos, 0, 10);
} else {
    if (!isset($sedes_config[$vista_actual])) { header("Location: inventario.php"); exit(); }
    $config = $sedes_config[$vista_actual]; $tabla_productos = $config['tabla_productos']; $tabla_movimientos = $config['tabla_movimientos'];
    $productos = $pdo->query("SELECT * FROM `$tabla_productos` ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    $movimientos = $pdo->query("SELECT m.*, p.nombre FROM `$tabla_movimientos` m JOIN `$tabla_productos` p ON m.producto_id = p.id ORDER BY m.fecha DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    $stats_sede = obtenerEstadisticasSede($pdo, $tabla_productos, $tabla_movimientos);
    $total_productos = $stats_sede['total_productos']; $movimientos_hoy = $stats_sede['movimientos_hoy']; $stock_bajo = $stats_sede['stock_bajo']; $sin_stock = $stats_sede['sin_stock'];
}

$color_actual_hex = '#667eea'; $color_actual_rgb = '102, 126, 234';
if ($vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual])) { $color_actual_hex = $sedes_config[$vista_actual]['color']; $color_actual_rgb = hex2rgb($color_actual_hex); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $vista_actual == 'vista_inventario_dashboard' ? 'Dashboard de Inventario' : 'Inventario ' . $sedes_config[$vista_actual]['nombre'] ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#fff') ?>; min-height: 100vh; color: #333; }
        .header { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.2); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto; flex-wrap: wrap; }
        .header h1 { color: #2c3e50; font-size: 2rem; font-weight: 700; display: flex; align-items: center; gap: 15px; }
        .header h1 i { color: <?= $color_actual_hex ?>; font-size: 2.2rem; }
        .sede-badge { background: <?= $vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : 'linear-gradient(45deg, #667eea, #764ba2)' ?>; color: white; padding: 5px 15px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-left: 15px; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border: none; border-radius: 25px; text-decoration: none; font-weight: 500; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .btn-nav:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn-nav.active { background: linear-gradient(45deg, #2c3e50, #34495e); color: white; border: 1px solid #2c3e50; }
        .user-info { display: flex; align-items: center; gap: 15px; color: #555; font-weight: 500; }
        .user-info i { color: <?= $color_actual_hex ?>; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .welcome-section { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; }
        .stats-overview, .sedes-section { margin-bottom: 2rem; }
        .stats-title { color: white; font-size: 1.5rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 10px; text-shadow: 1px 1px 3px rgba(0,0,0,0.2); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 2rem; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.15); transition: all 0.3s ease; position: relative; overflow: hidden; }
        .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; }
        .stat-card.primary::before { background: linear-gradient(45deg, #3498db, #2980b9); }
        .stat-card.success::before { background: linear-gradient(45deg, #27ae60, #2ecc71); }
        .stat-card.warning::before { background: linear-gradient(45deg, #f39c12, #e67e22); }
        .stat-card.danger::before { background: linear-gradient(45deg, #e74c3c, #c0392b); }
        .stat-card.info::before { background: linear-gradient(45deg, #9b59b6, #8e44ad); }
        .stat-card i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.8; }
        .stat-card.primary i { color: #3498db; } .stat-card.success i { color: #27ae60; } .stat-card.warning i { color: #f39c12; } .stat-card.danger i { color: #e74c3c; } .stat-card.info i { color: #9b59b6; }
        .stat-card h3 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; color: #2c3e50; }
        .stat-card p { color: #666; font-size: 1rem; font-weight: 500; }
        .sedes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .sede-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 1.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); transition: all 0.3s ease; }
        .sede-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #ecf0f1; }
        .sede-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 1rem; }
        .sede-stat { text-align: center; padding: 1rem; background: #f8f9fa; border-radius: 10px; }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .movements-section, .quick-actions { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border-radius: 20px; padding: 1.5rem; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
        .card h3 { color: #2c3e50; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px; }
        .card h3 i { color: <?= $color_actual_hex ?>; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { margin-bottom: 0.5rem; font-weight: 500; color: #555; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin: 5px; }
        .btn-primary { background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(45deg, #667eea, #764ba2)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#ccc') ?>; color: white; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #229954); color: white; }
        .btn-danger { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        th { background: <?= $vista_actual == 'vista_inventario_dashboard' ? 'linear-gradient(45deg, #34495e, #2c3e50)' : (isset($sedes_config[$vista_actual]) ? $sedes_config[$vista_actual]['gradient'] : '#ccc') ?>; color: white; padding: 15px 10px; text-align: left; font-weight: 600; }
        td { padding: 12px 10px; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        .action-buttons { display: flex; flex-direction: column; gap: 1rem; }
        .table-actions { display: flex; gap: 5px; flex-wrap: wrap; }
        input, select, textarea { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; transition: all 0.3s ease; background: #fdfdfd; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: <?= $color_actual_hex ?>; box-shadow: 0 0 0 4px rgba(<?= $color_actual_rgb ?>, 0.2); }
        
        /* ===== SECCIÓN DE ESTILOS NUEVA Y MEJORADA PARA MOVIMIENTOS ===== */
        .movement-list { padding: 0; list-style: none; }
        .movement-item { display: flex; align-items: center; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f0f0f0; }
        .movement-list li:last-child .movement-item { border-bottom: none; }
        .movement-icon { font-size: 1.5rem; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; flex-shrink: 0; }
        .movement-icon.entrada { background-color: #28a745; }
        .movement-icon.salida { background-color: #dc3545; }
        .movement-details { flex-grow: 1; }
        .movement-details h4 { font-size: 1rem; font-weight: 600; color: #2c3e50; margin: 0 0 4px 0; }
        .movement-details p { font-size: 0.85rem; color: #777; margin: 0; display: flex; align-items: center; gap: 8px; }
        .sede-badge-inline { padding: 3px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #e9ecef; color: #495057; }
        .movement-quantity { text-align: right; }
        .movement-quantity strong { font-size: 1.2rem; font-weight: 700; color: #333; }
        .movement-quantity span { font-size: 0.8rem; text-transform: uppercase; color: #888; display: block; }
        .no-movements { text-align: center; padding: 2rem; color: #888; }
        /* ===== FIN DE LA SECCIÓN DE ESTILOS ===== */

    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-<?= $vista_actual == 'vista_inventario_dashboard' ? 'tachometer-alt' : 'boxes' ?>"></i> <?= $vista_actual == 'vista_inventario_dashboard' ? 'Dashboard de Inventario' : 'Inventario ' . ($sedes_config[$vista_actual]['nombre'] ?? 'Desconocida') ?>
                <?php if ($vista_actual != 'vista_inventario_dashboard' && isset($sedes_config[$vista_actual])): ?>
                    <span class="sede-badge" style="background: <?= $sedes_config[$vista_actual]['gradient'] ?>;"><?= htmlspecialchars($sedes_config[$vista_actual]['nombre']) ?></span>
                <?php endif; ?>
            </h1>
            <div class="nav-buttons">
                <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Página Principal</a>
                <a href="inventario.php" class="btn-nav <?= $vista_actual == 'vista_inventario_dashboard' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard Inventario</a>
                <?php foreach($sedes_config as $id => $sede): ?>
                    <a href="?sede_id=<?= $id ?>" class="btn-nav <?= $vista_actual == $id ? 'active' : '' ?>" style="<?= $vista_actual == $id ? '' : 'background: '.$sede['gradient'].'; color: white; border-color: transparent;' ?>"><i class="fas fa-building"></i> <?= htmlspecialchars($sede['nombre']) ?></a>
                <?php endforeach; ?>
                <div class="user-info"><i class="fas fa-user-circle"></i> <span>Administrador</span><a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i></a></div>
            </div>
        </div>
    </div>
    <div class="container">
        <?php if ($vista_actual == 'vista_inventario_dashboard'): ?>
            <!-- VISTA DASHBOARD DE INVENTARIO -->
            <div class="welcome-section">
                <h2>Dashboard de Inventario</h2>
                <p>Aquí puedes ver un resumen del estado del inventario en todas las sedes y acceder a cada una de ellas.</p>
                <a href="dashboard.php" class="btn btn-primary" style="margin-top: 1rem;"><i class="fas fa-arrow-left"></i> Volver al Dashboard Principal</a>
            </div>
            <div class="stats-overview">
                <h2 class="stats-title"><i class="fas fa-chart-pie"></i> Resumen General</h2>
                <div class="stats-grid">
                    <div class="stat-card primary"><i class="fas fa-cubes"></i><h3><?= number_format($totales['total_productos']) ?></h3><p>Total de Productos</p></div>
                    <div class="stat-card success"><i class="fas fa-exchange-alt"></i><h3><?= number_format($totales['movimientos_hoy']) ?></h3><p>Movimientos Hoy</p></div>
                    <div class="stat-card warning"><i class="fas fa-exclamation-triangle"></i><h3><?= number_format($totales['stock_bajo']) ?></h3><p>Productos con Stock Bajo</p></div>
                    <div class="stat-card danger"><i class="fas fa-times-circle"></i><h3><?= number_format($totales['sin_stock']) ?></h3><p>Productos Sin Stock</p></div>
                </div>
            </div>
            <div class="sedes-section">
                <h2 class="stats-title"><i class="fas fa-sitemap"></i> Estadísticas por Sede</h2>
                <div class="sedes-grid">
                    <?php foreach($sedes_config as $id => $sede): $stats = $stats_por_sede[$id]; ?>
                        <div class="sede-card" style="border-left: 5px solid <?= $sede['color'] ?>">
                            <div class="sede-header"><h3><i class="fas fa-map-marker-alt" style="color: <?= $sede['color'] ?>"></i> <?= htmlspecialchars($sede['nombre']) ?></h3><a href="?sede_id=<?= $id ?>" class="btn btn-primary" style="background: <?= $sede['gradient'] ?>"><i class="fas fa-eye"></i> Ver Inventario</a></div>
                            <div class="sede-stats"><div class="sede-stat"><h4><?= $stats['total_productos'] ?></h4><span>Productos</span></div><div class="sede-stat"><h4><?= $stats['stock_bajo'] ?></h4><span>Stock Bajo</span></div><div class="sede-stat"><h4><?= $stats['sin_stock'] ?></h4><span>Sin Stock</span></div><div class="sede-stat"><h4><?= $stats['movimientos_hoy'] ?></h4><span>Mov. Hoy</span></div></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="content-grid">
                <!-- ===== SECCIÓN DE MOVIMIENTOS CON NUEVO ESTILO ===== -->
                <div class="movements-section">
                    <h3><i class="fas fa-history"></i> Últimos Movimientos Globales</h3>
                    <?php if (empty($ultimos_movimientos)): ?>
                        <div class="no-movements">
                            <i class="fas fa-box-open fa-2x"></i>
                            <p>No hay movimientos recientes.</p>
                        </div>
                    <?php else: ?>
                        <ul class="movement-list">
                            <?php foreach ($ultimos_movimientos as $mov): ?>
                                <li>
                                    <div class="movement-item">
                                        <div class="movement-icon <?= $mov['tipo'] ?>">
                                            <i class="fas fa-<?= $mov['tipo'] == 'entrada' ? 'plus' : 'minus' ?>"></i>
                                        </div>
                                        <div class="movement-details">
                                            <h4><?= htmlspecialchars($mov['producto_nombre']) ?></h4>
                                            <p>
                                                <i class="far fa-calendar-alt"></i> <?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?>
                                                <span class="sede-badge-inline"><?= htmlspecialchars($mov['sede_nombre']) ?></span>
                                            </p>
                                        </div>
                                        <div class="movement-quantity">
                                            <strong><?= $mov['cantidad'] ?></strong>
                                            <span><?= ucfirst($mov['tipo']) ?></span>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                <!-- ===== FIN DE LA SECCIÓN DE MOVIMIENTOS ===== -->
                <div class="quick-actions">
                    <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
                    <div class="action-buttons"><a href="reportes_2.php" class="btn btn-primary" style="background: linear-gradient(45deg, #17a2b8, #117a8b);"><i class="fas fa-chart-line"></i> Ver Reportes Detallados</a><a href="configuracion.php" class="btn btn-primary" style="background: linear-gradient(45deg, #6c757d, #495057);"><i class="fas fa-cog"></i> Configuración del Sistema</a></div>
                </div>
            </div>
        <?php else: ?>
            <!-- VISTA DE SEDE ESPECÍFICA (sin cambios aquí) -->
            <div class="stats-grid">
                <div class="stat-card info"><i class="fas fa-cube"></i><h3><?= $total_productos ?></h3><p>Total Productos</p></div>
                <div class="stat-card success"><i class="fas fa-exchange-alt"></i><h3><?= $movimientos_hoy ?></h3><p>Movimientos Hoy</p></div>
                <div class="stat-card warning"><i class="fas fa-exclamation-triangle"></i><h3><?= $stock_bajo ?></h3><p>Stock Bajo</p></div>
                <div class="stat-card danger"><i class="fas fa-times-circle"></i><h3><?= $sin_stock ?></h3><p>Sin Stock</p></div>
            </div>
            <div class="content-grid">
                <div class="card">
                    <h3><i class="fas fa-plus-circle"></i> Agregar Producto</h3>
                    <form method="POST" action="?sede_id=<?= $vista_actual ?>">
                        <div class="form-grid">
                            <div class="form-group"><label>Nombre</label><input type="text" name="nombre" required></div>
                            <div class="form-group"><label>Código</label><input type="text" name="codigo" required></div>
                            <div class="form-group"><label>Stock Inicial</label><input type="number" name="stock_actual" value="0" required></div>
                            <div class="form-group"><label>Stock Mínimo</label><input type="number" name="stock_minimo" value="0" required></div>
                            <div class="form-group full-width"><label>Descripción</label><textarea name="descripcion" rows="2"></textarea></div>
                        </div>
                        <input type="hidden" name="accion" value="agregar"><button class="btn btn-success" style="margin-top:1rem;"><i class="fas fa-save"></i> Guardar Producto</button>
                    </form>
                </div>
                <div class="card">
                    <h3><i class="fas fa-people-carry"></i> Registrar Movimiento</h3>
                    <form method="POST" action="?sede_id=<?= $vista_actual ?>">
                        <div class="form-group full-width"><label>Producto</label><select name="producto_id" required><option value="">Seleccione un producto...</option><?php foreach ($productos as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?> (Stock: <?= $p['stock_actual'] ?>)</option><?php endforeach; ?></select></div>
                        <div class="form-grid">
                            <div class="form-group"><label>Tipo de Movimiento</label><select name="tipo" required><option value="entrada">Entrada (+)</option><option value="salida">Salida (-)</option></select></div>
                            <div class="form-group"><label>Cantidad</label><input type="number" name="cantidad" min="1" required></div>
                        </div>
                        <input type="hidden" name="accion" value="movimiento"><button class="btn btn-primary" style="margin-top:1rem;"><i class="fas fa-check"></i> Registrar Movimiento</button>
                    </form>
                </div>
            </div>
            <div class="table-container">
                <h3><i class="fas fa-list"></i> Lista de Productos - <?= htmlspecialchars($sedes_config[$vista_actual]['nombre']) ?></h3>
                <table>
                    <thead><tr><th>ID</th><th>Nombre</th><th>Código</th><th>Descripción</th><th>Stock</th><th>Mínimo</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($productos as $p): 
                            $stock_class = 'stock-ok'; if ($p['stock_actual'] <= 0) $stock_class = 'stock-out'; elseif ($p['stock_actual'] <= $p['stock_minimo']) $stock_class = 'stock-low';
                        ?>
                        <tr><form method="POST" action="?sede_id=<?= $vista_actual ?>">
                            <td><?= $p['id'] ?></td>
                            <td><input type="text" name="nombre" value="<?= htmlspecialchars($p['nombre']) ?>" style="border:none;background:transparent;"></td>
                            <td><input type="text" name="codigo" value="<?= htmlspecialchars($p['codigo']) ?>" style="border:none;background:transparent;"></td>
                            <td><input type="text" name="descripcion" value="<?= htmlspecialchars($p['descripcion']) ?>" style="border:none;background:transparent;"></td>
                            <td><strong><?= $p['stock_actual'] ?></strong></td>
                            <td><input type="number" name="stock_minimo" value="<?= $p['stock_minimo'] ?>" style="border:none;background:transparent;width:60px;"></td>
                            <td><span class="stock-indicator <?= $stock_class ?>"><?php if ($p['stock_actual'] <= 0) echo 'Sin Stock'; elseif ($p['stock_actual'] <= $p['stock_minimo']) echo 'Stock Bajo'; else echo 'Normal'; ?></span></td>
                            <td><div class="table-actions"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button name="accion" value="editar" class="btn btn-primary" style="padding:8px 12px;margin:2px;"><i class="fas fa-edit"></i></button><button name="accion" value="eliminar" class="btn btn-danger" style="padding:8px 12px;margin:2px;" onclick="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.')"><i class="fas fa-trash"></i></button></div></td>
                        </form></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-container">
                <h3><i class="fas fa-history"></i> Historial de Movimientos Recientes</h3>
                <table>
                    <thead><tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Fecha</th></tr></thead>
                    <tbody>
                        <?php foreach ($movimientos as $m): ?>
                        <tr>
                            <td><?= $m['id'] ?></td><td><?= htmlspecialchars($m['nombre']) ?></td>
                            <td><span class="<?= $m['tipo'] == 'entrada' ? 'movement-in' : 'movement-out' ?>"><i class="fas fa-<?= $m['tipo'] == 'entrada' ? 'arrow-up' : 'arrow-down' ?>"></i> <?= ucfirst($m['tipo']) ?></span></td>
                            <td><strong><?= $m['cantidad'] ?></strong></td><td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>