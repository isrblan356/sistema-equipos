<?php
require_once 'config.php';

$pdo = conectarDB();

// Crear Rol
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_rol'])) {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $stmt = $pdo->prepare("INSERT INTO roles (nombre, descripcion) VALUES (?, ?)");
    $stmt->execute([$nombre, $descripcion]);
    header("Location: roles.php");
    exit();
}

// Eliminar Rol
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$id]);
    header("Location: roles.php");
    exit();
}

$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Roles</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2 class="mb-4">Gestión de Roles</h2>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label>Nombre del Rol</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="descripcion" class="form-control"></textarea>
        </div>
        <button type="submit" name="crear_rol" class="btn btn-primary">Crear Rol</button>
    </form>

    <h4>Lista de Roles</h4>
    <table class="table table-bordered">
        <tr>
            <th>ID</th><th>Nombre</th><th>Descripción</th><th>Acciones</th>
        </tr>
        <?php foreach ($roles as $rol): ?>
            <tr>
                <td><?= $rol['id'] ?></td>
                <td><?= $rol['nombre'] ?></td>
                <td><?= $rol['descripcion'] ?></td>
                <td>
                    <a href="?eliminar=<?= $rol['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar rol?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>

