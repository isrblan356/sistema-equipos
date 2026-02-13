<?php
// =================================================================================
// 1. INICIALIZACIÓN Y CONFIGURACIÓN
// =================================================================================
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();
$mensaje = '';

// =================================================================================
// 2. CONFIGURACIÓN DE PAGINACIÓN
// =================================================================================
$registros_por_pagina = 15; // Puedes ajustar este número
$pagina_actual = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// =================================================================================
// 3. FUNCIONES AUXILIARES
// =================================================================================
function mostrarAlerta($texto, $tipo = 'info') {
    $iconos = ['success' => 'fa-check-circle', 'error' => 'fa-exclamation-triangle', 'info' => 'fa-info-circle'];
    return "<div class='alerta alerta-{$tipo}'><i class='fas {$iconos[$tipo]}'></i><p>{$texto}</p></div>";
}

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

function calcularInventarioTotal($pdo, $producto_id) {
    $inventario_total = 0;
    
    // 1. Obtener stock en bodega (solo sede Medellín)
    try {
        $sedes_query = $pdo->prepare("SELECT tabla_productos FROM sedes WHERE activa = 1 AND nombre = 'Medellin'");
        $sedes_query->execute();
        $sedes = $sedes_query->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sedes as $sede) {
            $tabla_productos = $sede['tabla_productos'];
            
            try {
                $stmt = $pdo->prepare("SELECT stock_actual FROM `{$tabla_productos}` WHERE id = ?");
                $stmt->execute([$producto_id]);
                $stock = $stmt->fetchColumn();
                
                if ($stock !== false) {
                    $inventario_total += intval($stock);
                }
            } catch (PDOException $e) {
                continue;
            }
        }
    } catch (PDOException $e) {
        // Error al obtener stock
    }
    
    // 2. Sumar la diferencia de cada técnico (Preinstalaciones - Instalaciones OK - Sobrantes)
    try {
        $sedes_query = $pdo->prepare("SELECT tabla_movimientos FROM sedes WHERE activa = 1 AND nombre = 'Medellin'");
        $sedes_query->execute();
        $sedes = $sedes_query->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sedes as $sede) {
            $tabla_movimientos = $sede['tabla_movimientos'];
            
            try {
                // Obtener el ID del técnico a excluir
                $tecnico_excluir = null;
                $stmt_excluir = $pdo->prepare("SELECT id FROM tecnicos WHERE nombre = 'Maria Camila Ossa'");
                $stmt_excluir->execute();
                if ($row = $stmt_excluir->fetch(PDO::FETCH_ASSOC)) {
                    $tecnico_excluir = $row['id'];
                }
                
                // Obtener técnicos únicos para este producto (excluyendo Maria Camila Ossa)
                $sql_tecnicos = "SELECT DISTINCT tecnico_id 
                                FROM `{$tabla_movimientos}` 
                                WHERE producto_id = ? AND tecnico_id IS NOT NULL";
                
                if ($tecnico_excluir) {
                    $sql_tecnicos .= " AND tecnico_id != ?";
                    $stmt_tecnicos = $pdo->prepare($sql_tecnicos);
                    $stmt_tecnicos->execute([$producto_id, $tecnico_excluir]);
                } else {
                    $stmt_tecnicos = $pdo->prepare($sql_tecnicos);
                    $stmt_tecnicos->execute([$producto_id]);
                }
                
                $tecnicos = $stmt_tecnicos->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($tecnicos as $tecnico_id) {
                    // Calcular preinstalaciones (entregado al técnico)
                    $stmt_pre = $pdo->prepare("
                        SELECT COALESCE(SUM(cantidad), 0) 
                        FROM `{$tabla_movimientos}` 
                        WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Preinstalaciones'
                    ");
                    $stmt_pre->execute([$producto_id, $tecnico_id]);
                    $preinstalaciones = intval($stmt_pre->fetchColumn());
                    
                    // Calcular instalaciones OK
                    $stmt_inst = $pdo->prepare("
                        SELECT COALESCE(SUM(cantidad), 0) 
                        FROM `{$tabla_movimientos}` 
                        WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Instalaciones OK'
                    ");
                    $stmt_inst->execute([$producto_id, $tecnico_id]);
                    $instalaciones_ok = intval($stmt_inst->fetchColumn());
                    
                    // Calcular sobrantes
                    $stmt_sob = $pdo->prepare("
                        SELECT COALESCE(SUM(cantidad), 0) 
                        FROM `{$tabla_movimientos}` 
                        WHERE producto_id = ? AND tecnico_id = ? AND tipo = 'Sobrantes'
                    ");
                    $stmt_sob->execute([$producto_id, $tecnico_id]);
                    $sobrantes = intval($stmt_sob->fetchColumn());
                    
                    // Calcular diferencia: Preinstalaciones - (Instalaciones OK + Sobrantes)
                    $devueltos = $instalaciones_ok + $sobrantes;
                    $diferencia = $preinstalaciones - $devueltos;
                    
                    // Sumar la diferencia al inventario total
                    $inventario_total += $diferencia;
                }
            } catch (PDOException $e) {
                continue;
            }
        }
    } catch (PDOException $e) {
        // Error al procesar movimientos
    }
    
    return $inventario_total;
}

$tieneStockMinimo = verificarColumnaStockMinimo($pdo);
$marcas = obtenerMarcas($pdo);
$tipos_tecnologia = obtenerTiposTecnologia($pdo);

// =================================================================================
// 4. PROCESAMIENTO DE FORMULARIOS
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
                    $campos = ['nombre = ?', 'part_number = ?', 'marca_id = ?', 'tipo_tecnologia_id = ?'];
                    $valores = [$nombre, $partnumber, $marca_id, $tipo_tecnologia_id];
                    
                    if ($tieneStockMinimo) {
                        $campos[] = 'stock_minimo = ?';
                        $valores[] = $stock_minimo;
                    }
                    
                    $valores[] = $id;
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
        }
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error en la operación: ' . $e->getMessage(), 'error');
    }
}

// =================================================================================
// 5. OBTENCIÓN DE DATOS PARA LA VISTA CON PAGINACIÓN
// =================================================================================

// Contar total de productos
$stmt_count = $pdo->query("SELECT COUNT(*) FROM productos");
$total_productos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_productos / $registros_por_pagina);

// Obtener productos de la página actual
$sql = "SELECT 
            p.*, 
            m.nombre as marca_nombre, 
            t.nombre as tecnologia_nombre
        FROM productos p
        LEFT JOIN marca m ON p.marca_id = m.marca_id
        LEFT JOIN tipo_tecnologia t ON p.tipo_tecnologia_id = t.tipo_tecnologia_id
        ORDER BY p.nombre ASC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $registros_por_pagina, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll();

// Calcular inventario total para cada producto
foreach ($productos as &$producto) {
    $producto['inventario_total'] = calcularInventarioTotal($pdo, $producto['id']);
}
unset($producto); // Romper referencia

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
        body { 
            font-family: 'Segoe UI', sans-serif; 
            background-color: #f4f7f6; 
            margin: 0;
            padding: 0;
        }
        .header { 
            background: #fff; 
            padding: 1.5rem 2rem; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.08); 
            margin-bottom: 2rem;
        }
        .header-content { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            max-width: 1600px; 
            margin: 0 auto; 
            flex-wrap: wrap; 
            gap: 1rem;
        }
        .header h1 { 
            color: #2c3e50; 
            font-size: 2rem; 
            display: flex; 
            align-items: center; 
            gap: 15px; 
            margin: 0; 
        }
        .header h1 i { color: #3498db; }
        .nav-buttons { 
            display: flex; 
            gap: 10px; 
            align-items: center; 
            flex-wrap: wrap;
        }
        .btn-nav { 
            background: #f8f9fa; 
            color: #333; 
            padding: 10px 20px; 
            border-radius: 25px; 
            text-decoration: none; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 0.9rem; 
            border: 1px solid #ddd; 
            transition: all 0.3s ease;
        }
        .btn-nav:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }
        .logout-btn { 
            background: linear-gradient(45deg, #e74c3c, #c0392b); 
            color: white; 
            border: none;
        }
        .container { 
            max-width: 1600px; 
            margin: 0 auto; 
            padding: 0 2rem 2rem 2rem; 
        }
        .content-grid { 
            display: grid; 
            grid-template-columns: 380px 1fr; 
            gap: 2rem; 
            align-items: flex-start; 
        }
        @media (max-width: 992px) { 
            .content-grid { grid-template-columns: 1fr; } 
        }
        .card { 
            background: white; 
            border-radius: 15px; 
            padding: 1.5rem 2rem; 
            box-shadow: 0 8px 32px rgba(0,0,0,0.08); 
        }
        .card h3 { 
            color: #2c3e50; 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .card h3 i { color: #3498db; }
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 500; 
            color: #555; 
        }
        .form-control { 
            width: 100%; 
            padding: 12px; 
            border: 2px solid #e1e5e9; 
            border-radius: 8px; 
            font-size: 1rem; 
        }
        .btn { 
            padding: 12px 24px; 
            border: none; 
            border-radius: 25px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            text-decoration: none; 
        }
        .btn-primary { 
            background: linear-gradient(45deg, #3498db, #2980b9); 
            color: white; 
            width: 100%; 
            margin-top: 1rem; 
        }
        .btn-sm { 
            padding: 8px 12px; 
            font-size: 0.8rem; 
            border-radius: 20px; 
        }
        .btn-warning { 
            background: linear-gradient(45deg, #f39c12, #e67e22); 
            color: white; 
        }
        .btn-danger { 
            background: linear-gradient(45deg, #e74c3c, #c0392b); 
            color: white; 
        }
        .btn-secondary { 
            background: linear-gradient(45deg, #95a5a6, #7f8c8d); 
            color: white; 
        }
        .btn-success { 
            background: linear-gradient(45deg, #2ecc71, #27ae60); 
            color: white; 
        }
        .table-container { 
            overflow-x: auto; 
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 1rem; 
        }
        th, td { 
            padding: 14px 12px; 
            text-align: left; 
            border-bottom: 1px solid #ecf0f1; 
            vertical-align: middle; 
        }
        th { 
            background-color: #f8f9fa; 
            font-weight: 600; 
            color: #34495e; 
            font-size: 0.85rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) { background-color: #f9fafb; }
        tr:hover { background-color: #f0f4f8; }
        td:nth-child(1) strong { color: #2c3e50; }
        th:nth-child(5), td:nth-child(5), 
        th:nth-child(6), td:nth-child(6), 
        th:nth-child(7), td:nth-child(7), 
        th:nth-child(8), td:nth-child(8) { 
            text-align: center; 
        }
        .table-actions { 
            display: flex; 
            gap: 8px; 
            justify-content: center; 
        }
        .badge { 
            padding: 6px 14px; 
            font-size: 0.85rem; 
            font-weight: 600; 
            border-radius: 20px; 
            color: white; 
            display: inline-block;
        }
        .badge-activo { background-color: #27ae60; }
        .badge-inactivo { background-color: #7f8c8d; }
        .stock-minimo { 
            display: inline-block; 
            background-color: #e67e22; 
            color: white; 
            padding: 6px 12px; 
            border-radius: 12px; 
            font-size: 0.85rem; 
            font-weight: 600; 
        }
        .inventario-total { 
            display: inline-block; 
            background-color: #3498db; 
            color: white; 
            padding: 8px 16px; 
            border-radius: 12px; 
            font-size: 0.95rem; 
            font-weight: 700; 
        }
        .inventario-total.bajo { background-color: #e74c3c; }
        .inventario-total.medio { background-color: #f39c12; }
        .inventario-total.alto { background-color: #27ae60; }
        
        /* Estilos de paginación */
        .pagination-container { 
            display: flex; 
            justify-content: space-between;
            align-items: center; 
            margin-top: 2rem; 
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            flex-wrap: wrap;
            gap: 1rem;
        }
        .pagination { 
            display: flex; 
            gap: 0.5rem; 
            list-style: none; 
            padding: 0; 
            margin: 0;
            flex-wrap: wrap;
        }
        .pagination li { 
            display: inline-block; 
        }
        .pagination a, .pagination span { 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 12px;
            border-radius: 10px;
            text-decoration: none;
            color: #2c3e50;
            background: #f8f9fa;
            border: 2px solid #e1e5e9;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .pagination a:hover { 
            background: #3498db;
            color: white;
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .pagination .active span { 
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            border-color: #3498db;
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        .pagination .disabled span { 
            opacity: 0.4;
            cursor: not-allowed;
            background: #f8f9fa;
        }
        .page-info {
            color: #666;
            font-weight: 500;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-info i {
            color: #3498db;
        }
        
        /* Alerta mejorada */
        .alerta {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.3s ease;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .alerta i { font-size: 1.5rem; }
        .alerta-success { 
            background: #d4edda; 
            color: #155724; 
            border-left: 4px solid #28a745; 
        }
        .alerta-error { 
            background: #f8d7da; 
            color: #721c24; 
            border-left: 4px solid #dc3545; 
        }
        .alerta-info { 
            background: #d1ecf1; 
            color: #0c5460; 
            border-left: 4px solid #17a2b8; 
        }
        @keyframes slideIn { 
            from { transform: translateY(-20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-boxes"></i> Gestión de Productos</h1>
            <div class="nav-buttons">
                <a href="inventario_compras.php" class="btn-nav"><i class="fas fa-home"></i> Dashboard</a>
                <a href="marcas.php" class="btn-nav"><i class="fas fa-tag"></i> Marcas</a>
                <a href="tipos_tecnologia.php" class="btn-nav"><i class="fas fa-microchip"></i> Tipos Tecnología</a>
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
                    
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Producto</button>
                </form>
            </div>

            <div class="card">
                <h3><i class="fas fa-list-ul"></i> Lista de productos (<?= $total_productos ?> total)</h3>
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
                                <th><i class="fas fa-warehouse"></i> Inventario Total</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($productos)): ?>
                                <tr><td colspan="8" style="text-align: center; color: #888; padding: 2rem;">No hay productos registrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($productos as $producto): 
                                    // Determinar clase de color según inventario
                                    $clase_inventario = 'alto';
                                    if ($producto['inventario_total'] <= 0) {
                                        $clase_inventario = 'bajo';
                                    } elseif ($producto['inventario_total'] <= 10) {
                                        $clase_inventario = 'medio';
                                    }
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($producto['nombre']) ?></strong></td>
                                        <td><?= htmlspecialchars($producto['marca_nombre'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($producto['tecnologia_nombre'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($producto['part_number'] ?? 'N/A') ?></td>
                                        <?php if ($tieneStockMinimo): ?>
                                        <td><span class="stock-minimo"><?= intval($producto['stock_minimo'] ?? 0) ?></span></td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="inventario-total <?= $clase_inventario ?>" title="Stock en bodega + equipos con técnicos">
                                                <i class="fas fa-boxes"></i> <?= $producto['inventario_total'] ?>
                                            </span>
                                        </td>
                                        <td><span class="badge badge-<?= $producto['estado'] ?>"><?= ucfirst($producto['estado']) ?></span></td>
                                        <td class="table-actions">
                                            <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEditar<?= $producto['id'] ?>" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" action="productos.php?pagina=<?= $pagina_actual ?>" style="display: inline-block; margin: 0;" onsubmit="return confirm('¿Seguro de cambiar el estado?');">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="id" value="<?= $producto['id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $producto['estado'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $producto['estado'] == 'activo' ? 'btn-secondary' : 'btn-success' ?>" title="<?= $producto['estado'] == 'activo' ? 'Desactivar' : 'Activar' ?>">
                                                    <i class="fas fa-<?= $producto['estado'] == 'activo' ? 'toggle-off' : 'toggle-on' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <div class="pagination-container">
                    <span class="page-info">
                        <i class="fas fa-info-circle"></i>
                        Mostrando página <strong><?= $pagina_actual ?></strong> de <strong><?= $total_paginas ?></strong> 
                        (<?= $total_productos ?> productos en total)
                    </span>
                    <ul class="pagination">
                        <?php if ($pagina_actual > 1): ?>
                            <li><a href="?pagina=1" title="Primera página"><i class="fas fa-angle-double-left"></i></a></li>
                            <li><a href="?pagina=<?= $pagina_actual - 1 ?>" title="Página anterior"><i class="fas fa-angle-left"></i></a></li>
                        <?php else: ?>
                            <li class="disabled"><span><i class="fas fa-angle-double-left"></i></span></li>
                            <li class="disabled"><span><i class="fas fa-angle-left"></i></span></li>
                        <?php endif; ?>
                        
                        <?php
                        // Mostrar páginas cercanas
                        $rango = 2;
                        $inicio = max(1, $pagina_actual - $rango);
                        $fin = min($total_paginas, $pagina_actual + $rango);
                        
                        for ($i = $inicio; $i <= $fin; $i++):
                        ?>
                            <li class="<?= $i == $pagina_actual ? 'active' : '' ?>">
                                <?php if ($i == $pagina_actual): ?>
                                    <span><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?pagina=<?= $i ?>"><?= $i ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($pagina_actual < $total_paginas): ?>
                            <li><a href="?pagina=<?= $pagina_actual + 1 ?>" title="Página siguiente"><i class="fas fa-angle-right"></i></a></li>
                            <li><a href="?pagina=<?= $total_paginas ?>" title="Última página"><i class="fas fa-angle-double-right"></i></a></li>
                        <?php else: ?>
                            <li class="disabled"><span><i class="fas fa-angle-right"></i></span></li>
                            <li class="disabled"><span><i class="fas fa-angle-double-right"></i></span></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <?php endif; ?>
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
                <form method="POST" action="productos.php?pagina=<?= $pagina_actual ?>">
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
                    alerta.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => { alerta.style.display = 'none'; }, 500);
                }, 4000);
            }
        });
    </script>
</body>
</html>