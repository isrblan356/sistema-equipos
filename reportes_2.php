<?php
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// --- CONFIGURACIÓN DE FILTROS APLICADA ---
$tecnicoAExcluirNombre = 'Maria Camila Ossa'; // Nombre exacto del técnico a excluir.
$sedeUnicaPermitida = 'Medellin'; // Nombre exacto de la única sede a mostrar/filtrar.
$idSedeUnica = null; // Variable para almacenar el ID de Medellín.


// --- LECTURA DINÁMICA DE SEDES Y TÉCNICOS ---
$sedes = $pdo->prepare("SELECT id, nombre, tabla_productos, tabla_movimientos FROM sedes WHERE activa = 1 AND nombre = :nombre_sede ORDER BY id");
$sedes->execute([':nombre_sede' => $sedeUnicaPermitida]);
$sedes = $sedes->fetchAll(PDO::FETCH_ASSOC);

if (!empty($sedes)) {
    $idSedeUnica = $sedes[0]['id'];
}

// Obtener lista de técnicos únicos con sus nombres
$tecnicos = [];
foreach ($sedes as $sede) {
    $stmt = $pdo->query("SELECT DISTINCT m.tecnico_id, COALESCE(t.nombre, CONCAT('Técnico #', m.tecnico_id)) as nombre
                         FROM `{$sede['tabla_movimientos']}` m
                         LEFT JOIN tecnicos t ON m.tecnico_id = t.id
                         WHERE m.tecnico_id IS NOT NULL
                         ORDER BY m.tecnico_id");

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $tec) {
        if (trim($tec['nombre']) === $tecnicoAExcluirNombre) {
            continue; // Saltar y no añadir a este técnico
        }

        if (!in_array($tec['tecnico_id'], array_column($tecnicos, 'id'))) {
            $tecnicos[] = ['id' => $tec['tecnico_id'], 'nombre' => $tec['nombre']];
        }
    }
}

// --- MANEJO DE FILTROS ---
$fechaInicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fechaFin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-d');
$sedeFiltro = $idSedeUnica !== null ? $idSedeUnica : 'todas';
$tecnicoFiltro = isset($_GET['tecnico_id']) ? $_GET['tecnico_id'] : 'todos';

// --- ANÁLISIS DE MOVIMIENTOS POR TÉCNICO ---
$analisisTecnicos = [];
$movimientosDetalle = [];

if (isset($_GET['buscar'])) {
    foreach ($sedes as $sede) {
        if ($sedeFiltro == $sede['id']) {
            $sqlCondiciones = "DATE(m.fecha) BETWEEN :fecha_inicio AND :fecha_fin";
            $params = [
                ':fecha_inicio' => $fechaInicio,
                ':fecha_fin' => $fechaFin
            ];

            $tecnicoIdExcluir = null;
            $stmtExcluir = $pdo->prepare("SELECT id FROM tecnicos WHERE nombre = :nombre");
            $stmtExcluir->execute([':nombre' => $tecnicoAExcluirNombre]);
            if ($row = $stmtExcluir->fetch(PDO::FETCH_ASSOC)) {
                $tecnicoIdExcluir = $row['id'];
                $sqlCondiciones .= " AND m.tecnico_id != :tecnico_excluir_id";
                $params[':tecnico_excluir_id'] = $tecnicoIdExcluir;
            }

            if ($tecnicoFiltro != 'todos') {
                $sqlCondiciones .= " AND m.tecnico_id = :tecnico_id";
                $params[':tecnico_id'] = $tecnicoFiltro;
            }

            $sql = "SELECT m.id, p.nombre AS producto_nombre, m.tipo, m.cantidad, m.fecha, m.tecnico_id,
                           COALESCE(t.nombre, CONCAT('Técnico #', m.tecnico_id)) as tecnico_nombre,
                           '{$sede['nombre']}' AS sede_nombre
                    FROM `{$sede['tabla_movimientos']}` m
                    JOIN `{$sede['tabla_productos']}` p ON m.producto_id = p.id
                    LEFT JOIN tecnicos t ON m.tecnico_id = t.id
                    WHERE {$sqlCondiciones}
                    ORDER BY m.tecnico_id, p.nombre, m.fecha ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($movimientos as $mov) {
                $tecnicoId = isset($mov['tecnico_id']) ? trim($mov['tecnico_id']) : 'Sin asignar';
                if ($tecnicoId === '') $tecnicoId = 'Sin asignar';

                $movimientosDetalle[] = $mov;

                if (!isset($analisisTecnicos[$tecnicoId])) {
                    $analisisTecnicos[$tecnicoId] = [
                        'nombre' => $mov['tecnico_nombre'],
                        'total_movimientos' => 0,
                        'productos' => []
                    ];
                }

                $analisisTecnicos[$tecnicoId]['total_movimientos']++;

                $producto = $mov['producto_nombre'];
                if (!isset($analisisTecnicos[$tecnicoId]['productos'][$producto])) {
                    $analisisTecnicos[$tecnicoId]['productos'][$producto] = [
                        'preinstalaciones' => 0, 'instalaciones_ok' => 0, 'sobrantes' => 0, 'desinstalaciones' => 0
                    ];
                }

                // --- CORRECCIÓN APLICADA AQUÍ ---
                // Se reemplaza el espacio por guion bajo para coincidir con la clave del array.
                $tipo = str_replace(' ', '_', strtolower($mov['tipo']));

                if (isset($analisisTecnicos[$tecnicoId]['productos'][$producto][$tipo])) {
                     $analisisTecnicos[$tecnicoId]['productos'][$producto][$tipo] += (int)$mov['cantidad'];
                }
            }
        }
    }

    // Lógica de cálculo (sin cambios)
    foreach ($analisisTecnicos as $tecnicoId => &$datos) {
        foreach ($datos['productos'] as $nombreProducto => &$prodDatos) {
            $prodDatos['devueltos'] = $prodDatos['instalaciones_ok'] + $prodDatos['sobrantes'];
            $prodDatos['diferencia'] = $prodDatos['preinstalaciones'] - $prodDatos['devueltos'];

            $estadoProd = 'ok';
            if ($prodDatos['diferencia'] > 0) {
                $estadoProd = 'debe';
            } elseif ($prodDatos['diferencia'] < 0) {
                $estadoProd = 'favor';
            }
            $prodDatos['estado'] = $estadoProd;
        }
        unset($prodDatos);
    }
    unset($datos);

    // --- ORDENAMIENTO PARA LA TABLA DE DETALLES ---
    // Se ordena el array de detalles por fecha descendente (más reciente primero)
    // Esto se hace aquí para no afectar el cálculo anterior que depende de otro orden.
    if (!empty($movimientosDetalle)) {
        usort($movimientosDetalle, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
    }
}

// Lógica para Estadísticas Generales (sin cambios)
$totalEntregados = 0;
$totalDevueltos = 0;
$tecnicosConDeuda = 0;
if (isset($_GET['buscar'])) {
    $tecnicosConDeudaSet = [];
    foreach ($analisisTecnicos as $tecnicoId => $datos) {
        foreach ($datos['productos'] as $prod) {
            $totalEntregados += $prod['preinstalaciones'];
            $totalDevueltos += $prod['devueltos'];
            if ($prod['estado'] == 'debe') {
                $tecnicosConDeudaSet[$tecnicoId] = true;
            }
        }
    }
    $tecnicosConDeuda = count($tecnicosConDeudaSet);
}


// (El resto del código PHP para PDF y CSV no cambia)
// --- EXPORTAR A PDF ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'pdf') {
    require('fpdf186/fpdf.php');

    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('Reporte de Análisis de Técnicos'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 6, utf8_decode('Período: ' . $_GET['fecha_inicio'] . ' al ' . $_GET['fecha_fin']), 0, 1, 'C');
            $this->Cell(0, 6, utf8_decode('Sede: ' . $GLOBALS['sedeUnicaPermitida']), 0, 1, 'C');
            $this->Ln(5);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AddPage('L', 'A4');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, utf8_decode('Resumen General por Técnico'), 0, 1);
    $pdf->Ln(2);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(40, 7, utf8_decode('Técnico'), 1);
    $pdf->Cell(25, 7, 'Entregados', 1);
    $pdf->Cell(25, 7, 'Inst. OK', 1);
    $pdf->Cell(25, 7, 'Sobrantes', 1);
    $pdf->Cell(25, 7, 'Devueltos', 1);
    $pdf->Cell(25, 7, 'Diferencia', 1);
    $pdf->Cell(30, 7, 'Estado', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 9);
    foreach ($analisisTecnicos as $datos) {
        $pdf->Cell(40, 6, utf8_decode($datos['nombre']), 1);
        $pdf->Cell(25, 6, $datos['validacion']['entregados'], 1, 0, 'C');
        $pdf->Cell(25, 6, $datos['instalaciones_ok'], 1, 0, 'C');
        $pdf->Cell(25, 6, $datos['sobrantes'], 1, 0, 'C');
        $pdf->Cell(25, 6, $datos['validacion']['devueltos'], 1, 0, 'C');
        $pdf->Cell(25, 6, $datos['validacion']['diferencia'], 1, 0, 'C');

        $estado = $datos['validacion']['estado'];
        $estadoTexto = $estado == 'ok' ? 'Cuadrado' : ($estado == 'debe' ? 'DEBE ' . abs($datos['validacion']['diferencia']) : 'A FAVOR ' . abs($datos['validacion']['diferencia']));
        $pdf->Cell(30, 6, utf8_decode($estadoTexto), 1, 0, 'C');
        $pdf->Ln();
    }

    if (!empty($movimientosDetalle)) {
        $pdf->AddPage('L', 'A4');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode('Detalle de Movimientos'), 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(20, 7, utf8_decode('Técnico'), 1);
        $pdf->Cell(60, 7, 'Producto', 1);
        $pdf->Cell(30, 7, 'Tipo', 1);
        $pdf->Cell(20, 7, 'Cantidad', 1);
        $pdf->Cell(50, 7, 'Sede', 1);
        $pdf->Cell(40, 7, 'Fecha', 1);
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        foreach ($movimientosDetalle as $m) {
            $pdf->Cell(20, 6, utf8_decode(substr($m['tecnico_nombre'], 0, 10)), 1);
            $pdf->Cell(60, 6, utf8_decode(substr($m['producto_nombre'], 0, 30)), 1);
            $pdf->Cell(30, 6, utf8_decode(ucfirst($m['tipo'])), 1);
            $pdf->Cell(20, 6, $m['cantidad'], 1, 0, 'C');
            $pdf->Cell(50, 6, utf8_decode($m['sede_nombre']), 1);
            $pdf->Cell(40, 6, date('d/m/Y H:i', strtotime($m['fecha'])), 1);
            $pdf->Ln();
        }
    }

    $pdf->Output('D', 'Reporte_Tecnicos_' . date('Y-m-d') . '.pdf');
    exit;
}

// --- EXPORTAR A CSV ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Reporte_Tecnicos_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');

    fputcsv($output, ['Reporte de Analisis de Tecnicos']);
    fputcsv($output, ['Periodo', $fechaInicio . ' al ' . $fechaFin]);
    fputcsv($output, ['Sede', $sedeUnicaPermitida]);
    fputcsv($output, []);

    fputcsv($output, ['Resumen General por Tecnico']);
    fputcsv($output, ['Tecnico', 'Entregados', 'Instalaciones OK', 'Sobrantes', 'Total Devueltos', 'Diferencia', 'Estado']);

    foreach ($analisisTecnicos as $datos) {
        $estado = $datos['validacion']['estado'];
        $estadoTexto = $estado == 'ok' ? 'Cuadrado' : ($estado == 'debe' ? 'DEBE ' . abs($datos['validacion']['diferencia']) : 'A FAVOR ' . abs($datos['validacion']['diferencia']));

        fputcsv($output, [
            $datos['nombre'],
            $datos['validacion']['entregados'],
            $datos['instalaciones_ok'],
            $datos['sobrantes'],
            $datos['validacion']['devueltos'],
            $datos['validacion']['diferencia'],
            $estadoTexto
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['Detalle de Movimientos']);
    fputcsv($output, ['Tecnico', 'Producto', 'Tipo', 'Cantidad', 'Sede', 'Fecha']);

    foreach ($movimientosDetalle as $m) {
        fputcsv($output, [
            $m['tecnico_nombre'],
            $m['producto_nombre'],
            ucfirst($m['tipo']),
            $m['cantidad'],
            $m['sede_nombre'],
            date('d/m/Y H:i', strtotime($m['fecha']))
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
    <title>Reporte de Análisis de Técnicos</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* (El CSS se mantiene igual para el estilo) */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 2rem; }
        .container { max-width: 1600px; margin: 0 auto; }
        .header-card { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .header-card h1 { color: #2c3e50; font-size: 2rem; display: flex; align-items: center; gap: 1rem; }
        .header-card h1 i { color: #667eea; }
        .card { background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.3); margin-bottom: 2rem; }
        .btn { padding: 12px 24px; border: none; border-radius: 50px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 10px; transition: all 0.3s; font-size: 0.95rem; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(45deg, #11998e, #38ef7d); color: white; }
        .btn-danger { background: linear-gradient(45deg, #eb3349, #f45c43); color: white; }
        .btn-warning { background: linear-gradient(45deg, #f093fb, #f5576c); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(0,0,0,0.3); }
        .filtros-section { background: #f8f9fa; padding: 1.5rem; border-radius: 15px; margin-bottom: 2rem; }
        .filtros-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; color: #495057; margin-bottom: 0.5rem; font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 12px; border: 2px solid #dee2e6; border-radius: 10px; font-size: 1rem; transition: all 0.3s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .stat-card.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-card.danger { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card h3 { font-size: 0.9rem; opacity: 0.9; margin-bottom: 0.5rem; }
        .stat-card .value { font-size: 2.5rem; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2c3e50; color: white; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        .tecnico-header-row { background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%); font-weight: bold; font-size: 1.1rem; }
        .tecnico-header-row td { padding: 12px 15px; } /* Más compacto */
        .producto-row td { padding-left: 25px; vertical-align: middle; }
        .producto-row .producto-nombre { font-style: italic; color: #555; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; display: inline-block; white-space: nowrap; }
        .badge-ok { background: #d4edda; color: #155724; }
        .badge-debe { background: #f8d7da; color: #721c24; }
        .badge-favor { background: #d1ecf1; color: #0c5460; }
        .export-actions { display: flex; gap: 1rem; padding-top: 1rem; border-top: 2px solid #dee2e6; margin-top: 1rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        .alert-info { background: #d1ecf1; border-left: 4px solid #0c5460; color: #0c5460; }
        .alert-warning { background: #fff3cd; border-left: 4px solid #856404; color: #856404; }
        .section-title { font-size: 1.5rem; color: #2c3e50; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .section-title i { color: #667eea; }

        /* --- CSS PARA TABLA CON SCROLL --- */
        .table-container {
            max-height: 500px; /* Altura máxima de la tabla */
            overflow-y: auto;   /* Scroll vertical */
            position: relative;
        }
        .table-container thead th {
            position: -webkit-sticky; /* para Safari */
            position: sticky;
            top: 0;
            z-index: 1;
        }

        @media print { body { background: white; } .no-print { display: none; } .card { box-shadow: none; page-break-inside: avoid; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card no-print">
            <h1><i class="fas fa-user-tie"></i> Reporte de Análisis de Técnicos</h1>
            <a href="inventario.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver</a>
        </div>

        <!-- Filtros -->
        <div class="card no-print">
            <h2 class="section-title"><i class="fas fa-filter"></i> Filtros de Búsqueda</h2>
            <form method="GET" class="filtros-section">
                <div class="filtros-grid">
                    <div class="form-group">
                        <label for="sede_id"><i class="fas fa-building"></i> Sede</label>
                        <select name="sede_id" id="sede_id" disabled>
                            <?php if ($idSedeUnica !== null): ?>
                                <option value="<?= $idSedeUnica ?>" selected><?= htmlspecialchars($sedeUnicaPermitida) ?></option>
                            <?php else: ?>
                                <option value="" disabled>Sede no encontrada</option>
                            <?php endif; ?>
                        </select>
                        <?php if ($idSedeUnica !== null): ?>
                             <input type="hidden" name="sede_id" value="<?= $idSedeUnica ?>">
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="tecnico_id"><i class="fas fa-user"></i> Técnico</label>
                        <select name="tecnico_id" id="tecnico_id">
                            <option value="todos" <?= $tecnicoFiltro == 'todos' ? 'selected' : '' ?>>Todos los Técnicos</option>
                            <?php foreach ($tecnicos as $tec): ?>
                                <option value="<?= $tec['id'] ?>" <?= $tecnicoFiltro == $tec['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tec['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_inicio"><i class="fas fa-calendar-alt"></i> Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio" value="<?= htmlspecialchars($fechaInicio) ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="fecha_fin"><i class="fas fa-calendar-check"></i> Fecha Fin</label>
                        <input type="date" name="fecha_fin" id="fecha_fin" value="<?= htmlspecialchars($fechaFin) ?>" required>
                    </div>
                </div>

                <button type="submit" name="buscar" class="btn btn-primary">
                    <i class="fas fa-search"></i> Generar Reporte
                </button>
            </form>
        </div>

        <?php if (isset($_GET['buscar'])): ?>
            <?php if (empty($analisisTecnicos)): ?>
                <div class="card">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> No se encontraron movimientos para los filtros seleccionados.
                    </div>
                </div>
            <?php else: ?>

                <div class="card">
                    <h2 class="section-title"><i class="fas fa-chart-bar"></i> Estadísticas Generales</h2>
                    <div class="stats-grid">
                        <div class="stat-card"><h3><i class="fas fa-users"></i> Total Técnicos</h3><div class="value"><?= count($analisisTecnicos) ?></div></div>
                        <div class="stat-card success"><h3><i class="fas fa-box"></i> Equipos Entregados</h3><div class="value"><?= $totalEntregados ?></div></div>
                        <div class="stat-card warning"><h3><i class="fas fa-undo"></i> Equipos Devueltos</h3><div class="value"><?= $totalDevueltos ?></div></div>
                        <div class="stat-card <?= $tecnicosConDeuda > 0 ? 'danger' : 'success' ?>"><h3><i class="fas fa-exclamation-circle"></i> Técnicos con Deuda</h3><div class="value"><?= $tecnicosConDeuda ?></div></div>
                    </div>
                </div>

                <div class="card">
                    <h2 class="section-title"><i class="fas fa-clipboard-check"></i> Validación de Equipos por Técnico y Producto</h2>
                    <div class="alert alert-info"><i class="fas fa-info-circle"></i> <strong>Sede:</strong> <?= htmlspecialchars($sedeUnicaPermitida) ?>. La validación se calcula para cada producto individualmente.</div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 25%;">Técnico / Producto</th>
                                <th><i class="fas fa-box-open"></i> Entregados</th>
                                <th><i class="fas fa-check-circle"></i> Inst. OK</th>
                                <th><i class="fas fa-plus-circle"></i> Sobrantes</th>
                                <th><i class="fas fa-undo"></i> Total Devueltos</th>
                                <th><i class="fas fa-minus-circle"></i> Desinstalaciones</th>
                                <th><i class="fas fa-calculator"></i> Diferencia</th>
                                <th><i class="fas fa-clipboard-check"></i> Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($analisisTecnicos as $tecnicoId => $datos): ?>
                                <!-- FILA DE CABECERA DEL TÉCNICO (SOLO INFORMATIVA) -->
                                <tr class="tecnico-header-row">
                                    <td colspan="8">
                                        <i class="fas fa-user-tie"></i> <?= htmlspecialchars($datos['nombre']) ?>
                                        <small style="opacity: 0.7;">(<?= $datos['total_movimientos'] ?> movimientos)</small>
                                    </td>
                                </tr>

                                <!-- FILAS DE PRODUCTOS CON VALIDACIÓN INDIVIDUAL -->
                                <?php if (!empty($datos['productos'])): ?>
                                    <?php foreach ($datos['productos'] as $nombreProducto => $cantidades): ?>
                                        <tr class="producto-row">
                                            <td class="producto-nombre"><i class="fas fa-box"></i> <?= htmlspecialchars($nombreProducto) ?></td>
                                            <td><strong><?= $cantidades['preinstalaciones'] ?></strong></td>
                                            <td><?= $cantidades['instalaciones_ok'] ?></td>
                                            <td><?= $cantidades['sobrantes'] ?></td>
                                            <td><strong><?= $cantidades['devueltos'] ?></strong></td>
                                            <td><?= $cantidades['desinstalaciones'] ?></td>
                                            <td><strong style="color: <?= $cantidades['diferencia'] == 0 ? '#28a745' : ($cantidades['diferencia'] > 0 ? '#dc3545' : '#17a2b8') ?>"><?= $cantidades['diferencia'] ?></strong></td>
                                            <td>
                                                <?php if ($cantidades['estado'] == 'ok'): ?>
                                                    <span class="badge badge-ok"><i class="fas fa-check"></i> Cuadrado</span>
                                                <?php elseif ($cantidades['estado'] == 'debe'): ?>
                                                    <span class="badge badge-debe"><i class="fas fa-exclamation-triangle"></i> DEBE <?= abs($cantidades['diferencia']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge badge-favor"><i class="fas fa-info-circle"></i> A FAVOR <?= abs($cantidades['diferencia']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                     <tr>
                                        <td colspan="8" style="text-align: center; font-style: italic; padding-left: 40px;">No hay productos con movimientos para este técnico en el período seleccionado.</td>
                                     </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($movimientosDetalle)): ?>
                    <div class="card">
                        <h2 class="section-title"><i class="fas fa-list"></i> Detalle Completo de Movimientos</h2>
                        <div class="table-container"> <!-- Contenedor para scroll -->
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha y Hora</th>
                                        <th>Técnico</th>
                                        <th>Producto</th>
                                        <th>Tipo de Movimiento</th>
                                        <th>Cantidad</th>
                                        <th>Sede</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movimientosDetalle as $mov): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($mov['id']) ?></td>
                                            <td><?= date('d/m/Y H:i:s', strtotime($mov['fecha'])) ?></td>
                                            <td><i class="fas fa-user"></i> <?= htmlspecialchars($mov['tecnico_nombre']) ?></td>
                                            <td><?= htmlspecialchars($mov['producto_nombre']) ?></td>
                                            <td>
                                                <?php
                                                $tipo_mov = strtolower($mov['tipo']); $icon = 'fas fa-exchange-alt'; $color = '#6c757d';
                                                switch($tipo_mov) { case 'preinstalaciones': $icon = 'fas fa-box-open'; $color = '#007bff'; break; case 'instalaciones ok': $icon = 'fas fa-check-circle'; $color = '#28a745'; break; case 'sobrantes': $icon = 'fas fa-plus-circle'; $color = '#17a2b8'; break; case 'desinstalaciones': $icon = 'fas fa-minus-circle'; $color = '#dc3545'; break; case 'compras': $icon = 'fas fa-shopping-cart'; $color = '#ffc107'; break; }
                                                ?>
                                                <span style="color: <?= $color ?>"><i class="<?= $icon ?>"></i> <?= htmlspecialchars(ucfirst($mov['tipo'])) ?></span>
                                            </td>
                                            <td><strong><?= htmlspecialchars($mov['cantidad']) ?></strong></td>
                                            <td><?= htmlspecialchars($mov['sede_nombre']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card no-print">
                    <h2 class="section-title"><i class="fas fa-download"></i> Exportar Reporte</h2>
                    <div class="export-actions">
                        <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'pdf', 'sede_id' => $sedeFiltro])) ?>" class="btn btn-danger"><i class="fas fa-file-pdf"></i> Descargar PDF</a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 'csv', 'sede_id' => $sedeFiltro])) ?>" class="btn btn-success"><i class="fas fa-file-excel"></i> Descargar Excel (CSV)</a>
                        <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimir</button>
                    </div>
                </div>

            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Para ver el reporte, seleccione los filtros y haga clic en <strong>"Generar Reporte"</strong>.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            const inicio = new Date(document.getElementById('fecha_inicio').value);
            const fin = new Date(document.getElementById('fecha_fin').value);

            if (inicio > fin) {
                e.preventDefault();
                alert('La fecha de inicio no puede ser mayor que la fecha final');
            }
        });
    </script>
</body>
</html>