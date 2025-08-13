<?php
require_once 'config.php';
verificarLogin();

$pdo = conectarDB();
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'principal';
$esAdmin = esAdmin();

if ($vista === 'permisos' || $vista === 'usuarios') {
    verificarAdmin();
}

if ($esAdmin && ($vista === 'permisos' || $vista === 'usuarios')) {
    $mensajeHtml = '';
    if (isset($_SESSION['mensaje_flash'])) { $mensajeHtml = '<div class="mensaje">' . $_SESSION['mensaje_flash'] . '</div>'; unset($_SESSION['mensaje_flash']); }
    if (isset($_SESSION['error_flash'])) { $mensajeHtml = '<div class="error">' . $_SESSION['error_flash'] . '</div>'; unset($_SESSION['error_flash']); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
        try {
            if ($_POST['accion'] == 'guardar_permisos') {
                $rol_id = intval($_POST['rol_id']);
                if ($rol_id > 1) {
                    $p_equipos = isset($_POST['permiso_equipos']) ? 1 : 0;
                    $p_inventario = isset($_POST['permiso_inventario']) ? 1 : 0;
                    $p_portatiles = isset($_POST['permiso_portatiles']) ? 1 : 0;
                    $stmt = $pdo->prepare("UPDATE permisos SET permiso_equipos = ?, permiso_inventario = ?, permiso_portatiles = ? WHERE rol_id = ?");
                    $stmt->execute([$p_equipos, $p_inventario, $p_portatiles, $rol_id]);
                    $_SESSION['mensaje_flash'] = 'Permisos actualizados correctamente.';
                }
            }
            if ($_POST['accion'] == 'crear_usuario') {
                $nombre = limpiarDatos($_POST['nombre']); $email = limpiarDatos($_POST['email']); $password = $_POST['password']; $rol_id = intval($_POST['rol_id']);
                if (empty($nombre) || empty($email) || empty($password) || empty($rol_id)) throw new Exception("Todos los campos son obligatorios.");
                $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?"); $stmt_check->execute([$email]);
                if ($stmt_check->fetch()) throw new Exception("El correo electrónico '$email' ya está registrado.");
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $email, $password_hash, $rol_id]);
                $_SESSION['mensaje_flash'] = 'Usuario creado exitosamente.';
            }
            if ($_POST['accion'] == 'editar_usuario') {
                $id = intval($_POST['id']); $nombre = limpiarDatos($_POST['nombre']); $email = limpiarDatos($_POST['email']); $rol_id = intval($_POST['rol_id']);
                if (empty($nombre) || empty($email) || empty($rol_id)) throw new Exception("Nombre, email y rol son obligatorios.");
                $stmt_check = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?"); $stmt_check->execute([$email, $id]);
                if ($stmt_check->fetch()) throw new Exception("El correo electrónico '$email' ya está en uso por otro usuario.");
                $stmt = $pdo->prepare("UPDATE usuarios SET nombre = ?, email = ?, rol_id = ? WHERE id = ?");
                $stmt->execute([$nombre, $email, $rol_id, $id]);
                $_SESSION['mensaje_flash'] = 'Usuario actualizado exitosamente.';
            }
            if ($_POST['accion'] == 'cambiar_password') {
                $id = intval($_POST['id']); $password = $_POST['password'];
                if (empty($password)) throw new Exception("La nueva contraseña no puede estar vacía.");
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $id]);
                $_SESSION['mensaje_flash'] = 'Contraseña actualizada exitosamente.';
            }
            if ($_POST['accion'] == 'eliminar_usuario') {
                $id = intval($_POST['id']);
                if ($id == $_SESSION['usuario_id']) throw new Exception("No puedes eliminar tu propio usuario.");
                if ($id == 1) throw new Exception("El usuario Administrador principal no puede ser eliminado.");
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['mensaje_flash'] = 'Usuario eliminado exitosamente.';
            }
        } catch (Exception $e) {
            $_SESSION['error_flash'] = 'Error: ' . $e->getMessage();
        }
        header("Location: dashboard.php?vista=" . $vista);
        exit();
    }
    
    $roles_con_permisos = $pdo->query("SELECT r.id, r.nombre_rol, p.* FROM roles r LEFT JOIN permisos p ON r.id = p.rol_id ORDER BY r.id")->fetchAll(PDO::FETCH_ASSOC);
    $usuarios = $pdo->query("SELECT u.id, u.nombre, u.email, u.rol_id, r.nombre_rol FROM usuarios u JOIN roles r ON u.rol_id = r.id ORDER BY u.nombre")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Principal</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.08); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: var(--bg-color); color: var(--text-color); }
        .header { background: var(--card-bg); padding: 1.25rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .header-content { display: flex; justify-content: space-between; align-items: center; max-width: 1600px; margin: 0 auto; }
        .header h1 { font-size: 1.75rem; }
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-info a { text-decoration: none; color: inherit; font-weight: 500; }
        .logout-btn { background: #ffeef0; color: #d93749; font-size: 0.9rem; font-weight: 500; text-decoration: none; padding: 8px 16px; border-radius: 20px; transition: all 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .logout-btn:hover { background: #d93749; color: white; }
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }
        .main-container { text-align: center; }
        .logo-section h2 { font-size: 2.5rem; }
        .logo-section p { color: #777; font-size: 1.1rem; margin-bottom: 3rem; }
        .grid-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; max-width: 1200px; margin: 0 auto; }
        .sistema-card { background: var(--card-bg); border: 1px solid #eef; border-radius: var(--border-radius); padding: 2.5rem; transition: all 0.3s ease; text-decoration: none; color: inherit; display: flex; flex-direction: column; height: 100%; }
        .sistema-card:hover { transform: translateY(-5px); box-shadow: var(--shadow); border-color: var(--primary-color); }
        .sistema-card .icon { font-size: 3.5rem; margin-bottom: 1.5rem; background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card { background: var(--card-bg); border-radius: var(--border-radius); padding: 2rem; box-shadow: var(--shadow); margin-bottom: 2rem; }
        .card-header { background: none; border-bottom: 1px solid #eef; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; }
        .card-header h3 { font-size: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h2 { font-size: 2rem; }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; padding: 12px 24px; font-size: 1rem; }
        .btn-sm { padding: 8px 16px; font-size: 0.875rem; }
        .btn-xs { padding: 6px 12px; font-size: 0.75rem; border-radius: 20px;}
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-outline-warning { color: #f59e0b; border: 1px solid #fde68a; background: #fffbeb; }
        .btn-outline-warning:hover { background: #f59e0b; color: white; }
        .btn-outline-info { color: #3b82f6; border: 1px solid #bfdbfe; background: #eff6ff; }
        .btn-outline-info:hover { background: #3b82f6; color: white; }
        .btn-outline-danger { color: #ef4444; border: 1px solid #fecaca; background: #fef2f2; }
        .btn-outline-danger:hover { background: #ef4444; color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .btn-group { display: flex; gap: 8px; align-items: center; }
        .mensaje, .error { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid; }
        .mensaje { background-color: #e7f5f2; color: #008a6e; border-color: #a3e9d8; }
        .error { background-color: #fff1f2; color: #d93749; border-color: #ffb8bf; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        input, select { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid #eef; vertical-align: middle; text-align: left;}
        th { background: #f8f9fa; color: #555; font-weight: 600; }
        .badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 20px; font-weight: 600; background-color: #eef; color: #5154d9; }
        .badge.admin { background-color: #fff8e1; color: #f59e0b; }
        .nav-tabs { border-bottom: 2px solid #dee2e6; margin-bottom: 1.5rem; }
        .nav-tabs .nav-link { border: none; border-bottom: 2px solid transparent; color: #6c757d; font-weight: 600; padding: 0.75rem 1.25rem; text-decoration: none; }
        .nav-tabs .nav-link.active { color: var(--primary-color); border-bottom-color: var(--primary-color); }
        .modal { z-index: 1055 !important; }
        .text-success { color: #10b981 !important; }
        .text-muted { color: #6b7280 !important; }
        .permisos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; }
        .permiso-card { background: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--shadow); }
        .permiso-header { padding: 1.5rem; border-bottom: 1px solid #eef; }
        .permiso-header h4 { margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 10px; }
        .permiso-body { padding: 1.5rem; }
        .permiso-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f8f9fa; }
        .permiso-item:last-child { border-bottom: none; }
        .permiso-item span { font-weight: 500; }
        .permiso-footer { padding: 1.5rem; background: #f8f9fa; border-top: 1px solid #eef; text-align: right; }
        .form-switch { position: relative; display: inline-block; width: 50px; height: 28px; }
        .form-switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 28px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 4px; bottom: 4px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--primary-color); }
        input:checked + .slider:before { transform: translateX(22px); }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1><i class="fas fa-cogs"></i> Sistema Unificado</h1>
            <div class="user-info">
                <a href="perfil.php"><i class="fas fa-user-circle"></i> <span><?= htmlspecialchars($_SESSION['usuario_nombre']); ?> (<?= htmlspecialchars($_SESSION['usuario_rol']); ?>)</span></a>
                <a class="logout-btn" href="logout.php"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if ($vista === 'principal'): ?>
            <div class="main-container">
                <div class="logo-section"><h2>Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre']); ?></h2><p>Selecciona el módulo al que deseas acceder</p></div>
                <div class="grid-container">
                    <?php if (tienePermiso('permiso_equipos')): ?><a href="equipos.php" class="sistema-card"><div class="icon"><i class="fas fa-tools"></i></div><h3>Sistema de Equipos</h3><p>Gestión y mantenimiento de equipos.</p></a><?php endif; ?>
                    <?php if (tienePermiso('permiso_inventario')): ?><a href="inventario.php" class="sistema-card"><div class="icon"><i class="fas fa-boxes"></i></div><h3>Sistema de Inventario</h3><p>Control de stock y movimientos.</p></a><?php endif; ?>
                    <?php if (tienePermiso('permiso_portatiles')): ?><a href="portatiles.php" class="sistema-card"><div class="icon"><i class="fas fa-laptop"></i></div><h3>Inv. de Portátiles</h3><p>Revisión e inventario de laptops.</p></a><?php endif; ?>
                    <?php if (esAdmin()): ?><a href="?vista=permisos" class="sistema-card"><div class="icon"><i class="fas fa-users-cog"></i></div><h3>Admin y Permisos</h3><p>Gestionar usuarios y acceso a módulos.</p></a><?php endif; ?>
                </div>
            </div>

        <?php elseif ($esAdmin && ($vista === 'permisos' || $vista === 'usuarios')): ?>
            <div class="page-header"><h2>Panel de Administración</h2><a href="dashboard.php?vista=principal" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a></div>
            <?= $mensajeHtml; ?>
            <div class="card">
                <ul class="nav nav-tabs">
                    <li class="nav-item"><a class="nav-link <?= $vista === 'permisos' ? 'active' : '' ?>" href="?vista=permisos">Roles y Permisos</a></li>
                    <li class="nav-item"><a class="nav-link <?= $vista === 'usuarios' ? 'active' : '' ?>" href="?vista=usuarios">Gestión de Usuarios</a></li>
                </ul>
                
                <?php if ($vista === 'permisos'): ?>
                    <div class="card-header border-0"><h3><i class="fas fa-shield-alt"></i> Permisos por Rol</h3></div>
                    <div class="permisos-grid">
                        <?php foreach ($roles_con_permisos as $rol): ?>
                            <div class="permiso-card">
                                <form method="POST">
                                    <div class="permiso-header"><h4><i class="fas fa-user-tag"></i> <?= htmlspecialchars($rol['nombre_rol']) ?> <?php if ($rol['id'] == 1): ?><i class="fas fa-lock fa-xs text-muted" title="No editable"></i><?php endif; ?></h4></div>
                                    <div class="permiso-body">
                                        <input type="hidden" name="accion" value="guardar_permisos"><input type="hidden" name="rol_id" value="<?= $rol['id'] ?>">
                                        <div class="permiso-item"><span>Acceso a Módulo de Equipos</span><label class="form-switch"><input type="checkbox" name="permiso_equipos" <?= !empty($rol['permiso_equipos']) ? 'checked' : '' ?> <?= $rol['id'] == 1 ? 'disabled' : '' ?>><span class="slider"></span></label></div>
                                        <div class="permiso-item"><span>Acceso a Módulo de Inventario</span><label class="form-switch"><input type="checkbox" name="permiso_inventario" <?= !empty($rol['permiso_inventario']) ? 'checked' : '' ?> <?= $rol['id'] == 1 ? 'disabled' : '' ?>><span class="slider"></span></label></div>
                                        <div class="permiso-item"><span>Acceso a Módulo de Portátiles</span><label class="form-switch"><input type="checkbox" name="permiso_portatiles" <?= !empty($rol['permiso_portatiles']) ? 'checked' : '' ?> <?= $rol['id'] == 1 ? 'disabled' : '' ?>><span class="slider"></span></label></div>
                                    </div>
                                    <?php if ($rol['id'] != 1): ?><div class="permiso-footer"><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Guardar Permisos</button></div><?php endif; ?>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($vista === 'usuarios'): ?>
                    <div class="card-header"><h3><i class="fas fa-users"></i> Lista de Usuarios</h3><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrearUsuario"><i class="fas fa-plus"></i> Crear Usuario</button></div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead><tr><th>Usuario</th><th>Rol</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($usuario['nombre']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($usuario['email']) ?></small></td>
                                        <td><span class="badge <?= $usuario['nombre_rol'] == 'Administrador' ? 'admin' : '' ?>"><?= htmlspecialchars($usuario['nombre_rol']) ?></span></td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-xs btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEditarUsuario<?= $usuario['id'] ?>" title="Editar"><i class="fas fa-edit"></i></button>
                                                <button class="btn btn-xs btn-outline-info" data-bs-toggle="modal" data-bs-target="#modalCambiarPassword<?= $usuario['id'] ?>" title="Cambiar Contraseña"><i class="fas fa-key"></i></button>
                                                <?php if ($usuario['id'] != $_SESSION['usuario_id'] && $usuario['id'] != 1): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Estás seguro de eliminar a \'<?= htmlspecialchars(addslashes($usuario['nombre'])) ?>\'?');">
                                                    <input type="hidden" name="id" value="<?= $usuario['id'] ?>"><button type="submit" name="accion" value="eliminar_usuario" class="btn btn-xs btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== INICIO: MODALES MOVIDOS AQUÍ DENTRO ===== -->
            <?php if ($vista === 'usuarios'): ?>
                <div class="modal fade" id="modalCrearUsuario" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                        <div class="modal-header"><h5 class="modal-title">Crear Nuevo Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="accion" value="crear_usuario">
                                <div class="form-group"><label>Nombre Completo</label><input type="text" name="nombre" class="form-control" required></div>
                                <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" required></div>
                                <div class="form-group"><label>Contraseña</label><input type="password" name="password" class="form-control" required></div>
                                <div class="form-group"><label>Rol</label><select name="rol_id" class="form-select" required><option value="" disabled selected>Selecciona un rol...</option><?php foreach($roles_con_permisos as $rol): ?><option value="<?= $rol['id'] ?>"><?= htmlspecialchars($rol['nombre_rol']) ?></option><?php endforeach; ?></select></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Crear Usuario</button></div>
                        </form>
                    </div></div>
                </div>
                
                <?php foreach ($usuarios as $usuario): ?>
                    <div class="modal fade" id="modalEditarUsuario<?= $usuario['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Editar Usuario</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="accion" value="editar_usuario"><input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                    <div class="form-group"><label>Nombre</label><input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required></div>
                                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario['email']) ?>" required></div>
                                    <div class="form-group"><label>Rol</label><select name="rol_id" class="form-select" required>
                                        <?php foreach($roles_con_permisos as $rol): ?><option value="<?= $rol['id'] ?>" <?= $usuario['rol_id'] == $rol['id'] ? 'selected' : '' ?>><?= htmlspecialchars($rol['nombre_rol']) ?></option><?php endforeach; ?>
                                    </select></div>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar Cambios</button></div>
                            </form>
                        </div></div>
                    </div>
                    <div class="modal fade" id="modalCambiarPassword<?= $usuario['id'] ?>" tabindex="-1">
                         <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Cambiar Contraseña</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <form method="POST">
                                <div class="modal-body">
                                    <p>Estás reseteando la contraseña para <strong><?= htmlspecialchars($usuario['nombre']) ?></strong>.</p>
                                    <input type="hidden" name="accion" value="cambiar_password"><input type="hidden" name="id" value="<?= $usuario['id'] ?>">
                                    <div class="form-group"><label>Nueva Contraseña</label><input type="password" name="password" class="form-control" required></div>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Actualizar</button></div>
                            </form>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- ===== FIN: MODALES MOVIDOS ===== -->
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>