<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensaje = '';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID de equipo inválido');
}
$id = (int) $_GET['id'];

// Obtener tipos de equipo
$stmtTipos = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre");
$tipos_equipo = $stmtTipos->fetchAll();

// Obtener equipo actual
$stmt = $pdo->prepare("SELECT * FROM equipos WHERE id = ?");
$stmt->execute([$id]);
$equipo = $stmt->fetch();

if (!$equipo) {
    die('Equipo no encontrado');
}

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $modelo = limpiarDatos($_POST['modelo']);
    $marca = limpiarDatos($_POST['marca']);
    $numero_serie = limpiarDatos($_POST['numero_serie']);
    $tipo_equipo_id = $_POST['tipo_equipo_id'];
    $ubicacion = limpiarDatos($_POST['ubicacion']);
    $ip_address = limpiarDatos($_POST['ip_address']);
    $estado = $_POST['estado'];
    $fecha_instalacion = $_POST['fecha_instalacion'];

    try {
        $stmt = $pdo->prepare("UPDATE equipos 
                               SET nombre = ?, modelo = ?, marca = ?, numero_serie = ?, 
                                   tipo_equipo_id = ?, ubicacion = ?, ip_address = ?, 
                                   estado = ?, fecha_instalacion = ? 
                               WHERE id = ?");
        $stmt->execute([$nombre, $modelo, $marca, $numero_serie, $tipo_equipo_id, $ubicacion, $ip_address, $estado, $fecha_instalacion, $id]);
        header('Location: equipos.php?actualizado=1');
        exit();
    } catch (PDOException $e) {
        $mensaje = mostrarAlerta('Error al actualizar equipo: ' . $e->getMessage(), 'danger');
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Equipo</title>
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
                    <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
                </li>
            </ul>
        </nav>

        <!-- Main -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 pt-4">
            <h2><i class="fas fa-edit"></i> Editar Equipo</h2>
            <?php echo $mensaje; ?>

            <form method="POST" class="mt-4">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Nombre del Equipo *</label>
                        <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($equipo['nombre']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Equipo *</label>
                        <select name="tipo_equipo_id" class="form-select" required>
                            <?php foreach ($tipos_equipo as $tipo): ?>
                                <option value="<?php echo $tipo['id']; ?>" <?php echo $equipo['tipo_equipo_id'] == $tipo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($tipo['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Marca *</label>
                        <input type="text" name="marca" class="form-control" value="<?php echo htmlspecialchars($equipo['marca']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Modelo *</label>
                        <input type="text" name="modelo" class="form-control" value="<?php echo htmlspecialchars($equipo['modelo']); ?>" required>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Número de Serie *</label>
                        <input type="text" name="numero_serie" class="form-control" value="<?php echo htmlspecialchars($equipo['numero_serie']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dirección IP</label>
                        <input type="text" name="ip_address" class="form-control" value="<?php echo htmlspecialchars($equipo['ip_address']); ?>">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Estado *</label>
                        <select name="estado" class="form-select" required>
                            <option value="Activo" <?php echo $equipo['estado'] == 'Activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="Inactivo" <?php echo $equipo['estado'] == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="Mantenimiento" <?php echo $equipo['estado'] == 'Mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                            <option value="Dañado" <?php echo $equipo['estado'] == 'Dañado' ? 'selected' : ''; ?>>Dañado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha de Instalación</label>
                        <input type="date" name="fecha_instalacion" class="form-control" value="<?php echo htmlspecialchars($equipo['fecha_instalacion']); ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ubicación</label>
                    <textarea name="ubicacion" class="form-control" rows="2"><?php echo htmlspecialchars($equipo['ubicacion']); ?></textarea>
                </div>

                <div class="mt-4">
                    <a href="equipos.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
