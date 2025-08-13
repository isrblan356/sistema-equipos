<?php
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// --- LECTURA DINÁMICA DE SEDES ---
$sedes = $pdo->query("SELECT id, nombre, tabla_productos, tabla_movimientos FROM sedes WHERE activa = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// --- CÁLCULO DEL INVENTARIO DETALLADO POR PRODUCTO ---
$inventarioPorSede = [];
$totalesPorProducto = [];

foreach ($sedes as $sede) {
    $tablaProductos = $sede['tabla_productos'];
    $inventarioPorSede[$sede['nombre']] = [];

    // Obtenemos todos los productos con stock > 0 para esta sede
    $stmt = $pdo->query("SELECT nombre, stock_actual FROM `{$tablaProductos}` WHERE stock_actual > 0 ORDER BY nombre ASC");
    $productosDeSede = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($productosDeSede as $producto) {
        $nombreProducto = $producto['nombre'];
        $stock = (int)$producto['stock_actual'];
        
        // Añadir al inventario de la sede
        $inventarioPorSede[$sede['nombre']][$nombreProducto] = $stock;

        // Sumar al total general del producto
        if (isset($totalesPorProducto[$nombreProducto])) {
            $totalesPorProducto[$nombreProducto] += $stock;
        } else {
            $totalesPorProducto[$nombreProducto] = $stock;
        }
    }
}
// Ordenar los totales generales por nombre de producto
ksort($totalesPorProducto);

// --- MANEJO DE FILTROS PARA MOVIMIENTOS ---
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$sedeFiltro = isset($_GET['sede_id']) ? $_GET['sede_id'] : 'todas';

// --- LÓGICA DE CONSULTA DE MOVIMIENTOS ---
$movimientos = [];
if (isset($_GET['fecha_inicio'])) { // Solo buscar si se ha hecho clic en el botón
    $uniones = [];
    $parametros = [];

    foreach ($sedes as $sede) {
        if ($sedeFiltro == 'todas' || $sedeFiltro == $sede['id']) {
            $uniones[] = "SELECT m.id, p.nombre AS producto_nombre, m.tipo, m.cantidad, m.fecha, '{$sede['nombre']}' AS sede_nombre FROM `{$sede['tabla_movimientos']}` m JOIN `{$sede['tabla_productos']}` p ON m.producto_id = p.id WHERE DATE(m.fecha) BETWEEN ? AND ?";
            $parametros[] = $fechaInicio;
            $parametros[] = $fechaFin;
        }
    }

    if (!empty($uniones)) {
        $sql = implode(" UNION ALL ", $uniones) . " ORDER BY fecha DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// --- LÓGICA DE EXPORTACIÓN UNIFICADA ---

// EXPORTAR A PDF
if (isset($_GET['exportar']) && $_GET['exportar'] == 'pdf') {
    require('fpdf186/fpdf.php');

    class PDF extends FPDF {
        function Header() { $this->SetFont('Arial', 'B', 16); $this->Cell(0, 10, utf8_decode('Reporte General de Inventario'), 0, 1, 'C'); $this->Ln(5); }
        function Footer() { $this->SetY(-15); $this->SetFont('Arial', 'I', 8); $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C'); }
        function SectionTitle($title) { $this->SetFont('Arial', 'B', 12); $this->SetFillColor(220, 220, 220); $this->Cell(0, 8, utf8_decode($title), 0, 1, 'L', true); $this->Ln(2); }
        function SedeHeader($title) { $this->SetFont('Arial', 'B', 10); $this->SetFillColor(240, 240, 240); $this->Cell(0, 7, utf8_decode($title), 'LR', 1, 'L', true); }
    }

    $pdf = new PDF();
    $pdf->AddPage('P', 'A4');
    $pdf->SectionTitle('Resumen de Stock por Producto y Sede');
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(130, 7, 'Producto', 1, 0, 'C');
    $pdf->Cell(60, 7, 'Stock Actual', 1, 0, 'C');
    $pdf->Ln();
    
    $pdf->SetFont('Arial', '', 10);
    foreach ($inventarioPorSede as $sedeNombre => $productos) {
        $pdf->SedeHeader($sedeNombre);
        if (empty($productos)) {
            $pdf->Cell(190, 7, utf8_decode('No hay productos con stock en esta sede.'), 'LRB', 1, 'C');
        } else {
            foreach ($productos as $productoNombre => $stock) {
                $pdf->Cell(130, 7, utf8_decode($productoNombre), 'LR');
                $pdf->Cell(60, 7, $stock, 'R', 1, 'C');
            }
            $pdf->Cell(190, 0, '', 'T'); // Linea final de la tabla de sede
            $pdf->Ln(0);
        }
    }
    $pdf->Ln(5);

    $pdf->SectionTitle('Totales Generales por Producto');
    foreach($totalesPorProducto as $productoNombre => $stockTotal) {
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(130, 7, utf8_decode($productoNombre), 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(60, 7, $stockTotal, 1, 1, 'C');
    }
    $pdf->Ln(10);

    if (!empty($movimientos)) {
        $pdf->AddPage('L', 'A4'); // Nueva página apaisada para movimientos
        $pdf->SectionTitle("Detalle de Movimientos ({$fechaInicio} a {$fechaFin})");
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(90, 8, 'Producto', 1); $pdf->Cell(25, 8, 'Tipo', 1); $pdf->Cell(20, 8, 'Cantidad', 1); $pdf->Cell(50, 8, 'Sede', 1); $pdf->Cell(50, 8, 'Fecha', 1);
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 8);
        foreach ($movimientos as $m) {
            $pdf->Cell(90, 7, utf8_decode($m['producto_nombre']), 1); $pdf->Cell(25, 7, ucfirst($m['tipo']), 1); $pdf->Cell(20, 7, $m['cantidad'], 1, 0, 'C'); $pdf->Cell(50, 7, utf8_decode($m['sede_nombre']), 1); $pdf->Cell(50, 7, date('d/m/Y H:i', strtotime($m['fecha'])), 1);
            $pdf->Ln();
        }
    }

    $pdf->Output('D', 'Reporte_General_'.date('Y-m-d').'.pdf');
    exit;
}

// EXPORTAR A CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Reporte_General_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    
    fputcsv($output, ['Reporte de Stock por Sede y Producto']);
    fputcsv($output, ['Sede/Producto', 'Stock Actual']);
    foreach ($inventarioPorSede as $sedeNombre => $productos) {
        fputcsv($output, [$sedeNombre, '']);
        if(empty($productos)){
            fputcsv($output, ['  - Sin productos con stock', '']);
        } else {
            foreach($productos as $productoNombre => $stock){
                fputcsv($output, ["  - ".$productoNombre, $stock]);
            }
        }
    }
    fputcsv($output, []);
    fputcsv($output, ['Totales Generales por Producto']);
    foreach($totalesPorProducto as $productoNombre => $stockTotal){
        fputcsv($output, [$productoNombre, $stockTotal]);
    }
    fputcsv($output, []); 

    if (!empty($movimientos)) {
        fputcsv($output, ["Detalle de Movimientos ({$fechaInicio} a {$fechaFin})"]);
        fputcsv($output, ['ID', 'Producto', 'Tipo', 'Cantidad', 'Sede', 'Fecha']);
        foreach ($movimientos as $m) {
            fputcsv($output, [$m['id'], $m['producto_nombre'], ucfirst($m['tipo']), $m['cantidad'], $m['sede_nombre'], date('d/m/Y H:i', strtotime($m['fecha']))]);
        }
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte General de Inventario</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; color: #333; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        h1, h2 { color: #2c3e50; margin-bottom: 1rem; }
        h2 i { color: #667eea; }
        .btn { padding: 10px 20px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #229954); color: white; }
        .btn-danger-alt { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .filtros-form { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; border-bottom: 1px solid #eee; padding-bottom: 1.5rem; }
        .form-group { display: flex; flex-direction: column; }
        label { margin-bottom: 0.5rem; font-weight: 500; }
        input[type="date"], select { padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { padding: 12px 15px; border: 1px solid #ddd; text-align: left; }
        th { background: #34495e; color: white; }
        tr.sede-header td { background-color: #f2f2f2; font-weight: bold; color: #333; }
        tr.total-header td { background-color: #e9ecef; font-weight: bold; font-size: 1.1em; }
        td.producto-nombre { padding-left: 30px !important; }
        .header { padding: 1rem 2rem; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display:flex; justify-content: space-between; align-items:center; }
        .acciones-reporte { display: flex; gap: 1rem; align-items: center; padding-top: 1.5rem; margin-top: 1.5rem; }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-file-invoice"></i> Reporte General de Inventario</h1>
        <a href="inventario.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>

    <div class="container">
        <!-- Tarjeta de Resumen de Inventario Detallado -->
        <div class="card">
            <h2><i class="fas fa-boxes"></i> Resumen de Stock por Producto y Sede</h2>
            <table>
                <thead>
                    <tr>
                        <th>Sede / Producto</th>
                        <th>Stock Actual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventarioPorSede as $sedeNombre => $productos): ?>
                        <tr class="sede-header">
                            <td colspan="2"><i class="fas fa-building"></i> <?= htmlspecialchars($sedeNombre) ?></td>
                        </tr>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="2" style="text-align: center; font-style: italic;">No hay productos con stock en esta sede.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $productoNombre => $stock): ?>
                                <tr>
                                    <td class="producto-nombre"><?= htmlspecialchars($productoNombre) ?></td>
                                    <td><strong><?= htmlspecialchars($stock) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tbody>
                    <tr class="total-header">
                        <td colspan="2"><i class="fas fa-globe-americas"></i> Totales Generales por Producto</td>
                    </tr>
                    <?php if(empty($totalesPorProducto)): ?>
                        <tr><td colspan="2" style="text-align:center; font-style:italic;">No hay productos con stock en ninguna sede.</td></tr>
                    <?php else: ?>
                        <?php foreach($totalesPorProducto as $productoNombre => $stockTotal): ?>
                            <tr>
                                <td class="producto-nombre"><strong><?= htmlspecialchars($productoNombre) ?></strong></td>
                                <td><strong><?= htmlspecialchars($stockTotal) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tarjeta de Filtros y Detalle de Movimientos -->
        <div class="card">
            <h2><i class="fas fa-history"></i> Detalle de Movimientos</h2>
            <form method="GET" class="filtros-form">
                <div class="form-group"><label for="sede_id">Filtrar Sede (para movimientos)</label><select name="sede_id" id="sede_id"><option value="todas" <?= $sedeFiltro == 'todas' ? 'selected' : '' ?>>-- Todas las Sedes --</option><?php foreach ($sedes as $sede): ?><option value="<?= $sede['id'] ?>" <?= $sedeFiltro == $sede['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sede['nombre']) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="fecha_inicio">Desde</label><input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>"></div>
                <div class="form-group"><label for="fecha_fin">Hasta</label><input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>"></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar Movimientos</button>
            </form>
            
            <?php if (isset($_GET['fecha_inicio'])): ?>
                <div class="acciones-reporte">
                    <span><i class="fas fa-download"></i> Exportar Reporte Completo:</span>
                    <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'csv'])) ?>" class="btn btn-success"><i class="fas fa-file-csv"></i> Excel (CSV)</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'pdf'])) ?>" class="btn btn-danger-alt"><i class="fas fa-file-pdf"></i> PDF</a>
                </div>
                <?php if (!empty($movimientos)): ?>
                    <table>
                        <thead><tr><th>ID</th><th>Producto</th><th>Tipo</th><th>Cantidad</th><th>Sede</th><th>Fecha y Hora</th></tr></thead>
                        <tbody><?php foreach ($movimientos as $m): ?><tr><td><?= htmlspecialchars($m['id']) ?></td><td><?= htmlspecialchars($m['producto_nombre']) ?></td><td><?= htmlspecialchars(ucfirst($m['tipo'])) ?></td><td><strong><?= htmlspecialchars($m['cantidad']) ?></strong></td><td><?= htmlspecialchars($m['sede_nombre']) ?></td><td><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($m['fecha']))) ?></td></tr><?php endforeach ?></tbody>
                    </table>
                <?php else: ?>
                    <p style="margin-top: 2rem; font-weight: bold; color: #721c24;">No se encontraron movimientos con los criterios seleccionados.</p>
                <?php endif ?>
            <?php else: ?>
                <p style="margin-top: 2rem; font-style: italic; color: #555;">Seleccione un rango de fechas y haga clic en "Buscar Movimientos" para ver el detalle y activar la exportación.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>