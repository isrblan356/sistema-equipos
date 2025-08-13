<?php
// La sesión debe iniciarse al principio de todo.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';

// Si el usuario ya está logueado, redirigir inmediatamente al dashboard.
if (isset($_SESSION['usuario_logueado']) && $_SESSION['usuario_logueado'] === true) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$logoEmpresa = '';

try {
    $pdo = conectarDB();
    
    // Obtener logo de la empresa
    $stmt_logo = $pdo->query("SELECT valor FROM configuraciones WHERE clave = 'logo_empresa'");
    $logoConfig = $stmt_logo->fetchColumn();
    if ($logoConfig && file_exists($logoConfig)) {
        $logoEmpresa = $logoConfig;
    }

    // Procesar formulario
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $email = limpiarDatos($_POST['email']);
        $password = $_POST['password'];
        
        if (empty($email) || empty($password)) {
            $error = 'Email y contraseña son obligatorios.';
        } else {
            // Consulta que une usuarios con roles
            $stmt = $pdo->prepare("SELECT u.*, r.nombre_rol FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE u.email = ?");
            $stmt->execute([$email]);
            
            if ($usuario = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (password_verify($password, $usuario['password'])) {
                    // --- OBTENER PERMISOS ---
                    $stmt_permisos = $pdo->prepare("SELECT * FROM permisos WHERE rol_id = ?");
                    $stmt_permisos->execute([$usuario['rol_id']]);
                    $permisos = $stmt_permisos->fetch();

                    // Medida de seguridad: regenerar el ID de sesión
                    session_regenerate_id(true);

                    // Guardar todos los datos en la sesión
                    $_SESSION['usuario_logueado'] = true;
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['usuario_nombre'] = $usuario['nombre'];
                    $_SESSION['usuario_email'] = $usuario['email'];
                    $_SESSION['rol_id'] = $usuario['rol_id'];
                    $_SESSION['usuario_rol'] = $usuario['nombre_rol'];
                    $_SESSION['permisos'] = $permisos;
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'La contraseña es incorrecta.';
                }
            } else {
                $error = 'No se encontró un usuario con ese correo electrónico.';
            }
        }
    }
} catch (PDOException $e) {
    // Error genérico para no exponer detalles de la base de datos
    $error = 'No se pudo procesar la solicitud. Intente de nuevo más tarde.';
    // En un entorno de producción, registrarías el error real: error_log($e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Gestión</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary-color: #667eea; --secondary-color: #764ba2; --text-color: #2c3e50; --bg-color: #f4f7f9; --card-bg: white; --shadow: 0 10px 30px rgba(0,0,0,0.1); --border-radius: 15px; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .login-card { background: var(--card-bg); border-radius: var(--border-radius); box-shadow: var(--shadow); max-width: 450px; width: 100%; overflow: hidden; }
        .card-header { text-align: center; padding: 2rem 1.5rem; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; }
        .company-logo { width: 80px; height: 80px; margin: 0 auto 1.5rem auto; display: block; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.3); object-fit: cover; background: rgba(255, 255, 255, 0.1); padding: 10px; }
        .company-logo-placeholder { width: 80px; height: 80px; margin: 0 auto 1.5rem auto; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 3px solid rgba(255, 255, 255, 0.3); background: rgba(255, 255, 255, 0.1); font-size: 2.5rem; color: rgba(255, 255, 255, 0.8); }
        .card-header h3 { font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .card-header p { opacity: 0.8; margin: 0; }
        .card-body { padding: 2.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #555; }
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .form-control { font-family: inherit; font-size: 1rem; width: 100%; padding: 12px 12px 12px 40px; border: 1px solid #ddd; border-radius: 8px; transition: all 0.3s ease; }
        .form-control:focus { outline: none; border-color: var(--primary-color); box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15); }
        .btn { border: none; border-radius: 25px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; display: inline-flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; padding: 14px 24px; font-size: 1rem; width: 100%; }
        .btn-primary { background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
        .alert-danger { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border: 1px solid #ffb8bf; background-color: #fff1f2; color: #d93749; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="card-header">
            <?php if (!empty($logoEmpresa)): ?>
                <img src="<?= htmlspecialchars($logoEmpresa) ?>" alt="Logo Empresa" class="company-logo">
            <?php else: ?>
                <div class="company-logo-placeholder"><i class="fas fa-building"></i></div>
            <?php endif; ?>
            
            <h3><i class="fas fa-desktop"></i> Sistema de Gestión</h3>
            <p>Inventario y Mantenimiento de Equipos</p>
        </div>
        <div class="card-body">
            <?php if (!empty($error)): ?>
                <div class="alert-danger"><?= htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="email">Correo Electrónico</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                </button>
            </form>
        </div>
    </div>
</body>
</html>