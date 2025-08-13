<?php
// session_start() debe ser lo primero para usar mensajes flash.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
verificarLogin();
$pdo = conectarDB();

// Lógica para mostrar mensajes de éxito/error de la sesión
$mensaje = '';
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Procesar formularios POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] == 'editar_sede') {
            // Lógica para editar...
            $stmt = $pdo->prepare("UPDATE sedes SET nombre = ?, color = ?, gradient = ? WHERE id = ?");
            $stmt->execute([limpiar($_POST['nombre']), limpiar($_POST['color']), limpiar($_POST['gradient']), intval($_POST['id'])]);
            $_SESSION['mensaje'] = "Sede actualizada correctamente.";
        }

        if ($_POST['accion'] == 'crear_sede') {
            // Lógica para crear...
            $nombre_nueva_sede = limpiar($_POST['nombre']);
            if (empty($nombre_nueva_sede)) throw new Exception("El nombre de la sede no puede estar vacío.");
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO sedes (nombre, color, gradient, tabla_productos, tabla_movimientos) VALUES (?, ?, ?, '', '')");
            $stmt->execute([$nombre_nueva_sede, '#007bff', 'linear-gradient(135deg, #007bff 0%, #0056b3 100%)']);
            $nueva_sede_id = $pdo->lastInsertId();
            $tabla_productos_nueva = "productos_sede" . $nueva_sede_id;
            $tabla_movimientos_nueva = "movimientos_sede" . $nueva_sede_id;
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$tabla_productos_nueva` LIKE `productos`;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS `$tabla_movimientos_nueva` LIKE `movimientos`;");
            try { $pdo->exec("ALTER TABLE `$tabla_movimientos_nueva` DROP FOREIGN KEY `movimientos_ibfk_1`;"); } catch (PDOException $ex) {}
            $pdo->exec("ALTER TABLE `$tabla_movimientos_nueva` ADD CONSTRAINT `fk_mov_prod_sede_{$nueva_sede_id}` FOREIGN KEY (`producto_id`) REFERENCES `$tabla_productos_nueva`(`id`) ON DELETE CASCADE;");
            $stmt_update = $pdo->prepare("UPDATE sedes SET tabla_productos = ?, tabla_movimientos = ? WHERE id = ?");
            $stmt_update->execute([$tabla_productos_nueva, $tabla_movimientos_nueva, $nueva_sede_id]);
            $pdo->commit();
            $_SESSION['mensaje'] = "¡Sede '{$nombre_nueva_sede}' creada con éxito!";
        }

        if ($_POST['accion'] == 'eliminar_sede') {
            // Lógica para eliminar...
            $id_a_eliminar = intval($_POST['id']);
            if ($id_a_eliminar == 1) throw new Exception("La sede principal (ID 1) no puede ser eliminada.");
            $pdo->beginTransaction();
            $stmt_get_tables = $pdo->prepare("SELECT tabla_productos, tabla_movimientos FROM sedes WHERE id = ?");
            $stmt_get_tables->execute([$id_a_eliminar]);
            $tablas_a_eliminar = $stmt_get_tables->fetch(PDO::FETCH_ASSOC);
            if ($tablas_a_eliminar) {
                $stmt_delete_sede = $pdo->prepare("DELETE FROM sedes WHERE id = ?");
                $stmt_delete_sede->execute([$id_a_eliminar]);
                $pdo->exec("DROP TABLE IF EXISTS `{$tablas_a_eliminar['tabla_productos']}`;");
                $pdo->exec("DROP TABLE IF EXISTS `{$tablas_a_eliminar['tabla_movimientos']}`;");
                $pdo->commit();
                $_SESSION['mensaje'] = "La sede ha sido eliminada permanentemente.";
            } else {
                throw new Exception("No se encontró la sede para eliminar.");
            }
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }

    // REDIRECCIÓN: Previene duplicados al refrescar
    header("Location: configuracion.php");
    exit();
}

$sedes = $pdo->query("SELECT * FROM sedes ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

function limpiar($dato) {
    return htmlspecialchars(trim($dato));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Sistema de Inventario</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Tus estilos aquí (no cambian) */
        body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; color: #333; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 8px 32px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        h1, h2, h3 { color: #2c3e50; }
        .btn { padding: 10px 20px; border: none; border-radius: 25px; font-weight: 500; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.3s; margin: 0 5px; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); color: white; }
        .btn-success { background: linear-gradient(45deg, #27ae60, #229954); color: white; }
        .btn-danger { background: linear-gradient(45deg, #e74c3c, #c0392b); color: white; }
        .btn-danger-alt { background: linear-gradient(45deg, #dc3545, #b02a37); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input[type="text"], input[type="color"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ecf0f1; text-align: left; vertical-align: middle; }
        th { background: #34495e; color: white; }
        .actions-cell { display: flex; flex-wrap: wrap; }
        .actions-cell form { margin: 2px; }
        .mensaje { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .header { padding: 1rem 2rem; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header a { text-decoration: none; }
    </style>
</head>
<body>
    <div class="header">
        <!-- ===== CAMBIO REALIZADO AQUÍ ===== -->
        <a href="inventario.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
    </div>

    <div class="container">
        <h1><i class="fas fa-cogs"></i> Configuración del Sistema</h1>

        <?php if ($mensaje): ?><div class="mensaje"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- El resto del HTML no cambia -->
        <div class="card">
            <h2><i class="fas fa-building"></i> Gestionar Sedes</h2>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>ID</th><th style="width:25%">Nombre</th><th style="width:10%">Color</th><th style="width:30%">Gradiente</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($sedes as $sede): ?>
                        <tr>
                            <td><?= $sede['id'] ?></td>
                            <td colspan="4">
                                <form method="POST" class="actions-cell">
                                    <input type="hidden" name="id" value="<?= $sede['id'] ?>">
                                    <div style="flex: 2; padding-right: 10px;"><input type="text" name="nombre" value="<?= htmlspecialchars($sede['nombre']) ?>" required></div>
                                    <div style="flex: 1; padding-right: 10px;"><input type="color" name="color" value="<?= htmlspecialchars($sede['color']) ?>" required></div>
                                    <div style="flex: 3; padding-right: 10px;"><input type="text" name="gradient" value="<?= htmlspecialchars($sede['gradient']) ?>" required></div>
                                    <div class="actions-cell">
                                        <button type="submit" name="accion" value="editar_sede" class="btn btn-primary" title="Guardar Cambios"><i class="fas fa-save"></i></button>
                                        <?php if ($sede['id'] != 1): ?>
                                            <button type="submit" name="accion" value="eliminar_sede" class="btn btn-danger" title="Eliminar Sede" onclick="return confirm('¿ESTÁ SEGURO?\n\nEsta acción eliminará permanentemente la sede \'<?= htmlspecialchars(addslashes($sede['nombre'])) ?>\' y TODOS sus datos.\n\nEsta acción NO SE PUEDE DESHACER.')"><i class="fas fa-trash"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h3 style="margin-top: 2rem; border-top: 1px solid #eee; padding-top: 2rem;">Crear Nueva Sede</h3>
            <form method="POST">
                <input type="hidden" name="accion" value="crear_sede">
                <div class="form-group">
                    <label for="nombre_nueva_sede">Nombre de la Nueva Sede</label>
                    <input type="text" id="nombre_nueva_sede" name="nombre" placeholder="Ej: Cali" required>
                </div>
                <button type="submit" class="btn btn-success"><i class="fas fa-plus-circle"></i> Crear Sede</button>
            </form>
        </div>
        <div class="card">
            <h2><i class="fas fa-database"></i> Copia de Seguridad</h2>
            <p>Genera una copia de seguridad completa de toda la base de datos en un archivo .sql.</p>
            <a href="backup.php" class="btn btn-danger-alt"><i class="fas fa-download"></i> Descargar Backup Completo</a>
        </div>
    </div>
</body>
</html>