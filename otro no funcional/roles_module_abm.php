<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();

// Insertar nuevo rol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_rol'])) {
    $nombre = limpiarDatos($_POST['nombre']);
    $stmt = $pdo->prepare("INSERT INTO roles (nombre) VALUES (?)");
    $stmt->execute([$nombre]);
}

// Actualizar permisos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_permisos'])) {
    $rol_id = $_POST['rol_id'];
    $puede_editar = isset($_POST['puede_editar']) ? 1 : 0;
    $puede_eliminar = isset($_POST['puede_eliminar']) ? 1 : 0;

    // Verifica si ya existe
    $stmt = $pdo->prepare("SELECT id FROM permisos WHERE rol_id = ?");
    $stmt->execute([$rol_id]);
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("UPDATE permisos SET puede_editar = ?, puede_eliminar = ? WHERE rol_id = ?");
        $stmt->execute([$puede_editar, $puede_eliminar, $rol_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO permisos (rol_id, puede_editar, puede_eliminar) VALUES (?, ?, ?)");
        $stmt->execute([$rol_id, $puede_editar, $puede_eliminar]);
    }
}

// Eliminar rol
if (isset($_GET['eliminar'])) {
    $rol_id = $_GET['eliminar'];
    $pdo->prepare("DELETE FROM permisos WHERE rol_id = ?")->execute([$rol_id]);
    $pdo->prepare("DELETE FROM roles WHERE id = ?")->execute([$rol_id]);
    header("Location: roles.php");
    exit();
}

$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Roles y Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-4">
    <h2>Gestión de Roles</h2>

    <!-- Crear nuevo rol -->
    <form method="POST" class="mb-4">
        <div class="input-group">
            <input type="text" name="nombre" class="form-control" placeholder="Nombre del rol" required>
            <button type="submit" name="crear_rol" class="btn btn-success">Crear Rol</button>
        </div>
    </form>

    <!-- Listado de roles -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Rol</th>
                <th>Permisos</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $rol): 
            $perm = $pdo->prepare("SELECT * FROM permisos WHERE rol_id = ?");
            $perm->execute([$rol['id']]);
            $permiso = $perm->fetch(PDO::FETCH_ASSOC);
        ?>
            <tr>
                <td><?= htmlspecialchars($rol['nombre']) ?></td>
                <td>
                    <form method="POST" class="d-flex gap-2">
                        <input type="hidden" name="rol_id" value="<?= $rol['id'] ?>">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="puede_editar" <?= $permiso && $permiso['puede_editar'] ? 'checked' : '' ?>>
                            <label class="form-check-label">Editar</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="puede_eliminar" <?= $permiso && $permiso['puede_eliminar'] ? 'checked' : '' ?>>
                            <label class="form-check-label">Eliminar</label>
                        </div>
                        <button type="submit" name="actualizar_permisos" class="btn btn-primary btn-sm">Guardar</button>
                    </form>
                </td>
                <td>
                    <a href="?eliminar=<?= $rol['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este rol?')">Eliminar</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
