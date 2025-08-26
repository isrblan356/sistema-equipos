<?php
// Muestra errores para depuración (puedes comentar en producción)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =================================================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN
// =================================================================================
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// =================================================================================
// 2. CONFIGURACIÓN DE VISTA Y CARGA DE DATOS
// =================================================================================
$sedes_query = $pdo->query("SELECT * FROM sedes WHERE activa = 1 ORDER BY id")->fetchAll();
$sedes_config = [];
foreach ($sedes_query as $sede) { $sedes_config[$sede['id']] = $sede; }

// Determinar la sede actual. Si no hay sede_id, no se puede continuar.
$sede_actual_id = isset($_GET['sede_id']) && array_key_exists($_GET['sede_id'], $sedes_config) ? $_GET['sede_id'] : null;

$movimientos = [];
$productos = [];

if ($sede_actual_id) {
    // =================================================================================
    // 3. OBTENCIÓN DE DATOS PARA LA SEDE SELECCIONADA
    // =================================================================================
    $config_sede_actual = $sedes_config[$sede_actual_id];
    $tabla_productos = $config_sede_actual['tabla_productos'];
    $tabla_movimientos = $config_sede_actual['tabla_movimientos'];

    // Cargar productos de la sede actual para el filtro
    $productos = $pdo->query("SELECT id, nombre, codigo FROM `{$tabla_productos}` ORDER BY nombre ASC")->fetchAll();

    // Lógica de filtros
    $filtro_producto = isset($_GET['producto']) ? limpiarDatos($_GET['producto']) : '';
    $filtro_tipo = isset($_GET['tipo']) ? limpiarDatos($_GET['tipo']) : '';
    $fecha_inicio = isset($_GET['fecha_inicio']) ? limpiarDatos($_GET['fecha_inicio']) : '';
    $fecha_fin = isset($_GET['fecha_fin']) ? limpiarDatos($_GET['fecha_fin']) : '';

    // Construcción de la consulta SQL
    $sql = "SELECT m.*, p.nombre as producto_nombre, p.codigo as producto_codigo, t.nombre as tecnico_nombre
            FROM `{$tabla_movimientos}` m
            JOIN `{$tabla_productos}` p ON m.producto_id = p.id
            LEFT JOIN tecnicos t ON m.tecnico_id = t.id
            WHERE 1=1";
    
    $params = [];

    if (!empty($filtro_producto)) {
        $sql .= " AND m.producto_id = ?";
        $params[] = $filtro_producto;
    }
    if (!empty($filtro_tipo)) {
        $sql .= " AND m.tipo = ?";
        $params[] = $filtro_tipo;
    }
    if (!empty($fecha_inicio)) {
        $sql .= " AND DATE(m.fecha) >= ?";
        $params[] = $fecha_inicio;
    }
    if (!empty($fecha_fin)) {
        $sql .= " AND DATE(m.fecha) <= ?";
        $params[] = $fecha_fin;
    }

    $sql .= " ORDER BY m.fecha DESC LIMIT 500"; // Límite generoso para el historial

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $movimientos = $stmt->fetchAll();
}

// =================================================================================
// 4. LÓGICA DE PRESENTACIÓN
// =================================================================================
$color_actual_hex = $sede_actual_id ? $sedes_config[$sede_actual_id]['color'] : '#6c757d';
$gradient_actual = $sede_actual_id ? $sedes_config[$sede_actual_id]['gradient'] : 'linear-gradient(45deg, #6c757d, #495057)';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Movimientos <?= $sede_actual_id ? '- ' . htmlspecialchars($sedes_config[$sede_actual_id]['nombre']) : '' ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; color: #333; }
        .header { background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(10px); padding: 1.5rem 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 1000; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; flex-wrap: wrap; gap: 1rem; }
        .header h1 { color: #2c3e50; font-size: clamp(1.5rem, 3vw, 2rem); display: flex; align-items: center; gap: 15px; }
        .header h1 i { color: <?= $color_actual_hex ?>; }
        .nav-buttons { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .btn-nav { background: #f8f9fa; color: #333; padding: 10px 20px; border-radius: 25px; text-decoration: none; font-weight: 500; display: flex; align-items: center; gap: 8px; font-size: 0.9rem; border: 1px solid #ddd; }
        .logout-btn { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; border: none;}
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 15px; padding: 1.5rem; box-shadow: 0 8px 32px rgba(0,0,0,0.08); margin-bottom: 2rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; text-decoration: none; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 15px; border-bottom: 1px solid #ecf0f1; vertical-align: middle; }
        th { background: #f8f9fa; color: #34495e; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; }
        input, select { width: 100%; padding: 12px; border: 2px solid #e1e5e9; border-radius: 8px; font-size: 1rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: end; }
        .form-group label { margin-bottom: 0.5rem; font-weight: 500; color: #555; display: block; }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-history"></i> Historial de Movimientos</h1>
            <nav class="nav-buttons">
                <a href="dashboard.php" class="btn-nav"><i class="fas fa-home"></i> Principal</a>
                <a href="inventario.php" class="btn-nav"><i class="fas fa-boxes"></i> Inventario</a>
                <a href="tecnicos.php" class="btn-nav"><i class="fas fa-users-cog"></i> Técnicos</a>
                <a href="logout.php" class="btn-nav logout-btn"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="card">
            <h3><i class="fas fa-filter"></i> Filtrar Movimientos</h3>
            <form method="GET">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="sede_id">Seleccionar Sede *</label>
                        <select id="sede_id" name="sede_id" onchange="this.form.submit()" required>
                            <option value="">-- Elija una sede para ver sus movimientos --</option>
                            <?php foreach ($sedes_config as $id => $sede): ?>
                                <option value="<?= $id ?>" <?= $sede_actual_id == $id ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sede['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if ($sede_actual_id): // Mostrar filtros solo si hay una sede seleccionada ?>
                    <div class="form-group">
                        <label for="producto">Producto</label>
                        <select id="producto" name="producto">
                            <option value="">Todos</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $filtro_producto == $p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select id="tipo" name="tipo">
                            <option value="">Todos</option>
                            <option value="entrada" <?= $filtro_tipo == 'entrada' ? 'selected' : '' ?>>Entrada</option>
                            <option value="salida" <?= $filtro_tipo == 'salida' ? 'selected' : '' ?>>Salida</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio">Desde</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin">Hasta</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn" style="background: <?= $gradient_actual ?>; color: white; width: 100%;"><i class="fas fa-search"></i> Filtrar</button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($sede_actual_id): ?>
        <div class="card">
            <h3><i class="fas fa-list-ul"></i> Resultados (<?= count($movimientos) ?> encontrados)</h3>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID Mov.</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Técnico Asignado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movimientos)): ?>
                            <tr><td colspan="6" style="text-align: center; color: #888;">No se encontraron movimientos con los filtros seleccionados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movimientos as $m): ?>
                            <tr>
                                <td><?= $m['id'] ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($m['fecha'])) ?></td>
                                <td><strong><?= htmlspecialchars($m['producto_nombre']) ?></strong><br><small><?= htmlspecialchars($m['producto_codigo']) ?></small></td>
                                <td>
                                    <span style="font-weight: bold; color: <?= $m['tipo'] == 'entrada' ? '#27ae60' : '#e74c3c' ?>;">
                                        <i class="fas fa-arrow-<?= $m['tipo'] == 'entrada' ? 'up' : 'down' ?>"></i> 
                                        <?= ucfirst($m['tipo']) ?>
                                    </span>
                                </td>
                                <td><strong><?= $m['cantidad'] ?></strong></td>
                                <td><?= htmlspecialchars($m['tecnico_nombre'] ?? 'N/A') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>