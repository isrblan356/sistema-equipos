<?php
require_once 'config.php';
verificarAdmin(); // Solo los admins pueden acceder

$pdo = conectarDB();
$mensajeHtml = '';

// Lógica de mensajes flash...
if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

// Procesar formularios...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] == 'crear_usuario') {
            $nombre = limpiarDatos($_POST['nombre']);
            $email = limpiarDatos($_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $rol_id = intval($_POST['rol_id']);
            if (empty($nombre) || empty($email) || empty($_POST['password']) || empty($rol_id)) {
                throw new Exception("Todos los campos son obligatorios.");
            }
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $email, $password, $rol_id]);
            $_SESSION['mensaje_flash'] = 'Usuario creado exitosamente.';
        }
        if ($_POST['accion'] == 'editar_usuario') {
            $id = intval($_POST['id']);
            $nombre = limpiarDatos($_POST['nombre']);
            $email = limpiarDatos($_POST['email']);
            $rol_id = intval($_POST['rol_id']);
            $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol_id = ? WHERE id = ?");
            $stmt->execute([$nombre, $email, $rol_id, $id]);
            $_SESSION['mensaje_flash'] = 'Usuario actualizado exitosamente.';
        }
        if ($_POST['accion'] == 'cambiar_password') {
            $id = intval($_POST['id']);
            $password = $_POST['password'];
            if (empty($password)) throw new Exception("La nueva contraseña no puede estar vacía.");
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$password_hash, $id]);
            $_SESSION['mensaje_flash'] = 'Contraseña actualizada exitosamente.';
        }
        if ($_POST['accion'] == 'eliminar_usuario') {
            $id = intval($_POST['id']);
            if ($id == $_SESSION['usuario_id']) throw new Exception("No puedes eliminar a tu propio usuario.");
            if ($id == 1) throw new Exception("El usuario Administrador principal no puede ser eliminado."); // Protección adicional
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['mensaje_flash'] = 'Usuario eliminado exitosamente.';
        }
    } catch (Exception $e) {
        $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
    }
    header("Location: usuarios.php");
    exit();
}

// ===== CONSULTA CORREGIDA AQUÍ =====
// Obtener usuarios con el nombre del rol a través de un JOIN
$usuarios = $pdo->query("SELECT u.id, u.nombre, u.email, r.nombre_rol FROM usuarios u JOIN roles r ON u.rol_id = r.id ORDER BY u.nombre")->fetchAll(PDO::FETCH_ASSOC);
$roles = $pdo->query("SELECT * FROM roles ORDER BY nombre_rol")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Usuarios y Roles</title>
    <!-- Incluye el mismo bloque <style> profesional que usas en equipos.php -->
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
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #e7f5f2; color: #008a6e; border-color: #a3e9d8; }
        .error { background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        input:focus, select:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8f9fa; color: #555; padding: 15px; text-align: left; font-weight: 600; border-bottom: 2px solid #eef; }
        td { padding: 15px; border-bottom: 1px solid #eef; vertical-align: middle; }
        tr:hover { background: #f8f9fa; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; background-color: #eef; color: #5154d9; }
        .badge.admin { background-color: #fff8e1; color: #f59e0b; }
        .table-actions .btn { padding: 8px 12px; border-radius: 20px; }
        .modal-content { border: none; border-radius: var(--border-radius); }
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
                <a class="btn-nav" href="reportes.php"><i class="fas fa-chart-bar"></i> Reportes</a>
                <?php if (isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] == 'Administrador'): ?>
                    <a class="btn-nav active" href="usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a>
                <?php endif; ?>
                <div class="user-info"><a href="perfil.php" style="text-decoration:none; color:inherit;"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?></span></a></div>
                <a class="btn-nav logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2>Gestión de Usuarios y Roles</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario"><i class="fas fa-plus"></i> Crear Usuario</button>
        </div>
        <?= $mensajeHtml; ?>
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-users"></i> Lista de Usuarios</h3></div>
            <div style="overflow-x:auto;">
                <table>
                    <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Acciones</th></tr></thead>
                    <tbody>
                        <?php foreach ($usuarios as $usuario): ?>
                            <tr>
                                <td><?= htmlspecialchars($usuario['nombre']) ?></td>
                                <td><?= htmlspecialchars($usuario['email']) ?></td>
                                <td><span class="badge <?= $usuario['nombre_rol'] == 'Administrador' ? 'admin' : '' ?>"><?= htmlspecialchars($usuario['nombre_rol']) ?></span></td>
                                <td class="table-actions">
                                    <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= $usuario['id'] ?>"><i class="fas fa-edit"></i> Editar</button>
                                    <button class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalCambiarPassword<?= $usuario['id'] ?>"><i class="fas fa-key"></i> Contraseña</button>
                                    <?php if ($usuario['id'] != $_SESSION['usuario_id'] && $usuario['id'] != 1): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar este usuario?');">
                                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                        <button type="submit" name="accion" value="eliminar_usuario" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modales para cada usuario -->
    <?php foreach ($usuarios as $usuario): ?>
        <!-- Modal Editar Usuario -->
        <div class="modal fade" id="modalEditarUsuario<?= $usuario['id'] ?>">
            <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="accion" value="editar_usuario">
                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                        <div class="form-group mb-3"><label>Nombre</label><input type="text" name="nombre" value="<?= htmlspecialchars($usuario['nombre']) ?>" required></div>
                        <div class="form-group mb-3"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>" required></div>
                        <div class="form-group"><label>Rol</label><select name="rol_id" required>
                            <?php foreach($roles as $rol): ?>
                                <option value="<?= $rol['id'] ?>" <?= $rol['nombre_rol'] == $usuario['nombre_rol'] ? 'selected' : '' ?>><?= htmlspecialchars($rol['nombre_rol']) ?></option>
                            <?php endforeach; ?>
                        </select></div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                </form>
            </div></div>
        </div>
        <!-- Modal Cambiar Contraseña -->
        <div class="modal fade" id="modalCambiarPassword<?= $usuario['id'] ?>">
             <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Cambiar Contraseña</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Estás cambiando la contraseña para <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>.</p>
                        <input type="hidden" name="accion" value="cambiar_password">
                        <input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                        <div class="form-group"><label>Nueva Contraseña</label><input type="password" name="password" required></div>
                    </div>
                    <div class="modal-footer"><button type="submit" class="btn btn-primary">Actualizar Contraseña</button></div>
                </form>
            </div></div>
        </div>
    <?php endforeach; ?>
    
    <!-- Modal Crear Usuario -->
    <div class="modal fade" id="modalCrearUsuario">
         <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Crear Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear_usuario">
                    <div class="form-group mb-3"><label>Nombre Completo</label><input type="text" name="nombre" required></div>
                    <div class="form-group mb-3"><label>Email</label><input type="email" name="email" required></div>
                    <div class="form-group mb-3"><label>Contraseña</label><input type="password" name="password" required></div>
                    <div class="form-group"><label>Rol</label><select name="rol_id" required>
                        <?php foreach($roles as $rol): ?>
                            <option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre_rol']) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Crear Usuario</button></div>
            </form>
        </div></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>