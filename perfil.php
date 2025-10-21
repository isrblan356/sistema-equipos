<?php
require_once 'config.php';
verificarLogin(); // Cualquiera puede acceder si está logueado

$pdo = conectarDB();
$mensajeHtml = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];
    $usuario_id = $_SESSION['usuario_id'];

    try {
        if ($password_nueva !== $password_confirmar) {
            throw new Exception("Las contraseñas nuevas no coinciden.");
        }

        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();

        if (!$usuario || !password_verify($password_actual, $usuario['password'])) {
            throw new Exception("La contraseña actual es incorrecta.");
        }

        $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([$nuevo_hash, $usuario_id]);
        
        $mensajeHtml = '<div class="mensaje exito">Contraseña actualizada exitosamente.</div>';

    } catch (Exception $e) {
        $mensajeHtml = '<div class="mensaje error">Error: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f7f6;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .header {
            background-color: #2c3e50;
            color: #ffffff;
            padding: 1rem 2rem;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
        }

        .container {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            box-sizing: border-box;
        }

        .page-header {
            text-align: center;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .page-header h2 {
            font-size: 2.5rem;
            font-weight: 700;
        }

        .card {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            max-width: 600px;
            margin: auto;
        }

        .card-header {
            background-color: #3498db;
            color: #ffffff;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .card-header h3 {
            margin: 0;
            font-size: 1.25rem;
        }

        .card-header i {
            margin-right: 0.5rem;
        }

        form {
            padding: 2rem 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 700;
        }

        .form-group input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input[type="password"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .btn {
            display: inline-block;
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 4px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            width: 100%;
            background-color: #3498db;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-secondary {
            background-color: #6c757d;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .action-links {
            max-width: 600px;
            margin: 0 auto 1rem auto;
            text-align: left;
        }

        .mensaje {
            padding: 1rem;
            margin: 0 auto 1.5rem auto;
            border-radius: 4px;
            max-width: 600px;
            text-align: center;
        }

        .mensaje.exito {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .mensaje.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Mi Aplicación</h1>
    </div>
    <div class="container">
        <div class="page-header"><h2>Mi Perfil</h2></div>
        
        <?= $mensajeHtml; ?>

        <div class="action-links">
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
        </div>

        <div class="card">
             <div class="card-header"><h3><i class="fas fa-key"></i> Cambiar Contraseña</h3></div>
             <form method="POST">
                 <div class="form-group">
                     <label for="password_actual">Contraseña Actual</label>
                     <input type="password" id="password_actual" name="password_actual" required>
                 </div>
                 <div class="form-group">
                     <label for="password_nueva">Nueva Contraseña</label>
                     <input type="password" id="password_nueva" name="password_nueva" required>
                 </div>
                 <div class="form-group">
                     <label for="password_confirmar">Confirmar Nueva Contraseña</label>
                     <input type="password" id="password_confirmar" name="password_confirmar" required>
                 </div>
                 <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
             </form>
        </div>
    </div>
</body>
</html>