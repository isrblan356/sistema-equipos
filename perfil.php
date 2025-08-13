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
        
        $mensajeHtml = '<div class="mensaje">Contraseña actualizada exitosamente.</div>';

    } catch (Exception $e) {
        $mensajeHtml = '<div class="error">Error: ' . $e->getMessage() . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil</title>
    <!-- Incluye el mismo bloque <style> profesional -->
</head>
<body>
    <!-- Incluye el mismo <div class="header"> -->
    <div class="container">
        <div class="page-header"><h2>Mi Perfil</h2></div>
        <?= $mensajeHtml; ?>
        <div class="card" style="max-width: 600px; margin: auto;">
             <div class="card-header"><h3><i class="fas fa-key"></i> Cambiar Contraseña</h3></div>
             <form method="POST">
                 <div class="form-group"><label>Contraseña Actual</label><input type="password" name="password_actual" required></div>
                 <div class="form-group"><label>Nueva Contraseña</label><input type="password" name="password_nueva" required></div>
                 <div class="form-group"><label>Confirmar Nueva Contraseña</label><input type="password" name="password_confirmar" required></div>
                 <button type="submit" class="btn btn-primary">Actualizar Contraseña</button>
             </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>