<?php
// Requerir config.php para obtener las credenciales de la BD
require_once 'config.php'; 

// --- LÓGICA DE BACKUP ---
// Conectar usando las constantes de config.php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

$tables = array();
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "";
foreach ($tables as $table) {
    // Preparar la estructura de la tabla
    $result = $conn->query("SHOW CREATE TABLE $table");
    $row = $result->fetch_row();
    $sqlScript .= "\n\n" . $row[1] . ";\n\n";

    // Preparar los datos
    $result = $conn->query("SELECT * FROM $table");
    $columnCount = $result->field_count;

    // Obtener los datos de cada fila
    while ($row = $result->fetch_row()) {
        $sqlScript .= "INSERT INTO $table VALUES(";
        for ($j = 0; $j < $columnCount; $j++) {
            $row[$j] = $row[$j];
            if (isset($row[$j])) {
                $sqlScript .= '"' . $conn->real_escape_string($row[$j]) . '"';
            } else {
                $sqlScript .= '""';
            }
            if ($j < ($columnCount - 1)) {
                $sqlScript .= ',';
            }
        }
        $sqlScript .= ");\n";
    }
    $sqlScript .= "\n";
}

if (!empty($sqlScript)) {
    // Headers para forzar la descarga
    header('Content-type: application/sql');
    header('Content-Disposition: attachment; filename="backup_inventario_' . date('Y-m-d_H-i-s') . '.sql"');
    
    echo $sqlScript;
    exit();
}

$conn->close();
?>```

### 3. Página de Reportes (`reportes.php`)

Esta página permite filtrar movimientos por sede y rango de fechas, y exportar los resultados a CSV o imprimirlos (que se puede guardar como PDF).

**Crea un archivo llamado `reportes.php`:**

```php
<?php
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// Obtener sedes para el filtro
$sedes = $pdo->query("SELECT id, nombre, tabla_movimientos, tabla_productos FROM sedes WHERE activa = 1")->fetchAll(PDO::FETCH_ASSOC);

// Valores por defecto para los filtros
$sede_id_filtro = isset($_GET['sede_id']) ? intval($_GET['sede_id']) : 'todas';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');

// Construir la consulta
$movimientos = [];
$uniones = [];

foreach ($sedes as $sede) {
    if ($sede_id_filtro == 'todas' || $sede_id_filtro == $sede['id']) {
        $uniones[] = "
            SELECT m.*, p.nombre as producto_nombre, '{$sede['nombre']}' as sede_nombre 
            FROM {$sede['tabla_movimientos']} m
            JOIN {$sede['tabla_productos']} p ON m.producto_id = p.id
            WHERE DATE(m.fecha) BETWEEN ? AND ?
        ";
    }
}

if (!empty($uniones)) {
    $sql = implode(" UNION ALL ", $uniones) . " ORDER BY fecha DESC";
    $stmt = $pdo->prepare($sql);
    
    $num_uniones = count($uniones);
    $params = [];
    for ($i = 0; $i < $num_uniones; $i++) {
        $params[] = $fecha_inicio;
        $params[] = $fecha_fin;
    }

    $stmt->execute($params);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Lógica de exportación CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=reporte_movimientos.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Sede', 'Producto', 'Tipo', 'Cantidad', 'Fecha']);
    
    foreach ($movimientos as $mov) {
        fputcsv($output, [
            $mov['id'],
            $mov['sede_nombre'],
            $mov['producto_nombre'],
            ucfirst($mov['tipo']),
            $mov['cantidad'],
            date('d/m/Y H:i', strtotime($mov['fecha']))
        ]);
    }
    fclose($output);
    exit;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - Sistema de Inventario</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Estilos similares a configuración -->
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; color: #333; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        h1 { color: #2c3e50; }
        .btn { padding: 10px 20px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #229954); color: white; }
        .btn-secondary { background: linear-gradient(45deg, #5c6ac4, #3e4a89); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .filtros-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 2rem; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 0.5rem; font-weight: 500; }
        input, select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ecf0f1; text-align: left; }
        th { background: #34495e; color: white; }
        .header { padding: 1rem 2rem; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display:flex; justify-content: space-between; align-items:center; }
        @media print {
            body, .container { background: white; margin: 0; padding: 0; box-shadow: none; }
            .card { box-shadow: none; border: 1px solid #ccc; }
            .header, .filtros-form, .acciones-reporte { display: none; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-chart-line"></i> Reportes de Movimientos</h1>
        <a href="index.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver al Inventario</a>
    </div>

    <div class="container">
        <!-- Filtros -->
        <div class="card">
            <form method="GET" class="filtros-form">
                <div class="form-group">
                    <label for="sede_id">Sede</label>
                    <select name="sede_id" id="sede_id">
                        <option value="todas" <?= $sede_id_filtro == 'todas' ? 'selected' : '' ?>>Todas las Sedes</option>
                        <?php foreach ($sedes as $sede): ?>
                            <option value="<?= $sede['id'] ?>" <?= $sede_id_filtro == $sede['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sede['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="fecha_inicio">Desde</label>
                    <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= $fecha_inicio ?>">
                </div>
                <div class="form-group">
                    <label for="fecha_fin">Hasta</label>
                    <input type="date" name="fecha_fin" id="fecha_fin" value="<?= $fecha_fin ?>">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
            </form>
        </div>
        
        <!-- Resultados -->
        <div class="card">
            <div class="acciones-reporte" style="margin-bottom: 1rem; display:flex; gap: 10px;">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                    <i class="fas fa-file-csv"></i> Exportar a CSV
                </a>
                <button onclick="window.print()" class="btn btn-secondary">
                    <i class="fas fa-print"></i> Imprimir / Guardar PDF
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Sede</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movimientos)): ?>
                        <tr><td colspan="6" style="text-align:center;">No se encontraron movimientos con los filtros seleccionados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= $mov['id'] ?></td>
                            <td><?= htmlspecialchars($mov['sede_nombre']) ?></td>
                            <td><?= htmlspecialchars($mov['producto_nombre']) ?></td>
                            <td><?= ucfirst($mov['tipo']) ?></td>
                            <td><strong><?= $mov['cantidad'] ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($mov['fecha'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>