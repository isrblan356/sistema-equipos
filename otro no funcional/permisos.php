<?php
require_once 'config.php';

$pdo = conectarDB();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['crear_permiso'])) {
    $nombre = limpiarDatos($_POST['nombre']);
    $descripcion = limpiarDatos($_POST['descripcion']);
    $stmt = $pdo->prepare("INSERT INTO permisos (nombre, descripcion) VALUES (?, ?)");
    $stmt->execute([$nombre, $descripcion]);
    header("Location: permisos.php");
    exit();
}

if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    $pdo->prepare("DELETE FROM permisos WHERE id = ?")->execute([$id]);
    header("Location: permisos.php");
    exit();
}

$permisos = $pdo->query("SELECT * FROM permisos")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2 class="mb-4">Gestión de Permisos</h2>

    <form method="POST" class="mb-4">
        <div class="mb-3">
            <label>Nombre del Permiso</label>
            <input type="text" name="nombre" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Descripción</label>
            <textarea name="descripcion" class="form-control"></textarea>
        </div>
        <button type="submit" name="crear_permiso" class="btn btn-primary">Crear Permiso</button>
    </form>

    <h4>Lista de Permisos</h4>
    <table class="table table-bordered">
        <tr>
            <th>ID</th><th>Nombre</th><th>Descripción</th><th>Acciones</th>
        </tr>
        <?php foreach ($permisos as $permiso): ?>
            <tr>
                <td><?= $permiso['id'] ?></td>
                <td><?= $permiso['nombre'] ?></td>
                <td><?= $permiso['descripcion'] ?></td>
                <td>
                    <a href="?eliminar=<?= $permiso['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar permiso?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
