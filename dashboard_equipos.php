<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();

// Estadísticas de equipos - ajusta según tu estructura de BD
$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos");
$total_equipos = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos WHERE estado = 'ACTIVO'");
$equipos_activos = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos WHERE estado = 'MANTENIMIENTO'");
$equipos_mantenimiento = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos WHERE estado = 'FUERA_SERVICIO'");
$equipos_fuera_servicio = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM equipos WHERE estado = 'INACTIVO'");
$equipos_inactivos = $stmt->fetch()['total'] ?? 0;

$stmt = $pdo->query("SELECT COUNT(*) as total FROM mantenimientos WHERE DATE(fecha_programada) = CURDATE()");
$mantenimientos_hoy = $stmt->fetch()['total'] ?? 0;

// Obtener equipos recientes
$stmt = $pdo->query("
    SELECT e.*, 
    CASE 
        WHEN e.estado = 'ACTIVO' THEN 'activo'
        WHEN e.estado = 'MANTENIMIENTO' THEN 'mantenimiento'
        WHEN e.estado = 'FUERA_SERVICIO' THEN 'fuera_servicio'
        ELSE 'inactivo'
    END as estado_equipo
    FROM equipos e 
    ORDER BY e.fecha_registro DESC 
    LIMIT 5
");
$equipos_recientes = $stmt->fetchAll();

// Obtener mantenimientos recientes
$stmt = $pdo->query("
    SELECT m.*, e.nombre as equipo_nombre, e.codigo, u.nombre as usuario_nombre
    FROM mantenimientos m
    LEFT JOIN equipos e ON m.equipo_id = e.id
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    ORDER BY m.fecha_realizacion DESC
    LIMIT 5
");
$mantenimientos_recientes = $stmt->fetchAll();

// Equipos que requieren mantenimiento
$stmt = $pdo->query("
    SELECT e.*, 
           DATEDIFF(CURDATE(), e.ultimo_mantenimiento) as dias_sin_mantenimiento
    FROM equipos e
    WHERE e.estado = 'ACTIVO' 
    AND (e.ultimo_mantenimiento IS NULL OR DATEDIFF(CURDATE(), e.ultimo_mantenimiento) >= e.frecuencia_mantenimiento)
    ORDER BY dias_sin_mantenimiento DESC
    LIMIT 10
");
$equipos_mantenimiento_pendiente = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Equipos</title>
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
        
        .estado-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .estado-activo { background-color: #28a745; }
        .estado-mantenimiento { background-color: #ffc107; }
        .estado-fuera-servicio { background-color: #dc3545; }
        .estado-inactivo { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
                <div class="text-center mb-4">
                    <h4 class="text-white">
                        <i class="fas fa-tools"></i> Sistema Equipos
                    </h4>
                    <small class="text-white-50">Bienvenido, <?php echo $_SESSION['usuario_nombre']; ?></small>
                </div>
                
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-home"></i> Página Principal
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link active" href="dashboard_equipos.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2 position-relative">
                        <a class="nav-link" href="equipos.php">
                            <i class="fas fa-tools"></i> Gestión de Equipos
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="mantenimientos.php">
                            <i class="fas fa-wrench"></i> Mantenimientos
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link" href="reportes_equipos.php">
                            <i class="fas fa-chart-line"></i> Reportes Equipos
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
                    <h1 class="h2">Dashboard - Sistema de Equipos</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y'); ?>
                            </button>
                        </div>
                        <div class="btn-group">
                            <a href="equipos.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-tools"></i> Ir a Equipos
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Alertas de Mantenimiento -->
                <?php if (count($equipos_mantenimiento_pendiente) > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>¡Atención!</strong> Tienes <?php echo count($equipos_mantenimiento_pendiente); ?> equipo(s) que requieren mantenimiento.
                    <a href="mantenimientos.php?pendientes=1" class="alert-link">Ver equipos</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tarjetas de estadísticas -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card primary">
                            <div class="card-body text-center">
                                <i class="fas fa-tools fa-2x text-primary mb-2"></i>
                                <h3 class="mb-0"><?php echo number_format($total_equipos); ?></h3>
                                <small class="text-muted">Total Equipos</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card success">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <h3 class="mb-0"><?php echo number_format($equipos_activos); ?></h3>
                                <small class="text-muted">Equipos Activos</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card warning">
                            <div class="card-body text-center">
                                <i class="fas fa-wrench fa-2x text-warning mb-2"></i>
                                <h3 class="mb-0"><?php echo $equipos_mantenimiento; ?></h3>
                                <small class="text-muted">En Mantenimiento</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card danger">
                            <div class="card-body text-center">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                                <h3 class="mb-0"><?php echo $equipos_fuera_servicio; ?></h3>
                                <small class="text-muted">Fuera de Servicio</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card info">
                            <div class="card-body text-center">
                                <i class="fas fa-calendar-check fa-2x text-info mb-2"></i>
                                <h3 class="mb-0"><?php echo $mantenimientos_hoy; ?></h3>
                                <small class="text-muted">Mantenimientos Hoy</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                        <div class="card stat-card purple">
                            <div class="card-body text-center">
                                <i class="fas fa-pause-circle fa-2x text-purple mb-2"></i>
                                <h3 class="mb-0"><?php echo $equipos_inactivos; ?></h3>
                                <small class="text-muted">Equipos Inactivos</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Equipos Recientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-tools"></i> Equipos Recientes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($equipos_recientes)): ?>
                                    <p class="text-muted">No hay equipos registrados.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($equipos_recientes as $equipo): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($equipo['nombre']); ?></strong><br>
                                                    <small class="text-muted">
                                                        Código: <?php echo htmlspecialchars($equipo['codigo']); ?>
                                                    </small>
                                                </div>
                                                <div class="text-end">
                                                    <span class="estado-indicator estado-<?php echo str_replace('_', '-', $equipo['estado_equipo']); ?>"></span>
                                                    <span class="badge bg-<?php 
                                                        echo $equipo['estado_equipo'] == 'activo' ? 'success' : 
                                                            ($equipo['estado_equipo'] == 'mantenimiento' ? 'warning' : 
                                                            ($equipo['estado_equipo'] == 'fuera_servicio' ? 'danger' : 'secondary')); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $equipo['estado'])); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="equipos.php" class="btn btn-outline-primary btn-sm">
                                        Ver todos los equipos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mantenimientos Recientes -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-wrench"></i> Mantenimientos Recientes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($mantenimientos_recientes)): ?>
                                    <p class="text-muted">No hay mantenimientos registrados.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($mantenimientos_recientes as $mantenimiento): ?>
                                            <div class="list-group-item">
                                                <div class="d-flex w-100 justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <span class="badge bg-<?php echo $mantenimiento['tipo'] == 'PREVENTIVO' ? 'success' : 'warning'; ?> me-2">
                                                                <?php echo $mantenimiento['tipo']; ?>
                                                            </span>
                                                            <?php echo htmlspecialchars($mantenimiento['equipo_nombre']); ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <strong>Descripción:</strong> <?php echo htmlspecialchars($mantenimiento['descripcion']); ?>
                                                        </p>
                                                        <small>Por: <?php echo htmlspecialchars($mantenimiento['usuario_nombre']); ?></small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($mantenimiento['fecha_realizacion'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-center mt-3">
                                    <a href="mantenimientos.php" class="btn btn-outline-info btn-sm">
                                        Ver historial completo
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipos que Requieren Mantenimiento -->
                <?php if (!empty($equipos_mantenimiento_pendiente)): ?>
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> 
                                    Equipos que Requieren Mantenimiento
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Equipo</th>
                                                <th>Código</th>
                                                <th>Último Mantenimiento</th>
                                                <th>Días Sin Mantenimiento</th>
                                                <th>Estado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($equipos_mantenimiento_pendiente as $equipo): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($equipo['nombre']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($equipo['codigo']); ?></td>
                                                    <td>
                                                        <?php echo $equipo['ultimo_mantenimiento'] ? date('d/m/Y', strtotime($equipo['ultimo_mantenimiento'])) : 'Nunca'; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $equipo['dias_sin_mantenimiento'] > 30 ? 'danger' : 'warning'; ?>">
                                                            <?php echo $equipo['dias_sin_mantenimiento'] ?? 'N/A'; ?> días
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-success">Activo</span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-warning" onclick="programarMantenimiento(<?php echo $equipo['id']; ?>)">
                                                            <i class="fas fa-wrench"></i> Programar
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
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function programarMantenimiento(equipoId) {
            // Redirigir al módulo de mantenimientos con modal de programación abierto
            window.location.href = `mantenimientos.php?programar=${equipoId}`;
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