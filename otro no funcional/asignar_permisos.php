<?php
require_once 'config.php';
$pdo = conectarDB();

$roles = $pdo->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$permisos = $pdo->query("SELECT * FROM permisos")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rol_id = $_POST['rol_id'];
    $seleccionados = $_POST['permisos'] ?? [];

    $pdo->prepare("DELETE FROM rol_permiso WHERE rol_id = ?")->execute([$rol_id]);

    foreach ($seleccionados as $permiso_id) {
        $pdo->prepare("INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (?, ?)")->execute([$rol_id, $permiso_id]);
    }

    header("Location: asignar_permisos.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asignar Permisos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container py-5">
    <h2 class="mb-4">Asignar Permisos a Roles</h2>

    <form method="POST">
        <div class="mb-3">
            <label>Selecciona Rol</label>
            <select name="rol_id" class="form-select" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?= $rol['id'] ?>"><?= $rol['nombre'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label>Permisos</label><br>
            <?php foreach ($permisos as $permiso): ?>
                <div class="form-check">
                    <input type="checkbox" name="permisos[]" value="<?= $permiso['id'] ?>" class="form-check-input">
                    <label class="form-check-label"><?= $permiso['nombre'] ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-success">Guardar</button>
    </form>
</body>
</html>
