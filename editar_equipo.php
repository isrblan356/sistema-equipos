<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$mensajeHtml = '';

// Validar ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Redirigir si el ID no es válido para evitar una página de error simple
    $_SESSION['error_flash'] = 'ID de equipo no válido.';
    header("Location: equipos.php");
    exit();
}
$id = (int)$_GET['id'];

// Obtener equipo actual para editar
$stmt = $pdo->prepare("SELECT * FROM equipos WHERE id = ?");
$stmt->execute([$id]);
$equipo = $stmt->fetch();

// Si el equipo no se encuentra, redirigir
if (!$equipo) {
    $_SESSION['error_flash'] = 'El equipo que intenta editar no fue encontrado.';
    header("Location: equipos.php");
    exit();
}

// Procesar actualización del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $modelo = limpiarDatos($_POST['modelo']);
    $marca = limpiarDatos($_POST['marca']);
    $numero_serie = limpiarDatos($_POST['numero_serie']);
    $tipo_equipo_id = $_POST['tipo_equipo_id'];
    $ubicacion = limpiarDatos($_POST['ubicacion']);
    $persona_responsable = limpiarDatos($_POST['persona_responsable']);
    $ip_address = limpiarDatos($_POST['ip_address']);
    $estado = $_POST['estado'];
    $fecha_instalacion = !empty($_POST['fecha_instalacion']) ? $_POST['fecha_instalacion'] : null;

    try {
        $sql = "UPDATE equipos SET 
                    nombre = ?, modelo = ?, marca = ?, numero_serie = ?, 
                    tipo_equipo_id = ?, ubicacion = ?, persona_responsable = ?, 
                    ip_address = ?, estado = ?, fecha_instalacion = ? 
                WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $nombre, $modelo, $marca, $numero_serie, 
            $tipo_equipo_id, $ubicacion, $persona_responsable, 
            $ip_address, $estado, $fecha_instalacion, 
            $id
        ]);
        
        // Usar mensaje flash para consistencia
        $_SESSION['mensaje_flash'] = 'Equipo actualizado exitosamente.';
        header('Location: equipos.php');
        exit();
    } catch (PDOException $e) {
        // Mostrar error en la misma página para que el usuario pueda corregir
        $mensajeHtml = '<div class="error">Error al actualizar equipo: ' . $e->getMessage() . '</div>';
    }
}

// Obtener tipos de equipo para el dropdown
$tipos_equipo = $pdo->query("SELECT * FROM tipos_equipo ORDER BY nombre")->fetchAll();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Equipo</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
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
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #e7f5f2; color: #008a6e; border-color: #a3e9d8; }
        .error { background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select, textarea { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        .form-actions { display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eef; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-desktop"></i> Sistema de Equipos</h1>
            <div class="nav-buttons">
                <a class="btn-nav" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="btn-nav active" href="equipos.php"><i class="fas fa-router"></i> Equipos</a>
                <a class="btn-nav" href="revisiones.php"><i class="fas fa-clipboard-check"></i> Revisiones</a>
                <a class="btn-nav" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Editar Equipo</h2>
        </div>

        <?= $mensajeHtml; ?>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-edit"></i> Editando: <?= htmlspecialchars($equipo['nombre']) ?></h3>
            </div>
            
            <form method="POST" id="editForm">
                <div class="form-grid">
                    <div class="form-group"><label>Nombre del Equipo *</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($equipo['nombre']); ?>" required></div>
                    <div class="form-group"><label>Tipo de Equipo *</label><select name="tipo_equipo_id" class="form-select" required><option value="">Seleccionar...</option><?php foreach ($tipos_equipo as $tipo): ?><option value="<?= $tipo['id']; ?>" <?= $equipo['tipo_equipo_id'] == $tipo['id'] ? 'selected' : ''; ?>><?= htmlspecialchars($tipo['nombre']); ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Marca *</label><input type="text" name="marca" class="form-control" value="<?= htmlspecialchars($equipo['marca']); ?>" required></div>
                    <div class="form-group"><label>Modelo *</label><input type="text" name="modelo" class="form-control" value="<?= htmlspecialchars($equipo['modelo']); ?>" required></div>
                    <div class="form-group"><label>Número de Serie *</label><input type="text" name="numero_serie" class="form-control" value="<?= htmlspecialchars($equipo['numero_serie']); ?>" required></div>
                    <div class="form-group"><label>Dirección IP</label><input type="text" name="ip_address" class="form-control" placeholder="Ej: 192.168.1.100" value="<?= htmlspecialchars($equipo['ip_address']); ?>"></div>
                    <div class="form-group"><label>Estado *</label><select name="estado" class="form-select" required><option value="Activo" <?= $equipo['estado'] == 'Activo' ? 'selected' : ''; ?>>Activo</option><option value="Inactivo" <?= $equipo['estado'] == 'Inactivo' ? 'selected' : ''; ?>>Inactivo</option><option value="Mantenimiento" <?= $equipo['estado'] == 'Mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option><option value="Dañado" <?= $equipo['estado'] == 'Dañado' ? 'selected' : ''; ?>>Dañado</option></select></div>
                    <div class="form-group"><label>Fecha de Instalación</label><input type="date" name="fecha_instalacion" class="form-control" value="<?= htmlspecialchars($equipo['fecha_instalacion']); ?>"></div>
                    <div class="form-group"><label>Persona Responsable *</label><input type="text" name="persona_responsable" class="form-control" value="<?= htmlspecialchars($equipo['persona_responsable']); ?>" required></div>
                    <div class="form-group"><label>Ubicación</label><input type="text" name="ubicacion" class="form-control" value="<?= htmlspecialchars($equipo['ubicacion']); ?>"></div>
                </div>

                <div class="form-actions">
                    <a href="equipos.php" class="btn btn-secondary"><i class="fas fa-times"></i> Cancelar</a>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('editForm');
        const submitBtn = form.querySelector('button[type="submit"]');
        const requiredInputs = form.querySelectorAll('[required]');

        form.addEventListener('submit', function(e) {
            let formIsValid = true;
            
            // Validar campos requeridos
            requiredInputs.forEach(input => {
                if (input.value.trim() === '') {
                    formIsValid = false;
                    input.style.borderColor = '#d93749'; // Error color
                } else {
                    input.style.borderColor = '#ddd'; // Reset
                }
            });

            if (formIsValid) {
                // Deshabilitar botón y mostrar estado de carga
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
            } else {
                e.preventDefault(); // Detener envío si hay errores
                console.log('Formulario inválido');
            }
        });
        
        // Limpiar estilos de error al escribir
        requiredInputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.style.borderColor = '#ddd';
                }
            });
        });
    });
    </script>
</body>
</html>