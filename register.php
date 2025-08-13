<?php
require_once 'config.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = limpiarDatos($_POST['nombre']);
    $email = limpiarDatos($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validaciones
    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $mensaje = mostrarAlerta('Todos los campos son obligatorios', 'error');
    } elseif ($password !== $confirm_password) {
        $mensaje = mostrarAlerta('Las contraseñas no coinciden', 'error');
    } elseif (strlen($password) < 6) {
        $mensaje = mostrarAlerta('La contraseña debe tener al menos 6 caracteres', 'error');
    } else {
        try {
            $pdo = conectarDB();
            
            // Verificar si el email ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                $mensaje = mostrarAlerta('El email ya está registrado', 'error');
            } else {
                // Encriptar contraseña y guardar usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $email, $password_hash]);
                
                $mensaje = mostrarAlerta('Usuario registrado exitosamente. Ya puedes iniciar sesión.', 'success');
            }
        } catch (PDOException $e) {
            $mensaje = mostrarAlerta('Error al registrar usuario: ' . $e->getMessage(), 'error');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Equipos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .card {
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border: none;
            border-radius: 15px;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 col-lg-5">
                <div class="card">
                    <div class="card-header text-center py-4">
                        <h3><i class="fas fa-user-plus"></i> Registro de Usuario</h3>
                        <p class="mb-0">Sistema de Revisión de Equipos</p>
                    </div>
                    <div class="card-body p-4">
                        <?php echo $mensaje; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="nombre" class="form-label">
                                    <i class="fas fa-user"></i> Nombre Completo
                                </label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">
                                    <i class="fas fa-envelope"></i> Email
                                </label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock"></i> Contraseña
                                </label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Mínimo 6 caracteres</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">
                                    <i class="fas fa-lock"></i> Confirmar Contraseña
                                </label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus"></i> Registrarse
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <p>¿Ya tienes cuenta? <a href="login.php" class="text-decoration-none">Inicia Sesión</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>