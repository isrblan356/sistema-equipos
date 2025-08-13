<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();

// --- OBTENCIÓN DE DATOS ESTADÍSTICOS ---
$total_equipos = $pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn();
$total_revisiones = $pdo->query("SELECT COUNT(*) FROM revisiones")->fetchColumn();
$equipos_por_estado = $pdo->query("SELECT estado, COUNT(*) as cantidad FROM equipos GROUP BY estado ORDER BY cantidad DESC")->fetchAll();
$revisiones_por_estado = $pdo->query("SELECT estado_revision, COUNT(*) as cantidad FROM revisiones WHERE fecha_revision >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY estado_revision ORDER BY cantidad DESC")->fetchAll();
$equipos_mas_revisados = $pdo->query("SELECT e.nombre, e.modelo, COUNT(r.id) as total_revisiones FROM equipos e LEFT JOIN revisiones r ON e.id = r.equipo_id GROUP BY e.id, e.nombre, e.modelo ORDER BY total_revisiones DESC LIMIT 5")->fetchAll();
$revisiones_por_mes = $pdo->query("SELECT DATE_FORMAT(fecha_revision, '%Y-%m') as mes, COUNT(*) as cantidad FROM revisiones WHERE fecha_revision >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(fecha_revision, '%Y-%m') ORDER BY mes ASC")->fetchAll();
$equipos_mantenimiento = $pdo->query("SELECT COUNT(DISTINCT e.id) FROM equipos e LEFT JOIN revisiones r ON e.id = r.equipo_id WHERE e.estado = 'Mantenimiento' OR r.requiere_mantenimiento = 1")->fetchColumn();

// --- LÓGICA DE EXPORTACIÓN A PDF ---
if (isset($_GET['exportar']) && $_GET['exportar'] == 'pdf') {
    require('fpdf186/fpdf.php');

    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, utf8_decode('Reporte General de Estadísticas'), 0, 1, 'C');
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 7, 'Generado el: ' . date('d/m/Y H:i'), 0, 1, 'C');
            $this->Ln(10);
        }
        function Footer() { $this->SetY(-15); $this->SetFont('Arial', 'I', 8); $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C'); }
        function SectionTitle($title) { $this->SetFont('Arial', 'B', 12); $this->SetFillColor(230, 240, 255); $this->Cell(0, 8, utf8_decode($title), 0, 1, 'L', true); $this->Ln(4); }
        function StatCard($title, $value, $icon) { $this->SetFont('Arial', 'B', 10); $this->Cell(60, 10, utf8_decode($title), 1, 0); $this->SetFont('Arial', '', 10); $this->Cell(35, 10, $value, 1, 1); }
    }

    $pdf = new PDF('P', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 10);

    $pdf->SectionTitle('Resumen General');
    $pdf->StatCard('Total de Equipos', $total_equipos, '');
    $pdf->StatCard('Total de Revisiones', $total_revisiones, '');
    $pdf->StatCard('Equipos en Mantenimiento', $equipos_mantenimiento, '');
    $pdf->StatCard('Promedio Rev./Equipo', ($total_equipos > 0 ? round($total_revisiones / $total_equipos, 1) : 0), '');
    $pdf->Ln(10);

    if (!empty($equipos_mas_revisados)) {
        $pdf->SectionTitle('Top 5 Equipos con Más Revisiones');
        $pdf->SetFont('Arial', 'B', 10); $pdf->Cell(100, 7, 'Equipo', 1); $pdf->Cell(40, 7, 'Total Revisiones', 1, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        foreach ($equipos_mas_revisados as $item) {
            $pdf->Cell(100, 7, utf8_decode($item['nombre'] . ' (' . $item['modelo'] . ')'), 1);
            $pdf->Cell(40, 7, $item['total_revisiones'], 1, 1, 'C');
        }
        $pdf->Ln(10);
    }
    
    if(!empty($equipos_por_estado)){
        $pdf->SectionTitle('Desglose de Equipos por Estado');
        $pdf->SetFont('Arial','B',10); $pdf->Cell(60,7,'Estado',1); $pdf->Cell(30,7,'Cantidad',1,1,'C');
        $pdf->SetFont('Arial','',10);
        foreach($equipos_por_estado as $item){
            $pdf->Cell(60,7,utf8_decode($item['estado']),1);
            $pdf->Cell(30,7,$item['cantidad'],1,1,'C');
        }
        $pdf->Ln(10);
    }

    if(!empty($revisiones_por_estado)){
        $pdf->SectionTitle(utf8_decode('Desglose de Revisiones por Estado (Últimos 30 días)'));
        $pdf->SetFont('Arial','B',10); $pdf->Cell(60,7,'Estado',1); $pdf->Cell(30,7,'Cantidad',1,1,'C');
        $pdf->SetFont('Arial','',10);
        foreach($revisiones_por_estado as $item){
            $pdf->Cell(60,7,utf8_decode($item['estado_revision']),1);
            $pdf->Cell(30,7,$item['cantidad'],1,1,'C');
        }
    }

    $pdf->Output('D', 'Reporte_Estadisticas_'.date('Y-m-d').'.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes y Estadísticas</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; display: flex; align-items: center; gap: 12px; }
        .header h1 i { color: var(--primary-color); }
        .nav-buttons { display: flex; align-items: center; gap: 10px; }
        .btn-nav { font-size: 0.9rem; font-weight: 500; color: #555; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .btn-nav:hover { background-color: #eef; color: var(--primary-color); }
        .btn-nav.active { background: var(--primary-color); color: white; }
        .user-info { display: flex; align-items: center; gap: 10px; padding-left: 15px; border-left: 1px solid #ddd; }
        .logout-btn { background: #ffeef0; color: #d93749; }
        .logout-btn:hover { background: #d93749; color: white; }
        .container { max-width: 1600px; margin: 2rem auto; padding: 0 2rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2.5rem; color: var(--text-color); }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #eef; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .card-header h3 { font-size: 1.5rem; color: var(--text-color); display: flex; align-items: center; gap: 10px; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-outline-secondary { background: #fff; border: 1px solid #ddd; color: #555; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .stat-card { display: flex; align-items: center; gap: 1.5rem; }
        .stat-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stat-icon.primary { background-color: #eef; color: #5154d9; }
        .stat-icon.success { background-color: #e7f5f2; color: #008a6e; }
        .stat-icon.warning { background-color: #fff8e1; color: #f59e0b; }
        .stat-icon.danger { background-color: #fff1f2; color: #d93749; }
        .stat-info h4 { font-size: 2.25rem; font-weight: 700; }
        .stat-info p { margin: 0; color: #777; }
        .chart-container { position: relative; height: 350px; }
        .list-group-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-top: 1px solid #eef; }
        .list-group-item:first-child { border-top: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                <a class="btn-nav active" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <div class="user-info"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Reportes y Estadísticas</h2>
            <a href="?exportar=pdf" class="btn btn-outline-secondary"><i class="fas fa-file-pdf"></i> Exportar a PDF</a>
        </div>

        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4"><div class="card stat-card"><div class="stat-icon primary"><i class="fas fa-router"></i></div><div class="stat-info"><p>Total Equipos</p><h4><?= $total_equipos; ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 mb-4"><div class="card stat-card"><div class="stat-icon success"><i class="fas fa-clipboard-check"></i></div><div class="stat-info"><p>Total Revisiones</p><h4><?= $total_revisiones; ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 mb-4"><div class="card stat-card"><div class="stat-icon warning"><i class="fas fa-tools"></i></div><div class="stat-info"><p>En Mantenimiento</p><h4><?= $equipos_mantenimiento; ?></h4></div></div></div>
            <div class="col-lg-3 col-md-6 mb-4"><div class="card stat-card"><div class="stat-icon danger"><i class="fas fa-chart-line"></i></div><div class="stat-info"><p>Prom. Rev/Equipo</p><h4><?= $total_equipos > 0 ? round($total_revisiones / $total_equipos, 1) : 0; ?></h4></div></div></div>
        </div>

        <div class="row">
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Revisiones por Mes (Últimos 6 meses)</h3></div>
                    <div class="chart-container"><canvas id="chartRevisionesMes"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Equipos por Estado</h3></div>
                    <div class="chart-container"><canvas id="chartEquiposPorEstado"></canvas></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-trophy"></i> Top 5 Equipos Más Revisados</h3></div>
                    <?php if (empty($equipos_mas_revisados)): ?><p class="text-muted p-3">No hay datos.</p><?php else: ?>
                        <ul class="list-group list-group-flush"><?php foreach ($equipos_mas_revisados as $equipo): ?>
                            <li class="list-group-item"><div><strong><?= htmlspecialchars($equipo['nombre']); ?></strong><br><small class="text-muted"><?= htmlspecialchars($equipo['modelo']); ?></small></div><span class="badge bg-success rounded-pill"><?= $equipo['total_revisiones']; ?> rev.</span></li>
                        <?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-7 mb-4">
                <div class="card">
                    <div class="card-header"><h3><i class="fas fa-tasks"></i> Desglose de Revisiones (Últimos 30 días)</h3></div>
                    <div class="chart-container"><canvas id="chartRevisionesEstado"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const chartColors = { primary: '#667eea', success: '#28a745', warning: '#f59e0b', danger: '#d93749', info: '#17a2b8', secondary: '#6c757d' };
            
            // Gráfico de revisiones por mes (Líneas)
            new Chart(document.getElementById('chartRevisionesMes'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($revisiones_por_mes, 'mes')); ?>,
                    datasets: [{
                        label: 'Revisiones',
                        data: <?= json_encode(array_column($revisiones_por_mes, 'cantidad')); ?>,
                        borderColor: chartColors.primary,
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }, plugins: { legend: { display: false } } }
            });

            // Gráfico de equipos por estado (Pie)
            new Chart(document.getElementById('chartEquiposPorEstado'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($equipos_por_estado, 'estado')); ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($equipos_por_estado, 'cantidad')); ?>,
                        backgroundColor: ['#28a745', '#ffc107', '#dc3545', '#6c757d', '#17a2b8']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });

            // Gráfico de revisiones por estado (Doughnut)
            new Chart(document.getElementById('chartRevisionesEstado'), {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_column($revisiones_por_estado, 'estado_revision')); ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($revisiones_por_estado, 'cantidad')); ?>,
                        backgroundColor: ['#28a745', '#007bff', '#ffc107', '#dc3545', '#d93749']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
            });
        });
    </script>
</body>
</html>