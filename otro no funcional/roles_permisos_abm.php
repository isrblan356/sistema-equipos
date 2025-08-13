<?php
require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

$mensaje = '';

// Crear nuevo rol
if (isset($_POST['crear_rol'])) {
    $nombre = limpiarDatos($_POST['nombre']);
    $stmt = $pdo->prepare("INSERT INTO roles (nombre) VALUES (?)");
    if ($stmt->execute([$nombre])) {
        $rol_id = $pdo->lastInsertId();
        $stmt_permiso = $pdo->prepare("INSERT INTO permisos (rol_id, puede_agregar, puede_editar, puede_eliminar) VALUES (?, 0, 0, 0)");
        $stmt_permiso->execute([$rol_id]);
        $mensaje = mostrarAlerta('Rol creado con permisos vacíos', 'success');
    }
}

// Actualizar permisos
if (isset($_POST['guardar_permisos'])) {
    foreach ($_POST['permisos'] as $rol_id => $permisos) {
        $puede_agregar = isset($permisos['agregar']) ? 1 : 0;
        $puede_editar = isset($permisos['editar']) ? 1 : 0;
        $puede_eliminar = isset($permisos['eliminar']) ? 1 : 0;

        $stmt = $pdo->prepare("UPDATE permisos SET puede_agregar=?, puede_editar=?, puede_eliminar=? WHERE rol_id=?");
        $stmt->execute([$puede_agregar, $puede_editar, $puede_eliminar, $rol_id]);
    }
    $mensaje = mostrarAlerta('Permisos actualizados correctamente', 'success');
}

// Obtener roles y permisos
$stmt = $pdo->query("SELECT r.id, r.nombre, p.puede_agregar, p.puede_editar, p.puede_eliminar
                     FROM roles r LEFT JOIN permisos p ON r.id = p.rol_id");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Gestión de Roles y Permisos</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="container py-4">
    <h2>Gestión de Roles y Permisos</h2>
    <?= $mensaje ?>

    <form method="POST" class="mb-4">
        <div class="input-group">
            <input type="text" name="nombre" class="form-control" placeholder="Nombre del nuevo rol" required>
            <button type="submit" name="crear_rol" class="btn btn-primary">Crear Rol</button>
        </div>
    </form>

    <form method="POST">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Rol</th>
                    <th>Agregar</th>
                    <th>Editar</th>
                    <th>Eliminar</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roles as $rol): ?>
                    <tr>
                        <td><?= htmlspecialchars($rol['nombre']) ?></td>
                        <td><input type="checkbox" name="permisos[<?= $rol['id'] ?>][agregar]" <?= $rol['puede_agregar'] ? 'checked' : '' ?>></td>
                        <td><input type="checkbox" name="permisos[<?= $rol['id'] ?>][editar]" <?= $rol['puede_editar'] ? 'checked' : '' ?>></td>
                        <td><input type="checkbox" name="permisos[<?= $rol['id'] ?>][eliminar]" <?= $rol['puede_eliminar'] ? 'checked' : '' ?>></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <button type="submit" name="guardar_permisos" class="btn btn-success">Guardar Cambios</button>
    </form>
</body>
</html>
